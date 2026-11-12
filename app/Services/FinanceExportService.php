<?php

namespace App\Services;

/**
 * Finanzas — exportación de todos los datos para estudiarlos fuera de la plataforma.
 *
 * Devuelve conjuntos de datos (`clave => ['title','columns','rows']`) listos para
 * volcar a JSON o a CSV. Incluye también lo ANULADO (marcado), porque para
 * analizar hace falta ver todo. Respeta `FinanceExclusion` (usuarios de prueba) y
 * la fecha de inicio de las finanzas. Con `$anonymize` los nombres de alumnos se
 * sustituyen por «Alumno #id» (el id se conserva para poder cruzar tablas).
 *
 * Solo lo debe llamar quien tenga acceso total (`FinanceAccessService::canFull()`);
 * la ruta ya lo exige con `finance:full`.
 */
class FinanceExportService
{
    private \CodeIgniter\Database\BaseConnection $db;

    public function __construct(?\CodeIgniter\Database\BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    /** Céntimos → euros con dos decimales (número, para que Excel/JSON lo traten como tal). */
    private static function eur($cents): ?float
    {
        return $cents === null || $cents === '' ? null : round(((int) $cents) / 100, 2);
    }

    private static function name(?string $name, $id, bool $anon): string
    {
        return $anon ? 'Alumno #' . (int) $id : (string) $name;
    }

    /**
     * @return array<string,array{title:string,columns:array<int,string>,rows:array<int,array>}>
     */
    public function build(string $from, string $to, bool $anonymize = false): array
    {
        $start = FinanceService::startDate($this->db);
        $from  = max($from, $start);
        $sets  = [];
        $run   = function (string $key, callable $fn) use (&$sets) {
            try {
                $sets[$key] = $fn();
            } catch (\Throwable $e) {
                log_message('error', 'FinanceExport ' . $key . ': ' . $e->getMessage());
                $sets[$key] = ['title' => $key, 'columns' => ['error'], 'rows' => [['No se pudo leer: ' . $e->getMessage()]]];
            }
        };

        $run('resumen_mensual', fn() => $this->monthly($from, $to));
        $run('cargos',          fn() => $this->charges($from, $to, $anonymize));
        $run('cobros',          fn() => $this->payments($from, $to, $anonymize));
        $run('reparto_cobros',  fn() => $this->allocations($from, $to));
        $run('gastos',          fn() => $this->expenses($from, $to));
        $run('bonos',           fn() => $this->bonos($start, $anonymize));
        $run('alumnos_saldos',  fn() => $this->players($anonymize));
        $run('sesiones_alumno', fn() => $this->attendance($from, $to, $anonymize));

        return $sets;
    }

    private function monthly(string $from, string $to): array
    {
        $rep  = new FinanceReportService($this->db);
        $rows = [];
        foreach ($rep->monthly(36) as $m) {
            if ($m['month'] < substr($from, 0, 7) || $m['month'] > substr($to, 0, 7)) {
                continue;
            }
            $rows[] = [$m['month'], self::eur($m['sold']), self::eur($m['paid']), self::eur($m['service']),
                       self::eur($m['expenses']), self::eur($m['paid'] - $m['expenses']), self::eur($m['sold'] - $m['paid'])];
        }
        return ['title' => 'Resumen por mes',
                'columns' => ['mes', 'vendido_eur', 'cobrado_eur', 'servicio_prestado_eur', 'gastos_eur', 'resultado_cobrado_menos_gastos_eur', 'vendido_menos_cobrado_eur'],
                'rows' => $rows];
    }

    private function charges(string $from, string $to, bool $anon): array
    {
        $r = $this->db->query(
            "SELECT c.id, c.charged_at, c.player_id, u.name AS player, c.bono_id, c.concept, c.list_cents, c.discount_cents,
                    c.discount_reason, c.amount_cents, COALESCE(x.paid, 0) AS paid_cents, c.estimated, c.note,
                    c.voided_at, c.void_reason, c.created_at
             FROM fin_charges c
             LEFT JOIN users u ON u.id = c.player_id
             LEFT JOIN (SELECT a.charge_id, SUM(a.amount_cents) paid FROM fin_payment_allocations a
                        JOIN fin_payments p ON p.id = a.payment_id AND p.voided_at IS NULL GROUP BY a.charge_id) x ON x.charge_id = c.id
             WHERE c.charged_at BETWEEN ? AND ?" . FinanceExclusion::playerSql('c.player_id') . "
             ORDER BY c.charged_at, c.id", [$from, $to])->getResultArray();
        $rows = array_map(fn($c) => [
            $c['id'], $c['charged_at'], $c['player_id'], self::name($c['player'], $c['player_id'], $anon), $c['bono_id'], $c['concept'],
            self::eur($c['list_cents']), self::eur($c['discount_cents']), $anon ? '' : $c['discount_reason'], self::eur($c['amount_cents']),
            self::eur($c['paid_cents']), $c['voided_at'] ? 0.0 : self::eur(max(0, (int) $c['amount_cents'] - (int) $c['paid_cents'])),
            $c['estimated'] ? 'sí' : '', $c['voided_at'] ? 'sí' : '', $c['void_reason'], $c['created_at'],
        ], $r);
        return ['title' => 'Cargos (lo vendido)',
                'columns' => ['id', 'fecha', 'alumno_id', 'alumno', 'bono_id', 'concepto', 'precio_tarifa_eur', 'descuento_eur', 'motivo_descuento',
                              'importe_eur', 'pagado_eur', 'pendiente_eur', 'precio_estimado', 'anulado', 'motivo_anulacion', 'creado'],
                'rows' => $rows];
    }

    private function payments(string $from, string $to, bool $anon): array
    {
        $r = $this->db->query(
            "SELECT p.id, p.paid_at, p.player_id, u.name AS player, m.name AS method, p.amount_cents, p.reference, p.note,
                    p.estimated, COALESCE(a.used, 0) AS used_cents, p.voided_at, p.void_reason, p.created_at
             FROM fin_payments p
             LEFT JOIN users u ON u.id = p.player_id
             LEFT JOIN fin_payment_methods m ON m.id = p.method_id
             LEFT JOIN (SELECT payment_id, SUM(amount_cents) used FROM fin_payment_allocations GROUP BY payment_id) a ON a.payment_id = p.id
             WHERE p.paid_at BETWEEN ? AND ?" . FinanceExclusion::playerSql('p.player_id') . "
             ORDER BY p.paid_at, p.id", [$from, $to])->getResultArray();
        $rows = array_map(fn($p) => [
            $p['id'], $p['paid_at'], $p['player_id'], self::name($p['player'], $p['player_id'], $anon), $p['method'] ?? 'Sin medio',
            self::eur($p['amount_cents']), self::eur($p['used_cents']), self::eur((int) $p['amount_cents'] - (int) $p['used_cents']),
            $anon ? '' : $p['reference'], $anon ? '' : $p['note'], $p['estimated'] ? 'sí' : '', $p['voided_at'] ? 'sí' : '', $p['void_reason'], $p['created_at'],
        ], $r);
        return ['title' => 'Cobros (lo que ha entrado)',
                'columns' => ['id', 'fecha', 'alumno_id', 'alumno', 'medio', 'importe_eur', 'aplicado_a_cargos_eur', 'saldo_a_favor_eur',
                              'referencia', 'nota', 'estimado', 'anulado', 'motivo_anulacion', 'creado'],
                'rows' => $rows];
    }

    private function allocations(string $from, string $to): array
    {
        $r = $this->db->query(
            "SELECT a.id, a.payment_id, a.charge_id, a.amount_cents, a.created_at
             FROM fin_payment_allocations a
             JOIN fin_payments p ON p.id = a.payment_id
             WHERE p.paid_at BETWEEN ? AND ?" . FinanceExclusion::playerSql('p.player_id') . "
             ORDER BY a.id", [$from, $to])->getResultArray();
        return ['title' => 'Reparto de cada cobro entre cargos',
                'columns' => ['id', 'cobro_id', 'cargo_id', 'importe_eur', 'creado'],
                'rows' => array_map(fn($a) => [$a['id'], $a['payment_id'], $a['charge_id'], self::eur($a['amount_cents']), $a['created_at']], $r)];
    }

    private function expenses(string $from, string $to): array
    {
        $r = $this->db->query(
            "SELECT e.id, e.spent_at, c.name AS category, m.name AS method, e.supplier, e.description, e.amount_cents,
                    e.staff_id, s.name AS staff, l.name AS location, e.attachment_name, e.voided_at, e.void_reason, e.created_at
             FROM fin_expenses e
             LEFT JOIN fin_categories c ON c.id = e.category_id
             LEFT JOIN fin_payment_methods m ON m.id = e.method_id
             LEFT JOIN users s ON s.id = e.staff_id
             LEFT JOIN locations l ON l.id = e.location_id
             WHERE e.spent_at BETWEEN ? AND ?
             ORDER BY e.spent_at, e.id", [$from, $to])->getResultArray();
        return ['title' => 'Gastos',
                'columns' => ['id', 'fecha', 'categoria', 'medio', 'proveedor', 'descripcion', 'importe_eur', 'entrenador_id', 'entrenador', 'sede',
                              'tiene_adjunto', 'anulado', 'motivo_anulacion', 'creado'],
                'rows' => array_map(fn($e) => [
                    $e['id'], $e['spent_at'], $e['category'] ?? 'Sin categoría', $e['method'] ?? '', $e['supplier'], $e['description'], self::eur($e['amount_cents']),
                    $e['staff_id'], $e['staff'], $e['location'], $e['attachment_name'] ? 'sí' : '', $e['voided_at'] ? 'sí' : '', $e['void_reason'], $e['created_at'],
                ], $r)];
    }

    private function bonos(string $start, bool $anon): array
    {
        $r = $this->db->query(
            "SELECT pb.id, pb.player_id, u.name AS player, bt.name AS bono, pb.sessions_total, pb.sessions_remaining, pb.price_list_cents,
                    pb.price_cents, pb.price_estimated, pb.created_at, pb.expires_at, pb.voided_at, pb.void_reason
             FROM player_bonos pb
             LEFT JOIN users u ON u.id = pb.player_id
             LEFT JOIN bono_types bt ON bt.id = pb.bono_type_id
             WHERE pb.created_at >= ?" . FinanceExclusion::playerSql('pb.player_id') . "
             ORDER BY pb.created_at, pb.id", [$start])->getResultArray();
        return ['title' => 'Bonos vendidos',
                'columns' => ['id', 'alumno_id', 'alumno', 'tipo', 'sesiones_total', 'sesiones_restantes', 'precio_tarifa_eur', 'precio_pagado_eur',
                              'precio_por_sesion_eur', 'precio_estimado', 'creado', 'caduca', 'anulado', 'motivo_anulacion'],
                'rows' => array_map(fn($b) => [
                    $b['id'], $b['player_id'], self::name($b['player'], $b['player_id'], $anon), $b['bono'], $b['sessions_total'], $b['sessions_remaining'],
                    self::eur($b['price_list_cents']), self::eur($b['price_cents']),
                    (int) $b['sessions_total'] > 0 && $b['price_cents'] !== null ? round(((int) $b['price_cents'] / (int) $b['sessions_total']) / 100, 2) : null,
                    $b['price_estimated'] ? 'sí' : '', $b['created_at'], $b['expires_at'], $b['voided_at'] ? 'sí' : '', $b['void_reason'],
                ], $r)];
    }

    private function players(bool $anon): array
    {
        $r = (new FinanceService($this->db))->playersOverview();
        return ['title' => 'Saldo de cada alumno (a hoy)',
                'columns' => ['alumno_id', 'alumno', 'estado', 'comprado_eur', 'pagado_eur', 'debe_eur_negativo_es_a_favor', 'sesiones_vivas'],
                'rows' => array_map(fn($p) => [$p['id'], self::name($p['name'], $p['id'], $anon), $p['status'], self::eur($p['charged']),
                                               self::eur($p['paid']), self::eur($p['due']), (int) $p['sessions_left']], $r)];
    }

    private function attendance(string $from, string $to, bool $anon): array
    {
        $r = $this->db->query(
            "SELECT cs.id AS session_id, cs.session_date, cs.start_time, cs.title, cs.status AS session_status, csp.user_id, u.name AS player,
                    csp.attendance, csp.bono_deducted_at, csp.bono_deducted_from_id, csp.bono_resolution,
                    pb.price_cents / NULLIF(pb.sessions_total, 0) AS unit_cents
             FROM class_session_players csp
             JOIN class_sessions cs ON cs.id = csp.session_id
             LEFT JOIN users u ON u.id = csp.user_id
             LEFT JOIN player_bonos pb ON pb.id = csp.bono_deducted_from_id
             WHERE cs.session_date BETWEEN ? AND ?" . FinanceExclusion::sessionSql('cs') . "
             ORDER BY cs.session_date, cs.start_time, cs.id, csp.user_id", [$from, $to])->getResultArray();
        return ['title' => 'Clases por alumno: asistencia y descuento de bono',
                'columns' => ['sesion_id', 'fecha', 'hora', 'clase', 'estado_clase', 'alumno_id', 'alumno', 'asistencia', 'bono_descontado_el',
                              'bono_id', 'valor_sesion_eur', 'resolucion_sin_bono'],
                'rows' => array_map(fn($c) => [
                    $c['session_id'], $c['session_date'], substr((string) $c['start_time'], 0, 5), $c['title'], $c['session_status'], $c['user_id'],
                    self::name($c['player'], $c['user_id'], $anon), $c['attendance'], $c['bono_deducted_at'], $c['bono_deducted_from_id'],
                    $c['unit_cents'] === null ? null : round((float) $c['unit_cents'] / 100, 2), $c['bono_resolution'],
                ], $r)];
    }

    /** Texto explicativo que acompaña al archivo: qué es cada hoja y cómo se calcula cada cifra. */
    public static function readme(string $from, string $to, bool $anon, array $sets): string
    {
        $l   = ['EXPORTACIÓN DE FINANZAS · JP Preparation',
                'Periodo: ' . $from . ' a ' . $to . ' · generado el ' . date('Y-m-d H:i') . ($anon ? ' · nombres anonimizados' : ''),
                '',
                'Importes en euros. Las filas anuladas se incluyen marcadas (anulado = sí) y NO cuentan en las cifras de la plataforma.',
                '',
                'Conceptos:',
                '  vendido  = cargos (bonos entregados y cargos manuales), con su descuento.',
                '  cobrado  = cobros registrados (dinero que ha entrado).',
                '  resultado = cobrado - gastos (caja).  vendido - cobrado = lo pendiente de cobro del periodo.',
                '  servicio prestado = sesiones consumidas x precio por sesión de su bono.',
                '',
                'Ficheros:'];
        foreach ($sets as $k => $s) {
            $l[] = '  ' . $k . ' — ' . $s['title'] . ' (' . count($s['rows']) . ' filas)';
        }
        return implode("\n", $l) . "\n";
    }
}
