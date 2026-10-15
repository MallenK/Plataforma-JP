<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;
use Config\Database;

/**
 * Datos del sondeo único de la plataforma (GET /live/ping): sustituye a los tres
 * sondeos sueltos de la barra superior (campanita, mensajes y tickets) y añade
 * «sellos de cambio» por zona (clases, bonos, tickets) para que las pantallas abiertas
 * se actualicen solas cuando alguien cambia algo, haya aviso o no.
 *
 * Todo son consultas de agregados (MAX / COUNT) acotadas al usuario y a su rol.
 * Cada sello va en su propio try: si uno falla devuelve null y el resto sigue.
 */
class LiveService
{
    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    /**
     * @param int $sinceId último id de notificación que el navegador ya vio (0 = primera vez)
     * @return array{
     *   unread:int, msg_unread:int, tickets_pending:?int, max_id:int,
     *   new_kinds:list<string>, stamps:array<string,?string>
     * }
     */
    public function snapshot(int $userId, string $role, int $sinceId = 0): array
    {
        $isAdmin = in_array($role, ['admin', 'superadmin'], true);

        return [
            'unread'          => $this->safe(fn () => $this->unread($userId), 0),
            'msg_unread'      => $this->safe(fn () => $this->messagesUnread($userId), 0),
            'tickets_pending' => $isAdmin ? $this->safe(fn () => (new \App\Models\TicketModel())->countOpen(), 0) : null,
            'max_id'          => $this->safe(fn () => $this->maxNotificationId($userId), 0),
            'new_kinds'       => $sinceId > 0 ? $this->safe(fn () => $this->newKinds($userId, $sinceId), []) : [],
            'stamps'          => [
                'class'  => $this->safe(fn () => $this->classStamp($userId, $role), null),
                'bono'   => $this->safe(fn () => $this->bonoStamp($userId, $role), null),
                'ticket' => $this->safe(fn () => $this->ticketStamp($userId, $role), null),
            ],
        ];
    }

    // ── Contadores de la barra superior ─────────────────────────────────

    private function unread(int $userId): int
    {
        return (int) $this->db->table('notification_recipients')
            ->where('recipient_id', $userId)->where('read_at IS NULL')->countAllResults();
    }

    private function messagesUnread(int $userId): int
    {
        $row = $this->db->query(
            'SELECT COUNT(*) AS n FROM messages ms
               JOIN conversations c ON c.id = ms.conversation_id
              WHERE (c.user1_id = ? OR c.user2_id = ?) AND ms.sender_id != ? AND ms.read_at IS NULL',
            [$userId, $userId, $userId]
        )->getRowArray();
        return (int) ($row['n'] ?? 0);
    }

    // ── Avisos nuevos ───────────────────────────────────────────────────

    private function maxNotificationId(int $userId): int
    {
        $row = $this->db->table('notification_recipients')->selectMax('notification_id', 'm')
            ->where('recipient_id', $userId)->get()->getRowArray();
        return (int) ($row['m'] ?? 0);
    }

    /** Tipos de los avisos nuevos desde $sinceId ('*' = aviso sin origen concreto). @return list<string> */
    private function newKinds(int $userId, int $sinceId): array
    {
        $rows = $this->db->table('notification_recipients nr')
            ->select('n.source_type')
            ->join('notifications n', 'n.id = nr.notification_id')
            ->where('nr.recipient_id', $userId)
            ->where('nr.notification_id >', $sinceId)
            ->groupBy('n.source_type')
            ->get()->getResultArray();

        $kinds = [];
        foreach ($rows as $r) {
            $kinds[] = ($r['source_type'] ?? '') !== '' ? (string) $r['source_type'] : '*';
        }
        return array_values(array_unique($kinds));
    }

    // ── Sellos de cambio por zona ───────────────────────────────────────

