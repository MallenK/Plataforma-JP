<?php

namespace App\Services;

use App\Models\NotificationModel;
use CodeIgniter\Database\BaseConnection;
use Config\Database;

/**
 * Avisos de clases: nueva clase, cambio de fecha/hora/sede, cancelación, alumno
 * añadido o quitado y recordatorio 25 h antes. Todos son de la categoría «Clases»
 * (source_type = class) y llevan a la ficha de la clase. Un fallo aquí nunca debe
 * tumbar la acción que lo provoca: cada método traga y registra sus errores.
 *
 * Quien hace el cambio nunca recibe su propio aviso.
 */
class ClassNoticeService
{
    /** Antelación del recordatorio: se envía cuando faltan 25 h o menos. */
    public const REMINDER_HOURS = 25;

    /** No se recuerda lo inminente (quien lo creó ya recibió el aviso de alta). */
    public const REMINDER_MIN_LEAD_MINUTES = 60;

    /** Sesiones que un solo disparo puede recordar (el resto, en el siguiente). */
    private const REMINDER_BATCH = 200;

    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    // ────────────────────────────────────────────────────────────────
    //  Piezas puras (testeables sin BD)
    // ────────────────────────────────────────────────────────────────

    /** «hoy a las 18:00», «mañana (17/10) a las 18:00» o «el 17/10 a las 18:00». */
    public static function whenLabel(array $session, ?string $today = null): string
    {
        $today = $today ?? date('Y-m-d');
        $date  = (string) ($session['session_date'] ?? '');
        $time  = ClasesService::normalizeTime($session['start_time'] ?? null) ?? '';
        $at    = $time !== '' ? ' a las ' . $time : '';

        if ($date === $today) {
            return 'hoy' . $at;
        }
        $dm = $date !== '' ? date('d/m', strtotime($date)) : '';
        if ($date === date('Y-m-d', strtotime($today . ' +1 day'))) {
            return 'mañana (' . $dm . ')' . $at;
        }
        return 'el ' . $dm . $at;
    }

    /**
     * Qué ha cambiado entre dos versiones de una sesión (fecha, hora, sede).
     * Lista vacía = nada que avisar. Pura.
     *
     * @return list<string>
     */
    public static function describeChanges(array $before, array $after, string $beforeLoc = '', string $afterLoc = ''): array
    {
        $out = [];

        $bd = (string) ($before['session_date'] ?? '');
        $ad = (string) ($after['session_date'] ?? '');
        if ($bd !== '' && $ad !== '' && $bd !== $ad) {
            $out[] = 'fecha: ' . date('d/m/Y', strtotime($bd)) . ' → ' . date('d/m/Y', strtotime($ad));
        }

        $bt = ClasesService::normalizeTime($before['start_time'] ?? null) ?? '';
        $at = ClasesService::normalizeTime($after['start_time'] ?? null) ?? '';
        $be = ClasesService::normalizeTime($before['end_time'] ?? null) ?? '';
        $ae = ClasesService::normalizeTime($after['end_time'] ?? null) ?? '';
        if (($bt !== '' && $at !== '' && $bt !== $at) || ($be !== '' && $ae !== '' && $be !== $ae)) {
            $out[] = 'hora: ' . $bt . '–' . $be . ' → ' . $at . '–' . $ae;
        }

        if (trim($beforeLoc) !== trim($afterLoc)) {
            $out[] = 'sede: ' . (trim($beforeLoc) !== '' ? $beforeLoc : 'sin indicar') . ' → ' . (trim($afterLoc) !== '' ? $afterLoc : 'sin indicar');
        }

        return $out;
    }

    /**
     * ¿Toca recordar esta sesión ahora? Programada, sin recordatorio previo y con
     * el inicio entre dentro de REMINDER_MIN_LEAD_MINUTES y dentro de REMINDER_HOURS. Pura.
     */
    public static function shouldRemind(array $session, int $now): bool
    {
        if (($session['status'] ?? '') !== 'scheduled' || !empty($session['reminder_sent_at'])) {
            return false;
        }
        $time = ClasesService::normalizeTime($session['start_time'] ?? null);
        if ($time === null || empty($session['session_date'])) {
            return false;
        }
        $start = strtotime($session['session_date'] . ' ' . $time . ':00');
        if ($start === false) {
            return false;
        }
        $lead = $start - $now;
        return $lead > self::REMINDER_MIN_LEAD_MINUTES * 60 && $lead <= self::REMINDER_HOURS * 3600;
    }

