<?php

namespace App\Services;

/**
 * Finanzas 2.0 — informes: resumen del periodo, evolución mensual, todas las
 * operaciones del periodo (Movimientos), entrenadores y análisis.
 *
 * Conceptos (glosario en la vista):
 *  - Vendido:            cargos del periodo (bonos vendidos con su descuento, cargos manuales).
 *  - Cobrado:            cobros del periodo (dinero que entra).
 *  - Servicio prestado:  sesiones consumidas en el periodo × precio por sesión de su bono.
 *  - Gastos:             gastos del periodo.
 *  - Resultado:          cobrado − gastos.
 *  - Pendiente de cobro: lo vendido aún sin pagar (a hoy).
 *  - Sesiones pagadas sin dar: saldo vivo de los bonos (a hoy).
 *  - Caducado sin usar:  saldo de bonos que caducaron en el periodo.
 *
 * `period()` es pura.
 */
class FinanceReportService
{
    private \CodeIgniter\Database\BaseConnection $db;

    private const MONTHS = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto',
                            'septiembre', 'octubre', 'noviembre', 'diciembre'];

    public function __construct(?\CodeIgniter\Database\BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    /**
     * Periodo pedido: `mes=YYYY-MM` o `desde`/`hasta` (YYYY-MM-DD). Por defecto,
     * el mes en curso. Pura.
     *
     * @return array{from:string,to:string,label:string,month:?string}
     */
    public static function period(?string $month, ?string $from = null, ?string $to = null, ?string $today = null): array
    {
        $today = $today ?: date('Y-m-d');
        $okDate = static fn($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false;
        if ($okDate($from) && $okDate($to) && $from <= $to) {
            return ['from' => $from, 'to' => $to, 'month' => null,
                    'label' => date('d/m/Y', strtotime($from)) . ' – ' . date('d/m/Y', strtotime($to))];
        }
        if (!is_string($month) || !preg_match('/^(\d{4})-(\d{2})$/', $month, $m) || (int) $m[2] < 1 || (int) $m[2] > 12) {
            $month = substr($today, 0, 7);
            preg_match('/^(\d{4})-(\d{2})$/', $month, $m);
        }
        $first = $month . '-01';
        $last  = date('Y-m-t', strtotime($first));
        $label = ucfirst(self::MONTHS[(int) $m[2]]) . ' ' . $m[1] . ($month === substr($today, 0, 7) ? ' (hasta hoy)' : '');
        return ['from' => $first, 'to' => $last, 'label' => $label, 'month' => $month];
    }

    /** Últimos N meses (YYYY-MM), del más reciente al más antiguo. Pura. */
    public static function lastMonths(int $n, ?string $today = null): array
    {
        $base = strtotime(substr($today ?: date('Y-m-d'), 0, 7) . '-01');
        $out  = [];
        for ($i = 0; $i < $n; $i++) {
            $out[] = date('Y-m', strtotime("-{$i} months", $base));
        }
        return $out;
    }

    private function scalar(string $sql, array $binds = []): int
    {
        $row = $this->db->query($sql, $binds)->getRowArray();
        return (int) round((float) (array_values($row ?? [0])[0] ?? 0));
    }

    /** Valor (céntimos) de las sesiones consumidas en sesiones de un rango de fechas. */
    private function serviceValue(string $from, string $to, ?array $sessionIds = null): array
    {
        $sql = "SELECT COUNT(*) AS n, COALESCE(SUM(pb.price_cents / NULLIF(pb.sessions_total, 0)), 0) AS v
                FROM class_session_players csp
                JOIN class_sessions cs ON cs.id = csp.session_id
                JOIN player_bonos pb ON pb.id = csp.bono_deducted_from_id
                WHERE csp.bono_deducted_at IS NOT NULL AND cs.session_date BETWEEN ? AND ?";
        $binds = [$from, $to];
        if ($sessionIds !== null) {
            if (!$sessionIds) {
                return ['count' => 0, 'cents' => 0];
            }
            $sql .= ' AND cs.id IN (' . implode(',', array_map('intval', $sessionIds)) . ')';
        }
        $r = $this->db->query($sql, $binds)->getRowArray();
        return ['count' => (int) $r['n'], 'cents' => (int) round((float) $r['v'])];
    }

    // ────────────────────────────────────────────────────────────────
    //  Resumen
    // ────────────────────────────────────────────────────────────────

    public function summary(string $from, string $to, ?string $today = null): array
    {
        $today = $today ?: date('Y-m-d');

        $sold = $this->db->query("SELECT COUNT(*) n, COALESCE(SUM(amount_cents),0) v, COALESCE(SUM(discount_cents),0) d
                                  FROM fin_charges WHERE voided_at IS NULL AND charged_at BETWEEN ? AND ?", [$from, $to])->getRowArray();
        $paid = $this->db->query("SELECT COUNT(*) n, COALESCE(SUM(amount_cents),0) v
                                  FROM fin_payments WHERE voided_at IS NULL AND paid_at BETWEEN ? AND ?", [$from, $to])->getRowArray();
        $byMethod = $this->db->query("SELECT COALESCE(m.name, 'Sin medio') name, SUM(p.amount_cents) v
                                      FROM fin_payments p LEFT JOIN fin_payment_methods m ON m.id = p.method_id
                                      WHERE p.voided_at IS NULL AND p.paid_at BETWEEN ? AND ?
                                      GROUP BY m.name ORDER BY v DESC", [$from, $to])->getResultArray();
        $exp  = $this->db->query("SELECT COUNT(*) n, COALESCE(SUM(amount_cents),0) v
                                  FROM fin_expenses WHERE voided_at IS NULL AND spent_at BETWEEN ? AND ?", [$from, $to])->getRowArray();
        $byCat = $this->db->query("SELECT COALESCE(c.name, 'Sin categoría') name, SUM(e.amount_cents) v
                                   FROM fin_expenses e LEFT JOIN fin_categories c ON c.id = e.category_id
                                   WHERE e.voided_at IS NULL AND e.spent_at BETWEEN ? AND ?
                                   GROUP BY c.name ORDER BY v DESC", [$from, $to])->getResultArray();
        $service = $this->serviceValue($from, $to);

        $due = $this->scalar("SELECT COALESCE(SUM(c.amount_cents - COALESCE(x.paid, 0)), 0)
                              FROM fin_charges c
                              LEFT JOIN (SELECT a.charge_id, SUM(a.amount_cents) paid FROM fin_payment_allocations a
                                         JOIN fin_payments p ON p.id = a.payment_id AND p.voided_at IS NULL GROUP BY a.charge_id) x
                                     ON x.charge_id = c.id
                              WHERE c.voided_at IS NULL AND c.amount_cents > COALESCE(x.paid, 0)");
        $prepaid = $this->db->query("SELECT COALESCE(SUM(sessions_remaining),0) s,
                                            COALESCE(SUM(price_cents * sessions_remaining / NULLIF(sessions_total,0)),0) v
                                     FROM player_bonos WHERE voided_at IS NULL AND sessions_remaining > 0
                                       AND (expires_at IS NULL OR expires_at >= ?)", [$today])->getRowArray();
        $expired = $this->db->query("SELECT COUNT(*) n, COALESCE(SUM(sessions_remaining),0) s,
                                            COALESCE(SUM(price_cents * sessions_remaining / NULLIF(sessions_total,0)),0) v
                                     FROM player_bonos WHERE voided_at IS NULL AND sessions_remaining > 0
                                       AND expires_at BETWEEN ? AND ? AND expires_at < ?", [$from, $to, $today])->getRowArray();
        $avg = $this->db->query("SELECT COALESCE(SUM(price_cents),0) v, COALESCE(SUM(sessions_total),0) s FROM player_bonos WHERE voided_at IS NULL")->getRowArray();

        return [
            'sold'      => ['count' => (int) $sold['n'], 'cents' => (int) $sold['v'], 'discount' => (int) $sold['d']],
            'paid'      => ['count' => (int) $paid['n'], 'cents' => (int) $paid['v'], 'by_method' => $byMethod],
            'service'   => $service,
            'expenses'  => ['count' => (int) $exp['n'], 'cents' => (int) $exp['v'], 'by_category' => $byCat],
            'result'    => (int) $paid['v'] - (int) $exp['v'],
            'due'       => $due,
            'prepaid'   => ['sessions' => (int) $prepaid['s'], 'cents' => (int) round((float) $prepaid['v'])],
            'expired'   => ['count' => (int) $expired['n'], 'sessions' => (int) $expired['s'], 'cents' => (int) round((float) $expired['v'])],
            'avg_session_cents' => (int) $avg['s'] > 0 ? (int) round((int) $avg['v'] / (int) $avg['s']) : 0,
            'attendance' => $this->attendance($from, $to),
        ];
    }

    /**
     * Asistencia del periodo en sesiones cerradas, con los avisos separados por
     * antelación (horas de aviso configurables, por defecto 24).
     */
    public function attendance(string $from, string $to): array
    {
        $hours = $this->noticeHours();
        $r = $this->db->query(
            "SELECT SUM(csp.attendance = 'present') present, SUM(csp.attendance = 'absent') absent,
                    SUM(csp.attendance = 'unjustified') unjustified, SUM(csp.attendance = 'declined') declined,
                    SUM(csp.student_noted_at IS NOT NULL AND csp.attendance IN ('declined','absent')
                        AND TIMESTAMPDIFF(MINUTE, csp.student_noted_at, TIMESTAMP(cs.session_date, cs.start_time)) < ? * 60) late_notice,
                    SUM(csp.student_noted_at IS NOT NULL AND csp.attendance IN ('declined','absent')
                        AND TIMESTAMPDIFF(MINUTE, csp.student_noted_at, TIMESTAMP(cs.session_date, cs.start_time)) >= ? * 60) early_notice
             FROM class_session_players csp JOIN class_sessions cs ON cs.id = csp.session_id
             WHERE cs.status = 'completed' AND cs.session_date BETWEEN ? AND ?",
            [$hours, $hours, $from, $to]
        )->getRowArray();
        return array_map('intval', $r ?? []) + ['notice_hours' => $hours];
    }

    public function noticeHours(): int
    {
        $r = $this->db->table('academy_settings')->select('setting_value')->where('setting_key', 'fin_notice_hours')->get()->getRow();
        return $r ? max(1, (int) $r->setting_value) : 24;
    }

    /** Evolución de los últimos N meses: vendido, cobrado, servicio prestado y gastos. */
    public function monthly(int $n = 12, ?string $today = null): array
    {
        $out = [];
        foreach (array_reverse(self::lastMonths($n, $today)) as $m) {
            $from = $m . '-01';
            $to   = date('Y-m-t', strtotime($from));
            $out[] = [
                'month'    => $m,
                'label'    => ucfirst(mb_substr(self::MONTHS[(int) substr($m, 5, 2)], 0, 3)) . ' ' . substr($m, 2, 2),
                'sold'     => $this->scalar("SELECT COALESCE(SUM(amount_cents),0) FROM fin_charges WHERE voided_at IS NULL AND charged_at BETWEEN ? AND ?", [$from, $to]),
                'paid'     => $this->scalar("SELECT COALESCE(SUM(amount_cents),0) FROM fin_payments WHERE voided_at IS NULL AND paid_at BETWEEN ? AND ?", [$from, $to]),
                'service'  => $this->serviceValue($from, $to)['cents'],
                'expenses' => $this->scalar("SELECT COALESCE(SUM(amount_cents),0) FROM fin_expenses WHERE voided_at IS NULL AND spent_at BETWEEN ? AND ?", [$from, $to]),
            ];
        }
        return $out;
    }

    // ────────────────────────────────────────────────────────────────
    //  Movimientos: TODAS las operaciones del periodo
    // ────────────────────────────────────────────────────────────────

    public const MOVE_TYPES = [
        'venta'     => 'Venta',
        'cobro'     => 'Cobro',
        'gasto'     => 'Gasto',
        'servicio'  => 'Sesión consumida',
        'caducado'  => 'Caducado sin usar',
    ];

    /**
     * @return array{rows:array<int,array>,totals:array<string,array{count:int,cents:int}>}
     */
    public function movements(string $from, string $to, ?string $type = null, ?string $today = null): array
    {
        $today = $today ?: date('Y-m-d');
        $rows  = [];
        $want  = static fn(string $t) => $type === null || $type === '' || $type === $t;

        if ($want('venta')) {
            foreach ($this->db->query("SELECT c.id, c.charged_at d, c.concept, c.amount_cents, c.discount_cents, c.discount_reason, c.voided_at, c.void_reason,
                                              c.player_id, u.name who
                                       FROM fin_charges c LEFT JOIN users u ON u.id = c.player_id
                                       WHERE c.charged_at BETWEEN ? AND ?", [$from, $to])->getResultArray() as $r) {
                $rows[] = ['date' => $r['d'], 'type' => 'venta', 'who' => $r['who'], 'player_id' => (int) $r['player_id'],
                           'detail' => $r['concept'] . ($r['discount_cents'] > 0 ? ' · descuento ' . number_format($r['discount_cents'] / 100, 2, ',', '.') . ' € (' . ($r['discount_reason'] ?: '—') . ')' : ''),
                           'cents' => (int) $r['amount_cents'], 'voided' => (bool) $r['voided_at'], 'void_reason' => $r['void_reason'], 'ref' => 'C' . $r['id']];
            }
        }
        if ($want('cobro')) {
            foreach ($this->db->query("SELECT p.id, p.paid_at d, p.amount_cents, p.voided_at, p.void_reason, p.note, p.player_id, u.name who, m.name method
                                       FROM fin_payments p LEFT JOIN users u ON u.id = p.player_id
                                       LEFT JOIN fin_payment_methods m ON m.id = p.method_id
                                       WHERE p.paid_at BETWEEN ? AND ?", [$from, $to])->getResultArray() as $r) {
                $rows[] = ['date' => $r['d'], 'type' => 'cobro', 'who' => $r['who'], 'player_id' => (int) $r['player_id'],
                           'detail' => ($r['method'] ?? 'Sin medio') . ($r['note'] ? ' · ' . $r['note'] : ''),
                           'cents' => (int) $r['amount_cents'], 'voided' => (bool) $r['voided_at'], 'void_reason' => $r['void_reason'], 'ref' => 'P' . $r['id']];
            }
        }
        if ($want('gasto')) {
            foreach ((new ExpenseService($this->db))->list($from, $to) as $r) {
                $rows[] = ['date' => $r['spent_at'], 'type' => 'gasto', 'who' => $r['staff_name'] ?: ($r['supplier'] ?: '—'), 'player_id' => null,
                           'detail' => trim(($r['category_name'] ?? 'Sin categoría') . ($r['description'] ? ' · ' . $r['description'] : '') . ($r['location_name'] ? ' · ' . $r['location_name'] : '')),
                           'cents' => -(int) $r['amount_cents'], 'voided' => (bool) $r['voided_at'], 'void_reason' => $r['void_reason'], 'ref' => 'G' . $r['id']];
            }
        }
        if ($want('servicio')) {
            foreach ($this->db->query("SELECT csp.id, cs.session_date d, cs.id sid, u.name who, csp.user_id, csp.attendance, bt.name bono,
                                              pb.price_cents / NULLIF(pb.sessions_total, 0) v
                                       FROM class_session_players csp
                                       JOIN class_sessions cs ON cs.id = csp.session_id
                                       JOIN player_bonos pb ON pb.id = csp.bono_deducted_from_id
                                       LEFT JOIN bono_types bt ON bt.id = pb.bono_type_id
                                       LEFT JOIN users u ON u.id = csp.user_id
                                       WHERE csp.bono_deducted_at IS NOT NULL AND cs.session_date BETWEEN ? AND ?", [$from, $to])->getResultArray() as $r) {
                $att = ['present' => 'asistió', 'unjustified' => 'falta sin justificar', 'confirmed' => 'confirmada', 'declined' => 'aviso tardío'][$r['attendance']] ?? $r['attendance'];
                $rows[] = ['date' => $r['d'], 'type' => 'servicio', 'who' => $r['who'], 'player_id' => (int) $r['user_id'],
                           'detail' => ($r['bono'] ?? 'Bono') . ' · ' . $att, 'cents' => (int) round((float) $r['v']),
                           'voided' => false, 'void_reason' => null, 'ref' => 'S' . $r['id'], 'session_id' => (int) $r['sid']];
            }
        }
        if ($want('caducado')) {
            foreach ($this->db->query("SELECT pb.id, pb.expires_at d, pb.sessions_remaining, pb.sessions_total, pb.price_cents, u.name who, pb.player_id, bt.name bono
                                       FROM player_bonos pb LEFT JOIN users u ON u.id = pb.player_id
                                       LEFT JOIN bono_types bt ON bt.id = pb.bono_type_id
                                       WHERE pb.voided_at IS NULL AND pb.sessions_remaining > 0
                                         AND pb.expires_at BETWEEN ? AND ? AND pb.expires_at < ?", [$from, $to, $today])->getResultArray() as $r) {
                $rows[] = ['date' => $r['d'], 'type' => 'caducado', 'who' => $r['who'], 'player_id' => (int) $r['player_id'],
                           'detail' => ($r['bono'] ?? 'Bono') . ' · ' . (int) $r['sessions_remaining'] . ' de ' . (int) $r['sessions_total'] . ' sin usar',
                           'cents' => RevisionService::unusedValueCents((int) $r['price_cents'], (int) $r['sessions_remaining'], (int) $r['sessions_total']),
                           'voided' => false, 'void_reason' => null, 'ref' => 'B' . $r['id']];
            }
        }

        usort($rows, fn($a, $b) => [$b['date'], $b['ref']] <=> [$a['date'], $a['ref']]);

        $totals = array_fill_keys(array_keys(self::MOVE_TYPES), ['count' => 0, 'cents' => 0]);
        foreach ($rows as $r) {
            if (!$r['voided']) {
                $totals[$r['type']]['count']++;
                $totals[$r['type']]['cents'] += $r['cents'];
            }
        }
        return ['rows' => $rows, 'totals' => $totals];
    }

    // ────────────────────────────────────────────────────────────────
    //  Entrenadores
    // ────────────────────────────────────────────────────────────────

    public function coaches(string $from, string $to): array
    {
        $staff = $this->db->table('users')->select('id, name, role, status')
            ->whereIn('role', ['coach', 'staff', 'admin', 'superadmin'])->orderBy('name')->get()->getResultArray();
        $out = [];
        foreach ($staff as $s) {
            $sids = array_map('intval', array_column($this->db->query(
                "SELECT cs.id FROM class_session_coaches csc JOIN class_sessions cs ON cs.id = csc.session_id
                 WHERE csc.user_id = ? AND cs.status = 'completed' AND cs.session_date BETWEEN ? AND ?",
                [(int) $s['id'], $from, $to]
            )->getResultArray(), 'id'));
            $present = $sids ? $this->scalar("SELECT COUNT(*) FROM class_session_players WHERE attendance = 'present' AND session_id IN (" . implode(',', $sids) . ")") : 0;
            $service = $this->serviceValue($from, $to, $sids);
            $exp = $this->db->query("SELECT COUNT(*) n, COALESCE(SUM(amount_cents),0) v FROM fin_expenses
                                     WHERE voided_at IS NULL AND staff_id = ? AND spent_at BETWEEN ? AND ?", [(int) $s['id'], $from, $to])->getRowArray();
            if (!$sids && !(int) $exp['n'] && $s['role'] !== 'coach') {
                continue;   // admins/staff sin clases ni gastos no aparecen
            }
            $out[] = $s + [
                'sessions'      => count($sids),
                'attended'      => $present,
                'service_cents' => $service['cents'],
                'expense_count' => (int) $exp['n'],
                'expense_cents' => (int) $exp['v'],
            ];
        }
        usort($out, fn($a, $b) => [$b['sessions'], $a['name']] <=> [$a['sessions'], $b['name']]);
        return $out;
    }

    // ────────────────────────────────────────────────────────────────
    //  Análisis
    // ────────────────────────────────────────────────────────────────

    public function analysis(string $from, string $to): array
    {
        $byType = $this->db->query(
            "SELECT COALESCE(bt.name, c.concept) name, COUNT(*) n, SUM(c.amount_cents) v, SUM(c.discount_cents) d,
                    MAX(bt.sessions) sessions
             FROM fin_charges c
             LEFT JOIN player_bonos pb ON pb.id = c.bono_id
             LEFT JOIN bono_types bt ON bt.id = pb.bono_type_id
             WHERE c.voided_at IS NULL AND c.charged_at BETWEEN ? AND ?
             GROUP BY COALESCE(bt.name, c.concept) ORDER BY v DESC", [$from, $to])->getResultArray();

        $byFormat = $this->db->query(
            "SELECT cs.class_format f, COUNT(DISTINCT cs.id) sessions, SUM(csp.attendance = 'present') present
             FROM class_sessions cs LEFT JOIN class_session_players csp ON csp.session_id = cs.id
             WHERE cs.status = 'completed' AND cs.session_date BETWEEN ? AND ?
             GROUP BY cs.class_format", [$from, $to])->getResultArray();

        $byLocation = $this->db->query(
            "SELECT COALESCE(l.name, 'Sin sede') name, COUNT(*) sessions
             FROM class_sessions cs LEFT JOIN locations l ON l.id = cs.location_id
             WHERE cs.status = 'completed' AND cs.session_date BETWEEN ? AND ?
             GROUP BY l.name ORDER BY sessions DESC", [$from, $to])->getResultArray();

        $byCategory = $this->db->query(
            "SELECT COALESCE(pp.category, 'sin categoría') cat, COUNT(DISTINCT csp.user_id) players, COUNT(*) attended
             FROM class_session_players csp
             JOIN class_sessions cs ON cs.id = csp.session_id
             LEFT JOIN player_profiles pp ON pp.player_id = csp.user_id
             WHERE cs.status = 'completed' AND csp.attendance = 'present' AND cs.session_date BETWEEN ? AND ?
             GROUP BY pp.category ORDER BY attended DESC", [$from, $to])->getResultArray();

        return [
            'by_type'     => $byType,
            'by_format'   => $byFormat,
            'by_location' => $byLocation,
            'by_category' => $byCategory,
            'attendance'  => $this->attendance($from, $to),
        ];
    }
}
