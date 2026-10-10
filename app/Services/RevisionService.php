<?php

namespace App\Services;

/**
 * Finanzas › Revisión (v1.33.0, Fase 0 de Finanzas; antes «Pendiente de revisar»).
 *
 * Reúne en un solo sitio lo que la administración tiene que revisar a mano
 * para que el histórico económico cuadre. Nada se resuelve solo: cada
 * elemento enlaza a su pantalla.
 *
 *  1. Sesiones pasadas que siguen «programadas» (sin cerrar).
 *  2. Clases dadas sin descontar bono (después y antes del punto de control).
 *  3. Bonos con precio estimado (anteriores a v1.33.0; se confirma el real).
 *  4. Bonos caducados con sesiones sin usar («sin usar»: se dan por ganados,
 *     pero se señalan).
 *
 * `unusedValueCents()` y `parseEuroToCents()` son puras.
 */
class RevisionService
{
    private \CodeIgniter\Database\BaseConnection $db;

    public function __construct(?\CodeIgniter\Database\BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    /** Valor de las sesiones sin usar de un bono, en céntimos. Pura. */
    public static function unusedValueCents(?int $priceCents, int $remaining, int $total): int
    {
        if ($priceCents === null || $total <= 0 || $remaining <= 0) {
            return 0;
        }
        return (int) round($priceCents * $remaining / $total);
    }

    /**
     * "225", "225,50", "225.5", "1.225,00 €" → céntimos. null si no es válido
     * o es negativo. Pura.
     */
    public static function parseEuroToCents(?string $raw): ?int
    {
        $s = trim(str_replace(['€', ' ', "\xc2\xa0"], '', (string) $raw));
        if ($s === '') {
            return null;
        }
        // Formato español con miles: 1.225,50 → 1225.50
        if (preg_match('/^\d{1,3}(\.\d{3})+(,\d{1,2})?$/', $s)) {
            $s = str_replace(['.', ','], ['', '.'], $s);
        } else {
            $s = str_replace(',', '.', $s);
        }
        if (!preg_match('/^\d+(\.\d{1,2})?$/', $s)) {
            return null;
        }
        return (int) round(((float) $s) * 100);
    }

    /** Recuentos para el aviso del dashboard (consultas baratas). */
    public function counts(?string $today = null): array
    {
        $today   = $today ?: date('Y-m-d');
        $control = new BonoControlService($this->db);

        return [
            'unclosed'  => (int) $this->db->table('class_sessions')->where('status', 'scheduled')->where('session_date <', $today)->countAllResults(),
            'debts'     => count($control->openDebts()),
            'pre_control' => count($control->unreflected()),
            'estimated' => (int) $this->db->table('player_bonos')->where('voided_at IS NULL')->where('price_estimated', 1)->countAllResults(),
            'unused'    => (int) $this->db->table('player_bonos')->where('voided_at IS NULL')->where('sessions_remaining >', 0)
                                    ->where('expires_at <', $today)->countAllResults(),
        ];
    }

    /** Sesiones pasadas sin cerrar, de la más antigua a la más reciente. */
    public function unclosedSessions(?string $today = null): array
    {
        $today = $today ?: date('Y-m-d');
        return $this->db->table('class_sessions cs')
            ->select("cs.id, cs.title, cs.session_date, cs.start_time, cs.lista_pasada_at,
                      GROUP_CONCAT(DISTINCT u.name ORDER BY u.name SEPARATOR ', ') AS coach_names,
                      COUNT(DISTINCT csp.id) AS players,
                      SUM(CASE WHEN csp.attendance <> 'pending' THEN 1 ELSE 0 END) AS marked", false)
            ->join('class_session_coaches csc', 'csc.session_id = cs.id', 'left')
            ->join('users u', 'u.id = csc.user_id', 'left')
            ->join('class_session_players csp', 'csp.session_id = cs.id', 'left')
            ->where('cs.status', 'scheduled')
            ->where('cs.session_date <', $today)
            ->groupBy('cs.id, cs.title, cs.session_date, cs.start_time, cs.lista_pasada_at')
            ->orderBy('cs.session_date', 'ASC')->orderBy('cs.start_time', 'ASC')
            ->get()->getResultArray();
    }

    /** Bonos con precio estimado (no anulados), del más antiguo al más reciente. */
    public function estimatedPriceBonos(): array
    {
        return $this->db->table('player_bonos pb')
            ->select('pb.id, pb.player_id, pb.price_cents, pb.sessions_total, pb.sessions_remaining, pb.start_date, pb.created_at,
                      u.name AS player_name, bt.name AS bono_name')
            ->join('users u', 'u.id = pb.player_id', 'left')
            ->join('bono_types bt', 'bt.id = pb.bono_type_id', 'left')
            ->where('pb.voided_at IS NULL')
            ->where('pb.price_estimated', 1)
            ->orderBy('pb.created_at', 'ASC')
            ->get()->getResultArray();
    }

    /** Bonos caducados con sesiones sin usar (no anulados), con su valor. */
    public function expiredUnused(?string $today = null): array
    {
        $today = $today ?: date('Y-m-d');
        $rows = $this->db->table('player_bonos pb')
            ->select('pb.id, pb.player_id, pb.price_cents, pb.price_estimated, pb.sessions_total, pb.sessions_remaining, pb.expires_at,
                      u.name AS player_name, bt.name AS bono_name')
            ->join('users u', 'u.id = pb.player_id', 'left')
            ->join('bono_types bt', 'bt.id = pb.bono_type_id', 'left')
            ->where('pb.voided_at IS NULL')
            ->where('pb.sessions_remaining >', 0)
            ->where('pb.expires_at <', $today)
            ->orderBy('pb.expires_at', 'DESC')
            ->get()->getResultArray();
        foreach ($rows as &$r) {
            $r['unused_cents'] = self::unusedValueCents(
                $r['price_cents'] !== null ? (int) $r['price_cents'] : null,
                (int) $r['sessions_remaining'], (int) $r['sessions_total']
            );
        }
        return $rows;
    }
}
