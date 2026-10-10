<?php

namespace App\Services;

/**
 * Informe de bonos por alumno (solo administración): cuántos bonos con saldo
 * tiene cada uno, saldo total, lo emitido y lo que queda por consumir en euros,
 * clases programadas, deudas y alertas. Pensado para el control financiero:
 * todo sale de `player_bonos` (+ precio del tipo de bono) y del calendario.
 *
 * Importes: desde v1.33.0 con el precio CONGELADO de cada bono (`price_cents`);
 * los bonos anteriores llevan el precio del tipo de entonces, marcado como
 * estimado. Los bonos anulados no cuentan. "Pendiente" = sesiones que quedan
 * × (precio / sesiones).
 *
 * `alertsFor()` es pura (sin BD) para poder probar las reglas.
 */
class BonoReportService
{
    public const ALERT_OVERLAP   = 'overlap';    // 2+ bonos con saldo a la vez
    public const ALERT_LOW       = 'low';        // saldo total bajo (1 o 2)
    public const ALERT_EMPTY     = 'empty';      // sin saldo pero con clases programadas
    public const ALERT_OVER      = 'over';       // más clases programadas que saldo
    public const ALERT_DEBT      = 'debt';       // clases dadas sin bono
    public const ALERT_EXPIRING  = 'expiring';   // un bono con saldo caduca pronto

    /** Saldo total a partir del cual se avisa de "saldo bajo". */
    public const LOW_BALANCE = 2;

    private \CodeIgniter\Database\BaseConnection $db;