    /**
     * Texto del aviso de «clases nuevas». Una sola clase: detalle. Varias (serie):
     * un único resumen en lugar de una notificación por sesión. Pura.
     *
     * @param list<array> $sessions ordenadas por fecha
     * @return array{title:string, body:string}
     */
    public static function createdMessage(array $sessions, bool $forCoach): array
    {
        $first = $sessions[0];
        $n     = count($sessions);
        $who   = $forCoach ? 'Se te ha asignado' : 'Tienes';

        if ($n === 1) {
            return [
                'title' => '📅 Nueva clase: ' . $first['title'],
                'body'  => sprintf('%s una clase de "%s" %s.', $who, $first['title'], self::whenLabel($first)),
            ];
        }

        $last = $sessions[$n - 1];
        return [
            'title' => sprintf('📅 %d clases nuevas: %s', $n, $first['title']),
            'body'  => sprintf('%s %d clases de "%s", desde el %s hasta el %s.',
                $who, $n, $first['title'],
                date('d/m', strtotime($first['session_date'])), date('d/m', strtotime($last['session_date']))),
        ];
    }

    // ────────────────────────────────────────────────────────────────
    //  Avisos disparados por acciones
    // ────────────────────────────────────────────────────────────────

    /** Clases recién creadas (suelta o serie): un aviso por persona, no por sesión. */
    public function created(array $sessionIds, int $actorId): void
    {
        $this->safely('created', function () use ($sessionIds, $actorId) {
            $sessionIds = array_values(array_unique(array_filter(array_map('intval', $sessionIds))));
            if ($sessionIds === [] || $actorId <= 0) {
                return;
            }
            $sessions = $this->db->table('class_sessions')->whereIn('id', $sessionIds)
                ->orderBy('session_date')->orderBy('start_time')->get()->getResultArray();
            if ($sessions === []) {
                return;
            }

            // Persona → sus sesiones (para agrupar la serie en un solo aviso).
            $byPlayer = [];
            foreach ($this->db->table('class_session_players')->select('session_id, user_id')
                ->whereIn('session_id', $sessionIds)->get()->getResultArray() as $r) {
                $byPlayer[(int) $r['user_id']][] = (int) $r['session_id'];
            }
            $byCoach = [];
            foreach ($this->db->table('class_session_coaches')->select('session_id, user_id')
                ->whereIn('session_id', $sessionIds)->get()->getResultArray() as $r) {
                $byCoach[(int) $r['user_id']][] = (int) $r['session_id'];
            }

            $index = [];
            foreach ($sessions as $s) {
                $index[(int) $s['id']] = $s;
            }

            foreach ([[$byPlayer, false], [$byCoach, true]] as [$map, $forCoach]) {
                foreach ($map as $userId => $ids) {
                    if ($userId === $actorId) {
                        continue;
                    }
                    $mine = array_values(array_filter(array_map(static fn ($i) => $index[$i] ?? null, array_unique($ids))));
                    if ($mine === []) {
                        continue;
                    }
                    usort($mine, static fn ($a, $b) => [$a['session_date'], $a['start_time']] <=> [$b['session_date'], $b['start_time']]);
                    $msg = self::createdMessage($mine, $forCoach);
                    $this->notify($actorId, [$userId], $msg['title'], $msg['body'], (int) $mine[0]['id']);
                }
            }
        });
    }