    /** Clases visibles para el usuario: admin = todas; coach/staff = donde son responsables; alumno = las suyas. */
    private function classStamp(int $userId, string $role): ?string
    {
        if (in_array($role, ['admin', 'superadmin'], true)) {
            $s = $this->db->query('SELECT MAX(updated_at) AS m, COUNT(*) AS n FROM class_sessions')->getRowArray();
            $p = $this->db->query('SELECT MAX(updated_at) AS m, COUNT(*) AS n FROM class_session_players')->getRowArray();
            return self::stamp($s['m'] ?? null, $s['n'] ?? 0, $p['m'] ?? null, $p['n'] ?? 0);
        }

        if (in_array($role, ['coach', 'staff'], true)) {
            $r = $this->db->query(
                'SELECT GREATEST(IFNULL(MAX(cs.updated_at), 0), IFNULL(MAX(csp.updated_at), 0)) AS m, COUNT(DISTINCT cs.id) AS n, COUNT(csp.id) AS k
                   FROM class_session_coaches csc
                   JOIN class_sessions cs ON cs.id = csc.session_id
                   LEFT JOIN class_session_players csp ON csp.session_id = cs.id
                  WHERE csc.user_id = ?',
                [$userId]
            )->getRowArray();
            return self::stamp($r['m'] ?? null, $r['n'] ?? 0, null, $r['k'] ?? 0);
        }

        $r = $this->db->query(
            'SELECT GREATEST(IFNULL(MAX(cs.updated_at), 0), IFNULL(MAX(csp.updated_at), 0)) AS m, COUNT(*) AS n
               FROM class_session_players csp
               JOIN class_sessions cs ON cs.id = csp.session_id
              WHERE csp.user_id = ?',
            [$userId]
        )->getRowArray();
        return self::stamp($r['m'] ?? null, $r['n'] ?? 0);
    }

    /** Bonos: admin = todos; alumno = los suyos; el resto no los ve. */
    private function bonoStamp(int $userId, string $role): ?string
    {
        if (in_array($role, ['admin', 'superadmin'], true)) {
            $r = $this->db->query('SELECT MAX(updated_at) AS m, COUNT(*) AS n FROM player_bonos')->getRowArray();
            return self::stamp($r['m'] ?? null, $r['n'] ?? 0);
        }
        if (in_array($role, ['player', 'alumno'], true)) {
            $r = $this->db->query('SELECT MAX(updated_at) AS m, COUNT(*) AS n FROM player_bonos WHERE player_id = ?', [$userId])->getRowArray();
            return self::stamp($r['m'] ?? null, $r['n'] ?? 0);
        }
        return null;
    }

    /** Tickets: admin = todos; el resto, los que abrió. Incluye sus respuestas. */
    private function ticketStamp(int $userId, string $role): ?string
    {
        if (in_array($role, ['admin', 'superadmin'], true)) {
            $t = $this->db->query('SELECT MAX(updated_at) AS m, COUNT(*) AS n FROM tickets')->getRowArray();
            $r = $this->db->query('SELECT MAX(created_at) AS m, COUNT(*) AS n FROM ticket_replies')->getRowArray();
            return self::stamp($t['m'] ?? null, $t['n'] ?? 0, $r['m'] ?? null, $r['n'] ?? 0);
        }
        $t = $this->db->query('SELECT MAX(updated_at) AS m, COUNT(*) AS n FROM tickets WHERE user_id = ?', [$userId])->getRowArray();
        $r = $this->db->query(
            'SELECT MAX(tr.created_at) AS m, COUNT(*) AS n FROM ticket_replies tr JOIN tickets t ON t.id = tr.ticket_id WHERE t.user_id = ?',
            [$userId]
        )->getRowArray();
        return self::stamp($t['m'] ?? null, $t['n'] ?? 0, $r['m'] ?? null, $r['n'] ?? 0);
    }

    // ── Utilidades ──────────────────────────────────────────────────────

    /** Sello estable «fecha|nº» (+ otro par): cambia si algo se modifica, se añade o se borra. Pura. */
    public static function stamp(?string $maxA, $countA, ?string $maxB = null, $countB = null): string
    {
        $part = static fn (?string $m, $n): string => ($m && $m !== '0' ? $m : '-') . '|' . (int) $n;
        return $countB === null && $maxB === null ? $part($maxA, $countA) : $part($maxA, $countA) . '/' . $part($maxB, $countB);
    }

    private function safe(callable $fn, $fallback)
    {
        try {
            return $fn();
        } catch (\Throwable $e) {
            log_message('warning', '[LiveService] ' . $e->getMessage());
            return $fallback;
        }
    }
}
