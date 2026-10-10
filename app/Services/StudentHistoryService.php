<?php

namespace App\Services;

/**
 * Historial completo de un alumno: TODO lo que ha pasado con él o ha hecho él
 * en la plataforma (Finanzas › Alumnos › Historial).
 *
 * `report()` devuelve cuatro piezas pensadas para alguien sin contexto:
 *  - alerts:   lo que hay que revisar (saldos que no cuadran, clases sin
 *              descontar, sesiones que sobrarán, deuda…), en frases claras.
 *  - bonos:    cada bono con su compra, su pago y cada sesión usada, con el
 *              saldo después de cada paso y el saldo real de hoy.
 *  - upcoming: próximas clases y de qué bono saldrán.
 *  - rows:     la línea de tiempo completa (cargos y cobros, bonos, clases,
 *              accesos, mensajes —solo el registro, nunca el texto—, tickets,
 *              avisos, emails, documentos, anotaciones y auditoría).
 *
 * Reglas de lectura: una clase y su descuento van en UNA fila (con quién y
 * cuándo se descontó); las filas de bono llevan «quedan X de Y» y las de
 * dinero «Debe / Al día / A su favor»; el arranque de Finanzas (cargo + cobro
 * «sin especificar») sale en una sola fila; lo automático firma «Sistema».
 *
 * Fila: at, cat, event, detail, cents, who, link, saldo, future, noise.
 * Cada fuente va en su propio try: si una tabla no existe en un entorno, el
 * resto del historial se sigue mostrando. Las funciones estáticas son puras.
 */
class StudentHistoryService
{
    public const CATEGORIES = [
        'economico'    => 'Económico',
        'bonos'        => 'Bonos',
        'clases'       => 'Clases',
        'acceso'       => 'Acceso',
        'comunicacion' => 'Comunicación',
        'seguimiento'  => 'Seguimiento',
        'auditoria'    => 'Auditoría',
    ];

    public const ATT = [
        'present' => 'Presente', 'absent' => 'Ausencia justificada', 'unjustified' => 'Falta sin justificar',
        'declined' => 'Avisó ausencia', 'confirmed' => 'Confirmó asistencia', 'pending' => 'Pendiente',
    ];

    /** Notas con las que la migración de Finanzas marcó el arranque. */
    public const BOOT_CHARGE_NOTE  = 'Arranque de Finanzas';
    public const BOOT_PAYMENT_NOTE = 'Cobro anterior a Finanzas (medio sin especificar)';

    public const SYSTEM = 'Sistema (automático)';

    private \CodeIgniter\Database\BaseConnection $db;
    private array $names = [];

    public function __construct(?\CodeIgniter\Database\BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    private function name(?int $id): ?string
    {
        if (!$id) {
            return null;
        }
        if (!array_key_exists($id, $this->names)) {
            $r = $this->db->table('users')->select('name')->where('id', $id)->get()->getRow();
            $this->names[$id] = $r->name ?? ('Usuario #' . $id);
        }
        return $this->names[$id];
    }

    /**
     * Momento de un cargo o cobro: su fecha real (charged_at / paid_at) con la hora
     * de registro si coinciden; si se registró otro día (p. ej. el arranque de
     * Finanzas), la fecha real a las 00:00. Pura.
     */
    public static function when(?string $date, ?string $createdAt): ?string
    {
        if (!$date) {
            return $createdAt;
        }
        return ($createdAt && substr($createdAt, 0, 10) === $date) ? $createdAt : $date . ' 00:00:00';
    }

    public static function money(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '.') . "\u{00A0}€";
    }

    private static function d(?string $dt): string
    {
        return $dt ? date('d/m/Y', strtotime($dt)) : '—';
    }

    private static function plural(int $n, string $one, string $many): string
    {
        return $n . ' ' . ($n === 1 ? $one : $many);
    }

    /** «Debe 180,00 €» / «Al día» / «A su favor 20,00 €». Pura. */
    public static function balanceText(int $cents): string
    {
        return $cents > 0 ? 'Debe ' . self::money($cents) : ($cents < 0 ? 'A su favor ' . self::money(-$cents) : 'Al día');
    }

    /** Compatibilidad: solo la línea de tiempo, de lo más reciente a lo más antiguo. */
    public function timeline(int $playerId, ?string $from = null, ?string $to = null): array
    {
        return $this->report($playerId, $from, $to)['rows'];
    }

