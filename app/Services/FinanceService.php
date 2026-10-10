<?php

namespace App\Services;

/**
 * Finanzas 2.0 — cuenta económica del alumno: cargos (lo que debe), cobros
 * (lo que paga) y su reparto.
 *
 * Reglas:
 *  - Importes en céntimos. Nada se borra: cargos y cobros se ANULAN con motivo.
 *  - Un cobro se reparte entre los cargos pendientes del alumno: primero el
 *    cargo que se indique y luego del más antiguo al más reciente (FIFO). Lo
 *    que sobra queda como saldo a favor y se aplica solo al siguiente cargo.
 *  - Las asignaciones a un cargo anulado dejan de contar: ese dinero vuelve a
 *    ser saldo a favor.
 *
 * `allocate()`, `parseDiscount()` y `statement()` son puras (sin BD).
 */
class FinanceService
{
    private \CodeIgniter\Database\BaseConnection $db;

    public function __construct(?\CodeIgniter\Database\BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    // ────────────────────────────────────────────────────────────────
    //  Puras
    // ────────────────────────────────────────────────────────────────

    /**
     * Reparte un importe entre cargos pendientes. El cargo preferido va
     * primero; el resto en el orden dado (se pasan del más antiguo al más reciente).
     *
     * @param array<int,int> $pending chargeId => céntimos pendientes (orden = antigüedad)
     * @return array{allocations:array<int,int>,left:int} chargeId => céntimos asignados
     */
    public static function allocate(int $amount, array $pending, ?int $preferredChargeId = null): array
    {
        $order = array_keys($pending);
        if ($preferredChargeId !== null && isset($pending[$preferredChargeId])) {
            $order = array_merge([$preferredChargeId], array_values(array_diff($order, [$preferredChargeId])));
        }
        $out  = [];
        $left = max(0, $amount);
        foreach ($order as $id) {
            if ($left <= 0) {
                break;
            }
            $take = min($left, max(0, (int) $pending[$id]));
            if ($take > 0) {
                $out[$id] = $take;
                $left    -= $take;
            }
        }
        return ['allocations' => $out, 'left' => $left];
    }

    /**
     * Descuento a partir de lo que escribe el admin: "10" (euros), "10%" o
     * vacío. Nunca más que la tarifa. Pura.
     *
     * @return int céntimos de descuento (0 si no hay o no es válido)
     */
    public static function parseDiscount(?string $raw, int $listCents): int
    {
        $s = trim((string) $raw);
        if ($s === '' || $listCents <= 0) {
            return 0;
        }
        if (str_ends_with($s, '%')) {
            $p = (float) str_replace(',', '.', rtrim($s, '% '));
            return $p > 0 ? min($listCents, (int) round($listCents * min($p, 100) / 100)) : 0;
        }
        $c = RevisionService::parseEuroToCents($s);
        return $c === null ? 0 : min($listCents, $c);
    }

    /**
     * Estado de cuenta: líneas ordenadas por fecha con saldo acumulado
     * («debe» positivo, a favor negativo). Pura.
     *
     * @param array<int,array{date:string,kind:string,amount:int,voided?:bool}> $lines kind charge|payment
     * @return array<int,array> las mismas líneas con `balance`
     */
    public static function statement(array $lines): array
    {
        usort($lines, fn($a, $b) => [$a['date'], $a['kind'] === 'payment' ? 1 : 0, $a['seq'] ?? 0]
                                 <=> [$b['date'], $b['kind'] === 'payment' ? 1 : 0, $b['seq'] ?? 0]);
        $bal = 0;
        foreach ($lines as &$l) {
            if (empty($l['voided'])) {
                $bal += $l['kind'] === 'charge' ? (int) $l['amount'] : -(int) $l['amount'];
            }
            $l['balance'] = $bal;
        }
        return $lines;
    }

    // ────────────────────────────────────────────────────────────────
    //  Catálogos
    // ────────────────────────────────────────────────────────────────

    public function methods(bool $onlyActive = true): array
    {
        $q = $this->db->table('fin_payment_methods')->where('archived_at IS NULL');
        if ($onlyActive) {
            $q->where('active', 1);
        }
        return $q->orderBy('sort')->orderBy('name')->get()->getResultArray();
    }

    public function categories(?string $kind = null, bool $onlyActive = true): array
    {
        $q = $this->db->table('fin_categories')->where('archived_at IS NULL');
        if ($kind) {
            $q->where('kind', $kind);
        }
        if ($onlyActive) {
            $q->where('active', 1);
        }
        return $q->orderBy('sort')->orderBy('name')->get()->getResultArray();
    }

    private function categoryId(string $kind, string $name): ?int
    {
        $r = $this->db->table('fin_categories')->select('id')->where('kind', $kind)->where('name', $name)->get()->getRow();
        return $r ? (int) $r->id : null;
    }

    // ────────────────────────────────────────────────────────────────
    //  Cargos
    // ────────────────────────────────────────────────────────────────

    /**
     * Cargo por un bono vendido o asignado (precio congelado del bono). Aplica
     * solo el saldo a favor que tuviera el alumno.
     */
    public function chargeForBono(int $bonoId, ?int $actorId = null, ?string $discountReason = null): ?int
    {
        $b = $this->db->table('player_bonos pb')
            ->select('pb.id, pb.player_id, pb.price_list_cents, pb.price_cents, pb.start_date, bt.name')
            ->join('bono_types bt', 'bt.id = pb.bono_type_id', 'left')
            ->where('pb.id', $bonoId)->get()->getRowArray();
        if (!$b || empty($b['player_id'])) {
            return null;
        }
        $exists = $this->db->table('fin_charges')->where('bono_id', $bonoId)->where('voided_at IS NULL')->countAllResults();
        if ($exists) {
            return null;
        }
        $list   = (int) ($b['price_list_cents'] ?? $b['price_cents'] ?? 0);
        $amount = (int) ($b['price_cents'] ?? $list);
        return $this->createCharge((int) $b['player_id'], [
            'bono_id'         => $bonoId,
            'category_id'     => $this->categoryId('income', 'Bonos'),
            'concept'         => (string) ($b['name'] ?? 'Bono'),
            'list_cents'      => $list,
            'discount_cents'  => max(0, $list - $amount),
            'discount_reason' => $list > $amount ? ($discountReason ?: 'Descuento') : null,
            'amount_cents'    => $amount,
            'charged_at'      => date('Y-m-d'),
        ], $actorId);
    }

    /** Cargo manual (sesión suelta, material, otro concepto). */
    public function createCharge(int $playerId, array $data, ?int $actorId = null): int
    {
        $row = [
            'player_id'       => $playerId,
            'bono_id'         => $data['bono_id'] ?? null,
            'category_id'     => $data['category_id'] ?? null,
            'concept'         => mb_substr((string) ($data['concept'] ?? 'Cargo'), 0, 160),
            'list_cents'      => (int) ($data['list_cents'] ?? $data['amount_cents']),
            'discount_cents'  => (int) ($data['discount_cents'] ?? 0),
            'discount_reason' => $data['discount_reason'] ?? null,
            'amount_cents'    => (int) $data['amount_cents'],
            'charged_at'      => $data['charged_at'] ?? date('Y-m-d'),
            'note'            => $data['note'] ?? null,
            'created_by'      => $actorId,
            'created_at'      => date('Y-m-d H:i:s'),
        ];
        $this->db->table('fin_charges')->insert($row);
        $id = (int) $this->db->insertID();
        AuditService::record('fin_charge', $id, AuditService::CREATE, null, $row, null, $actorId);
        $this->applyCredit($playerId);
        return $id;
    }

    /** Anula un cargo (lo pagado vuelve a ser saldo a favor del alumno). */
    public function voidCharge(int $chargeId, ?string $reason, int $actorId): array
    {
        if (!BonoControlService::validReason($reason)) {
            return ['success' => false, 'error' => 'Indica el motivo de la anulación.'];
        }
        $c = $this->db->table('fin_charges')->where('id', $chargeId)->get()->getRowArray();
        if (!$c || $c['voided_at']) {
            return ['success' => false, 'error' => 'Cargo no encontrado o ya anulado.'];
        }
        $data = ['voided_at' => date('Y-m-d H:i:s'), 'voided_by' => $actorId, 'void_reason' => mb_substr(trim($reason), 0, 255)];
        $this->db->table('fin_charges')->where('id', $chargeId)->update($data);
        AuditService::record('fin_charge', $chargeId, AuditService::VOID, ['voided_at' => null], $data, $reason, $actorId);
        $this->applyCredit((int) $c['player_id']);
        return ['success' => true];
    }

    /** Al anular un bono, se anula su cargo. */
    public function onBonoVoided(int $bonoId, ?string $reason, int $actorId): void
    {
        foreach ($this->db->table('fin_charges')->select('id')->where('bono_id', $bonoId)->where('voided_at IS NULL')->get()->getResultArray() as $c) {
            $this->voidCharge((int) $c['id'], 'Bono anulado: ' . trim((string) $reason), $actorId);
        }
    }

    /** Al corregir el precio de un bono, se rehace su cargo (anular + nuevo). */
    public function onBonoPriceChanged(int $bonoId, int $actorId): void
    {
        foreach ($this->db->table('fin_charges')->select('id, amount_cents')->where('bono_id', $bonoId)->where('voided_at IS NULL')->get()->getResultArray() as $c) {
            $this->voidCharge((int) $c['id'], 'Precio del bono corregido', $actorId);
        }
        $this->chargeForBono($bonoId, $actorId);
    }

    // ────────────────────────────────────────────────────────────────
    //  Cobros
    // ────────────────────────────────────────────────────────────────

    /**
     * Registra un cobro y lo reparte entre los cargos pendientes.
     *
     * @return array{success:bool,id?:int,credit?:int,error?:string}
     */
    public function registerPayment(int $playerId, int $amountCents, ?int $methodId, ?string $paidAt, ?int $chargeId, ?string $reference, ?string $note, ?int $actorId): array
    {
        if ($amountCents <= 0) {
            return ['success' => false, 'error' => 'El importe debe ser mayor que 0.'];
        }
        $player = $this->db->table('users')->select('id')->where('id', $playerId)->where('role', 'player')->get()->getRow();
        if (!$player) {
            return ['success' => false, 'error' => 'Alumno no encontrado.'];
        }
        $date = $paidAt && strtotime($paidAt) ? date('Y-m-d', strtotime($paidAt)) : date('Y-m-d');
        if ($date > date('Y-m-d')) {
            return ['success' => false, 'error' => 'La fecha del cobro no puede ser futura.'];
        }
        $row = [
            'player_id'    => $playerId,
            'method_id'    => $methodId ?: null,
            'amount_cents' => $amountCents,
            'paid_at'      => $date,
            'reference'    => $reference !== null && trim($reference) !== '' ? mb_substr(trim($reference), 0, 120) : null,
            'note'         => $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 255) : null,
            'created_by'   => $actorId,
            'created_at'   => date('Y-m-d H:i:s'),
        ];
        $this->db->transStart();
        $this->db->table('fin_payments')->insert($row);
        $id = (int) $this->db->insertID();
        $res = self::allocate($amountCents, $this->pendingCharges($playerId), $chargeId);
        foreach ($res['allocations'] as $cid => $cents) {
            $this->db->table('fin_payment_allocations')->insert([
                'payment_id' => $id, 'charge_id' => $cid, 'amount_cents' => $cents, 'created_at' => date('Y-m-d H:i:s'),
            ]);
        }
        $this->db->transComplete();
        if (!$this->db->transStatus()) {
            return ['success' => false, 'error' => 'No se pudo guardar el cobro.'];
        }
        AuditService::record('fin_payment', $id, AuditService::CREATE, null, $row + ['allocations' => $res['allocations']], null, $actorId);
        return ['success' => true, 'id' => $id, 'credit' => $res['left']];
    }