    public function __construct(?\CodeIgniter\Database\BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    /**
     * Reglas de alerta de un alumno.
     *
     * @param array $r fila con: bonos_usable, saldo, scheduled, debts, next_expiry
     * @return string[] constantes ALERT_*
     */
    public static function alertsFor(array $r, ?string $today = null): array
    {
        $today   = $today ?: date('Y-m-d');
        $usable  = (int) ($r['bonos_usable'] ?? 0);
        $saldo   = (int) ($r['saldo'] ?? 0);
        $sched   = (int) ($r['scheduled'] ?? 0);
        $debts   = (int) ($r['debts'] ?? 0);
        $expiry  = $r['next_expiry'] ?? null;

        $out = [];
        if ($usable >= 2) {
            $out[] = self::ALERT_OVERLAP;
        }
        if ($usable >= 1 && $saldo <= self::LOW_BALANCE) {
            $out[] = self::ALERT_LOW;
        }
        if ($saldo === 0 && $sched > 0) {
            $out[] = self::ALERT_EMPTY;
        } elseif ($sched + $debts > $saldo && $sched > 0) {
            $out[] = self::ALERT_OVER;
        }
        if ($debts > 0) {
            $out[] = self::ALERT_DEBT;
        }
        if ($expiry && $expiry >= $today && $expiry <= date('Y-m-d', strtotime($today . ' +' . BonoCoverageService::EXPIRY_WARN_DAYS . ' days'))) {
            $out[] = self::ALERT_EXPIRING;
        }
        return $out;
    }

    /** Etiquetas de las alertas: [texto corto, color de fondo, color de texto]. */
    public static function alertLabels(): array
    {
        return [
            self::ALERT_OVERLAP  => ['Varios bonos',      '#ede9fe', '#6d28d9'],
            self::ALERT_LOW      => ['Saldo bajo',        '#fef3c7', '#92400e'],
            self::ALERT_EMPTY    => ['Sin saldo y con clases', '#fee2e2', '#b91c1c'],
            self::ALERT_OVER     => ['Clases sobre saldo', '#ffedd5', '#b45309'],
            self::ALERT_DEBT     => ['Clases sin bono',   '#fee2e2', '#b91c1c'],
            self::ALERT_EXPIRING => ['Caduca pronto',     '#fef3c7', '#92400e'],
        ];
    }

    /**
     * @return array{rows:array<int,array>,totals:array,alerts:array<string,int>}
     */
    public function build(?string $today = null): array
    {
        $today = $today ?: date('Y-m-d');
        $pb    = $this->db->prefixTable('player_bonos');
        $bt    = $this->db->prefixTable('bono_types');
        $us    = $this->db->prefixTable('users');

        $usable = "(pb.sessions_remaining > 0 AND (pb.expires_at IS NULL OR pb.expires_at >= ?))";
        // v1.33.0: precio congelado del bono; si es un bono antiguo sin él, el del tipo.
        $price  = "COALESCE(pb.price_cents / 100, bt.price, 0)";
        $sql = "SELECT u.id, u.name, u.email, u.status,
                    COUNT(pb.id) AS bonos_total,
                    SUM(CASE WHEN {$usable} THEN 1 ELSE 0 END) AS bonos_usable,
                    SUM(CASE WHEN {$usable} THEN pb.sessions_remaining ELSE 0 END) AS saldo,
                    SUM(pb.sessions_total - pb.sessions_remaining) AS consumed,
                    SUM({$price}) AS issued_eur,
                    SUM(CASE WHEN {$usable} AND pb.sessions_total > 0
                             THEN pb.sessions_remaining * {$price} / pb.sessions_total ELSE 0 END) AS pending_eur,
                    MIN(CASE WHEN {$usable} AND pb.expires_at IS NOT NULL THEN pb.expires_at ELSE NULL END) AS next_expiry
                FROM {$us} u
                JOIN {$pb} pb ON pb.player_id = u.id AND pb.voided_at IS NULL
                LEFT JOIN {$bt} bt ON bt.id = pb.bono_type_id
                WHERE u.role = 'player'
                GROUP BY u.id, u.name, u.email, u.status
                ORDER BY u.name ASC";
        $rows = $this->db->query($sql, [$today, $today, $today, $today])->getResultArray();

        $ids = array_map(fn($r) => (int) $r['id'], $rows);

        $scheduled = [];
        if ($ids) {
            foreach ($this->db->table('class_session_players csp')
                ->select('csp.user_id, COUNT(*) AS n')
                ->join('class_sessions cs', 'cs.id = csp.session_id')
                ->whereIn('csp.user_id', $ids)
                ->where('cs.status', 'scheduled')->where('cs.session_date >=', $today)
                ->where('csp.bono_deducted_at IS NULL', null, false)
                ->groupBy('csp.user_id')->get()->getResultArray() as $r) {
                $scheduled[(int) $r['user_id']] = (int) $r['n'];
            }
        }
        $debts = $ids ? (new BonoCoverageService($this->db))->debtCounts($ids) : [];

        $totals = ['players' => 0, 'multi' => 0, 'saldo' => 0, 'issued_eur' => 0.0, 'pending_eur' => 0.0, 'debts' => 0, 'scheduled' => 0];
        $alerts = array_fill_keys(array_keys(self::alertLabels()), 0);
        $out    = [];

        foreach ($rows as $r) {
            $id = (int) $r['id'];
            $row = [
                'id'           => $id,
                'name'         => $r['name'],
                'email'        => $r['email'],
                'status'       => $r['status'],
                'bonos_total'  => (int) $r['bonos_total'],
                'bonos_usable' => (int) $r['bonos_usable'],
                'saldo'        => (int) $r['saldo'],
                'consumed'     => (int) $r['consumed'],
                'issued_eur'   => (float) $r['issued_eur'],
                'pending_eur'  => (float) $r['pending_eur'],
                'next_expiry'  => $r['next_expiry'],
                'scheduled'    => $scheduled[$id] ?? 0,
                'debts'        => $debts[$id] ?? 0,
            ];
            $row['alerts'] = self::alertsFor($row, $today);
            foreach ($row['alerts'] as $a) {
                $alerts[$a]++;
            }

            if ($row['bonos_usable'] > 0) {
                $totals['players']++;
                if ($row['bonos_usable'] >= 2) {
                    $totals['multi']++;
                }
            }
            $totals['saldo']       += $row['saldo'];
            $totals['issued_eur']  += $row['issued_eur'];
            $totals['pending_eur'] += $row['pending_eur'];
            $totals['debts']       += $row['debts'];
            $totals['scheduled']   += $row['scheduled'];
            $out[] = $row;
        }

        return ['rows' => $out, 'totals' => $totals, 'alerts' => $alerts];
    }
}
