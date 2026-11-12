<?php

namespace App\Services;

use App\Models\ClassGuestModel;

/**
 * Personas externas como entidad propia (fuera de una clase concreta):
 *  - Ficha e historial: listado, búsqueda, todas sus clases, asistencia y cobros, edición.
 *  - Conversión a alumno: crea la cuenta (PlayerService::createAlumno) y enlaza la ficha.
 *    Sus clases anteriores NO se copian a class_session_players (eso dispararía bonos y descuentos):
 *    se conservan como histórico y salen en el historial del alumno (StudentHistoryService).
 *
 * NADA SE BORRA NI SE ANONIMIZA: las fichas, sus datos y su historial se conservan siempre.
 *
 * Errores de negocio → \DomainException. Si las tablas no existen, todo degrada a «sin datos».
 */
class ExternosPersonasService
{
    public const STATUSES = ['activos' => 'Activos', 'convertidos' => 'Ya son alumnos', 'todos' => 'Todos'];

    private \CodeIgniter\Database\BaseConnection $db;
    private ClassGuestModel $guests;

    public function __construct()
    {
        helper('money');
        $this->db     = \Config\Database::connect();
        $this->guests = new ClassGuestModel();
    }

    // ───────────────────────── Puras (testeables sin BD) ─────────────────────────

    public static function displayName(array $guest): string
    {
        return trim(($guest['first_name'] ?? '') . ' ' . ($guest['last_name'] ?? ''));
    }

