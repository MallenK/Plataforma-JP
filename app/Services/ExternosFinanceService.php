<?php

namespace App\Services;

/**
 * Finanzas de las personas EXTERNAS (clase de prueba / suelta, sin cuenta de alumno).
 *
 * Los externos no tienen cuenta, así que no pasan por fin_charges / fin_payments (que cuentan por
 * alumno). Aquí se calculan, con las MISMAS reglas, las cifras que FinanceReportService suma a las
 * suyas: vendido, cobrado, pendiente, servicio prestado, asistencia y movimientos.
 *
 * Una clase de externo es «facturable» cuando tiene precio fijado, la clase NO está cancelada y,
 * o bien vino (asistencia «present»), o bien ya hay un cobro activo (pagó por adelantado).
 * Los importes salen siempre de class_session_guests.price_cents y class_guest_payments (sin anular).
 * Toda consulta respeta la lista de usuarios excluidos de Finanzas (FinanceExclusion::sessionSql).
 * Sin las tablas (BD sin migrar) todo devuelve vacío / 0: nunca rompe los informes.
 */
class ExternosFinanceService
{
    private \CodeIgniter\Database\BaseConnection $db;
    private ?bool $ok = null;

    public function __construct(?\CodeIgniter\Database\BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    public function available(): bool
    {
        if ($this->ok === null) {
            try {
                $this->ok = $this->db->tableExists('class_session_guests')
                    && $this->db->tableExists('class_guest_payments')
                    && $this->db->fieldExists('price_cents', 'class_session_guests');
            } catch (\Throwable $e) {
                $this->ok = false;
            }
        }
        return $this->ok;
    }

    /** Cobros activos de una clase de externo (subconsulta: céntimos). */
    private const PAID_SQL = "(SELECT COALESCE(SUM(p.amount_cents), 0) FROM class_guest_payments p WHERE p.session_guest_id = csg.id AND p.voided_at IS NULL)";

    /** Condición «facturable» (ver cabecera). Requiere los alias csg y cs. */
    private const BILLABLE = "csg.price_cents IS NOT NULL AND cs.status <> 'cancelled'
                              AND (csg.attendance = 'present'
                                   OR EXISTS (SELECT 1 FROM class_guest_payments p2 WHERE p2.session_guest_id = csg.id AND p2.voided_at IS NULL))";

    /** Vendido en el periodo (por fecha de la clase). @return array{count:int,cents:int} */
    public function sold(string $from, string $to): array
    {
        if (!$this->available()) {
            return ['count' => 0, 'cents' => 0];
        }
        $r = $this->db->query(
            "SELECT COUNT(*) n, COALESCE(SUM(csg.price_cents), 0) v
             FROM class_session_guests csg JOIN class_sessions cs ON cs.id = csg.session_id
             WHERE " . self::BILLABLE . " AND cs.session_date BETWEEN ? AND ?" . FinanceExclusion::sessionSql('cs'),
            [$from, $to]
        )->getRowArray();
        return ['count' => (int) $r['n'], 'cents' => (int) $r['v']];
    }

    /** Cobrado en el periodo (por fecha del cobro). @return array{count:int,cents:int,by_method:array} */
    public function paid(string $from, string $to): array
    {
        if (!$this->available()) {
            return ['count' => 0, 'cents' => 0, 'by_method' => []];
        }
        $base = " FROM class_guest_payments p
                  JOIN class_session_guests csg ON csg.id = p.session_guest_id
                  JOIN class_sessions cs ON cs.id = csg.session_id
                  LEFT JOIN fin_payment_methods m ON m.id = p.method_id
                  WHERE p.voided_at IS NULL AND p.paid_at BETWEEN ? AND ?" . FinanceExclusion::sessionSql('cs');
        $t = $this->db->query("SELECT COUNT(*) n, COALESCE(SUM(p.amount_cents), 0) v" . $base, [$from, $to])->getRowArray();
        $m = $this->db->query("SELECT COALESCE(m.name, 'Sin medio') name, SUM(p.amount_cents) v" . $base . " GROUP BY m.name", [$from, $to])->getResultArray();
        return ['count' => (int) $t['n'], 'cents' => (int) $t['v'], 'by_method' => $m];
    }

    /**
     * Pendiente de cobro a hoy (clases facturables con precio > cobrado), desde la fecha de inicio.
     * Con $from/$to solo las clases de ese periodo.
     */
    public function due(string $start, ?string $from = null, ?string $to = null): int
    {
        if (!$this->available()) {
            return 0;
        }
        $sql = "SELECT COALESCE(SUM(csg.price_cents - " . self::PAID_SQL . "), 0)
                FROM class_session_guests csg JOIN class_sessions cs ON cs.id = csg.session_id
                WHERE " . self::BILLABLE . " AND csg.price_cents > " . self::PAID_SQL . "
                  AND cs.session_date >= ?" . FinanceExclusion::sessionSql('cs');
        $binds = [$start];
        if ($from !== null && $to !== null) {
            $sql .= ' AND cs.session_date BETWEEN ? AND ?';
            $binds[] = $from;
            $binds[] = $to;
        }
        $row = $this->db->query($sql, $binds)->getRowArray();
        return (int) round((float) (array_values($row ?? [0])[0] ?? 0));
    }

    /**
     * Servicio prestado: clases de externo dadas (vino, sesión cerrada) × su precio.
     *
     * @param int[]|null $sessionIds limita a esas sesiones (informe por entrenador)
     * @return array{count:int,cents:int}
     */
    public function service(string $from, string $to, ?array $sessionIds = null): array
    {
        if (!$this->available()) {
            return ['count' => 0, 'cents' => 0];
        }
        $sql = "SELECT COUNT(*) n, COALESCE(SUM(csg.price_cents), 0) v
                FROM class_session_guests csg JOIN class_sessions cs ON cs.id = csg.session_id
                WHERE csg.attendance = 'present' AND cs.status = 'completed'
                  AND cs.session_date BETWEEN ? AND ?" . FinanceExclusion::sessionSql('cs');
        $binds = [$from, $to];
        if ($sessionIds !== null) {
            if (!$sessionIds) {
                return ['count' => 0, 'cents' => 0];
            }
            $sql .= ' AND cs.id IN (' . implode(',', array_map('intval', $sessionIds)) . ')';
        }
        $r = $this->db->query($sql, $binds)->getRowArray();
        return ['count' => (int) $r['n'], 'cents' => (int) $r['v']];
    }

    /** Asistencia de externos en sesiones cerradas del periodo. @return array{present:int,absent:int} */
    public function attendance(string $from, string $to, ?array $sessionIds = null): array
    {
        if (!$this->db->tableExists('class_session_guests')) {
            return ['present' => 0, 'absent' => 0];
        }
        $sql = "SELECT COALESCE(SUM(csg.attendance = 'present'), 0) present, COALESCE(SUM(csg.attendance = 'absent'), 0) absent
                FROM class_session_guests csg JOIN class_sessions cs ON cs.id = csg.session_id
                WHERE cs.status = 'completed' AND cs.session_date BETWEEN ? AND ?" . FinanceExclusion::sessionSql('cs');
        $binds = [$from, $to];
        if ($sessionIds !== null) {
            if (!$sessionIds) {
                return ['present' => 0, 'absent' => 0];
            }
            $sql .= ' AND cs.id IN (' . implode(',', array_map('intval', $sessionIds)) . ')';
        }
        $r = $this->db->query($sql, $binds)->getRowArray();
        return ['present' => (int) $r['present'], 'absent' => (int) $r['absent']];
    }

    /** Ventas por tipo (prueba / suelta) para el análisis. @return array<int,array{name:string,n:int,v:int}> */
    public function byKind(string $from, string $to): array
    {
        if (!$this->available()) {
            return [];
        }
        $rows = $this->db->query(
            "SELECT csg.kind, COUNT(*) n, COALESCE(SUM(csg.price_cents), 0) v
             FROM class_session_guests csg JOIN class_sessions cs ON cs.id = csg.session_id
             WHERE " . self::BILLABLE . " AND cs.session_date BETWEEN ? AND ?" . FinanceExclusion::sessionSql('cs') . "
             GROUP BY csg.kind ORDER BY v DESC",
            [$from, $to]
        )->getResultArray();
        $out = [];
        foreach ($rows as $r) {
            $out[] = ['name' => ExternosService::KINDS[$r['kind']] ?? $r['kind'], 'n' => (int) $r['n'], 'v' => (int) $r['v']];
        }
        return $out;
    }

    /** Personas externas distintas que vinieron en el periodo (análisis por categoría). */
    public function distinctAttended(string $from, string $to): array
    {
        if (!$this->db->tableExists('class_session_guests')) {
            return ['people' => 0, 'attended' => 0];
        }
        $r = $this->db->query(
            "SELECT COUNT(DISTINCT csg.guest_id) people, COUNT(*) attended
             FROM class_session_guests csg JOIN class_sessions cs ON cs.id = csg.session_id
             WHERE cs.status = 'completed' AND csg.attendance = 'present' AND cs.session_date BETWEEN ? AND ?" . FinanceExclusion::sessionSql('cs'),
            [$from, $to]
        )->getRowArray();
        return ['people' => (int) $r['people'], 'attended' => (int) $r['attended']];
    }

    /**
     * Movimientos (filas con el mismo formato que FinanceReportService::movements()).
     *
     * @param string[] $types venta|cobro|servicio
     * @return array<int,array>
     */
    public function movements(string $from, string $to, array $types): array
    {
        if (!$this->available()) {
            return [];
        }
        $rows = [];
        $who  = static fn(array $r) => trim($r['first_name'] . ' ' . ($r['last_name'] ?? '')) . ' (externo)';

        if (in_array('venta', $types, true)) {
            foreach ($this->db->query(
                "SELECT csg.id, cs.session_date d, cs.id sid, csg.price_cents, csg.kind, g.first_name, g.last_name
                 FROM class_session_guests csg JOIN class_sessions cs ON cs.id = csg.session_id
                 JOIN class_guests g ON g.id = csg.guest_id
                 WHERE " . self::BILLABLE . " AND cs.session_date BETWEEN ? AND ?" . FinanceExclusion::sessionSql('cs'),
                [$from, $to]
            )->getResultArray() as $r) {
                $rows[] = ['date' => $r['d'], 'type' => 'venta', 'who' => $who($r), 'player_id' => null,
                           'detail' => (ExternosService::KINDS[$r['kind']] ?? 'Clase') . ' · clase #' . $r['sid'],
                           'cents' => (int) $r['price_cents'], 'voided' => false, 'void_reason' => null,
                           'ref' => 'XC' . $r['id'], 'session_id' => (int) $r['sid']];
            }
        }
        if (in_array('cobro', $types, true)) {
            foreach ($this->db->query(
                "SELECT p.id, p.paid_at d, p.amount_cents, p.voided_at, p.void_reason, p.note, cs.id sid, g.first_name, g.last_name, m.name method
                 FROM class_guest_payments p
                 JOIN class_session_guests csg ON csg.id = p.session_guest_id
                 JOIN class_sessions cs ON cs.id = csg.session_id
                 JOIN class_guests g ON g.id = csg.guest_id
                 LEFT JOIN fin_payment_methods m ON m.id = p.method_id
                 WHERE p.paid_at BETWEEN ? AND ?" . FinanceExclusion::sessionSql('cs'),
                [$from, $to]
            )->getResultArray() as $r) {
                $rows[] = ['date' => $r['d'], 'type' => 'cobro', 'who' => $who($r), 'player_id' => null,
                           'detail' => ($r['method'] ?? 'Sin medio') . ' · clase #' . $r['sid'] . ($r['note'] ? ' · ' . $r['note'] : ''),
                           'cents' => (int) $r['amount_cents'], 'voided' => (bool) $r['voided_at'], 'void_reason' => $r['void_reason'],
                           'ref' => 'XP' . $r['id'], 'session_id' => (int) $r['sid']];
            }
        }
        if (in_array('servicio', $types, true)) {
            foreach ($this->db->query(
                "SELECT csg.id, cs.session_date d, cs.id sid, csg.price_cents, csg.kind, g.first_name, g.last_name
                 FROM class_session_guests csg JOIN class_sessions cs ON cs.id = csg.session_id
                 JOIN class_guests g ON g.id = csg.guest_id
                 WHERE csg.attendance = 'present' AND cs.status = 'completed' AND csg.price_cents IS NOT NULL
                   AND cs.session_date BETWEEN ? AND ?" . FinanceExclusion::sessionSql('cs'),
                [$from, $to]
            )->getResultArray() as $r) {
                $rows[] = ['date' => $r['d'], 'type' => 'servicio', 'who' => $who($r), 'player_id' => null,
                           'detail' => (ExternosService::KINDS[$r['kind']] ?? 'Clase') . ' · asistió',
                           'cents' => (int) $r['price_cents'], 'voided' => false, 'void_reason' => null,
                           'ref' => 'XS' . $r['id'], 'session_id' => (int) $r['sid']];
            }
        }
        return $rows;
    }

    /** Clases de externo dadas o ya cobradas pero SIN precio fijado (nadie con acceso a Finanzas lo ha puesto). */
    public function unpriced(string $start): int
    {
        if (!$this->available()) {
            return 0;
        }
        $r = $this->db->query(
            "SELECT COUNT(*) n FROM class_session_guests csg JOIN class_sessions cs ON cs.id = csg.session_id
             WHERE csg.price_cents IS NULL AND cs.status <> 'cancelled' AND csg.attendance <> 'absent'
               AND cs.session_date >= ?" . FinanceExclusion::sessionSql('cs'),
            [$start]
        )->getRowArray();
        return (int) $r['n'];
    }
}
