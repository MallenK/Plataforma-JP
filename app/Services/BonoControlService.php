<?php

namespace App\Services;

use App\Models\NotificationModel;
use App\Models\PlayerBonoModel;
use App\Models\UserModel;

/**
 * TICKET-013 — Control de bonos: deudas de sesión, ampliaciones y avisos.
 *
 * DEUDA = sesión ya dada (cerrada, asistencia que consume bono) a la que NO
 * se ha descontado bono y que nadie ha resuelto, posterior al punto de
 * control (`bono_control_since`). Es DERIVADA: no hay tabla de deudas, se
 * calcula sobre class_session_players. Las anteriores al punto de control no
 * son deuda: se informan aparte como "no reflejadas" y nunca se tocan.
 */
class BonoControlService
{
    public const RESOLUTION_EXTERNAL = 'external'; // pagada fuera de bono
    public const RESOLUTION_WAIVED   = 'waived';   // condonada
    public const RESOLUTION_ACCEPTED = 'accepted'; // dada por buena en el cierre de revisión inicial (v1.33.0)

    /** Etiqueta de una resolución de clase sin bono. */
    public static function resolutionLabel(?string $resolution): string
    {
        return match ($resolution) {
            self::RESOLUTION_EXTERNAL => 'Pagada fuera de bono',
            self::RESOLUTION_WAIVED   => 'Condonada',
            self::RESOLUTION_ACCEPTED => 'Dada por buena (cierre inicial)',
            default                   => (string) $resolution,
        };
    }

    /** Ampliaciones rápidas (días). Además existe la fecha personalizada. */
    public const EXTEND_PRESETS = [15, 30, 60];

    private \CodeIgniter\Database\BaseConnection $db;

