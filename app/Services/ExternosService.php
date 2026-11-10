<?php

namespace App\Services;

use App\Models\ClassGuestModel;
use App\Models\ClassSessionGuestModel;

/**
 * Externos: personas que reciben una clase sin estar registradas como alumnos
 * (clase de prueba / primera vez, clase suelta pagada). Fase 1: ficha ligera + asistencia.
 * El dinero (cargo y cobro) llega en la Fase 2.
 *
 * Los errores de negocio se lanzan como \DomainException (el controller los convierte en aviso
 * sin «ref» de ticket). Si las tablas aún no existen (BD sin migrar) todo se comporta como
 * «no hay externos» en lugar de romper la ficha de clase.
 */
class ExternosService
{
    public const KINDS = [
        'trial'  => 'Clase de prueba',
        'single' => 'Clase suelta',
    ];

    public const ATTENDANCE = ['pending', 'present', 'absent'];

    private ClassGuestModel $guests;
    private ClassSessionGuestModel $links;

    public function __construct()
    {
        helper('money');
        $this->guests = new ClassGuestModel();
        $this->links  = new ClassSessionGuestModel();
    }

    // ───────────────────────── Puras (testeables sin BD) ─────────────────────────

    /** Solo dígitos, sin prefijo de España, para reconocer a la misma persona aunque escriban el teléfono distinto. */
    public static function phoneKey(?string $phone): ?string
    {
        $d = preg_replace('/\D+/', '', (string) $phone);
        if ($d === '') {
            return null;
        }
        if (str_starts_with($d, '0034')) {
            $d = substr($d, 4);
        } elseif (strlen($d) === 11 && str_starts_with($d, '34')) {
            $d = substr($d, 2);
        }
        return $d !== '' ? $d : null;
    }

