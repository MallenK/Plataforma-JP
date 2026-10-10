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

    /**
     * Cierre de revisión inicial (v1.33.0). Por decisión del responsable, lo
     * anterior se da por bueno en bloque y solo queda por revisar lo que de
     * verdad nadie cerró:
     *
     *  1. Sesiones pasadas «programadas» con ALGUNA asistencia marcada
     *     (presente, ausencia, aviso…) → se cierran (igual que «Cerrar sesión»:
     *     solo cambia el estado; no descuenta bonos ni avisa a nadie).
     *  2. Clases dadas sin descontar (antes y después del control) → se dan por
     *     buenas (`bono_resolution = accepted`). El saldo de los bonos no cambia.
     *  3. Precios estimados → confirmados al precio de tarifa con el que se
     *     rellenaron.
     *
     * Los bonos caducados con sesiones sin usar no se tocan: se señalan (no
     * son una tarea). Idempotente: una segunda ejecución no encuentra nada.
     * Todo queda en `audit_log` y en el libro del bono.
     *
     * @param bool $apply false = solo cuenta lo que haría (prueba en seco)
     * @return array{sessions:int,debts:int,prices:int,applied:bool}
     */
    public function initialClose(bool $apply, ?int $actorId = null, ?string $today = null): array
    {
        $today = $today ?: date('Y-m-d');
        $now   = date('Y-m-d H:i:s');

        $sessionIds = array_map('intval', array_column($this->db->query(
            "SELECT DISTINCT cs.id FROM class_sessions cs
             JOIN class_session_players csp ON csp.session_id = cs.id AND csp.attendance <> 'pending'
             WHERE cs.status = 'scheduled' AND cs.session_date < ?",
            [$today]
        )->getResultArray(), 'id'));

        $priceIds = array_map('intval', array_column($this->db->table('player_bonos')
            ->select('id')->where('voided_at IS NULL')->where('price_estimated', 1)
            ->get()->getResultArray(), 'id'));

        if (!$apply) {
            // Las clases sin descontar se cuentan como quedarían DESPUÉS de cerrar las sesiones.
            $debts = (int) $this->db->query(
                "SELECT COUNT(*) AS n FROM class_session_players csp
                 JOIN class_sessions cs ON cs.id = csp.session_id
                 WHERE (cs.status = 'completed' OR cs.id IN (" . ($sessionIds ? implode(',', $sessionIds) : '0') . "))
                   AND csp.attendance IN ('" . implode("','", ClasesService::BONO_CONSUMING_ATTENDANCE) . "')
                   AND csp.bono_deducted_at IS NULL AND csp.bono_resolution IS NULL"
            )->getRow()->n;
            return ['sessions' => count($sessionIds), 'debts' => $debts, 'prices' => count($priceIds), 'applied' => false];
        }

        $reason = 'Cierre de revisión inicial';
        $hasBy  = $this->db->fieldExists('lista_pasada_by', 'class_sessions');

        // 1. Sesiones con asistencia marcada → cerradas
        foreach ($sessionIds as $sid) {
            $s = $this->db->table('class_sessions')->select('status, lista_pasada_at')->where('id', $sid)->get()->getRowArray();
            $upd = ['status' => 'completed', 'updated_at' => $now];
            if (empty($s['lista_pasada_at'])) {
                $upd['lista_pasada_at'] = $now;
                if ($hasBy && $actorId) {
                    $upd['lista_pasada_by'] = $actorId;
                }
            }
            $this->db->table('class_sessions')->where('id', $sid)->where('status', 'scheduled')->update($upd);
            AuditService::record('class_session', $sid, AuditService::UPDATE,
                ['status' => $s['status'] ?? 'scheduled'], ['status' => 'completed'],
                $reason . ': tenía asistencia marcada', $actorId);
        }

        // 2. Clases dadas sin descontar → dadas por buenas
        $debtRows = $this->db->query(
            "SELECT csp.id, csp.user_id, csp.session_id FROM class_session_players csp
             JOIN class_sessions cs ON cs.id = csp.session_id
             WHERE cs.status = 'completed'
               AND csp.attendance IN ('" . implode("','", ClasesService::BONO_CONSUMING_ATTENDANCE) . "')
               AND csp.bono_deducted_at IS NULL AND csp.bono_resolution IS NULL"
        )->getResultArray();
        foreach ($debtRows as $d) {
            $this->db->table('class_session_players')->where('id', (int) $d['id'])->where('bono_resolution IS NULL', null, false)->update([
                'bono_resolution'  => BonoControlService::RESOLUTION_ACCEPTED,
                'bono_resolved_at' => $now,
                'bono_resolved_by' => $actorId,
            ]);
            BonoLedgerService::log((int) $d['user_id'], BonoLedgerService::DEBT_RESOLVED, 0, null, (int) $d['session_id'],
                $reason . ': clase dada por buena sin descontar bono', $actorId);
            AuditService::record('class_session_player', (int) $d['id'], AuditService::UPDATE,
                ['bono_resolution' => null], ['bono_resolution' => BonoControlService::RESOLUTION_ACCEPTED], $reason, $actorId);
        }

        // 3. Precios estimados → confirmados (precio de tarifa)
        foreach ($priceIds as $bid) {
            $this->db->table('player_bonos')->where('id', $bid)->where('price_estimated', 1)->update(['price_estimated' => 0]);
            AuditService::record('player_bono', $bid, AuditService::UPDATE,
                ['price_estimated' => 1], ['price_estimated' => 0], $reason . ': precio de tarifa confirmado', $actorId);
        }

        // Marca de cuándo se hizo (informativo)
        try {
            $exists = $this->db->table('academy_settings')->where('setting_key', 'finanzas_cierre_inicial_at')->countAllResults();
            $row = ['setting_value' => $now, 'updated_at' => $now];
            $exists
                ? $this->db->table('academy_settings')->where('setting_key', 'finanzas_cierre_inicial_at')->update($row)
                : $this->db->table('academy_settings')->insert($row + ['setting_key' => 'finanzas_cierre_inicial_at', 'setting_type' => 'string']);
        } catch (\Throwable $e) {
            log_message('error', 'initialClose: no se pudo guardar la marca: ' . $e->getMessage());
        }

        return ['sessions' => count($sessionIds), 'debts' => count($debtRows), 'prices' => count($priceIds), 'applied' => true];
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