    public function __construct(?\CodeIgniter\Database\BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    // ────────────────────────────────────────────────────────────────
    //  Deudas
    // ────────────────────────────────────────────────────────────────

    private function debtQuery(?int $playerId = null)
    {
        $since = (new BonoCoverageService($this->db))->controlSince();

        $q = $this->db->table('class_session_players csp')
            ->select('csp.id AS csp_id, csp.user_id, csp.attendance, cs.id AS session_id, cs.title, cs.session_date, cs.start_time, u.name AS player_name')
            ->join('class_sessions cs', 'cs.id = csp.session_id')
            ->join('users u', 'u.id = csp.user_id')
            ->where('cs.status', 'completed')
            ->whereIn('csp.attendance', ClasesService::BONO_CONSUMING_ATTENDANCE)
            ->where('csp.bono_deducted_at IS NULL', null, false)
            ->where('csp.bono_resolution IS NULL', null, false)
            ->orderBy('cs.session_date', 'ASC')->orderBy('cs.start_time', 'ASC');
        if ($playerId) {
            $q->where('csp.user_id', $playerId);
        }
        return [$q, $since];
    }

    /**
     * Deudas abiertas (posteriores al punto de control), de más antigua a más reciente.
     *
     * @return array<int,array>
     */
    public function openDebts(?int $playerId = null): array
    {
        [$q, $since] = $this->debtQuery($playerId);
        if (!$since) {
            return [];
        }
        return $q->where('cs.session_date >=', $since)->get()->getResultArray();
    }

    /**
     * "No reflejadas": sesiones dadas ANTES del punto de control, con asistencia
     * que consume bono y sin descuento. Solo informativo.
     *
     * @return array<int,array>
     */
    public function unreflected(?int $playerId = null): array
    {
        [$q, $since] = $this->debtQuery($playerId);
        if ($since) {
            $q->where('cs.session_date <', $since);
        }
        return $q->get()->getResultArray();
    }

    /**
     * Salda con UN bono concreto las deudas abiertas del alumno, de la más
     * antigua a la más reciente, hasta que se acabe el saldo de ese bono.
     *
     * SIEMPRE manual: lo dispara un admin tras confirmar ("Saldar con este
     * bono"); nunca ocurre solo al emitir o asignar un bono. Avisa a los admins.
     *
     * @param int|null $limit máximo de clases a saldar (null = todas las que quepan)
     * @return array{settled:int,remaining_debts:int,bono_id:int}
     */
    public function settleWithBono(int $playerId, ?int $actorId, int $bonoId, ?int $limit = null): array
    {
        $settled = 0;
        $names   = [];

        try {
            $svc = new ClasesService();
            foreach ($this->openDebts($playerId) as $debt) {
                if ($limit !== null && $settled >= $limit) {
                    break;
                }
                $res = $svc->deductBonoForPlayer((int) $debt['session_id'], $playerId, null, $bonoId);
                if (empty($res['success'])) {
                    break; // sin saldo (u otro motivo): se para, no se fuerza
                }
                $settled++;
                $names[] = date('d/m', strtotime($debt['session_date']));
                BonoLedgerService::log($playerId, BonoLedgerService::DEBT_SETTLED, 0, $bonoId, (int) $debt['session_id'],
                    'Saldada a mano con el bono elegido', $actorId);
            }
        } catch (\Throwable $e) {
            log_message('error', 'BonoControlService::settleWithBono falló: ' . $e->getMessage());
        }

        $remaining = count($this->openDebts($playerId));

        if ($settled > 0) {
            $bonoName = $this->bonoName($bonoId);
            $this->notifyAdmins(
                $playerId,
                '🎟️ Clases sin bono saldadas con un bono',
                sprintf('%s: se han saldado %d clase(s) dadas sin bono (%s) con el bono "%s", 1 sesión por cada una.%s Si algo no cuadra, puedes devolver la sesión desde la propia clase.',
                    $this->playerName($playerId), $settled, implode(', ', $names), $bonoName,
                    $remaining > 0 ? " Quedan {$remaining} clase(s) sin saldar." : ''),
                $actorId
            );
        }

        // El saldo ha cambiado: las marcas de cobertura de sus clases futuras también.
        (new BonoCoverageService($this->db))->refreshMarks($playerId);

        return ['settled' => $settled, 'remaining_debts' => $remaining, 'bono_id' => $bonoId];
    }

    /**
     * Resuelve una deuda sin bono: 'external' (pagada fuera) o 'waived' (condonada).
     * Queda en la fila y en el libro; nunca se borra nada.
     *
     * @return array{success:bool,error?:string}
     */
    public function resolveDebt(int $cspId, string $resolution, int $actorId, ?string $note = null): array
    {
        if (!in_array($resolution, [self::RESOLUTION_EXTERNAL, self::RESOLUTION_WAIVED], true)) {
            return ['success' => false, 'error' => 'Resolución no válida.'];
        }

        $row = $this->db->table('class_session_players')->where('id', $cspId)->get()->getRowArray();
        if (!$row) {
            return ['success' => false, 'error' => 'La sesión del alumno no existe.'];
        }
        if (!empty($row['bono_deducted_at']) || !empty($row['bono_resolution'])) {
            return ['success' => false, 'error' => 'Esta deuda ya está resuelta.'];
        }

        $this->db->table('class_session_players')->where('id', $cspId)->where('bono_resolution IS NULL', null, false)->update([
            'bono_resolution'  => $resolution,
            'bono_resolved_at' => date('Y-m-d H:i:s'),
            'bono_resolved_by' => $actorId,
        ]);
        if ($this->db->affectedRows() < 1) {
            return ['success' => false, 'error' => 'Esta deuda ya está resuelta.'];
        }

        BonoLedgerService::log((int) $row['user_id'], BonoLedgerService::DEBT_RESOLVED, 0, null, (int) $row['session_id'],
            ($resolution === self::RESOLUTION_EXTERNAL ? 'Pagada fuera de bono' : 'Condonada') . ($note ? ': ' . $note : ''), $actorId);

        return ['success' => true];
    }

    // ────────────────────────────────────────────────────────────────
    //  Ampliación de caducidad
    // ────────────────────────────────────────────────────────────────

    /**
     * Calcula la nueva fecha de caducidad (pura). La base es la mayor entre la
     * caducidad actual y hoy, de modo que ampliar un bono ya caducado cuenta
     * desde hoy y no "regala" días ya pasados.
     *
     * @param string|int $mode 15|30|60 (días) o 'custom'
     * @return array{ok:bool,date?:string,error?:string}
     */
    public static function computeExtension(?string $currentExpiry, $mode, ?string $customDate, string $today): array
    {
        if (empty($currentExpiry)) {
            return ['ok' => false, 'error' => 'Este bono no tiene fecha de caducidad.'];
        }

        if ($mode === 'custom') {
            $ts = $customDate ? strtotime($customDate) : false;
            if ($ts === false) {
                return ['ok' => false, 'error' => 'Indica una fecha válida.'];
            }
            $date = date('Y-m-d', $ts);
            if ($date <= $currentExpiry) {
                return ['ok' => false, 'error' => 'La nueva fecha debe ser posterior a la caducidad actual (' . date('d/m/Y', strtotime($currentExpiry)) . ').'];
            }
            if ($date < $today) {
                return ['ok' => false, 'error' => 'La nueva fecha no puede estar en el pasado.'];
            }
            if ($date > date('Y-m-d', strtotime($today . ' +2 years'))) {
                return ['ok' => false, 'error' => 'La nueva fecha no puede superar los 2 años desde hoy.'];
            }
            return ['ok' => true, 'date' => $date];
        }

        $days = (int) $mode;
        if (!in_array($days, self::EXTEND_PRESETS, true)) {
            return ['ok' => false, 'error' => 'Ampliación no válida.'];
        }
        $base = max($currentExpiry, $today);
        return ['ok' => true, 'date' => date('Y-m-d', strtotime($base . ' +' . $days . ' days'))];
    }

    /**
     * Amplía la caducidad de un bono (15/30/60 días o fecha personalizada),
     * lo anota en el libro, avisa al alumno y recalcula la cobertura.
     *
     * @return array{success:bool,date?:string,error?:string}
     */
    public function extendBono(int $bonoId, $mode, ?string $customDate, int $actorId): array
    {
        $bonoModel = new PlayerBonoModel();
        $bono      = $bonoModel->find($bonoId);
        if (!$bono) {
            return ['success' => false, 'error' => 'Bono no encontrado.'];
        }

        if (!empty($bono['voided_at'])) {
            return ['success' => false, 'error' => 'Este bono está anulado y no se puede ampliar.'];
        }

        $calc = self::computeExtension($bono['expires_at'] ?? null, $mode, $customDate, date('Y-m-d'));
        if (!$calc['ok']) {
            return ['success' => false, 'error' => $calc['error']];
        }

        $old = $bono['expires_at'];
        $bonoModel->update($bonoId, ['expires_at' => $calc['date']]);

        $playerId = (int) ($bono['player_id'] ?? 0);
        if ($playerId > 0) {
            BonoLedgerService::log($playerId, BonoLedgerService::EXTENDED, 0, $bonoId, null,
                date('d/m/Y', strtotime($old)) . ' → ' . date('d/m/Y', strtotime($calc['date'])), $actorId);

            try {
                (new NotificationModel())->createWithRecipients([
                    'sender_id' => $actorId,
                    'type'      => 'individual',
                    'title'     => '🎟️ Tu bono se ha ampliado',
                    'body'      => 'La caducidad de tu bono se ha ampliado hasta el ' . date('d/m/Y', strtotime($calc['date'])) . '.',
                    'category'  => \App\Models\NotificationPreferenceModel::CAT_BONOS,
                ], [$playerId]);
            } catch (\Throwable $e) {
                log_message('error', 'extendBono: notificación falló: ' . $e->getMessage());
            }

            (new BonoCoverageService($this->db))->refreshMarks($playerId);
        }

        return ['success' => true, 'date' => $calc['date']];
    }

    // ────────────────────────────────────────────────────────────────
    //  «Nada se borra» (v1.33.0): precio congelado, editar con motivo, anular
    // ────────────────────────────────────────────────────────────────

    /** Longitud mínima del motivo de una corrección o anulación. */
    public const MIN_REASON = 3;

    /**
     * Precio congelado al vender un bono (céntimos). Cambiar después la tarifa
     * del tipo no reescribe lo que costó este bono.
     *
     * @return array{price_list_cents:int,price_cents:int,price_estimated:int}
     */
    public static function priceSnapshot(array $type): array
    {
        $cents = (int) round(((float) ($type['price'] ?? 0)) * 100);
        return ['price_list_cents' => $cents, 'price_cents' => $cents, 'price_estimated' => 0];
    }

    /** ¿El motivo es suficiente? (no vacío, mínimo MIN_REASON caracteres) */
    public static function validReason(?string $reason): bool
    {
        return mb_strlen(trim((string) $reason)) >= self::MIN_REASON;
    }

    /**
     * Normaliza la edición de un bono a partir del formulario. Pura.
     * Cambiar el saldo o la caducidad exige motivo; las notas no.
     *
     * @param array $input claves opcionales: notes, sessions_remaining, expires_at
     * @return array{ok:bool,data?:array,needsReason?:bool,error?:string}
     */
    public static function buildBonoEdit(array $bono, array $input): array
    {
        if (!empty($bono['voided_at'])) {
            return ['ok' => false, 'error' => 'Este bono está anulado y ya no se puede editar.'];
        }
        $data = [];
        if (array_key_exists('notes', $input) && $input['notes'] !== null) {
            $data['notes'] = trim((string) $input['notes']) !== '' ? (string) $input['notes'] : null;
        }
        if (array_key_exists('sessions_remaining', $input) && $input['sessions_remaining'] !== null && $input['sessions_remaining'] !== '') {
            $n = (int) $input['sessions_remaining'];
            if ($n < 0 || $n > (int) $bono['sessions_total']) {
                return ['ok' => false, 'error' => 'Las sesiones restantes deben estar entre 0 y ' . (int) $bono['sessions_total'] . '.'];
            }
            $data['sessions_remaining'] = $n;
        }
        if (array_key_exists('expires_at', $input) && $input['expires_at'] !== null) {
            $d = trim((string) $input['expires_at']);
            if ($d !== '' && strtotime($d) === false) {
                return ['ok' => false, 'error' => 'La fecha de caducidad no es válida.'];
            }
            $data['expires_at'] = $d !== '' ? date('Y-m-d', strtotime($d)) : null;
        }

        [, $changed] = AuditService::changes($bono, $data);
        $needsReason = array_key_exists('sessions_remaining', $changed) || array_key_exists('expires_at', $changed);

        return ['ok' => true, 'data' => $changed, 'needsReason' => $needsReason];
    }

    /**
     * Edita un bono dejando registro: libro del bono (si cambia saldo o fecha)
     * y auditoría (antes / después / motivo).
     *
     * @return array{success:bool,changed?:int,error?:string}
     */
    public function updateBono(int $bonoId, array $input, ?string $reason, int $actorId): array
    {
        $bonoModel = new PlayerBonoModel();
        $bono      = $bonoModel->find($bonoId);
        if (!$bono) {
            return ['success' => false, 'error' => 'Bono no encontrado.'];
        }

        $edit = self::buildBonoEdit($bono, $input);
        if (!$edit['ok']) {
            return ['success' => false, 'error' => $edit['error']];
        }
        $data = $edit['data'];
        if (!$data) {
            return ['success' => true, 'changed' => 0];
        }
        if ($edit['needsReason'] && !self::validReason($reason)) {
            return ['success' => false, 'error' => 'Indica el motivo del cambio de saldo o de caducidad (queda registrado).'];
        }

        $bonoModel->update($bonoId, $data);
        [$before, $after] = AuditService::changes($bono, $data);
        AuditService::record('player_bono', $bonoId, AuditService::UPDATE, $before, $after, $reason, $actorId);

        $playerId = (int) ($bono['player_id'] ?? 0);
        if ($playerId > 0 && $edit['needsReason']) {
            $notes = [];
            if (array_key_exists('sessions_remaining', $data)) {
                $notes[] = 'sesiones ' . (int) $bono['sessions_remaining'] . ' → ' . (int) $data['sessions_remaining'];
            }
            if (array_key_exists('expires_at', $data)) {
                $notes[] = 'caducidad ' . ($bono['expires_at'] ? date('d/m/Y', strtotime($bono['expires_at'])) : '—')
                    . ' → ' . ($data['expires_at'] ? date('d/m/Y', strtotime($data['expires_at'])) : '—');
            }
            BonoLedgerService::log($playerId, BonoLedgerService::ADJUSTED,
                array_key_exists('sessions_remaining', $data) ? (int) $data['sessions_remaining'] - (int) $bono['sessions_remaining'] : 0,
                $bonoId, null, implode('; ', $notes) . ' · Motivo: ' . trim((string) $reason), $actorId);
            (new BonoCoverageService($this->db))->refreshMarks($playerId);
        }

        return ['success' => true, 'changed' => count($data)];
    }

    /**
     * Anula un bono (sustituye al antiguo «Eliminar»). El bono sigue existiendo
     * con todo su histórico: las sesiones ya descontadas quedan ligadas a él y
     * el saldo que quedaba se cancela (pasa a 0 y queda en el libro). Ya no se
     * puede usar ni editar. Exige motivo.
     *
     * @return array{success:bool,cancelled?:int,error?:string}
     */
    public function voidBono(int $bonoId, ?string $reason, int $actorId): array
    {
        if (!self::validReason($reason)) {
            return ['success' => false, 'error' => 'Indica el motivo de la anulación (queda registrado).'];
        }
        $bonoModel = new PlayerBonoModel();
        $bono      = $bonoModel->find($bonoId);
        if (!$bono) {
            return ['success' => false, 'error' => 'Bono no encontrado.'];
        }
        if (!empty($bono['voided_at'])) {
            return ['success' => false, 'error' => 'Este bono ya estaba anulado.'];
        }

        $remaining = (int) $bono['sessions_remaining'];
        $data = [
            'voided_at'          => date('Y-m-d H:i:s'),
            'voided_by'          => $actorId,
            'void_reason'        => mb_substr(trim((string) $reason), 0, 255),
            'sessions_remaining' => 0,
        ];
        $bonoModel->update($bonoId, $data);

        [$before, $after] = AuditService::changes($bono, $data);
        AuditService::record('player_bono', $bonoId, AuditService::VOID, $before, $after, $reason, $actorId);

        $playerId = (int) ($bono['player_id'] ?? 0);
        if ($playerId > 0) {
            BonoLedgerService::log($playerId, BonoLedgerService::VOIDED, -$remaining, $bonoId, null,
                'Motivo: ' . trim((string) $reason), $actorId);
            (new BonoCoverageService($this->db))->refreshMarks($playerId);
        }

        return ['success' => true, 'cancelled' => $remaining];
    }

    /**
     * Confirma el precio REAL pagado por un bono cuyo precio era estimado
     * (bonos anteriores a v1.33.0) o lo corrige. Queda en auditoría.
     *
     * @param string|null $priceRaw importe en euros tal como lo escribe el admin ("225", "225,50")
     * @return array{success:bool,error?:string}
     */
    public function confirmPrice(int $bonoId, ?string $priceRaw, ?string $reason, int $actorId): array
    {
        $cents = RevisionService::parseEuroToCents($priceRaw);
        if ($cents === null) {
            return ['success' => false, 'error' => 'Indica un importe válido en euros (por ejemplo 225 o 225,50).'];
        }
        if ($cents > 10000000) {
            return ['success' => false, 'error' => 'El importe es demasiado alto.'];
        }
        $bonoModel = new PlayerBonoModel();
        $bono      = $bonoModel->find($bonoId);
        if (!$bono) {
            return ['success' => false, 'error' => 'Bono no encontrado.'];
        }
        if (!empty($bono['voided_at'])) {
            return ['success' => false, 'error' => 'Este bono está anulado.'];
        }
        $wasEstimated = !empty($bono['price_estimated']);
        if (!$wasEstimated && !self::validReason($reason)) {
            return ['success' => false, 'error' => 'Para corregir un precio ya confirmado indica el motivo.'];
        }

        $data = ['price_cents' => $cents, 'price_estimated' => 0];
        $bonoModel->update($bonoId, $data);
        [$b, $a] = AuditService::changes($bono, $data);
        AuditService::record('player_bono', $bonoId, AuditService::UPDATE, $b, $a,
            $reason ?: ($wasEstimated ? 'Precio real confirmado' : null), $actorId);

        return ['success' => true];
    }

    // ────────────────────────────────────────────────────────────────
    //  Avisos de caducidad (sin cron: se disparan desde el uso normal)
    // ────────────────────────────────────────────────────────────────

    /**
     * Avisa de los bonos que caducan en ≤7 días con sesiones sin usar. Cada
     * bono se avisa UNA vez (queda anotado en el libro). Throttle de 1 h
     * con un ajuste, para poder llamarlo desde pantallas habituales sin coste.
     *
     * @return int nº de bonos avisados
     */
    public function runExpiryAlerts(bool $force = false): int
    {
        try {
            $settings = new \App\Models\SettingsModel();
            $last     = (string) $settings->get('bono_expiry_alert_last_run', '');
            if (!$force && $last !== '' && strtotime($last) > time() - 3600) {
                return 0;
            }
            $settings->setSetting('bono_expiry_alert_last_run', date('Y-m-d H:i:s'));

            $today = date('Y-m-d');
            $limit = date('Y-m-d', strtotime('+' . BonoCoverageService::EXPIRY_WARN_DAYS . ' days'));

            $rows = $this->db->table('player_bonos pb')
                ->select('pb.id, pb.player_id, pb.sessions_remaining, pb.expires_at, bt.name AS bono_name, u.name AS player_name')
                ->join('bono_types bt', 'bt.id = pb.bono_type_id', 'left')
                ->join('users u', 'u.id = pb.player_id')
                ->where('pb.player_id IS NOT NULL', null, false)
                ->where('pb.sessions_remaining >', 0)
                ->where('pb.expires_at >=', $today)
                ->where('pb.expires_at <=', $limit)
                ->where('NOT EXISTS (SELECT 1 FROM bono_movements bm WHERE bm.bono_id = pb.id AND bm.type = \'' . BonoLedgerService::EXPIRY_ALERT . '\')', null, false)
                ->get()->getResultArray();

            $n = 0;
            foreach ($rows as $r) {
                $after = (int) $this->db->table('class_session_players csp')
                    ->join('class_sessions cs', 'cs.id = csp.session_id')
                    ->where('csp.user_id', (int) $r['player_id'])
                    ->where('cs.status', 'scheduled')
                    ->where('cs.session_date >', $r['expires_at'])
                    ->countAllResults();

                $when = date('d/m/Y', strtotime($r['expires_at']));
                $extra = $after > 0 ? " Tiene {$after} clase(s) programadas para después de esa fecha." : '';

                $sender = $this->anyAdminId();
                if (!$sender) {
                    continue;
                }
                $notif = new NotificationModel();
                $notif->createWithRecipients([
                    'sender_id'   => $sender,
                    'type'        => 'group',
                    'title'       => '⏳ Bono a punto de caducar: ' . $r['player_name'],
                    'body'        => "El bono \"{$r['bono_name']}\" de {$r['player_name']} caduca el {$when} y le quedan {$r['sessions_remaining']} sesión(es) sin usar.{$extra} Si hace falta, puedes darle más días desde el detalle del bono.",
                    'source_type' => NotificationModel::SOURCE_BONO,
                    'source_id'   => (int) $r['id'],
                ], $this->adminIds());
                $notif->createWithRecipients([
                    'sender_id' => $sender,
                    'type'      => 'individual',
                    'title'     => '⏳ Tu bono caduca pronto',
                    'body'      => "Tu bono \"{$r['bono_name']}\" caduca el {$when} y te quedan {$r['sessions_remaining']} sesión(es) sin usar. Si quieres más días o renovarlo, habla con la academia.",
                    'category'  => \App\Models\NotificationPreferenceModel::CAT_BONOS,
                ], [(int) $r['player_id']]);

                BonoLedgerService::log((int) $r['player_id'], BonoLedgerService::EXPIRY_ALERT, 0, (int) $r['id'], null,
                    "Aviso: caduca el {$when}", $sender);
                $n++;
            }
            return $n;
        } catch (\Throwable $e) {
            log_message('error', 'BonoControlService::runExpiryAlerts falló: ' . $e->getMessage());
            return 0;
        }
    }

    // ────────────────────────────────────────────────────────────────
    //  Utilidades
    // ────────────────────────────────────────────────────────────────

    private function adminIds(): array
    {
        return array_map('intval', array_column(
            (new UserModel())->select('id')->whereIn('role', ['admin', 'superadmin'])->where('status', 'active')->findAll(),
            'id'
        ));
    }

    private function anyAdminId(): int
    {
        $ids = $this->adminIds();
        return (int) ($ids[0] ?? 0);
    }

    /**
     * Salda UNA deuda (una clase concreta) con el bono elegido.
     *
     * @return array{success:bool,error?:string}
     */
    public function settleOne(int $cspId, int $bonoId, ?int $actorId = null): array
    {
        $debt = null;
        foreach ($this->openDebts() as $d) {
            if ((int) $d['csp_id'] === $cspId) {
                $debt = $d;
                break;
            }
        }
        if (!$debt) {
            return ['success' => false, 'error' => 'Esa clase ya no figura como pendiente.'];
        }

        $playerId = (int) ($debt['player_id'] ?? $debt['user_id'] ?? 0);
        $res = (new ClasesService())->deductBonoForPlayer((int) $debt['session_id'], $playerId, null, $bonoId);
        if (empty($res['success'])) {
            return ['success' => false, 'error' => $res['error'] ?? 'No se pudo descontar del bono.'];
        }

        BonoLedgerService::log($playerId, BonoLedgerService::DEBT_SETTLED, 0, $bonoId, (int) $debt['session_id'],
            'Saldada a mano con el bono elegido', $actorId);
        (new BonoCoverageService($this->db))->refreshMarks($playerId);

        return ['success' => true];
    }

    private function bonoName(int $bonoId): string
    {
        $row = $this->db->table('player_bonos pb')->select('bt.name')
            ->join('bono_types bt', 'bt.id = pb.bono_type_id', 'left')
            ->where('pb.id', $bonoId)->get()->getRowArray();
        return $row['name'] ?? 'Bono';
    }

    private function playerName(int $playerId): string
    {
        $u = (new UserModel())->find($playerId);
        return $u['name'] ?? 'El alumno';
    }

    private function notifyAdmins(int $playerId, string $title, string $body, ?int $actorId): void
    {
        try {
            $sender = $actorId ?: $this->anyAdminId();
            $admins = $this->adminIds();
            if (!$sender || empty($admins)) {
                return;
            }
            (new NotificationModel())->createWithRecipients([
                'sender_id' => $sender,
                'type'      => 'group',
                'title'     => $title,
                'body'      => $body,
                'category'  => \App\Models\NotificationPreferenceModel::CAT_BONOS,
            ], $admins);
        } catch (\Throwable $e) {
            log_message('error', 'BonoControlService::notifyAdmins falló: ' . $e->getMessage());
        }
    }
}