    /**
     * Valida los datos para crear la cuenta de alumno. Devuelve [datos limpios, errores].
     *
     * @return array{0: array<string,mixed>, 1: string[]}
     */
    public static function validateConversion(array $input, array $guest): array
    {
        $errors = [];
        if (!empty($guest['converted_user_id'])) {
            $errors[] = 'Esta persona ya es alumno.';
        }
        $email = mb_strtolower(trim((string) ($input['email'] ?? '')));
        if ($email === '') {
            $errors[] = 'Indica el email con el que entrará el alumno.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'El email no es válido.';
        }
        $name = trim((string) ($input['name'] ?? ''));
        if ($name === '') {
            $name = self::displayName($guest);
        }
        $birth = trim((string) ($input['birth_date'] ?? '')) ?: ($guest['birth_date'] ?? null);
        if ($birth && !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $birth)) {
            $errors[] = 'La fecha de nacimiento no es válida.';
        }
        return [['name' => mb_substr($name, 0, 150), 'email' => $email, 'birth_date' => $birth ?: null], $errors];
    }

    /** Totales de una historia (clases no canceladas). Pura sobre las filas de history(). */
    public static function totals(array $history): array
    {
        $t = ['classes' => 0, 'present' => 0, 'absent' => 0, 'pending' => 0, 'billed' => 0, 'paid' => 0];
        foreach ($history as $h) {
            if (($h['status'] ?? '') === 'cancelled') {
                continue;
            }
            $t['classes']++;
            $a = $h['attendance'] ?? 'pending';
            $t[isset($t[$a]) ? $a : 'pending']++;
            $t['billed'] += (int) ($h['price_cents'] ?? 0);
            $t['paid']   += (int) ($h['paid_cents'] ?? 0);
        }
        return $t;
    }

    // ───────────────────────── Con BD ─────────────────────────

    public function available(): bool
    {
        try {
            return $this->db->tableExists('class_guests') && $this->db->tableExists('class_session_guests');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /**
     * Listado de personas con sus totales. $status: activos|convertidos|todos.
     *
     * @return array<int,array<string,mixed>>
     */
    public function listGuests(string $search = '', string $status = 'activos', bool $withMoney = false): array
    {
        if (!$this->available()) {
            return [];
        }
        $b = $this->db->table('class_guests g')
            ->select('g.*, COUNT(CASE WHEN cs.status <> \'cancelled\' THEN csg.id END) AS n_classes,'
                   . ' SUM(CASE WHEN csg.attendance = \'present\' AND cs.status <> \'cancelled\' THEN 1 ELSE 0 END) AS n_present,'
                   . ' MAX(CASE WHEN cs.status <> \'cancelled\' THEN cs.session_date END) AS last_date', false)
            ->join('class_session_guests csg', 'csg.guest_id = g.id', 'left')
            ->join('class_sessions cs', 'cs.id = csg.session_id', 'left')
            ->groupBy('g.id');

        if ($search !== '') {
            $b->groupStart()->like('g.first_name', $search)->orLike('g.last_name', $search)
              ->orLike('g.phone', $search)->orLike('g.email', $search)->groupEnd();
        }
        if ($status === 'activos') {
            $b->where('g.converted_user_id IS NULL');
        } elseif ($status === 'convertidos') {
            $b->where('g.converted_user_id IS NOT NULL');
        }
        $rows = $b->orderBy('last_date', 'DESC')->orderBy('g.id', 'DESC')->get()->getResultArray();

        $paid = [];
        if ($withMoney && $this->db->tableExists('class_guest_payments')) {
            foreach ($this->db->query(
                "SELECT x.guest_id, SUM(p.amount_cents) s FROM class_guest_payments p
                 JOIN class_session_guests x ON x.id = p.session_guest_id WHERE p.voided_at IS NULL GROUP BY x.guest_id"
            )->getResultArray() as $r) {
                $paid[(int) $r['guest_id']] = (int) $r['s'];
            }
        }
        foreach ($rows as &$r) {
            $r['name']      = self::displayName($r);
            $r['n_classes'] = (int) $r['n_classes'];
            $r['n_present'] = (int) $r['n_present'];
            if ($withMoney) {
                $r['paid_cents'] = $paid[(int) $r['id']] ?? 0;
            }
        }
        unset($r);
        return $rows;
    }

    public function getGuest(int $id): ?array
    {
        if (!$this->available()) {
            return null;
        }
        $g = $this->guests->find($id);
        if (!$g) {
            return null;
        }
        $g['name'] = self::displayName($g);
        $g['age']  = ExternosService::ageOn($g['birth_date'] ?? null);
        return $g;
    }

    /**
     * Todas las clases de una persona (la más reciente primero) con asistencia y, si $withMoney, precio y cobros.
     *
     * @return array<int,array<string,mixed>>
     */
    public function history(int $guestId, bool $withMoney = false): array
    {
        if (!$this->available()) {
            return [];
        }
        $moneyOk = $withMoney && $this->db->tableExists('class_guest_payments') && $this->db->fieldExists('price_cents', 'class_session_guests');
        $cols = 'csg.id AS link_id, csg.session_id, csg.attendance, cs.title, cs.session_date, cs.start_time, cs.end_time, cs.status'
              . ($moneyOk ? ', csg.price_cents' : '');
        $rows = $this->db->table('class_session_guests csg')->select($cols)
            ->join('class_sessions cs', 'cs.id = csg.session_id')
            ->where('csg.guest_id', $guestId)
            ->orderBy('cs.session_date', 'DESC')->orderBy('cs.start_time', 'DESC')
            ->get()->getResultArray();

        foreach ($rows as &$r) {
            if ($moneyOk) {
                $pays = $this->db->table('class_guest_payments p')
                    ->select('p.id, p.amount_cents, p.paid_at, p.voided_at, p.void_reason, m.name AS method')
                    ->join('fin_payment_methods m', 'm.id = p.method_id', 'left')
                    ->where('p.session_guest_id', (int) $r['link_id'])->orderBy('p.paid_at')->orderBy('p.id')
                    ->get()->getResultArray();
                $paid = 0;
                foreach ($pays as $p) {
                    if (empty($p['voided_at'])) {
                        $paid += (int) $p['amount_cents'];
                    }
                }
                $price = $r['price_cents'] === null ? null : (int) $r['price_cents'];
                $r['price_cents'] = $price;
                $r['paid_cents']  = $paid;
                $r['money_state'] = ExternosService::moneyState($price, $paid);
                $r['payments']    = $pays;
            }
        }
        unset($r);
        return $rows;
    }

    /**
     * Corrige los datos de la persona desde su ficha. Se audita con antes y después.
     *
     * @throws \DomainException
     */
    public function updateGuest(int $id, array $input, ?int $actorId): void
    {
        $g = $this->guests->find($id);
        if (!$g) {
            throw new \DomainException('No se encuentra a esa persona.');
        }
        [$clean, $errors] = ExternosService::validate($input);
        if ($errors) {
            throw new \DomainException(implode(' ', $errors));
        }
        $fields = ['first_name', 'last_name', 'phone', 'phone_key', 'email', 'birth_date', 'guardian_name', 'guardian_phone', 'image_rights', 'notes'];
        $before = $after = $upd = [];
        foreach ($fields as $f) {
            if ((string) ($g[$f] ?? '') !== (string) ($clean[$f] ?? '')) {
                $before[$f] = $g[$f] ?? null;
                $after[$f]  = $clean[$f] ?? null;
                $upd[$f]    = $clean[$f] ?? null;
            }
        }
        if (!$upd) {
            return;
        }
        $this->guests->update($id, $upd);
        AuditService::record('class_guest', $id, AuditService::UPDATE, $before, $after, 'Datos corregidos desde su ficha', $actorId);
    }

    /**
     * Convierte a la persona en alumno: crea la cuenta (contraseña temporal + email de bienvenida) y enlaza la ficha.
     * La ficha y todo su historial se conservan tal cual.
     *
     * @return array{user_id:int, password:string}
     * @throws \DomainException
     */
    public function convertToPlayer(int $id, array $input, ?int $actorId): array
    {
        $g = $this->guests->find($id);
        if (!$g) {
            throw new \DomainException('No se encuentra a esa persona.');
        }
        [$clean, $errors] = self::validateConversion($input, $g);
        if ($errors) {
            throw new \DomainException(implode(' ', $errors));
        }
        if ($this->db->table('users')->where('email', $clean['email'])->countAllResults() > 0) {
            throw new \DomainException('Ya existe un usuario con ese email. Usa otro, o si es la misma persona, añádela a sus clases como alumno normal.');
        }

        $password = (new AuthGuardService())->generateTempPassword();
        $profile  = ['image_rights_signed' => !empty($g['image_rights']) ? 1 : 0];
        if ($clean['birth_date']) {
            $profile['birth_date'] = $clean['birth_date'];
        }

        $this->db->transStart();
        $res = (new PlayerService())->createAlumno(
            ['name' => $clean['name'], 'email' => $clean['email'], 'password' => $password, 'role' => 'player', 'status' => 'active'],
            $profile
        );
        if (!$res['success']) {
            $this->db->transComplete();
            $msg = implode(' ', array_map('strval', (array) $res['errors']));
            throw new \DomainException($msg !== '' ? $msg : 'No se pudo crear la cuenta del alumno.');
        }
        $userId = (int) $res['userId'];
        $this->guests->update($id, ['converted_user_id' => $userId]);
        $this->db->transComplete();
        if (!$this->db->transStatus()) {
            throw new \RuntimeException('No se pudo completar la conversión.');
        }

        AuditService::record('class_guest', $id, AuditService::UPDATE, ['converted_user_id' => null], ['converted_user_id' => $userId],
            'Convertido en alumno (cuenta #' . $userId . ')', $actorId);
        return ['user_id' => $userId, 'password' => $password];
    }
}