    /**
     * @return array{rows:array,bonos:array,alerts:array,upcoming:array,ledger_start:?string,debt:int}
     */
    public function report(int $playerId, ?string $from = null, ?string $to = null): array
    {
        $user = $this->db->table('users')->select('id, email, created_at, welcomed_at, password_changed_at')->where('id', $playerId)->get()->getRowArray();
        if (!$user) {
            return ['rows' => [], 'bonos' => [], 'alerts' => [], 'upcoming' => [], 'ledger_start' => null, 'debt' => 0];
        }

        $rows = [];
        $add  = static function (?string $at, string $cat, string $event, string $detail, ?int $cents = null, ?string $who = null, ?string $link = null, array $extra = []) use (&$rows) {
            if (!$at) {
                return;
            }
            $rows[] = array_merge(['at' => $at, 'cat' => $cat, 'event' => $event, 'detail' => trim($detail), 'cents' => $cents, 'who' => $who,
                                   'link' => $link, 'saldo' => null, 'future' => false, 'noise' => false, 'fd' => null], $extra);
        };
        $safe = function (string $source, callable $fn) {
            try {
                $fn();
            } catch (\Throwable $e) {
                log_message('error', "StudentHistory[{$source}]: " . $e->getMessage());
            }
        };
        $now   = date('Y-m-d H:i:s');
        $today = substr($now, 0, 10);

        // ── Carga de bonos, libro de movimientos y clases ───────────────
        $bonos = $moves = $csp = $coaches = [];
        $ledgerStart = null;
        $safe('carga-bonos', function () use ($playerId, &$bonos) {
            foreach ($this->db->table('player_bonos pb')->select('pb.*, bt.name AS bono_name')
                         ->join('bono_types bt', 'bt.id = pb.bono_type_id', 'left')
                         ->where('pb.player_id', $playerId)->orderBy('pb.created_at')->get()->getResultArray() as $b) {
                $bonos[(int) $b['id']] = $b;
            }
        });
        $safe('carga-libro', function () use ($playerId, &$moves, &$ledgerStart) {
            $moves = $this->db->table('bono_movements bm')
                ->select('bm.*, cs.title AS session_title, cs.session_date')
                ->join('class_sessions cs', 'cs.id = bm.session_id', 'left')
                ->where('bm.player_id', $playerId)->orderBy('bm.created_at')->orderBy('bm.id')->get()->getResultArray();
            $ledgerStart = $this->db->table('bono_movements')->selectMin('created_at', 'm')->get()->getRow()->m ?? null;
        });
        $safe('carga-clases', function () use ($playerId, &$csp, &$coaches) {
            $csp = $this->db->table('class_session_players csp')
                ->select('csp.*, cs.title, cs.session_date, cs.start_time, cs.status')
                ->join('class_sessions cs', 'cs.id = csp.session_id')
                ->where('csp.user_id', $playerId)->orderBy('cs.session_date')->orderBy('cs.start_time')->get()->getResultArray();
            $ids = array_values(array_unique(array_map(fn($c) => (int) $c['session_id'], $csp)));
            if ($ids) {
                foreach ($this->db->table('class_session_coaches csc')->select('csc.session_id, u.name')
                             ->join('users u', 'u.id = csc.user_id')->whereIn('csc.session_id', $ids)->get()->getResultArray() as $r) {
                    $coaches[(int) $r['session_id']][] = $r['name'];
                }
            }
        });

        $bonoLabel = static fn(array $b): string => ($b['bono_name'] ?? 'Bono') . ' #' . (int) $b['id'];
        $mv = function (array $m): array {
            $who = $m['actor_id'] ? $this->name((int) $m['actor_id']) : null;
            if ($m['type'] === BonoLedgerService::EXPIRY_ALERT || !$who) {
                $who = self::SYSTEM;   // el aviso de caducidad lo lanza el sistema, aunque se guarde a nombre de un admin
            }
            return ['id' => (int) $m['id'], 'type' => $m['type'], 'delta' => (int) $m['delta'], 'at' => $m['created_at'], 'who' => $who,
                    'note' => (string) ($m['note'] ?? ''), 'session_id' => $m['session_id'] ? (int) $m['session_id'] : null,
                    'session_date' => $m['session_date'] ?? null, 'session_title' => $m['session_title'] ?? null];
        };

        // ── Cada bono con su saldo paso a paso ──────────────────────────
        $ledgers = [];          // bono_id => resultado de buildBonoLedger
        $absorbed = [];         // movimientos que ya salen dentro de otra fila
        $consumoBySession = []; // session_id => sesión usada (para la fila de la clase)
        foreach ($bonos as $id => $b) {
            $consumos = [];
            foreach ($csp as $c) {
                if ((int) ($c['bono_deducted_from_id'] ?? 0) === $id && !empty($c['bono_deducted_at'])) {
                    $consumos[] = ['session_id' => (int) $c['session_id'], 'date' => $c['session_date'],
                                   'at' => $c['session_date'] . ' ' . ($c['start_time'] ?: '00:00:00'), 'title' => $c['title'],
                                   'state' => self::ATT[$c['attendance']] ?? (string) $c['attendance'], 'deducted_at' => $c['bono_deducted_at']];
                }
            }
            $own = array_map($mv, array_values(array_filter($moves, fn($m) => (int) $m['bono_id'] === $id)));
            $ledger = self::buildBonoLedger([
                'id' => $id, 'label' => $bonoLabel($b), 'sessions_total' => (int) $b['sessions_total'], 'sessions_remaining' => (int) $b['sessions_remaining'],
                'created_at' => $b['created_at'], 'created_by' => $this->name((int) ($b['created_by'] ?? 0)), 'expires_at' => $b['expires_at'] ?? null,
            ], $consumos, $own);
            foreach ($ledger['absorbed'] as $mid) {
                $absorbed[$mid] = true;
            }
            foreach ($ledger['events'] as $e) {
                if ($e['kind'] === 'consumo' && $e['session_id'] && empty($e['inferred'])) {
                    $consumoBySession[$e['session_id']] = $e + ['bono_id' => $id];
                }
            }
            $ledgers[$id] = $ledger;
        }

        // ── Dinero: cargos, cobros y su reparto ─────────────────────────
        $charges = $payments = $allocs = [];
        $safe('carga-dinero', function () use ($playerId, &$charges, &$payments, &$allocs) {
            $charges  = $this->db->table('fin_charges')->where('player_id', $playerId)->orderBy('id')->get()->getResultArray();
            $payments = $this->db->table('fin_payments p')->select('p.*, m.name AS method')
                ->join('fin_payment_methods m', 'm.id = p.method_id', 'left')->where('p.player_id', $playerId)->orderBy('p.id')->get()->getResultArray();
            $pids = array_map(fn($p) => (int) $p['id'], $payments);
            if ($pids) {
                $allocs = $this->db->table('fin_payment_allocations')->whereIn('payment_id', $pids)->get()->getResultArray();
            }
        });
        $allocByPayment = $allocByCharge = $payById = [];
        foreach ($allocs as $a) {
            $allocByPayment[(int) $a['payment_id']][] = $a;
            $allocByCharge[(int) $a['charge_id']][]   = $a;
        }
        foreach ($payments as $p) {
            $payById[(int) $p['id']] = $p;
        }
        // Arranque de Finanzas: cargo + cobro «sin especificar» del mismo importe → una sola fila
        $bootPayments = [];
        foreach ($charges as $c) {
            if ($c['voided_at'] || ($c['note'] ?? '') !== self::BOOT_CHARGE_NOTE) {
                continue;
            }
            foreach ($allocByCharge[(int) $c['id']] ?? [] as $a) {
                $p = $payById[(int) $a['payment_id']] ?? null;
                if ($p && !$p['voided_at'] && ($p['note'] ?? '') === self::BOOT_PAYMENT_NOTE
                    && (int) $p['amount_cents'] === (int) $c['amount_cents'] && count($allocByPayment[(int) $p['id']] ?? []) === 1) {
                    $bootPayments[(int) $p['id']] = (int) $c['id'];
                }
            }
        }
        $bootCharges = array_flip($bootPayments);

        // ── Cuenta ──────────────────────────────────────────────────────
        $safe('cuenta', function () use ($user, $add) {
            $add($user['created_at'], 'acceso', 'Alta en la plataforma', 'Se creó su usuario');
            $add($user['welcomed_at'] ?? null, 'acceso', 'Primer acceso', 'Vio la bienvenida de la plataforma');
        });

        // ── Económico ───────────────────────────────────────────────────
        $safe('cargos', function () use ($playerId, $add, $charges, $bootCharges, $bonos) {
            foreach ($charges as $c) {
                $bono   = $c['bono_id'] && isset($bonos[(int) $c['bono_id']]) ? $bonos[(int) $c['bono_id']] : null;
                $what   = $c['concept'] . ($c['bono_id'] ? ' #' . (int) $c['bono_id'] : '');
                $amount = (int) $c['amount_cents'];
                if (isset($bootCharges[(int) $c['id']])) {
                    $add($bono['created_at'] ?? self::when($c['charged_at'], $c['created_at']), 'economico', 'Bono dado por pagado',
                        $what . ' · al poner en marcha Finanzas (' . self::d($c['created_at']) . ') este bono se registró como pagado; no se sabe con qué medio',
                        $amount, self::SYSTEM, 'finanzas/alumnos/' . $playerId, ['fd' => 0]);
                    continue;
                }
                $d = $what . ($c['discount_cents'] > 0 ? ' · descuento ' . self::money((int) $c['discount_cents']) . ' (' . ($c['discount_reason'] ?: '—') . ')' : '')
                   . ($c['note'] ? ' · ' . $c['note'] : '');
                $add(self::when($c['charged_at'], $c['created_at']), 'economico', $c['bono_id'] ? 'Bono por cobrar' : 'Cargo', $d, $amount,
                    $this->name((int) $c['created_by']), 'finanzas/alumnos/' . $playerId, ['fd' => $amount]);
                if ($c['voided_at']) {
                    $add($c['voided_at'], 'economico', 'Cargo anulado', $what . ' · motivo: ' . $c['void_reason'], -$amount,
                        $this->name((int) $c['voided_by']), null, ['fd' => -$amount]);
                }
            }
        });
        $safe('cobros', function () use ($playerId, $add, $payments, $bootPayments) {
            foreach ($payments as $p) {
                if (isset($bootPayments[(int) $p['id']])) {
                    continue;   // ya sale en «Bono dado por pagado»
                }
                $amount = (int) $p['amount_cents'];
                $d = ($p['method'] ?? 'Sin medio') . ' · fecha de pago ' . self::d($p['paid_at'])
                   . ($p['reference'] ? ' · ref. ' . $p['reference'] : '') . ($p['note'] ? ' · ' . $p['note'] : '');
                $add(self::when($p['paid_at'], $p['created_at']), 'economico', 'Pago recibido', $d, $amount,
                    $this->name((int) $p['created_by']), 'finanzas/alumnos/' . $playerId, ['fd' => -$amount]);
                if ($p['voided_at']) {
                    $add($p['voided_at'], 'economico', 'Pago anulado', ($p['method'] ?? 'Sin medio') . ' · motivo: ' . $p['void_reason'], -$amount,
                        $this->name((int) $p['voided_by']), null, ['fd' => $amount]);
                }
            }
        });

        // ── Bonos: compra, anulación y movimientos con su saldo ─────────
        $safe('bonos', function () use ($add, $bonos, $ledgers, $bonoLabel) {
            foreach ($bonos as $id => $b) {
                $l = $ledgers[$id];
                foreach ($l['events'] as $e) {
                    if ($e['kind'] === 'consumo' && empty($e['inferred'])) {
                        continue;   // sale en la fila de la clase
                    }
                    $text = $e['text'] . ($e['kind'] === 'emitido' && isset($b['price_cents']) && $b['price_cents'] !== null
                        ? ' · precio ' . self::money((int) $b['price_cents']) . (!empty($b['price_estimated']) ? ' (estimado)' : '') : '');
                    $add($e['at'], 'bonos', $e['title'], $text, null, $e['who'], $e['session_id'] ? 'clases/' . $e['session_id'] : 'bonos/' . $id,
                        ['saldo' => '#' . $id . ' · quedan ' . $e['saldo'] . ' de ' . (int) $b['sessions_total']]);
                }
                if (!empty($b['voided_at']) && !$l['has_void_move']) {
                    $add($b['voided_at'], 'bonos', 'Bono anulado', $bonoLabel($b) . ' · motivo: ' . $b['void_reason'], null,
                        $this->name((int) $b['voided_by']), 'bonos/' . $id);
                }
            }
        });
        $safe('libro-sin-bono', function () use ($add, $moves, $absorbed, $mv) {
            foreach ($moves as $m) {
                if ($m['bono_id'] || isset($absorbed[(int) $m['id']])) {
                    continue;
                }
                $e = $mv($m);
                [$label] = BonoLedgerService::label($e['type']);
                $add($e['at'], 'bonos', $label, ($e['session_title'] ? 'Clase del ' . self::d($e['session_date']) . ' (' . $e['session_title'] . ')' : 'Sin bono')
                    . ($e['note'] ? ' · ' . $e['note'] : ''), null, $e['who'], $e['session_id'] ? 'clases/' . $e['session_id'] : null);
            }
        });

        // ── Clases: una fila por clase con su descuento ─────────────────
        $usable = [];
        foreach ($bonos as $id => $b) {
            if (empty($b['voided_at']) && (int) $b['sessions_remaining'] > 0 && (empty($b['expires_at']) || $b['expires_at'] >= $today)) {
                $usable[] = ['id' => $id, 'remaining' => (int) $b['sessions_remaining'], 'start_date' => $b['start_date'] ?? null, 'expires_at' => $b['expires_at'] ?? null];
            }
        }
        $isFuture  = static fn(array $c): bool => $c['status'] === 'scheduled' && ($c['session_date'] . ' ' . ($c['start_time'] ?: '00:00:00')) >= $now;
        $plannable = array_values(array_filter($csp, fn($c) => $isFuture($c) && !in_array($c['attendance'], ['absent', 'declined'], true)));
        $plan = self::planUpcoming($usable, array_map(fn($c) => ['session_id' => (int) $c['session_id'], 'date' => $c['session_date']], $plannable));

        $upcoming = $unsettled = [];
        $safe('clases', function () use ($add, $csp, $coaches, $consumoBySession, $bonos, $bonoLabel, $plan, $isFuture, &$upcoming, &$unsettled) {
            foreach ($csp as $c) {
                $sid  = (int) $c['session_id'];
                $at   = $c['session_date'] . ' ' . ($c['start_time'] ?: '00:00:00');
                $who  = !empty($coaches[$sid]) ? implode(', ', $coaches[$sid]) : (!empty($c['coach_id']) ? $this->name((int) $c['coach_id']) : null);
                $link = 'clases/' . $sid;
                if ($isFuture($c)) {
                    $declined = in_array($c['attendance'], ['absent', 'declined'], true);
                    $bonoId   = $plan['assign'][$sid] ?? null;
                    $from     = $declined ? 'avisó que no vendrá' : ($bonoId ? 'saldrá del ' . $bonoLabel($bonos[$bonoId]) : 'sin saldo de bono que la cubra');
                    $upcoming[] = ['at' => $at, 'title' => $c['title'], 'coach' => $who, 'link' => $link, 'bono_id' => $bonoId, 'declined' => $declined, 'from' => $from];
                    $add($at, 'clases', 'Clase programada', $c['title'] . ' · ' . $from, null, $who, $link, ['future' => true]);
                    continue;
                }
                $state = $c['status'] === 'cancelled' ? 'cancelada'
                    : ($c['status'] === 'scheduled' ? 'sin cerrar' : (self::ATT[$c['attendance']] ?? (string) $c['attendance']));
                $extra = [];
                if (isset($consumoBySession[$sid])) {
                    $e = $consumoBySession[$sid];
                    $b = $bonos[$e['bono_id']];
                    $bonoTxt = ' · ' . $e['deduct_text'];
                    $extra['saldo'] = '#' . (int) $b['id'] . ' · quedan ' . $e['saldo'] . ' de ' . (int) $b['sessions_total'];
                } elseif (!empty($c['bono_resolution'])) {
                    $bonoTxt = ' · ' . BonoControlService::resolutionLabel($c['bono_resolution']);
                } elseif ($c['status'] === 'completed' && ClasesService::attendanceConsumesBono($c['attendance'])) {
                    $bonoTxt = ' · no se descontó de ningún bono';
                    $unsettled[] = ['date' => $c['session_date'], 'state' => $state, 'link' => $link];
                } else {
                    $bonoTxt = '';
                }
                $reason = $c['absence_reason'] ? ' · motivo: ' . $c['absence_reason'] : '';
                $add($at, 'clases', 'Clase · ' . $state, $c['title'] . $bonoTxt . $reason, null, $who, $link, $extra);
            }
            foreach ($csp as $c) {
                $sid = (int) $c['session_id'];
                if (!empty($c['student_noted_at'])) {
                    $add($c['student_noted_at'], 'clases', 'Aviso del alumno', 'Clase del ' . self::d($c['session_date']) . ' (' . $c['title'] . ')'
                        . ($c['student_note'] ? ' · «' . mb_substr($c['student_note'], 0, 160) . '»' : ''), null, null, 'clases/' . $sid);
                }
                if (!empty($c['responded_at'])) {
                    $add($c['responded_at'], 'clases', 'Respuesta del alumno', 'Clase del ' . self::d($c['session_date']) . ' (' . $c['title'] . ') · '
                        . (self::ATT[$c['attendance']] ?? $c['attendance']), null, null, 'clases/' . $sid);
                }
            }
        });

        // ── Acceso ──────────────────────────────────────────────────────
        $safe('accesos', function () use ($playerId, $user, $add) {
            $labels = ['login_success' => 'Inicio de sesión', 'login_fail' => 'Intento de acceso fallido', 'logout' => 'Cierre de sesión',
                       'password_reset' => 'Contraseña restablecida', 'password_change' => 'Cambio de contraseña', 'lockout' => 'Cuenta bloqueada temporalmente'];
            $q = $this->db->table('auth_events')->groupStart()->where('user_id', $playerId)->orWhere('identifier', $user['email'])->groupEnd()->get()->getResultArray();
            foreach ($q as $e) {
                $add($e['created_at'], 'acceso', $labels[$e['event_type']] ?? $e['event_type'], 'IP ' . ($e['ip_address'] ?? '—'));
            }
            $add($user['password_changed_at'] ?? null, 'acceso', 'Contraseña cambiada', 'Última vez que se cambió la contraseña');
        });

        // ── Comunicación ────────────────────────────────────────────────
        $safe('mensajes', function () use ($playerId, $add) {
            $q = $this->db->query(
                "SELECT m.created_at, m.file_name, IF(c.user1_id = ?, c.user2_id, c.user1_id) AS other
                 FROM messages m JOIN conversations c ON c.id = m.conversation_id WHERE m.sender_id = ?", [$playerId, $playerId])->getResultArray();
            foreach ($q as $m) {
                $add($m['created_at'], 'comunicacion', 'Mensaje enviado', 'A ' . $this->name((int) $m['other']) . ($m['file_name'] ? ' · con adjunto' : '') . ' (el texto es privado)');
            }
        });
        $safe('tickets', function () use ($playerId, $add) {
            foreach ($this->db->table('tickets')->where('user_id', $playerId)->get()->getResultArray() as $t) {
                $add($t['created_at'], 'comunicacion', 'Ticket creado', ($t['ticket_number'] ? $t['ticket_number'] . ' · ' : '') . $t['title'], null, null, 'tickets/' . (int) $t['id']);
            }
            foreach ($this->db->table('ticket_replies r')->select('r.created_at, t.id, t.ticket_number, t.title')
                         ->join('tickets t', 't.id = r.ticket_id')->where('r.user_id', $playerId)->where('r.is_internal', 0)->get()->getResultArray() as $r) {
                $add($r['created_at'], 'comunicacion', 'Respuesta en ticket', ($r['ticket_number'] ? $r['ticket_number'] . ' · ' : '') . $r['title'], null, null, 'tickets/' . (int) $r['id']);
            }
        });
        $safe('avisos', function () use ($playerId, $add) {
            foreach ($this->db->table('notifications')->where('sender_id', $playerId)->get()->getResultArray() as $n) {
                $add($n['created_at'], 'comunicacion', 'Aviso enviado', $n['title']);
            }
            foreach ($this->db->table('notification_recipients r')->select('n.created_at, n.title, r.read_at')
                         ->join('notifications n', 'n.id = r.notification_id')->where('r.recipient_id', $playerId)->get()->getResultArray() as $n) {
                $add($n['created_at'], 'comunicacion', 'Aviso recibido', $n['title'] . ($n['read_at'] ? ' · leído el ' . date('d/m/Y H:i', strtotime($n['read_at'])) : ' · sin leer'));
            }
        });
        $safe('emails', function () use ($playerId, $add) {
            foreach ($this->db->table('email_log')->where('recipient_id', $playerId)->get()->getResultArray() as $e) {
                $add($e['created_at'], 'comunicacion', 'Email recibido', $e['subject'] . ($e['status'] === 'failed' ? ' · NO se pudo enviar' : ''), null, $this->name((int) $e['sender_id']));
            }
        });

        // ── Seguimiento: documentos y anotaciones ───────────────────────
        $safe('documentos', function () use ($playerId, $add) {
            foreach ($this->db->table('documents')->where('uploader_id', $playerId)->get()->getResultArray() as $d) {
                $add($d['created_at'], 'seguimiento', 'Documento subido', $d['name_original'] . ($d['deleted_at'] ? ' · después eliminado' : ''));
            }
        });
        $safe('anotaciones', function () use ($playerId, $add) {
            foreach ($this->db->table('player_annotations')->where('player_id', $playerId)->get()->getResultArray() as $a) {
                $add($a['created_at'], 'seguimiento', 'Anotación ' . ($a['type'] === 'internal' ? 'interna' : 'pública'),
                    mb_substr(strip_tags((string) $a['content']), 0, 180), null, $this->name((int) $a['author_id']));
            }
        });

        // ── Auditoría: cambios que no salen ya en otras filas ───────────
        $safe('auditoria', function () use ($playerId, $add, $bonos, $bonoLabel, $csp) {
            $bonoIds = array_keys($bonos);
            $cspIds  = array_map(fn($c) => (int) $c['id'], $csp);
            $q = $this->db->table('audit_log a')->groupStart()
                ->groupStart()->where('a.entity_type', 'user')->where('a.entity_id', $playerId)->groupEnd();
            if ($bonoIds) {
                $q->orGroupStart()->where('a.entity_type', 'player_bono')->whereIn('a.entity_id', $bonoIds)->whereIn('a.action', [AuditService::UPDATE, AuditService::BLOCKED])->groupEnd();
            }
            if ($cspIds) {
                $q->orGroupStart()->where('a.entity_type', 'class_session_player')->whereIn('a.entity_id', $cspIds)->whereIn('a.action', [AuditService::BLOCKED, AuditService::DELETE])->groupEnd();
            }
            foreach ($q->groupEnd()->get()->getResultArray() as $a) {
                $who = $a['actor_id'] ? $this->name((int) $a['actor_id']) : self::SYSTEM;
                if ($a['action'] === AuditService::VIEW) {
                    $add($a['created_at'], 'auditoria', 'Consulta del historial', (string) $a['reason'], null, $who, null, ['noise' => true]);
                    continue;
                }
                $what = $a['entity_type'] === 'player_bono'
                    ? (isset($bonos[(int) $a['entity_id']]) ? $bonoLabel($bonos[(int) $a['entity_id']]) : 'Bono #' . $a['entity_id'])
                    : (['user' => 'Datos del alumno', 'class_session_player' => 'Inscripción en clase'][$a['entity_type']] ?? $a['entity_type']);
                $price = $a['entity_type'] === 'player_bono' && isset($bonos[(int) $a['entity_id']]['price_cents']) ? (int) $bonos[(int) $a['entity_id']]['price_cents'] : null;
                [$event, $changes] = self::describeAudit($a['action'], $a['before_json'], $a['after_json'], $price);
                $add($a['created_at'], 'auditoria', $event, $what . ($changes ? ' · ' . $changes : '') . ($a['reason'] ? ' · motivo: ' . $a['reason'] : ''), null, $who);
            }
        });

        // Saldo de dinero después de cada fila económica (orden cronológico)
        usort($rows, fn($a, $b) => strcmp($a['at'], $b['at']));
        $debt = 0;
        foreach ($rows as &$r) {
            if ($r['fd'] !== null) {
                $debt += $r['fd'];
                $r['saldo'] = self::balanceText($debt);
            }
            unset($r['fd']);
        }
        unset($r);
        if ($from || $to) {
            $rows = array_values(array_filter($rows, fn($r) => (!$from || substr($r['at'], 0, 10) >= $from) && (!$to || substr($r['at'], 0, 10) <= $to)));
        }
        $rows = array_reverse($rows);   // lo más reciente primero

        // ── Tarjetas «por bono» (la más reciente primero) ───────────────
        $cards = [];
        foreach (array_reverse($bonos, true) as $id => $b) {
            $l = $ledgers[$id];
            $charge = null;
            foreach ($charges as $c) {
                if ((int) $c['bono_id'] === $id && !$c['voided_at']) {
                    $charge = $c;
                }
            }
            $paid = 0;
            $methods = [];
            foreach ($charge ? ($allocByCharge[(int) $charge['id']] ?? []) : [] as $a) {
                $p = $payById[(int) $a['payment_id']] ?? null;
                if ($p && !$p['voided_at']) {
                    $paid += (int) $a['amount_cents'];
                    $methods[] = isset($bootPayments[(int) $p['id']]) ? 'dado por pagado al arrancar Finanzas (medio desconocido)' : ($p['method'] ?? 'sin medio');
                }
            }
            $status = !empty($b['voided_at']) ? 'Anulado'
                : (!empty($b['expires_at']) && $b['expires_at'] < $today ? 'Caducado' : ((int) $b['sessions_remaining'] <= 0 ? 'Agotado' : 'Vigente'));
            $cards[] = [
                'id' => $id, 'name' => $b['bono_name'] ?? 'Bono', 'label' => $bonoLabel($b), 'status' => $status,
                'total' => (int) $b['sessions_total'], 'real' => $l['real'], 'expected' => $l['expected'], 'diff' => $l['diff'],
                'price_cents' => isset($b['price_cents']) && $b['price_cents'] !== null ? (int) $b['price_cents'] : null,
                'price_estimated' => !empty($b['price_estimated']),
                'start_date' => $b['start_date'] ?? null, 'expires_at' => $b['expires_at'] ?? null,
                'created_at' => $b['created_at'], 'created_by' => $this->name((int) ($b['created_by'] ?? 0)),
                'charge_cents' => $charge ? (int) $charge['amount_cents'] : null, 'paid_cents' => $paid,
                'methods' => array_values(array_unique($methods)), 'events' => $l['events'],
                'planned' => array_values(array_filter($upcoming, fn($u) => $u['bono_id'] === $id)),
                'leftover' => $plan['leftover'][$id] ?? 0,
            ];
        }

        return ['rows' => $rows, 'bonos' => $cards, 'upcoming' => $upcoming, 'ledger_start' => $ledgerStart, 'debt' => $debt,
                'alerts' => self::buildAlerts($cards, $consumoBySession, $unsettled, $upcoming, $debt, $ledgerStart, $playerId)];
    }

    /**
     * Lo que hay que revisar, de lo más grave a lo informativo. Pura.
     * Niveles: danger (no cuadra), warn (hay que actuar), info (para saber).
     */
    public static function buildAlerts(array $cards, array $consumoBySession, array $unsettled, array $upcoming, int $debt, ?string $ledgerStart, int $playerId): array
    {
        $labels = array_column($cards, 'label', 'id');
        $alerts = [];
        foreach ($cards as $card) {
            if ($card['diff'] > 0) {
                $msg = 'Al ' . $card['label'] . ' le ' . ($card['diff'] === 1 ? 'falta 1 sesión' : 'faltan ' . $card['diff'] . ' sesiones') . ' por explicar: según los movimientos le '
                     . ($card['expected'] === 1 ? 'quedaría 1' : 'quedarían ' . $card['expected']) . ', pero tiene ' . $card['real'] . '.'
                     . ($ledgerStart ? ' Lo más probable es que se descontara antes del ' . self::d($ledgerStart) . ', cuando aún no se anotaba quién descontaba, o que se cambiara el saldo a mano.' : '');
                foreach (self::doubleDeductionSuspects($card, $consumoBySession) as $s) {
                    $msg .= ' Posible doble descuento: la clase del ' . self::d($s['date']) . ' se descontó del ' . ($labels[$s['bono_id']] ?? 'bono #' . $s['bono_id'])
                          . ' el ' . self::d($s['deducted_at']) . ', y quizá ya se había descontado de este bono el mismo día de la clase.';
                }
                $alerts[] = ['level' => 'danger', 'title' => 'El saldo no cuadra', 'text' => $msg, 'link' => 'bonos/' . $card['id']];
            } elseif ($card['diff'] < 0) {
                $alerts[] = ['level' => 'danger', 'title' => 'El saldo no cuadra', 'text' => 'El ' . $card['label'] . ' tiene ' . self::plural(-$card['diff'], 'sesión', 'sesiones')
                    . ' de más que ningún movimiento explica: según los movimientos le quedarían ' . $card['expected'] . ' y tiene ' . $card['real'] . '.', 'link' => 'bonos/' . $card['id']];
            }
            if ($card['status'] === 'Caducado' && $card['real'] > 0) {
                $alerts[] = ['level' => 'info', 'title' => 'Sesiones caducadas', 'text' => 'El ' . $card['label'] . ' caducó el ' . self::d($card['expires_at']) . ' con '
                    . self::plural($card['real'], 'sesión', 'sesiones') . ' sin usar.', 'link' => 'bonos/' . $card['id']];
            }
            if ($card['leftover'] > 0 && $card['status'] === 'Vigente') {
                $alerts[] = ['level' => 'info', 'title' => 'Le sobrarán sesiones', 'text' => 'Si no se programan más clases, le ' . ($card['leftover'] === 1 ? 'sobrará 1 sesión' : 'sobrarán ' . $card['leftover'] . ' sesiones')
                    . ' del ' . $card['label'] . ', que caduca el ' . self::d($card['expires_at']) . '.', 'link' => 'bonos/' . $card['id']];
            }
        }
        $dates = static function (array $list, string $key): string {
            $d = array_map(fn($x) => date('d/m/Y', strtotime($x[$key])), array_slice($list, 0, 6));
            return implode(', ', $d) . (count($list) > 6 ? ' y ' . (count($list) - 6) . ' más' : '');
        };
        if ($unsettled) {
            $alerts[] = ['level' => 'warn', 'title' => count($unsettled) === 1 ? 'Clase sin descontar' : 'Clases sin descontar',
                'text' => (count($unsettled) === 1 ? 'Asistió a 1 clase que no se descontó' : 'Asistió a ' . count($unsettled) . ' clases que no se descontaron')
                    . ' de ningún bono: ' . $dates($unsettled, 'date') . '.', 'link' => count($unsettled) === 1 ? $unsettled[0]['link'] : 'bonos/deudas'];
        }
        $uncovered = array_values(array_filter($upcoming, fn($u) => !$u['bono_id'] && !$u['declined']));
        if ($uncovered) {
            $alerts[] = ['level' => 'warn', 'title' => 'Clases sin saldo',
                'text' => (count($uncovered) === 1 ? 'Tiene 1 clase programada' : 'Tiene ' . count($uncovered) . ' clases programadas')
                    . ' sin saldo de bono que la' . (count($uncovered) === 1 ? '' : 's') . ' cubra: ' . $dates($uncovered, 'at') . '.', 'link' => $uncovered[0]['link']];
        }
        if ($debt > 0) {
            $alerts[] = ['level' => 'warn', 'title' => 'Pago pendiente', 'text' => 'Tiene ' . self::money($debt) . ' pendientes de pagar.', 'link' => 'finanzas/alumnos/' . $playerId];
        }
        $order = ['danger' => 0, 'warn' => 1, 'info' => 2];
        usort($alerts, fn($a, $b) => $order[$a['level']] <=> $order[$b['level']]);
        return $alerts;
    }

    /**
     * Clases descontadas de OTRO bono con retraso (≥1 día), en un día en que este
     * bono ya existía y era válido: candidatas a haberse descontado dos veces. Pura.
     */
    public static function doubleDeductionSuspects(array $card, array $consumoBySession): array
    {
        $out = [];
        foreach ($consumoBySession as $e) {
            if ($e['bono_id'] === $card['id'] || ($e['days_late'] ?? 0) < 1) {
                continue;
            }
            $date = substr($e['at'], 0, 10);
            if ($e['at'] >= $card['created_at'] && (!$card['start_date'] || $date >= $card['start_date']) && (!$card['expires_at'] || $date <= $card['expires_at'])) {
                $out[] = ['date' => $date, 'bono_id' => $e['bono_id'], 'deducted_at' => $e['deducted_at']];
            }
        }
        return $out;
    }

    /**
     * Saldo de un bono paso a paso. Pura.
     *
     * Une cada clase descontada de este bono con su movimiento «descontada» del
     * libro (quién y cuándo), deduce los descuentos anteriores al libro cuando
     * aparece una devolución sin descuento previo y calcula el saldo después de
     * cada paso. `diff` = saldo según movimientos − saldo real (>0: faltan
     * sesiones por explicar).
     *
     * @param array $bono     id, label, sessions_total, sessions_remaining, created_at, created_by, expires_at
     * @param array $consumos session_id, date, at, title, state, deducted_at — clases descontadas HOY de este bono
     * @param array $moves    id, type, delta, at, who, note, session_id, session_date, session_title — libro de este bono
     * @return array{events:array,expected:int,real:int,diff:int,absorbed:int[],has_void_move:bool}
     */
    public static function buildBonoLedger(array $bono, array $consumos, array $moves): array
    {
        usort($moves, fn($a, $b) => [$a['at'], $a['id']] <=> [$b['at'], $b['id']]);
        $used = $settled = [];
        foreach ($moves as $m) {
            if (in_array($m['type'], [BonoLedgerService::GRANTED, BonoLedgerService::ASSIGNED], true)) {
                $used[$m['id']] = true;     // la compra sale de la propia ficha del bono
            } elseif ($m['type'] === BonoLedgerService::DEBT_SETTLED) {
                $used[$m['id']] = true;     // se anota dentro de la sesión usada
                if ($m['session_id']) {
                    $settled[$m['session_id']] = true;
                }
            }
        }

        $events = [[
            'at' => $bono['created_at'], 'kind' => 'emitido', 'title' => 'Bono comprado', 'delta' => (int) $bono['sessions_total'],
            'text' => $bono['label'] . ' · ' . (int) $bono['sessions_total'] . ' sesiones' . (!empty($bono['expires_at']) ? ' · caduca el ' . self::d($bono['expires_at']) : ''),
            'who' => $bono['created_by'] ?? null, 'session_id' => null, 'move_id' => null, 'pin' => 0,
        ]];

        foreach ($consumos as $c) {
            $match = null;
            foreach ($moves as $m) {
                if (!isset($used[$m['id']]) && $m['type'] === BonoLedgerService::DEDUCTED && $m['session_id'] === (int) $c['session_id']) {
                    $match = $m;   // el último (orden cronológico) es el vigente
                }
            }
            if ($match) {
                $used[$match['id']] = true;
            }
            $deductedAt = $match['at'] ?? $c['deducted_at'];
            $who  = $match['who'] ?? null;
            $auto = $match && str_starts_with($match['note'], 'Automático');
            $days = $deductedAt ? (int) floor((strtotime(substr($deductedAt, 0, 10)) - strtotime($c['date'])) / 86400) : 0;
            $deductText = 'descontada del ' . $bono['label'] . ' el ' . self::d($deductedAt)
                . ($auto ? ' automáticamente (' . trim(mb_substr($match['note'], 11)) . ')' : ($who && $who !== self::SYSTEM ? ' por ' . $who : ' (no consta quién)'))
                . ($days >= 1 ? ' · ' . self::plural($days, 'día', 'días') . ' después de la clase' : '')
                . (isset($settled[(int) $c['session_id']]) ? ' · saldada a mano como clase sin bono' : '');
            $events[] = [
                'at' => $c['at'], 'kind' => 'consumo', 'title' => 'Sesión usada', 'delta' => -1,
                'text' => 'Clase del ' . self::d($c['date']) . ' (' . $c['title'] . ') · ' . $c['state'] . ' · ' . $deductText,
                'deduct_text' => $deductText, 'who' => $who, 'session_id' => (int) $c['session_id'], 'move_id' => $match['id'] ?? null,
                'deducted_at' => $deductedAt, 'days_late' => $days, 'pin' => 1,
            ];
        }

        // Devolución sin descuento previo en el libro → se había descontado antes de que existiera
        $balance = [];
        foreach ($moves as $m) {
            $sid = $m['session_id'];
            if (!$sid) {
                continue;
            }
            if ($m['type'] === BonoLedgerService::DEDUCTED) {
                $balance[$sid] = ($balance[$sid] ?? 0) + 1;
            } elseif ($m['type'] === BonoLedgerService::REFUNDED && $m['delta'] > 0) {
                if (($balance[$sid] ?? 0) > 0) {
                    $balance[$sid]--;
                    continue;
                }
                $events[] = [
                    'at' => ($m['session_date'] ?: substr($m['at'], 0, 10)) . ' 00:00:00', 'kind' => 'consumo', 'inferred' => true, 'title' => 'Sesión usada',
                    'delta' => -1, 'text' => 'Clase del ' . self::d($m['session_date']) . ($m['session_title'] ? ' (' . $m['session_title'] . ')' : '')
                        . ' · se había descontado antes de que existiera el registro de movimientos',
                    'who' => null, 'session_id' => $sid, 'move_id' => null, 'pin' => 1,
                ];
            }
        }

        $hasVoid = false;
        foreach ($moves as $m) {
            if (isset($used[$m['id']])) {
                continue;
            }
            $clase = $m['session_id'] ? 'Clase del ' . self::d($m['session_date']) . ($m['session_title'] ? ' (' . $m['session_title'] . ')' : '') : '';
            switch ($m['type']) {
                case BonoLedgerService::DEDUCTED:
                    [$title, $text] = ['Sesión usada (luego devuelta)', trim($clase . ' · se descontó y más tarde se devolvió o se pasó a otro bono', ' ·')];
                    break;
                case BonoLedgerService::REFUNDED:
                    [$title, $text] = ['Sesión devuelta', trim($clase . ($m['note'] === 'Cambio de bono' ? ' · pasada a otro bono' : ($m['note'] ? ' · ' . $m['note'] : '')), ' ·')];
                    break;
                case BonoLedgerService::ADJUSTED:
                    [$title, $text] = ['Saldo cambiado a mano', $m['note']];
                    break;
                case BonoLedgerService::VOIDED:
                    $hasVoid = true;
                    [$title, $text] = ['Bono anulado', 'Se anulan las sesiones que quedaban' . ($m['note'] ? ' · ' . $m['note'] : '')];
                    break;
                case BonoLedgerService::EXTENDED:
                    [$title, $text] = ['Caducidad ampliada', $m['note']];
                    break;
                case BonoLedgerService::EXPIRY_ALERT:
                    $when = preg_match('/caduca el (\S+)/', $m['note'], $mm) ? $mm[1] : self::d($bono['expires_at'] ?? null);
                    [$title, $text] = ['Aviso de caducidad', 'Se avisó al alumno y a la academia de que el bono caduca el ' . $when];
                    break;
                default:
                    [$title] = BonoLedgerService::label($m['type']);
                    $text = trim($clase . ($m['note'] ? ' · ' . $m['note'] : ''), ' ·');
            }
            $used[$m['id']] = true;
            $events[] = ['at' => $m['at'], 'kind' => $m['type'], 'title' => $title, 'delta' => $m['delta'], 'text' => (string) $text,
                         'who' => $m['who'], 'session_id' => $m['session_id'], 'move_id' => $m['id'], 'pin' => 1];
        }

        usort($events, fn($a, $b) => [$a['pin'], $a['at'], $a['move_id'] ?? 0] <=> [$b['pin'], $b['at'], $b['move_id'] ?? 0]);
        $saldo = 0;
        foreach ($events as &$e) {
            $saldo += $e['delta'];
            $e['saldo'] = $saldo;
        }
        unset($e);

        $real = (int) $bono['sessions_remaining'];
        return ['events' => $events, 'expected' => $saldo, 'real' => $real, 'diff' => $saldo - $real,
                'absorbed' => array_keys($used), 'has_void_move' => $hasVoid];
    }

    /**
     * Reparte las próximas clases entre los bonos usables (primero el que caduca
     * antes, como el descuento automático). Pura.
     *
     * @param array $bonos    id, remaining, start_date, expires_at
     * @param array $upcoming session_id, date — en orden cronológico
     * @return array{assign:array<int,?int>,leftover:array<int,int>}
     */
    public static function planUpcoming(array $bonos, array $upcoming): array
    {
        usort($bonos, fn($a, $b) => [$a['expires_at'] ?: '9999-12-31', $a['id']] <=> [$b['expires_at'] ?: '9999-12-31', $b['id']]);
        $left = [];
        foreach ($bonos as $b) {
            $left[(int) $b['id']] = (int) $b['remaining'];
        }
        $assign = [];
        foreach ($upcoming as $u) {
            $assign[(int) $u['session_id']] = null;
            foreach ($bonos as $b) {
                $id = (int) $b['id'];
                if ($left[$id] > 0 && (!$b['start_date'] || $u['date'] >= $b['start_date']) && (!$b['expires_at'] || $u['date'] <= $b['expires_at'])) {
                    $assign[(int) $u['session_id']] = $id;
                    $left[$id]--;
                    break;
                }
            }
        }
        return ['assign' => $assign, 'leftover' => $left];
    }

    /**
     * Título y texto claros para una fila de auditoría. Pura.
     * @return array{0:string,1:string}
     */
    public static function describeAudit(string $action, ?string $before, ?string $after, ?int $currentPrice = null): array
    {
        $changes = self::describeChange($before, $after);
        if ($changes === 'precio confirmado') {
            $a = json_decode((string) $after, true) ?: [];
            $b = json_decode((string) $before, true) ?: [];
            $price = $a['price_cents'] ?? $b['price_cents'] ?? $currentPrice;
            return ['Precio confirmado', $price !== null ? 'se confirma que pagó ' . self::money((int) $price) : 'el precio deja de ser una estimación'];
        }
        $titles = [AuditService::UPDATE => 'Cambio', AuditService::BLOCKED => 'Intento bloqueado', AuditService::DELETE => 'Eliminado',
                   AuditService::CREATE => 'Creado', AuditService::VOID => 'Anulado', AuditService::ARCHIVE => 'Archivado', AuditService::RESTORE => 'Restaurado'];
        return [$titles[$action] ?? AuditService::label($action), $changes];
    }

    /** «saldo de sesiones 5 → 4; caducidad 14/10/2026 → 21/10/2026…» a partir del antes/después de la auditoría. Pura. */
    public static function describeChange(?string $before, ?string $after): string
    {
        $b = json_decode((string) $before, true) ?: [];
        $a = json_decode((string) $after, true) ?: [];
        $parts = [];
        foreach ($a as $k => $v) {
            if (!is_scalar($v ?? '')) {
                continue;
            }
            $old = $b[$k] ?? null;
            switch ($k) {
                case 'sessions_remaining':
                    $parts[] = 'saldo de sesiones ' . ($old ?? '—') . ' → ' . $v;
                    break;
                case 'expires_at':
                    $parts[] = 'caducidad ' . ($old ? self::d($old) : '—') . ' → ' . ($v ? self::d($v) : '—');
                    break;
                case 'price_cents':
                    if ((string) $old !== (string) $v) {
                        $parts[] = 'precio pagado ' . ($old === null || $old === '' ? '—' : self::money((int) $old)) . ' → ' . ($v === null || $v === '' ? '—' : self::money((int) $v));
                    }
                    break;
                case 'price_estimated':
                    if ((int) $old === 1 && (int) $v === 0) {
                        $parts[] = 'precio confirmado';
                    } elseif ((int) $v === 1 && (int) $old !== 1) {
                        $parts[] = 'precio marcado como estimado';
                    }
                    break;
                case 'status':
                    $parts[] = 'estado ' . ($old ?? '—') . ' → ' . $v;
                    break;
                case 'notes':
                    $parts[] = 'notas cambiadas';
                    break;
                case 'voided_at':
                    $parts[] = 'anulado';
                    break;
            }
        }
        return implode('; ', $parts);
    }

    /** Filas para CSV (Excel): cabecera + datos. Pura. */
    public static function toCsvRows(array $rows): array
    {
        $out = [['Fecha', 'Hora', 'Categoría', 'Evento', 'Detalle', 'Importe (€)', 'Saldo después', 'Hecho por']];
        foreach ($rows as $r) {
            $out[] = [
                date('d/m/Y', strtotime($r['at'])), date('H:i', strtotime($r['at'])),
                self::CATEGORIES[$r['cat']] ?? $r['cat'], $r['event'], $r['detail'],
                $r['cents'] === null ? '' : number_format($r['cents'] / 100, 2, ',', ''),
                str_replace("\u{00A0}", ' ', (string) ($r['saldo'] ?? '')), $r['who'] ?? '',
            ];
        }
        return $out;
    }
}