    /** Edad en años cumplidos a una fecha (null si la fecha de nacimiento no es válida). */
    public static function ageOn(?string $birthDate, ?string $today = null): ?int
    {
        if (!$birthDate || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $birthDate)) {
            return null;
        }
        try {
            $b = new \DateTimeImmutable($birthDate);
            $t = new \DateTimeImmutable($today ?: 'today');
        } catch (\Throwable $e) {
            return null;
        }
        if ($b > $t) {
            return null;
        }
        return (int) $b->diff($t)->y;
    }

    /**
     * Limpia y valida el formulario de alta. Devuelve [datos limpios, errores].
     * Reglas: solo el nombre es obligatorio. Fecha de nacimiento y contacto son opcionales;
     * si se indica la fecha y resulta ser menor, hacen falta nombre y teléfono del tutor.
     *
     * @return array{0: array<string,mixed>, 1: string[]}
     */
    public static function validate(array $in, ?string $today = null): array
    {
        $t = static fn($k) => trim((string) ($in[$k] ?? ''));

        $clean = [
            'first_name'     => mb_substr($t('first_name'), 0, 100),
            'last_name'      => mb_substr($t('last_name'), 0, 100) ?: null,
            'phone'          => mb_substr($t('phone'), 0, 30) ?: null,
            'email'          => mb_strtolower(mb_substr($t('email'), 0, 150)) ?: null,
            'birth_date'     => $t('birth_date') ?: null,
            'guardian_name'  => mb_substr($t('guardian_name'), 0, 150) ?: null,
            'guardian_phone' => mb_substr($t('guardian_phone'), 0, 30) ?: null,
            'image_rights'   => !empty($in['image_rights']) ? 1 : 0,
            'notes'          => $t('notes') ?: null,
            // El «tipo» (prueba / suelta) ya no se pregunta: se guarda siempre «single».
            'kind'           => 'single',
        ];

        $errors = [];
        if ($clean['first_name'] === '') {
            $errors[] = 'Indica el nombre.';
        }

        $age = self::ageOn($clean['birth_date'], $today);
        if ($clean['birth_date'] !== null && $age === null) {
            $errors[] = 'La fecha de nacimiento no es válida.';
        }

        if ($clean['email'] !== null && !filter_var($clean['email'], FILTER_VALIDATE_EMAIL)) {
            $errors[] = 'El email no es válido.';
        }

        if ($age !== null && $age < 18 && ($clean['guardian_name'] === null || $clean['guardian_phone'] === null)) {
            $errors[] = 'Es menor de edad: indica el nombre y el teléfono de su madre, padre o tutor.';
        }

        $clean['phone_key'] = self::phoneKey($clean['phone'] ?? $clean['guardian_phone']);

        return [$clean, $errors];
    }

    /**
     * Personas externas que vienen en un formulario: `externos[i][...]` (varias) o, por compatibilidad,
     * `externo[...]` (una). Las filas totalmente vacías se descartan. Pura.
     *
     * @return array<int,array<string,mixed>>
     */
    public static function peopleFromPost(array $post): array
    {
        $raw = [];
        if (isset($post['externos']) && is_array($post['externos'])) {
            $raw = array_values($post['externos']);
        } elseif (isset($post['externo']) && is_array($post['externo'])) {
            $raw = [$post['externo']];
        }
        $out = [];
        foreach ($raw as $row) {
            if (!is_array($row)) {
                continue;
            }
            $filled = false;
            foreach ($row as $k => $v) {
                if (in_array($k, ['method_id', 'kind'], true)) {
                    continue;   // un desplegable siempre trae valor: no cuenta como «fila rellena»
                }
                if (is_scalar($v) && trim((string) $v) !== '') {
                    $filled = true;
                    break;
                }
            }
            if ($filled) {
                $out[] = $row;
            }
        }
        return $out;
    }

    /**
     * ¿Puede este usuario gestionar externos en esta sesión?
     * admin/superadmin siempre; coach/staff solo si figuran como responsables.
     */
    public static function canManage(array $session, ?int $userId, ?string $role): bool
    {
        if (in_array($role, ['superadmin', 'admin'], true)) {
            return true;
        }
        if (in_array($role, ['coach', 'staff'], true) && $userId) {
            foreach ($session['coaches'] ?? [] as $c) {
                if ((int) ($c['user_id'] ?? 0) === $userId) {
                    return true;
                }
            }
        }
        return false;
    }

    /** Datos personales (contacto, tutor, nacimiento): solo admin/superadmin. */
    public static function canSeePersonal(?string $role): bool
    {
        return in_array($role, ['superadmin', 'admin'], true);
    }

    /**
     * Estado del dinero de una clase de externo. Pura.
     *  sin_precio → nadie ha fijado cuánto cuesta · gratis → precio 0 · pagado · parcial · pendiente.
     */
    public static function moneyState(?int $price, int $paid): string
    {
        if ($price === null) {
            return 'sin_precio';
        }
        if ($price <= 0) {
            return 'gratis';
        }
        if ($paid >= $price) {
            return 'pagado';
        }
        return $paid > 0 ? 'parcial' : 'pendiente';
    }

    /**
     * Lee precio / cobro del formulario. Solo se acepta con acceso total a Finanzas (lo decide quien
     * llama): quien no lo tiene no puede fijar importes, la clase queda «sin precio» y lo completa
     * después quien sí. Pura.
     *
     * @return array{price:?int,pay_now:int,method_id:?int,errors:string[]}
     */
    public static function parseMoney(array $in): array
    {
        $errors = [];
        $price  = null;
        $raw    = trim((string) ($in['price'] ?? ''));
        if ($raw !== '') {
            $price = RevisionService::parseEuroToCents($raw);
            if ($price === null) {
                $errors[] = 'El precio no es válido (ejemplo: 25 o 25,50).';
            }
        }
        $pay = 0;
        if (!empty($in['paid_now'])) {
            if ($price === null || $price <= 0) {
                $errors[] = 'Para marcar «cobrado ahora» indica un precio mayor que 0.';
            } else {
                $pay = $price;   // «cobrado ahora» = el importe completo; los parciales se registran después
            }
        }
        $method = isset($in['method_id']) && (int) $in['method_id'] > 0 ? (int) $in['method_id'] : null;
        return ['price' => $price, 'pay_now' => $pay, 'method_id' => $method, 'errors' => $errors];
    }

    // ───────────────────────── Con BD ─────────────────────────

    public function available(): bool
    {
        try {
            $db = \Config\Database::connect();
            return $db->tableExists('class_guests') && $db->tableExists('class_session_guests');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** ¿Están las columnas/tablas del dinero (migración de la Fase 2)? */
    public function moneyAvailable(): bool
    {
        try {
            $db = \Config\Database::connect();
            return $this->available() && $db->tableExists('class_guest_payments') && $db->fieldExists('price_cents', 'class_session_guests');
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Medio de pago por defecto cuando no se indica ninguno («Sin especificar»). */
    private function defaultMethodId(): ?int
    {
        $db = \Config\Database::connect();
        if (!$db->tableExists('fin_payment_methods')) {
            return null;
        }
        $r = $db->table('fin_payment_methods')->select('id')->where('code', 'sin_especificar')->get()->getRowArray();
        return $r ? (int) $r['id'] : null;
    }

    /** ¿Hay algún cobro (aunque esté anulado) registrado en esta clase de externo? Entonces es histórico. */
    public static function hasPayments(int $linkId): bool
    {
        try {
            $db = \Config\Database::connect();
            return $db->tableExists('class_guest_payments')
                && $db->table('class_guest_payments')->where('session_guest_id', $linkId)->countAllResults() > 0;
        } catch (\Throwable $e) {
            return false;
        }
    }

    /** Cobros activos (no anulados) de una clase de externo, céntimos. */
    private function paidCents(int $linkId): int
    {
        $r = \Config\Database::connect()->table('class_guest_payments')->selectSum('amount_cents', 's')
            ->where('session_guest_id', $linkId)->where('voided_at IS NULL')->get()->getRowArray();
        return (int) ($r['s'] ?? 0);
    }

    /**
     * Fija (o cambia) el precio de la clase de un externo. null = «sin precio».
     * No puede quedar por debajo de lo ya cobrado.
     *
     * @throws \DomainException
     */
    public function setPrice(int $sessionId, int $linkId, ?int $cents, ?int $actorId): void
    {
        if (!$this->moneyAvailable()) {
            throw new \DomainException('Falta aplicar la migración del dinero de externos.');
        }
        $link = $this->links->where(['id' => $linkId, 'session_id' => $sessionId])->first();
        if (!$link) {
            throw new \DomainException('Ese externo no está en esta clase.');
        }
        if ($cents !== null && $cents < 0) {
            throw new \DomainException('El precio no puede ser negativo.');
        }
        $paid = $this->paidCents($linkId);
        if ($cents !== null && $cents < $paid) {
            throw new \DomainException('El precio no puede ser menor que lo ya cobrado (' . eur($paid) . '). Anula antes el cobro.');
        }
        $before = $link['price_cents'] ?? null;
        if ($before !== null && (int) $before === $cents) {
            return;
        }
        $this->links->update($linkId, ['price_cents' => $cents]);
        AuditService::record('class_guest', (int) $link['guest_id'], AuditService::UPDATE,
            ['price_cents' => $before, 'session_id' => $sessionId], ['price_cents' => $cents, 'session_id' => $sessionId], null, $actorId);
    }

    /**
     * Registra un cobro de la clase de un externo. Requiere precio fijado y no puede superar lo pendiente.
     *
     * @return int id del cobro
     * @throws \DomainException
     */
    public function addPayment(int $sessionId, int $linkId, int $cents, ?int $methodId, ?string $paidAt, ?string $note, ?int $actorId): int
    {
        if (!$this->moneyAvailable()) {
            throw new \DomainException('Falta aplicar la migración del dinero de externos.');
        }
        $link = $this->links->where(['id' => $linkId, 'session_id' => $sessionId])->first();
        if (!$link) {
            throw new \DomainException('Ese externo no está en esta clase.');
        }
        if ($cents <= 0) {
            throw new \DomainException('El importe del cobro debe ser mayor que 0.');
        }
        if (($link['price_cents'] ?? null) === null) {
            throw new \DomainException('Fija primero el precio de la clase.');
        }
        $pending = (int) $link['price_cents'] - $this->paidCents($linkId);
        if ($cents > $pending) {
            throw new \DomainException('El cobro (' . eur($cents) . ') supera lo pendiente (' . eur(max(0, $pending)) . ').');
        }
        $date = $paidAt && preg_match('/^\d{4}-\d{2}-\d{2}$/', $paidAt) && strtotime($paidAt) !== false ? $paidAt : date('Y-m-d');
        if ($date > date('Y-m-d')) {
            throw new \DomainException('La fecha del cobro no puede ser futura.');
        }
        $db = \Config\Database::connect();
        if ($methodId !== null && !$db->table('fin_payment_methods')->where('id', $methodId)->countAllResults()) {
            throw new \DomainException('Medio de pago no válido.');
        }
        $db->table('class_guest_payments')->insert([
            'session_guest_id' => $linkId,
            'method_id'        => $methodId ?? $this->defaultMethodId(),
            'amount_cents'     => $cents,
            'paid_at'          => $date,
            'note'             => $note !== null && trim($note) !== '' ? mb_substr(trim($note), 0, 255) : null,
            'created_by'       => $actorId,
            'created_at'       => date('Y-m-d H:i:s'),
        ]);
        $id = (int) $db->insertID();
        AuditService::record('class_guest_payment', $id, AuditService::CREATE, null,
            ['session_guest_id' => $linkId, 'guest_id' => (int) $link['guest_id'], 'amount_cents' => $cents, 'paid_at' => $date], null, $actorId);
        return $id;
    }

    /**
     * Cobra de una vez lo que falte por pagar de la clase de un externo («Cobrado»).
     *
     * @return int importe cobrado, céntimos (0 si no quedaba nada pendiente)
     * @throws \DomainException
     */
    public function collectPending(int $sessionId, int $linkId, ?int $methodId, ?int $actorId): int
    {
        if (!$this->moneyAvailable()) {
            throw new \DomainException('Falta aplicar la migración del dinero de externos.');
        }
        $link = $this->links->where(['id' => $linkId, 'session_id' => $sessionId])->first();
        if (!$link) {
            throw new \DomainException('Ese externo no está en esta clase.');
        }
        if (($link['price_cents'] ?? null) === null) {
            throw new \DomainException('Fija primero el precio de la clase.');
        }
        $pending = (int) $link['price_cents'] - $this->paidCents($linkId);
        if ($pending <= 0) {
            return 0;
        }
        $this->addPayment($sessionId, $linkId, $pending, $methodId, date('Y-m-d'), null, $actorId);
        return $pending;
    }

    /**
     * Anula un cobro (nunca se borra): motivo obligatorio.
     *
     * @throws \DomainException
     */
    public function voidPayment(int $sessionId, int $paymentId, string $reason, ?int $actorId): void
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new \DomainException('Indica el motivo de la anulación.');
        }
        $db  = \Config\Database::connect();
        $row = $db->table('class_guest_payments p')->select('p.*, csg.session_id, csg.guest_id')
            ->join('class_session_guests csg', 'csg.id = p.session_guest_id')
            ->where('p.id', $paymentId)->where('csg.session_id', $sessionId)->get()->getRowArray();
        if (!$row) {
            throw new \DomainException('Ese cobro no pertenece a esta clase.');
        }
        if (!empty($row['voided_at'])) {
            throw new \DomainException('Ese cobro ya estaba anulado.');
        }
        $db->table('class_guest_payments')->where('id', $paymentId)->update([
            'voided_at' => date('Y-m-d H:i:s'), 'voided_by' => $actorId, 'void_reason' => mb_substr($reason, 0, 255),
        ]);
        AuditService::record('class_guest_payment', $paymentId, AuditService::VOID,
            ['amount_cents' => (int) $row['amount_cents']], null, $reason, $actorId);
    }

    /**
     * Externos de una clase. $withPersonal=false oculta contacto, tutor y nacimiento.
     *
     * @return array<int,array<string,mixed>>
     */
    public function forSession(int $sessionId, bool $withPersonal, bool $withMoney = false): array
    {
        if (!$this->available()) {
            return [];
        }
        $withMoney = $withMoney && $this->moneyAvailable();
        $cols = 'csg.id AS link_id, csg.session_id, csg.guest_id, csg.kind, csg.attendance, csg.notes AS link_notes,'
              . ' g.first_name, g.last_name, g.image_rights, g.converted_user_id';
        if ($withMoney) {
            $cols .= ', csg.price_cents';
        }
        if ($withPersonal) {
            $cols .= ', g.phone, g.email, g.birth_date, g.guardian_name, g.guardian_phone';
        }
        $rows = $this->links->builder('class_session_guests csg')
            ->select($cols)
            ->join('class_guests g', 'g.id = csg.guest_id', 'inner')
            ->where('csg.session_id', $sessionId)
            ->orderBy('csg.id', 'ASC')
            ->get()->getResultArray();

        foreach ($rows as &$r) {
            $r['name']       = trim($r['first_name'] . ' ' . ($r['last_name'] ?? ''));
            $r['kind_label'] = self::KINDS[$r['kind']] ?? $r['kind'];
            // ¿Se puede quitar de la clase? No si ya tiene cobros (ni siquiera anulados): son histórico.
            $r['has_payments'] = self::hasPayments((int) $r['link_id']);
            if ($withPersonal) {
                $age = self::ageOn($r['birth_date'] ?? null);
                $r['age']      = $age;
                $r['is_minor'] = $age !== null && $age < 18;
            }
            if ($withMoney) {
                $price = $r['price_cents'] === null ? null : (int) $r['price_cents'];
                $pays  = $this->links->builder('class_guest_payments p')
                    ->select('p.id, p.amount_cents, p.paid_at, p.note, p.voided_at, p.void_reason, m.name AS method')
                    ->join('fin_payment_methods m', 'm.id = p.method_id', 'left')
                    ->where('p.session_guest_id', (int) $r['link_id'])->orderBy('p.paid_at', 'ASC')->orderBy('p.id', 'ASC')
                    ->get()->getResultArray();
                $paid = 0;
                foreach ($pays as $p) {
                    if (empty($p['voided_at'])) {
                        $paid += (int) $p['amount_cents'];
                    }
                }
                $r['price_cents']   = $price;
                $r['paid_cents']    = $paid;
                $r['pending_cents'] = $price === null ? null : max(0, $price - $paid);
                $r['money_state']   = self::moneyState($price, $paid);
                $r['payments']      = $pays;
            }
        }
        unset($r);
        return $rows;
    }

    /**
     * Da de alta (o reutiliza) al externo y lo apunta a la clase.
     *
     * @return array{link_id:int, guest_id:int, reused:bool}
     * @throws \DomainException
     */
    public function addToSession(array $session, array $input, ?int $actorId, ?array $money = null): array
    {
        if (!$this->available()) {
            throw new \DomainException('Falta aplicar la migración de externos en esta base de datos.');
        }
        if (($session['status'] ?? '') === 'cancelled') {
            throw new \DomainException('Esta clase está cancelada: no se pueden añadir externos.');
        }

        [$clean, $errors] = self::validate($input);
        if ($errors) {
            throw new \DomainException(implode(' ', $errors));
        }

        $kind      = $clean['kind'];
        $sessionId = (int) $session['id'];
        unset($clean['kind']);

        $db = \Config\Database::connect();
        $db->transStart();

        $guest  = $this->findExisting($clean);
        $reused = $guest !== null;
        if ($reused) {
            $guestId = (int) $guest['id'];
            // Si en esta ocasión nos dan datos que antes faltaban, se completan (nunca se pisan los que ya había).
            $fill = [];
            foreach (['last_name', 'phone', 'phone_key', 'email', 'guardian_name', 'guardian_phone'] as $f) {
                if (empty($guest[$f]) && !empty($clean[$f])) {
                    $fill[$f] = $clean[$f];
                }
            }
            if ($fill) {
                $this->guests->update($guestId, $fill);
            }
        } else {
            $clean['created_by'] = $actorId;
            $guestId = (int) $this->guests->insert($clean, true);
        }

        $exists = $this->links->where(['session_id' => $sessionId, 'guest_id' => $guestId])->first();
        if ($exists) {
            $db->transComplete();
            throw new \DomainException('Esa persona ya figura como externa en esta clase.');
        }

        $linkId = (int) $this->links->insert([
            'session_id' => $sessionId,
            'guest_id'   => $guestId,
            'kind'       => $kind,
            'attendance' => 'pending',
            'created_by' => $actorId,
        ], true);

        // Precio y «cobrado ahora» (solo llegan si quien crea tiene acceso total a Finanzas).
        if ($money !== null && $money['price'] !== null) {
            $this->setPrice($sessionId, $linkId, (int) $money['price'], $actorId);
            if (($money['pay_now'] ?? 0) > 0) {
                $this->addPayment($sessionId, $linkId, (int) $money['pay_now'], $money['method_id'] ?? null, date('Y-m-d'), null, $actorId);
            }
        }

        $db->transComplete();
        if (!$db->transStatus() || $linkId <= 0) {
            throw new \RuntimeException('No se pudo guardar el externo.');
        }

        AuditService::record('class_guest', $guestId, $reused ? AuditService::UPDATE : AuditService::CREATE, null, [
            'session_id' => $sessionId,
            'kind'       => $kind,
            'name'       => trim($clean['first_name'] . ' ' . ($clean['last_name'] ?? '')),
            'reused'     => $reused,
        ]);

        return ['link_id' => $linkId, 'guest_id' => $guestId, 'reused' => $reused];
    }

    /**
     * Crea una clase (formulario completo o pop-up de clase rápida) y, si viene en modo «Externo»,
     * también la ficha de la persona, todo o nada. Un solo sitio para las dos pantallas.
     *
     * @param callable $create   fn(array $post): array — createSession() o quickCreate()
     * @param bool     $canMoney  ¿quien crea tiene acceso total a Finanzas? Si no, precio y cobro se ignoran
     * @return array resultado de $create, o ['success'=>false,'error'=>...]
     */
    public function createClass(callable $create, array $post, ?int $actorId, bool $canMoney): array
    {
        $people = null;   // [['data' => fila del formulario, 'money' => ?array], ...]

        if (!empty($post['externo_mode']) && ($post['type'] ?? 'single') !== 'recurring') {
            $rows = self::peopleFromPost($post);
            $errs = [];
            if (!$this->available()) {
                $errs[] = 'Falta aplicar la migración de externos en esta base de datos.';
            }
            if (!$rows) {
                $errs[] = 'Indica el nombre de la persona externa.';
            }
            $people = [];
            foreach ($rows as $i => $row) {
                [, $e] = self::validate($row);
                $label = count($rows) > 1 ? 'Persona ' . ($i + 1) . ': ' : '';
                foreach ($e as $msg) {
                    $errs[] = $label . $msg;
                }
                $money = null;
                if ($canMoney) {
                    $m = self::parseMoney($row);
                    foreach ($m['errors'] as $msg) {
                        $errs[] = $label . $msg;
                    }
                    if ($m['price'] !== null) {
                        $money = $m;
                        if (!$this->moneyAvailable()) {
                            $errs[] = 'Falta aplicar la migración del dinero de externos.';
                        }
                    }
                }
                $people[] = ['data' => $row, 'money' => $money];
            }
            if ($errs) {
                return ['success' => false, 'error' => implode(' ', array_unique($errs))];
            }
            $post['player_ids']        = [];   // en modo externo no hay alumnos registrados
            $post['player_coach_map']  = [];
            $post['externo_validated'] = 1;
        } else {
            unset($post['externo_validated']);  // nunca se confía en un valor enviado por el navegador
        }

        $db = \Config\Database::connect();
        $db->transBegin();
        try {
            $result = $create($post);
            if (!empty($result['success']) && $people !== null) {
                foreach ($people as $p) {
                    $this->addToSession(['id' => (int) $result['id'], 'status' => 'scheduled'], $p['data'], $actorId, $p['money']);
                }
            }
            if (empty($result['success'])) {
                $db->transRollback();
            } else {
                $db->transCommit();
            }
            return $result;
        } catch (\DomainException $e) {
            $db->transRollback();
            return ['success' => false, 'error' => $e->getMessage()];
        } catch (\Throwable $e) {
            $db->transRollback();
            throw $e;
        }
    }

    /**
     * Marca la asistencia de un externo en una clase.
     *
     * @throws \DomainException
     */
    public function setAttendance(int $sessionId, int $linkId, string $attendance): void
    {
        if (!in_array($attendance, self::ATTENDANCE, true)) {
            throw new \DomainException('Asistencia no válida.');
        }
        $link = $this->links->where(['id' => $linkId, 'session_id' => $sessionId])->first();
        if (!$link) {
            throw new \DomainException('Ese externo no está en esta clase.');
        }
        if ($link['attendance'] === $attendance) {
            return;
        }
        $this->links->update($linkId, ['attendance' => $attendance]);
        AuditService::record('class_guest', (int) $link['guest_id'], AuditService::UPDATE,
            ['attendance' => $link['attendance'], 'session_id' => $sessionId],
            ['attendance' => $attendance,         'session_id' => $sessionId]
        );
    }

    /**
     * Guarda la asistencia de varios externos de una clase de una vez (pantalla Pasar lista).
     * Ignora ids que no son de esta clase y valores no válidos.
     *
     * @param array<int|string,string> $map link_id => pending|present|absent
     * @return array{changed:int,pending:int} cambiados y cuántos quedan sin marcar
     */
    public function saveAttendanceMap(int $sessionId, array $map): array
    {
        $changed = 0;
        if (!$this->available()) {
            return ['changed' => 0, 'pending' => 0];
        }
        foreach ($this->links->where('session_id', $sessionId)->findAll() as $link) {
            $lid = (int) $link['id'];
            if (!isset($map[$lid]) && !isset($map[(string) $lid])) {
                continue;
            }
            $want = (string) ($map[$lid] ?? $map[(string) $lid]);
            if (!in_array($want, self::ATTENDANCE, true) || $want === $link['attendance']) {
                continue;
            }
            $this->setAttendance($sessionId, $lid, $want);
            $changed++;
        }
        $pending = (int) $this->links->where(['session_id' => $sessionId, 'attendance' => 'pending'])->countAllResults();
        return ['changed' => $changed, 'pending' => $pending];
    }

    /**
     * Corrige los datos de una persona externa desde la ficha de una de sus clases.
     * $withPersonal=false (coach/staff): solo nombre y apellidos. Los cambios afectan a la FICHA de la
     * persona (todas sus clases). Se audita con el antes y el después.
     *
     * @throws \DomainException
     */
    public function updateGuest(int $sessionId, int $linkId, array $input, bool $withPersonal, ?int $actorId): void
    {
        $link = $this->links->where(['id' => $linkId, 'session_id' => $sessionId])->first();
        if (!$link) {
            throw new \DomainException('Ese externo no está en esta clase.');
        }
        $guest = $this->guests->find((int) $link['guest_id']);
        if (!$guest) {
            throw new \DomainException('No se encuentra la ficha de esta persona.');
        }

        if ($withPersonal) {
            [$clean, $errors] = self::validate($input);
            if ($errors) {
                throw new \DomainException(implode(' ', $errors));
            }
            $fields = ['first_name', 'last_name', 'phone', 'phone_key', 'email', 'birth_date', 'guardian_name', 'guardian_phone', 'image_rights', 'notes'];
        } else {
            $first = mb_substr(trim((string) ($input['first_name'] ?? '')), 0, 100);
            if ($first === '') {
                throw new \DomainException('Indica el nombre.');
            }
            $clean  = ['first_name' => $first, 'last_name' => mb_substr(trim((string) ($input['last_name'] ?? '')), 0, 100) ?: null];
            $fields = ['first_name', 'last_name'];
        }

        $before = $after = $upd = [];
        foreach ($fields as $f) {
            $old = $guest[$f] ?? null;
            $new = $clean[$f] ?? null;
            if ((string) $old !== (string) $new) {
                $before[$f] = $old;
                $after[$f]  = $new;
                $upd[$f]    = $new;
            }
        }
        if (!$upd) {
            return;
        }
        $this->guests->update((int) $guest['id'], $upd);
        AuditService::record('class_guest', (int) $guest['id'], AuditService::UPDATE, $before, $after, 'Datos corregidos desde la clase #' . $sessionId, $actorId);
    }

    /**
     * Quita al externo de la clase. Se puede aunque ya tenga asistencia marcada (queda en la auditoría);
     * NO se puede si tiene cobros (aunque estén anulados): son histórico económico. La ficha de la
     * persona nunca se borra.
     *
     * @throws \DomainException
     */
    public function removeFromSession(int $sessionId, int $linkId): void
    {
        $link = $this->links->where(['id' => $linkId, 'session_id' => $sessionId])->first();
        if (!$link) {
            throw new \DomainException('Ese externo no está en esta clase.');
        }
        if (self::hasPayments((int) $linkId)) {
            throw new \DomainException('Tiene cobros registrados (incluso anulados), que se conservan como histórico: no se puede quitar de la clase. Si no vino, márcalo como «Ausente» en Pasar lista.');
        }
        $this->links->delete($linkId);
        AuditService::record('class_guest', (int) $link['guest_id'], AuditService::DELETE, [
            'session_id' => $sessionId, 'kind' => $link['kind'], 'attendance' => $link['attendance'],
        ], null, $link['attendance'] === 'pending' ? 'Quitado de la clase' : 'Quitado de la clase (ya tenía asistencia marcada)');
    }

    /**
     * Misma persona = mismo nombre + mismo teléfono (o email). Así los hermanos que comparten
     * teléfono de la madre NO se mezclan.
     */
    private function findExisting(array $clean): ?array
    {
        $first = mb_strtolower($clean['first_name']);
        $last  = mb_strtolower((string) ($clean['last_name'] ?? ''));

        // Condición cruda con el valor escapado a mano: where(col, val, false) inlinea el valor SIN comillas.
        $q = $this->guests->where('archived_at', null)
            ->where('LOWER(first_name) = ' . \Config\Database::connect()->escape($first), null, false);
        $q->groupStart();
        $has = false;
        if (!empty($clean['phone_key'])) {
            $q->where('phone_key', $clean['phone_key']);
            $has = true;
        }
        if (!empty($clean['email'])) {
            $has ? $q->orWhere('email', $clean['email']) : $q->where('email', $clean['email']);
            $has = true;
        }
        $q->groupEnd();
        if (!$has) {
            return null;
        }

        foreach ($q->findAll() as $g) {
            // Apellido: si los dos lo tienen y difieren, son personas distintas.
            $gl = mb_strtolower((string) ($g['last_name'] ?? ''));
            if ($last !== '' && $gl !== '' && $last !== $gl) {
                continue;
            }
            return $g;
        }
        return null;
    }
}