    public function voidPayment(int $paymentId, ?string $reason, int $actorId): array
    {
        if (!BonoControlService::validReason($reason)) {
            return ['success' => false, 'error' => 'Indica el motivo de la anulación.'];
        }
        $p = $this->db->table('fin_payments')->where('id', $paymentId)->get()->getRowArray();
        if (!$p || $p['voided_at']) {
            return ['success' => false, 'error' => 'Cobro no encontrado o ya anulado.'];
        }
        $data = ['voided_at' => date('Y-m-d H:i:s'), 'voided_by' => $actorId, 'void_reason' => mb_substr(trim($reason), 0, 255)];
        $this->db->table('fin_payments')->where('id', $paymentId)->update($data);
        AuditService::record('fin_payment', $paymentId, AuditService::VOID, ['voided_at' => null], $data, $reason, $actorId);
        return ['success' => true];
    }

    /** Aplica el saldo a favor del alumno (cobros no asignados) a sus cargos pendientes. */
    public function applyCredit(int $playerId): void
    {
        $pending = $this->pendingCharges($playerId);
        if (!$pending) {
            return;
        }
        $credits = $this->db->query(
            "SELECT p.id, p.amount_cents - COALESCE(u.used, 0) AS free
             FROM fin_payments p
             LEFT JOIN (SELECT a.payment_id, SUM(a.amount_cents) AS used FROM fin_payment_allocations a
                        JOIN fin_charges c ON c.id = a.charge_id AND c.voided_at IS NULL GROUP BY a.payment_id) u
                    ON u.payment_id = p.id
             WHERE p.player_id = ? AND p.voided_at IS NULL AND p.amount_cents - COALESCE(u.used, 0) > 0
             ORDER BY p.paid_at, p.id",
            [$playerId]
        )->getResultArray();
        foreach ($credits as $cr) {
            $res = self::allocate((int) $cr['free'], $pending);
            foreach ($res['allocations'] as $cid => $cents) {
                $this->db->table('fin_payment_allocations')->insert([
                    'payment_id' => (int) $cr['id'], 'charge_id' => $cid, 'amount_cents' => $cents, 'created_at' => date('Y-m-d H:i:s'),
                ]);
                $pending[$cid] -= $cents;
                if ($pending[$cid] <= 0) {
                    unset($pending[$cid]);
                }
            }
            if (!$pending) {
                break;
            }
        }
    }