    /** Fecha, hora o sede cambiadas: avisa a alumnos y responsables de la clase. */
    public function changed(array $before, int $actorId): void
    {
        $this->safely('changed', function () use ($before, $actorId) {
            $id = (int) ($before['id'] ?? 0);
            if ($id <= 0 || $actorId <= 0 || ($before['status'] ?? 'scheduled') !== 'scheduled') {
                return;
            }
            $after = $this->db->table('class_sessions')->where('id', $id)->get()->getRowArray();
            if (!$after) {
                return;
            }
            $changes = self::describeChanges($before, $after, $this->locationLabel($before), $this->locationLabel($after));
            if ($changes === []) {
                return;
            }
            // Si cambió la fecha/hora, el recordatorio anterior ya no vale: se reenviará.
            if (self::describeChanges($before, $after) !== [] && $this->db->fieldExists('reminder_sent_at', 'class_sessions')) {
                $this->db->table('class_sessions')->where('id', $id)->update(['reminder_sent_at' => null]);
            }

            [$players, $coaches] = $this->audience($id);
            $title = '🔄 Cambio en la clase: ' . $after['title'];
            $body  = sprintf('La clase "%s" ha cambiado (%s). Ahora es %s.', $after['title'], implode('; ', $changes), self::whenLabel($after));
            $this->notify($actorId, array_merge($players, $coaches), $title, $body, $id);
        });
    }

    /** Clase cancelada: avisa a alumnos y responsables. */
    public function cancelled(array $session, int $actorId): void
    {
        $this->safely('cancelled', function () use ($session, $actorId) {
            $id = (int) ($session['id'] ?? 0);
            if ($id <= 0 || $actorId <= 0 || ($session['status'] ?? '') !== 'scheduled') {
                return; // una clase ya cerrada/cancelada no se "cancela" otra vez ni se avisa de pasado
            }
            [$players, $coaches] = $this->audience($id);
            $this->notify(
                $actorId,
                array_merge($players, $coaches),
                '🚫 Clase cancelada: ' . $session['title'],
                sprintf('La clase "%s" prevista para %s se ha cancelado.', $session['title'], self::whenLabel($session)),
                $id
            );
        });
    }

    /**
     * Alumnos añadidos o quitados de una clase ya existente.
     *
     * @param list<int> $added
     * @param list<int> $removed
     */
    public function playersChanged(array $session, array $added, array $removed, int $actorId): void
    {
        $this->safely('playersChanged', function () use ($session, $added, $removed, $actorId) {
            $id = (int) ($session['id'] ?? 0);
            if ($id <= 0 || $actorId <= 0 || ($session['status'] ?? 'scheduled') !== 'scheduled') {
                return;
            }
            if ($added !== []) {
                $this->notify($actorId, $added, '📅 Nueva clase: ' . $session['title'],
                    sprintf('Tienes una clase de "%s" %s.', $session['title'], self::whenLabel($session)), $id);
            }
            if ($removed !== []) {
                $this->notify($actorId, $removed, '🚫 Ya no tienes la clase: ' . $session['title'],
                    sprintf('Te han quitado de la clase "%s" prevista para %s.', $session['title'], self::whenLabel($session)), $id);
            }
        });
    }

    // ────────────────────────────────────────────────────────────────
    //  Recordatorio 25 h antes
    // ────────────────────────────────────────────────────────────────

    /**
     * Envía el recordatorio de las clases que empiezan en ≤ 25 h y aún no se
     * recordaron: alumnos (salvo los que ya avisaron de que no van) y responsables.
     * Pensado para llamarse a menudo (cron o disparador perezoso): cada sesión se
     * recuerda una sola vez. Devuelve cuántas sesiones se recordaron.
     */
    public function sendReminders(?int $now = null): int
    {
        $now = $now ?? time();
        try {
            if (!$this->db->fieldExists('reminder_sent_at', 'class_sessions')) {
                return 0; // entorno sin migrar: sin marca no se puede garantizar "una sola vez"
            }

            $from = date('Y-m-d', $now);
            $to   = date('Y-m-d', $now + self::REMINDER_HOURS * 3600);
            $rows = $this->db->table('class_sessions')
                ->where('status', 'scheduled')
                ->where('reminder_sent_at IS NULL')
                ->where('session_date >=', $from)
                ->where('session_date <=', $to)
                ->orderBy('session_date')->orderBy('start_time')
                ->limit(self::REMINDER_BATCH)
                ->get()->getResultArray();

            $sent = 0;
            foreach ($rows as $s) {
                if (!self::shouldRemind($s, $now)) {
                    continue;
                }
                // Marcar ANTES de enviar: dos disparos a la vez no duplican el aviso.
                $this->db->table('class_sessions')->where('id', $s['id'])->where('reminder_sent_at IS NULL')
                    ->update(['reminder_sent_at' => date('Y-m-d H:i:s', $now)]);
                if ($this->db->affectedRows() < 1) {
                    continue;
                }
                try {
                    $this->remindOne($s);
                    $sent++;
                } catch (\Throwable $e) {
                    log_message('error', 'ClassNoticeService::remindOne(' . $s['id'] . ') falló: ' . $e->getMessage());
                }
            }
            return $sent;
        } catch (\Throwable $e) {
            log_message('error', 'ClassNoticeService::sendReminders falló: ' . $e->getMessage());
            return 0;
        }
    }

