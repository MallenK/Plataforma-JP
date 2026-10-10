<?php

namespace App\Services;

/**
 * Historial completo de un alumno: TODO lo que ha pasado con él o ha hecho él
 * en la plataforma, en una sola línea de tiempo (Finanzas › Alumnos › Historial).
 *
 * Fuentes: cargos y cobros (+ anulaciones), bonos (alta, anulación y su libro de
 * movimientos), clases (asistencia, avisos y respuestas del alumno), accesos,
 * mensajes (solo el registro, nunca el texto: son privados), tickets, avisos
 * enviados y recibidos, emails, documentos, anotaciones y auditoría.
 *
 * Cada fila: at, cat, event, detail, cents (null o con signo), who, link.
 * Cada fuente va en su propio try: si una tabla no existe en un entorno, el
 * resto del historial se sigue mostrando. `toCsvRows()` es pura.
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

    private const ATT = [
        'present' => 'Presente', 'absent' => 'Ausencia justificada', 'unjustified' => 'Falta sin justificar',
        'declined' => 'Avisó ausencia', 'confirmed' => 'Confirmó asistencia', 'pending' => 'Pendiente',
    ];

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

    private static function money(int $cents): string
    {
        return number_format($cents / 100, 2, ',', '.') . ' €';
    }

    /**
     * @return array<int,array{at:string,cat:string,event:string,detail:string,cents:?int,who:?string,link:?string}>
     */
    public function timeline(int $playerId, ?string $from = null, ?string $to = null): array
    {
        $rows = [];
        $add  = static function (?string $at, string $cat, string $event, string $detail, ?int $cents = null, ?string $who = null, ?string $link = null) use (&$rows) {
            if (!$at) {
                return;
            }
            $rows[] = ['at' => $at, 'cat' => $cat, 'event' => $event, 'detail' => trim($detail), 'cents' => $cents, 'who' => $who, 'link' => $link];
        };
        $safe = function (string $source, callable $fn) {
            try {
                $fn();
            } catch (\Throwable $e) {
                log_message('error', "StudentHistory[{$source}]: " . $e->getMessage());
            }
        };

        $user = $this->db->table('users')->select('id, email, created_at, welcomed_at, password_changed_at')->where('id', $playerId)->get()->getRowArray();
        if (!$user) {
            return [];
        }

        // ── Cuenta ──────────────────────────────────────────────────────
        $safe('cuenta', function () use ($user, $add) {
            $add($user['created_at'], 'acceso', 'Alta en la plataforma', 'Se creó su usuario');
            $add($user['welcomed_at'] ?? null, 'acceso', 'Primer acceso', 'Vio la bienvenida de la plataforma');
        });

        // ── Económico: cargos y cobros ──────────────────────────────────
        $safe('cargos', function () use ($playerId, $add) {
            foreach ($this->db->table('fin_charges')->where('player_id', $playerId)->get()->getResultArray() as $c) {
                $d = $c['concept'] . ($c['discount_cents'] > 0 ? ' · descuento ' . self::money((int) $c['discount_cents']) . ' (' . ($c['discount_reason'] ?: '—') . ')' : '')
                   . ($c['note'] ? ' · ' . $c['note'] : '');
                $add(self::when($c['charged_at'], $c['created_at']), 'economico', 'Cargo', $d, (int) $c['amount_cents'], $this->name((int) $c['created_by']), 'finanzas/alumnos/' . $playerId);
                if ($c['voided_at']) {
                    $add($c['voided_at'], 'economico', 'Cargo anulado', $c['concept'] . ' · motivo: ' . $c['void_reason'], -(int) $c['amount_cents'], $this->name((int) $c['voided_by']));
                }
            }
        });
        $safe('cobros', function () use ($playerId, $add) {
            foreach ($this->db->table('fin_payments p')->select('p.*, m.name AS method')
                         ->join('fin_payment_methods m', 'm.id = p.method_id', 'left')
                         ->where('p.player_id', $playerId)->get()->getResultArray() as $p) {
                $d = ($p['method'] ?? 'Sin medio') . ' · fecha de pago ' . date('d/m/Y', strtotime($p['paid_at']))
                   . ($p['reference'] ? ' · ref. ' . $p['reference'] : '') . ($p['note'] ? ' · ' . $p['note'] : '');
                $add(self::when($p['paid_at'], $p['created_at']), 'economico', 'Cobro', $d, (int) $p['amount_cents'], $this->name((int) $p['created_by']), 'finanzas/alumnos/' . $playerId);
                if ($p['voided_at']) {
                    $add($p['voided_at'], 'economico', 'Cobro anulado', ($p['method'] ?? 'Sin medio') . ' · motivo: ' . $p['void_reason'], -(int) $p['amount_cents'], $this->name((int) $p['voided_by']));
                }
            }
        });

        // ── Bonos: alta, anulación y libro de movimientos ───────────────
        $safe('bonos', function () use ($playerId, $add) {
            foreach ($this->db->table('player_bonos pb')->select('pb.*, bt.name AS bono_name')
                         ->join('bono_types bt', 'bt.id = pb.bono_type_id', 'left')
                         ->where('pb.player_id', $playerId)->get()->getResultArray() as $b) {
                $d = ($b['bono_name'] ?? 'Bono') . ' · ' . (int) $b['sessions_total'] . ' sesiones · válido ' . date('d/m/Y', strtotime($b['start_date']))
                   . ($b['expires_at'] ? '–' . date('d/m/Y', strtotime($b['expires_at'])) : '')
                   . (isset($b['price_cents']) && $b['price_cents'] !== null ? ' · ' . self::money((int) $b['price_cents']) : '');
                $add($b['created_at'], 'bonos', 'Bono emitido', $d, null, $this->name((int) $b['created_by']), 'bonos/' . (int) $b['id']);
                if (!empty($b['voided_at'])) {
                    $add($b['voided_at'], 'bonos', 'Bono anulado', ($b['bono_name'] ?? 'Bono') . ' · motivo: ' . $b['void_reason'], null, $this->name((int) $b['voided_by']), 'bonos/' . (int) $b['id']);
                }
            }
        });
        $safe('libro', function () use ($playerId, $add) {
            $q = $this->db->table('bono_movements bm')
                ->select('bm.*, cs.title AS session_title, cs.session_date, bt.name AS bono_name')
                ->join('class_sessions cs', 'cs.id = bm.session_id', 'left')
                ->join('player_bonos pb', 'pb.id = bm.bono_id', 'left')
                ->join('bono_types bt', 'bt.id = pb.bono_type_id', 'left')
                ->where('bm.player_id', $playerId)
                ->whereNotIn('bm.type', [BonoLedgerService::GRANTED, BonoLedgerService::VOIDED])   // ya salen como «Bono emitido/anulado»
                ->get()->getResultArray();
            foreach ($q as $m) {
                [$label] = BonoLedgerService::label($m['type']);
                $d = ($m['bono_name'] ? $m['bono_name'] . ' #' . (int) $m['bono_id'] : 'Sin bono')
                   . ((int) $m['delta'] !== 0 ? ' · ' . ((int) $m['delta'] > 0 ? '+' : '') . (int) $m['delta'] . ' ses.' : '')
                   . ($m['session_title'] ? ' · ' . $m['session_title'] . ' (' . date('d/m/Y', strtotime($m['session_date'])) . ')' : '')
                   . ($m['note'] ? ' · ' . $m['note'] : '');
                $add($m['created_at'], 'bonos', $label, $d, null, $m['actor_id'] ? $this->name((int) $m['actor_id']) : 'Sistema',
                    $m['session_id'] ? 'clases/' . (int) $m['session_id'] : ($m['bono_id'] ? 'bonos/' . (int) $m['bono_id'] : null));
            }
        });

        // ── Clases: asistencia, avisos y respuestas ─────────────────────
        $safe('clases', function () use ($playerId, $add) {
            $q = $this->db->table('class_session_players csp')
                ->select('csp.*, cs.title, cs.session_date, cs.start_time, cs.status, cs.lista_pasada_at')
                ->join('class_sessions cs', 'cs.id = csp.session_id')
                ->where('csp.user_id', $playerId)->get()->getResultArray();
            foreach ($q as $c) {
                $at = $c['session_date'] . ' ' . ($c['start_time'] ?: '00:00:00');
                $state = $c['status'] === 'cancelled' ? 'Clase cancelada'
                    : ($c['status'] === 'scheduled' ? ($c['session_date'] < date('Y-m-d') ? 'Sesión sin cerrar' : 'Programada') : (self::ATT[$c['attendance']] ?? $c['attendance']));
                $bono = $c['bono_deducted_at'] ? ' · sesión descontada del bono' : ($c['bono_resolution'] ? ' · ' . BonoControlService::resolutionLabel($c['bono_resolution']) : '');
                $reason = $c['absence_reason'] ? ' · motivo: ' . $c['absence_reason'] : '';
                $add($at, 'clases', 'Clase', $c['title'] . ' · ' . $state . $bono . $reason, null, null, 'clases/' . (int) $c['session_id']);
                if (!empty($c['student_noted_at'])) {
                    $add($c['student_noted_at'], 'clases', 'Aviso del alumno', $c['title'] . ' (' . date('d/m/Y', strtotime($c['session_date'])) . ')'
                        . ($c['student_note'] ? ' · «' . mb_substr($c['student_note'], 0, 160) . '»' : ''), null, null, 'clases/' . (int) $c['session_id']);
                }
                if (!empty($c['responded_at'])) {
                    $add($c['responded_at'], 'clases', 'Respuesta del alumno', $c['title'] . ' (' . date('d/m/Y', strtotime($c['session_date'])) . ') · '
                        . (self::ATT[$c['attendance']] ?? $c['attendance']), null, null, 'clases/' . (int) $c['session_id']);
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
        $safe('auditoria', function () use ($playerId, $add) {
            $bonoIds = array_map('intval', array_column($this->db->table('player_bonos')->select('id')->where('player_id', $playerId)->get()->getResultArray(), 'id'));
            $cspIds  = array_map('intval', array_column($this->db->table('class_session_players')->select('id')->where('user_id', $playerId)->get()->getResultArray(), 'id'));
            $q = $this->db->table('audit_log a')->groupStart()
                ->groupStart()->where('a.entity_type', 'user')->where('a.entity_id', $playerId)->groupEnd();
            if ($bonoIds) {
                $q->orGroupStart()->where('a.entity_type', 'player_bono')->whereIn('a.entity_id', $bonoIds)->whereIn('a.action', [AuditService::UPDATE, AuditService::BLOCKED])->groupEnd();
            }
            if ($cspIds) {
                $q->orGroupStart()->where('a.entity_type', 'class_session_player')->whereIn('a.entity_id', $cspIds)->whereIn('a.action', [AuditService::BLOCKED, AuditService::DELETE])->groupEnd();
            }
            foreach ($q->groupEnd()->get()->getResultArray() as $a) {
                if ($a['action'] === AuditService::VIEW) {
                    $add($a['created_at'], 'auditoria', 'Consulta del historial', (string) $a['reason'], null, $a['actor_id'] ? $this->name((int) $a['actor_id']) : 'Sistema');
                    continue;
                }
                $what = ['user' => 'Usuario', 'player_bono' => 'Bono #' . $a['entity_id'], 'class_session_player' => 'Inscripción en clase'][$a['entity_type']] ?? $a['entity_type'];
                $changes = self::describeChange($a['before_json'], $a['after_json']);
                $add($a['created_at'], 'auditoria', AuditService::label($a['action']) . ' · ' . $what,
                    trim($changes . ($a['reason'] ? ' · motivo: ' . $a['reason'] : ''), ' ·'), null, $a['actor_id'] ? $this->name((int) $a['actor_id']) : 'Sistema');
            }
        });

        // Filtro de fechas y orden (lo más reciente primero)
        if ($from || $to) {
            $rows = array_values(array_filter($rows, fn($r) => (!$from || substr($r['at'], 0, 10) >= $from) && (!$to || substr($r['at'], 0, 10) <= $to)));
        }
        usort($rows, fn($a, $b) => strcmp($b['at'], $a['at']));
        return $rows;
    }

    /** «sesiones 5 → 4; caducidad …» a partir del antes/después de la auditoría. Pura. */
    public static function describeChange(?string $before, ?string $after): string
    {
        $b = json_decode((string) $before, true) ?: [];
        $a = json_decode((string) $after, true) ?: [];
        $names = ['sessions_remaining' => 'sesiones', 'expires_at' => 'caducidad', 'price_cents' => 'precio', 'price_estimated' => 'precio estimado',
                  'status' => 'estado', 'notes' => 'notas', 'voided_at' => 'anulado'];
        $parts = [];
        foreach ($a as $k => $v) {
            if (!isset($names[$k]) || !is_scalar($v ?? '')) {
                continue;
            }
            $fmt = static fn($x) => $x === null || $x === '' ? '—' : ($k === 'price_cents' ? number_format(((int) $x) / 100, 2, ',', '.') . ' €' : (string) $x);
            $parts[] = $names[$k] . ' ' . $fmt($b[$k] ?? null) . ' → ' . $fmt($v);
        }
        return implode('; ', $parts);
    }

    /** Filas para CSV (Excel): cabecera + datos. Pura. */
    public static function toCsvRows(array $rows): array
    {
        $out = [['Fecha', 'Hora', 'Categoría', 'Evento', 'Detalle', 'Importe (€)', 'Hecho por']];
        foreach ($rows as $r) {
            $out[] = [
                date('d/m/Y', strtotime($r['at'])), date('H:i', strtotime($r['at'])),
                self::CATEGORIES[$r['cat']] ?? $r['cat'], $r['event'], $r['detail'],
                $r['cents'] === null ? '' : number_format($r['cents'] / 100, 2, ',', ''), $r['who'] ?? '',
            ];
        }
        return $out;
    }
}