    /** @return array<int,int> chargeId => céntimos pendientes, del más antiguo al más reciente */
    public function pendingCharges(int $playerId): array
    {
        $rows = $this->db->query(
            "SELECT c.id, c.amount_cents - COALESCE(x.paid, 0) AS pending
             FROM fin_charges c
             LEFT JOIN (SELECT a.charge_id, SUM(a.amount_cents) AS paid FROM fin_payment_allocations a
                        JOIN fin_payments p ON p.id = a.payment_id AND p.voided_at IS NULL GROUP BY a.charge_id) x ON x.charge_id = c.id
             WHERE c.player_id = ? AND c.voided_at IS NULL AND c.amount_cents - COALESCE(x.paid, 0) > 0
             ORDER BY c.charged_at, c.id",
            [$playerId]
        )->getResultArray();
        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['id']] = (int) $r['pending'];
        }
        return $out;
    }

    // ────────────────────────────────────────────────────────────────
    //  Cuenta del alumno
    // ────────────────────────────────────────────────────────────────

    /**
     * Todo lo económico de un alumno para su cuenta (admin) o «Mis pagos».
     *
     * @return array{charges:array,payments:array,lines:array,totals:array}
     */
    public function account(int $playerId): array
    {
        $charges = $this->db->query(
            "SELECT c.*, COALESCE(x.paid, 0) AS paid_cents
             FROM fin_charges c
             LEFT JOIN (SELECT a.charge_id, SUM(a.amount_cents) AS paid FROM fin_payment_allocations a
                        JOIN fin_payments p ON p.id = a.payment_id AND p.voided_at IS NULL GROUP BY a.charge_id) x ON x.charge_id = c.id
             WHERE c.player_id = ?
             ORDER BY c.charged_at, c.id",
            [$playerId]
        )->getResultArray();
        $payments = $this->db->table('fin_payments p')
            ->select('p.*, m.name AS method_name')
            ->join('fin_payment_methods m', 'm.id = p.method_id', 'left')
            ->where('p.player_id', $playerId)
            ->orderBy('p.paid_at')->orderBy('p.id')
            ->get()->getResultArray();

        $lines = [];
        foreach ($charges as $c) {
            $lines[] = ['date' => $c['charged_at'], 'kind' => 'charge', 'amount' => (int) $c['amount_cents'], 'seq' => (int) $c['id'],
                        'concept' => $c['concept'], 'id' => (int) $c['id'], 'voided' => (bool) $c['voided_at'],
                        'void_reason' => $c['void_reason'], 'discount' => (int) $c['discount_cents'], 'discount_reason' => $c['discount_reason']];
        }
        foreach ($payments as $p) {
            $lines[] = ['date' => $p['paid_at'], 'kind' => 'payment', 'amount' => (int) $p['amount_cents'], 'seq' => (int) $p['id'],
                        'concept' => 'Cobro · ' . ($p['method_name'] ?? 'sin medio'), 'id' => (int) $p['id'],
                        'voided' => (bool) $p['voided_at'], 'void_reason' => $p['void_reason'], 'note' => $p['note']];
        }
        $lines = self::statement($lines);

        $charged = array_sum(array_map(fn($c) => $c['voided_at'] ? 0 : (int) $c['amount_cents'], $charges));
        $paid    = array_sum(array_map(fn($p) => $p['voided_at'] ? 0 : (int) $p['amount_cents'], $payments));

        return [
            'charges'  => $charges,
            'payments' => $payments,
            'lines'    => array_reverse($lines),
            'totals'   => ['charged' => $charged, 'paid' => $paid, 'due' => $charged - $paid],
        ];
    }

    /**
     * Resumen económico de todos los alumnos (pestaña Alumnos).
     *
     * @return array<int,array> id, name, status, charged, paid, due, sessions_left
     */
    public function playersOverview(?string $today = null): array
    {
        $today = $today ?: date('Y-m-d');
        return $this->db->query(
            "SELECT u.id, u.name, u.status,
                    COALESCE(ch.charged, 0) AS charged, COALESCE(pa.paid, 0) AS paid,
                    COALESCE(ch.charged, 0) - COALESCE(pa.paid, 0) AS due,
                    COALESCE(bo.left_s, 0) AS sessions_left
             FROM users u
             LEFT JOIN (SELECT player_id, SUM(amount_cents) charged FROM fin_charges WHERE voided_at IS NULL GROUP BY player_id) ch ON ch.player_id = u.id
             LEFT JOIN (SELECT player_id, SUM(amount_cents) paid FROM fin_payments WHERE voided_at IS NULL GROUP BY player_id) pa ON pa.player_id = u.id
             LEFT JOIN (SELECT player_id, SUM(sessions_remaining) left_s FROM player_bonos
                        WHERE voided_at IS NULL AND sessions_remaining > 0 AND (expires_at IS NULL OR expires_at >= ?) GROUP BY player_id) bo ON bo.player_id = u.id
             WHERE u.role = 'player'
             ORDER BY due DESC, u.name ASC",
            [$today]
        )->getResultArray();
    }
}