    private function remindOne(array $s): void
    {
        $id = (int) $s['id'];
        [$players, $coaches] = $this->audience($id, true);
        $recipients = array_values(array_unique(array_merge($players, $coaches)));
        if ($recipients === []) {
            return;
        }

        $loc  = $this->locationLabel($s);
        $body = sprintf('Recuerda: clase de "%s" %s%s.', $s['title'], self::whenLabel($s), $loc !== '' ? ' en ' . $loc : '');

        $sender = $this->reminderSender($recipients);
        if ($sender <= 0) {
            return;
        }
        $this->notify($sender, $recipients, '⏰ Clase ' . (str_starts_with(self::whenLabel($s), 'hoy') ? 'hoy' : 'mañana') . ': ' . $s['title'], $body, $id);
    }

    /** Un admin activo que NO sea destinatario (el push no llega al remitente). */
    private function reminderSender(array $recipients): int
    {
        $admins = $this->db->table('users')->select('id, role')
            ->whereIn('role', ['superadmin', 'admin'])->where('status', 'active')
            ->orderBy('role', 'DESC')->orderBy('id')->get()->getResultArray();
        foreach ($admins as $a) {
            if (!in_array((int) $a['id'], $recipients, true)) {
                return (int) $a['id'];
            }
        }
        return (int) ($admins[0]['id'] ?? 0);
    }

    // ────────────────────────────────────────────────────────────────
    //  Utilidades
    // ────────────────────────────────────────────────────────────────

    /**
     * @param bool $skipDeclined excluye a los alumnos que ya avisaron de que no van
     * @return array{0: list<int>, 1: list<int>} [alumnos, responsables]
     */
    private function audience(int $sessionId, bool $skipDeclined = false): array
    {
        $q = $this->db->table('class_session_players')->select('user_id, coach_id, attendance, student_noted_at')
            ->where('session_id', $sessionId);
        $players = [];
        $coaches = [];
        foreach ($q->get()->getResultArray() as $r) {
            if ($skipDeclined && (in_array($r['attendance'], ['declined', 'absent'], true) || !empty($r['student_noted_at']))) {
                continue;
            }
            $players[] = (int) $r['user_id'];
            if (!empty($r['coach_id'])) {
                $coaches[] = (int) $r['coach_id'];
            }
        }
        foreach ($this->db->table('class_session_coaches')->select('user_id')->where('session_id', $sessionId)->get()->getResultArray() as $r) {
            $coaches[] = (int) $r['user_id'];
        }
        return [array_values(array_unique($players)), array_values(array_unique($coaches))];
    }

    private function locationLabel(array $s): string
    {
        if (!empty($s['location_id'])) {
            $row = $this->db->table('locations')->select('name')->where('id', $s['location_id'])->get()->getRowArray();
            if ($row && !empty($row['name'])) {
                return (string) $row['name'];
            }
        }
        return trim((string) ($s['location_custom'] ?? ''));
    }

    /** Crea la notificación (sin el remitente entre los destinatarios). */
    private function notify(int $senderId, array $recipientIds, string $title, string $body, int $sessionId): void
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $recipientIds),
            static fn (int $i) => $i > 0 && $i !== $senderId
        )));
        if ($ids === []) {
            return;
        }
        (new NotificationModel())->createWithRecipients([
            'sender_id'   => $senderId,
            'type'        => 'individual',
            'title'       => $title,
            'body'        => $body,
            'created_at'  => date('Y-m-d H:i:s'),
            'source_type' => NotificationModel::SOURCE_CLASS,
            'source_id'   => $sessionId,
        ], $ids);
    }

    private function safely(string $what, callable $fn): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            log_message('error', 'ClassNoticeService::' . $what . ' falló: ' . $e->getMessage());
        }
    }
}
