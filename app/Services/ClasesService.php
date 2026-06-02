<?php

namespace App\Services;

use App\Models\ClassModel;
use App\Models\ClassSessionModel;
use App\Models\ClassSessionCoachModel;
use App\Models\ClassSessionPlayerModel;
use App\Models\PlayerBonoModel;
use App\Models\NotificationModel;
use App\Models\UserModel;

class ClasesService
{
    protected ClassModel $classModel;
    protected ClassSessionModel $sessionModel;
    protected ClassSessionCoachModel $coachModel;
    protected ClassSessionPlayerModel $playerModel;
    protected $db;

    public function __construct()
    {
        $this->classModel   = new ClassModel();
        $this->sessionModel = new ClassSessionModel();
        $this->coachModel   = new ClassSessionCoachModel();
        $this->playerModel  = new ClassSessionPlayerModel();
        $this->db           = \Config\Database::connect();
    }

    // ────────────────────────────────────────────────────────────────
    //  Crear
    // ────────────────────────────────────────────────────────────────

    public function createSession(array $data, int $userId): array
    {
        $type = $data['type'] ?? 'single';

        if ($type === 'recurring') {
            return $this->createRecurring($data, $userId);
        }

        $id = $this->insertSingle($data, $userId);
        if (!$id) {
            return ['success' => false, 'error' => 'Error al crear la sesión.'];
        }

        $this->syncCoaches($id, $data['coach_ids'] ?? []);
        $this->syncPlayers($id, $data['player_ids'] ?? [], $data['player_coach_map'] ?? []);

        return ['success' => true, 'id' => $id, 'count' => 1];
    }

    private function insertSingle(array $data, int $userId, ?int $classId = null): int
    {
        $fmt = in_array($data['class_format'] ?? '', ['individual', 'pareja']) ? $data['class_format'] : 'individual';
        return (int)$this->sessionModel->insert([
            'class_id'        => $classId ?? ($data['class_id'] ?? null),
            'title'           => trim($data['title']),
            'session_date'    => $data['session_date'],
            'start_time'      => $data['start_time'],
            'end_time'        => $data['end_time'] ?: date('H:i', strtotime($data['start_time'] ?? '00:00') + 3600),
            'location_id'     => ($data['location_id'] ?? '') ?: null,
            'location_custom' => ($data['location_custom'] ?? '') ?: null,
            'focus'           => ($data['focus'] ?? '') ?: null,
            'pre_notes'       => ($data['pre_notes'] ?? '') ?: null,
            'post_notes'      => ($data['post_notes'] ?? '') ?: null,
            'status'          => 'scheduled',
            'created_by'      => $userId,
            'class_format'    => $fmt,
        ]);
    }

    private function createRecurring(array $data, int $userId): array
    {
        $days = array_map('intval', (array)($data['recurrence_days'] ?? []));

        if (empty($days) || empty($data['recurrence_start']) || empty($data['recurrence_end'])) {
            return ['success' => false, 'error' => 'Faltan datos de recurrencia (días, inicio o fin).'];
        }

        // Guardar plantilla
        $fmt = in_array($data['class_format'] ?? '', ['individual', 'pareja']) ? $data['class_format'] : 'individual';
        $classId = (int)$this->classModel->insert([
            'title'                   => trim($data['title']),
            'description'             => ($data['description'] ?? '') ?: null,
            'type'                    => 'recurring',
            'class_format'            => $fmt,
            'recurrence_days'         => json_encode($days),
            'recurrence_start'        => $data['recurrence_start'],
            'recurrence_end'          => $data['recurrence_end'],
            'recurrence_time_start'   => $data['start_time'],
            'recurrence_time_end'     => $data['end_time'],
            'default_location_id'     => ($data['location_id'] ?? '') ?: null,
            'default_location_custom' => ($data['location_custom'] ?? '') ?: null,
            'default_focus'           => ($data['focus'] ?? '') ?: null,
            'created_by'              => $userId,
        ]);

        if (!$classId) {
            return ['success' => false, 'error' => 'Error al crear la plantilla recurrente.'];
        }

        // Generar sesiones
        $start   = new \DateTime($data['recurrence_start']);
        $end     = new \DateTime($data['recurrence_end']);
        $current = clone $start;
        $ids     = [];

        while ($current <= $end) {
            $dow = (int)$current->format('N'); // 1=Lun … 7=Dom
            if (in_array($dow, $days)) {
                $sid = $this->insertSingle(array_merge($data, [
                    'session_date' => $current->format('Y-m-d'),
                ]), $userId, $classId);

                if ($sid) {
                    $ids[] = $sid;
                    $this->syncCoaches($sid, $data['coach_ids'] ?? []);
                    $this->syncPlayers($sid, $data['player_ids'] ?? [], $data['player_coach_map'] ?? []);
                }
            }
            $current->modify('+1 day');
        }

        return [
            'success'  => true,
            'id'       => $ids[0] ?? null,
            'class_id' => $classId,
            'count'    => count($ids),
        ];
    }

    /**
     * Punto de entrada AJAX único para crear clases.
     * Soporta sesiones puntuales y recurrentes con todos los campos.
     */
    public function quickCreate(array $data, int $userId): array
    {
        $type = ($data['type'] ?? 'single') === 'recurring' ? 'recurring' : 'single';

        if (empty(trim($data['title'] ?? ''))) {
            return ['success' => false, 'error' => 'El título es obligatorio.'];
        }
        if (empty($data['start_time'] ?? '')) {
            return ['success' => false, 'error' => 'La hora de inicio es obligatoria.'];
        }

        if ($type === 'recurring') {
            $result = $this->createRecurring($data, $userId);
            if (!$result['success']) {
                return $result;
            }
            return [
                'success'  => true,
                'id'       => $result['id'],
                'count'    => $result['count'] ?? 1,
                'class_id' => $result['class_id'] ?? null,
            ];
        }

        if (empty($data['session_date'] ?? '')) {
            return ['success' => false, 'error' => 'La fecha es obligatoria.'];
        }

        try {
            $id = $this->insertSingle($data, $userId);
        } catch (\Throwable $e) {
            log_message('error', 'ClasesService::quickCreate insert error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Error al crear la sesión: ' . $e->getMessage()];
        }

        if (!$id) {
            $errors = $this->sessionModel->errors();
            $msg    = !empty($errors) ? implode(' ', $errors) : 'Error al crear la sesión.';
            log_message('error', 'ClasesService::quickCreate insert returned 0. Errors: ' . $msg);
            return ['success' => false, 'error' => $msg];
        }

        try {
            $this->syncCoaches($id, $data['coach_ids'] ?? []);
            $this->syncPlayers($id, $data['player_ids'] ?? [], $data['player_coach_map'] ?? []);
        } catch (\Throwable $e) {
            log_message('error', 'ClasesService::quickCreate sync error (session=' . $id . '): ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());
        }

        $session = $this->sessionModel->find($id);
        return [
            'success' => true,
            'id'      => $id,
            'title'   => $session['title'],
            'date'    => $session['session_date'],
            'count'   => 1,
        ];
    }

    // ────────────────────────────────────────────────────────────────
    //  Leer
    // ────────────────────────────────────────────────────────────────

    public function getSessionsForCalendar(int $year, int $month, int $userId, string $role): array
    {
        $isPlayer = in_array($role, ['alumno', 'player']);

        $start = sprintf('%04d-%02d-01', $year, $month);
        $end   = date('Y-m-t', strtotime($start));

        if ($isPlayer) {
            $sessions = $this->db->table('class_sessions cs')
                ->select('cs.id, cs.title, cs.session_date, cs.start_time, cs.end_time, cs.status')
                ->join('class_session_players csp', 'csp.session_id = cs.id')
                ->where('csp.user_id', $userId)
                ->where('cs.session_date >=', $start)
                ->where('cs.session_date <=', $end)
                ->where('cs.status !=', 'cancelled')
                ->orderBy('cs.session_date', 'ASC')
                ->orderBy('cs.start_time', 'ASC')
                ->get()->getResultArray();
        } else {
            $sessions = $this->sessionModel->getForMonth($year, $month);
        }

        return array_map(fn($s) => [
            'id'     => (int)$s['id'],
            'title'  => $s['title'],
            'date'   => $s['session_date'],
            'start'  => substr($s['start_time'], 0, 5),
            'end'    => substr($s['end_time'], 0, 5),
            'status' => $s['status'],
            'color'  => $this->statusColor($s['status']),
        ], $sessions);
    }

    public function getSession(int $id): ?array
    {
        $session = $this->sessionModel->find($id);
        if (!$session) return null;

        $session['coaches'] = $this->getCoachesForSession($id);
        $session['players'] = $this->getPlayersForSession($id);

        // Nombre de instalación si hay location_id
        if (!empty($session['location_id'])) {
            $loc = $this->db->table('locations')->where('id', $session['location_id'])->get()->getRowArray();
            $session['location_name'] = $loc['name'] ?? null;
        } else {
            $session['location_name'] = null;
        }

        // Info de la plantilla si es recurrente
        $session['class_info'] = !empty($session['class_id'])
            ? $this->classModel->find($session['class_id'])
            : null;

        return $session;
    }

    public function getUpcomingSessions(int $limit = 5): array
    {
        return $this->sessionModel->getUpcoming($limit);
    }

    // ────────────────────────────────────────────────────────────────
    //  Actualizar
    // ────────────────────────────────────────────────────────────────

    public function updateSession(int $id, array $data): bool
    {
        $allowed = ['title', 'session_date', 'start_time', 'end_time',
                    'location_id', 'location_custom', 'focus',
                    'pre_notes', 'post_notes', 'status'];

        $update = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $data)) {
                $update[$key] = $data[$key] !== '' ? $data[$key] : null;
            }
        }

        if (!empty($update)) {
            $this->sessionModel->update($id, $update);
        }

        if (isset($data['coach_ids'])) {
            $this->syncCoaches($id, $data['coach_ids']);
        }
        if (isset($data['player_ids'])) {
            $this->syncPlayers($id, $data['player_ids'], $data['player_coach_map'] ?? []);
        }

        return true;
    }

    public function markComplete(int $id): bool
    {
        return (bool)$this->sessionModel->update($id, ['status' => 'completed']);
    }

    /**
     * Al completar una sesión, descuenta 1 sesión del bono activo de cada
     * jugador que asistió REALMENTE (attendance='present').
     *
     * No se descuenta a 'pending', 'confirmed', 'declined' ni 'absent':
     * solo el 'present' representa una clase consumida por el alumno.
     *
     * Si tras descontar el bono queda con 1 sesión o se agota (0),
     * se emite una notificación interna al alumno y a todos los admins.
     */
    private function deductBonosForSession(int $sessionId): void
    {
        $bonoModel = new PlayerBonoModel();

        $players = $this->db->table('class_session_players')
            ->where('session_id', $sessionId)
            ->where('attendance', 'present')
            ->get()->getResultArray();

        foreach ($players as $player) {
            $bono = $bonoModel->deductSessionDetailed((int)$player['user_id']);
            if ($bono === null) {
                continue;
            }

            $remaining = (int)$bono['sessions_remaining'];
            if ($remaining === 1 || $remaining === 0) {
                $this->emitBonoLowSessionsNotification((int)$player['user_id'], $bono);
            }
        }
    }

    /**
     * Notifica al alumno y a los admins cuando un bono cae a 1 o 0 sesiones.
     * Usa el sistema de notificaciones internas (sin email).
     */
    private function emitBonoLowSessionsNotification(int $playerId, array $bono): void
    {
        $remaining = (int)$bono['sessions_remaining'];

        // Datos del bono (nombre del tipo) — un join puntual
        $bonoTypeRow = $this->db->table('player_bonos pb')
            ->select('bt.name AS bono_name')
            ->join('bono_types bt', 'bt.id = pb.bono_type_id')
            ->where('pb.id', (int)$bono['id'])
            ->get()->getRow();
        $bonoName = $bonoTypeRow->bono_name ?? 'Bono';

        // Nombre del alumno
        $userModel = new UserModel();
        $player    = $userModel->find($playerId);
        $playerName = $player['name'] ?? 'Alumno';

        if ($remaining === 0) {
            $title = "🎟️ Bono agotado: {$playerName}";
            $body  = "El bono \"{$bonoName}\" de {$playerName} se ha agotado (0 sesiones restantes). "
                   . "Si va a continuar entrenando, asígnale un nuevo bono.";
        } else {
            $title = "⚠️ Última sesión del bono: {$playerName}";
            $body  = "Al alumno {$playerName} le queda 1 sesión en su bono \"{$bonoName}\". "
                   . "Considera renovar o asignar un nuevo bono.";
        }

        // Destinatarios: el propio alumno + el creador del bono + todos los admins/superadmins
        $recipients = [$playerId];

        if (!empty($bono['created_by'])) {
            $recipients[] = (int)$bono['created_by'];
        }

        $admins = $userModel
            ->select('id')
            ->whereIn('role', ['admin', 'superadmin'])
            ->where('status', 'active')
            ->findAll();
        foreach ($admins as $a) {
            $recipients[] = (int)$a['id'];
        }

        $recipients = array_values(array_unique(array_filter($recipients, fn($r) => $r > 0)));
        if (empty($recipients)) {
            return;
        }

        // Sender: el creador del bono si existe; si no, el primer superadmin disponible
        $senderId = (int)($bono['created_by'] ?? 0);
        if ($senderId <= 0) {
            $sa = $userModel->select('id')->where('role', 'superadmin')->where('status', 'active')->first();
            $senderId = (int)($sa['id'] ?? 0);
        }
        if ($senderId <= 0) {
            return; // No hay sender válido, abortar
        }

        (new NotificationModel())->createWithRecipients([
            'sender_id' => $senderId,
            'type'      => 'group',
            'title'     => $title,
            'body'      => $body,
        ], $recipients);
    }

    /**
     * Admin descuenta manualmente 1 sesión del bono activo de un jugador.
     * Solo válido si el jugador tiene attendance='present' y el bono aún no fue descontado.
     */
    public function deductBonoForPlayer(int $sessionId, int $playerId): array
    {
        $player = $this->playerModel
            ->where('session_id', $sessionId)
            ->where('user_id', $playerId)
            ->first();

        if (!$player) {
            return ['success' => false, 'error' => 'Jugador no asignado a esta sesión.'];
        }

        if ($player['attendance'] !== 'present') {
            return ['success' => false, 'error' => 'Solo se puede descontar bono a jugadores marcados como presentes.'];
        }

        if (!empty($player['bono_deducted_at'])) {
            return ['success' => false, 'error' => 'El bono de este jugador ya fue descontado para esta sesión.'];
        }

        $bonoModel = new PlayerBonoModel();
        $bono      = $bonoModel->deductSessionDetailed($playerId);

        if ($bono === null) {
            return ['success' => false, 'error' => 'El jugador no tiene bono activo.'];
        }

        $this->playerModel->update($player['id'], [
            'bono_deducted_at' => date('Y-m-d H:i:s'),
        ]);

        $remaining = (int)$bono['sessions_remaining'];
        if ($remaining === 1 || $remaining === 0) {
            $this->emitBonoLowSessionsNotification($playerId, $bono);
        }

        $typeRow   = $this->db->table('bono_types')->select('name')->where('id', $bono['bono_type_id'])->get()->getRowArray();
        $bonoName  = $typeRow['name'] ?? null;

        return [
            'success'            => true,
            'sessions_remaining' => $remaining,
            'bono_name'          => $bonoName,
        ];
    }

    /**
     * Historial de sesiones completadas con resumen de asistencia y estado de bonos.
     */
    public function getAttendanceHistorial(int $limit = 50): array
    {
        $sessions = $this->db->table('class_sessions cs')
            ->select('cs.id, cs.title, cs.session_date, cs.start_time, cs.end_time, cs.status')
            ->where('cs.status', 'completed')
            ->orderBy('cs.session_date', 'DESC')
            ->orderBy('cs.start_time', 'DESC')
            ->limit($limit)
            ->get()->getResultArray();

        $bonoModel = new PlayerBonoModel();

        foreach ($sessions as &$s) {
            $sid = (int)$s['id'];

            // Coaches
            $s['coaches'] = $this->db->table('class_session_coaches csc')
                ->select('u.name')
                ->join('users u', 'u.id = csc.user_id')
                ->where('csc.session_id', $sid)
                ->get()->getResultArray();

            // Jugadores con estado bono actual
            $players = $this->db->table('class_session_players csp')
                ->select('csp.user_id, csp.attendance, csp.absence_reason, csp.bono_deducted_at, u.name')
                ->join('users u', 'u.id = csp.user_id')
                ->where('csp.session_id', $sid)
                ->orderBy('u.name')
                ->get()->getResultArray();

            $counts = ['present' => 0, 'absent' => 0, 'pending' => 0, 'other' => 0];
            $today = date('Y-m-d');
            foreach ($players as &$p) {
                $activeBono = $this->db->table('player_bonos pb')
                    ->select('pb.sessions_remaining, bt.name AS bono_name')
                    ->join('bono_types bt', 'bt.id = pb.bono_type_id')
                    ->where('pb.player_id', (int)$p['user_id'])
                    ->where('pb.sessions_remaining >', 0)
                    ->groupStart()
                        ->where('pb.expires_at IS NULL')
                        ->orWhere('pb.expires_at >=', $today)
                    ->groupEnd()
                    ->orderBy('pb.created_at', 'ASC')
                    ->get()->getRowArray();
                $p['sessions_remaining'] = $activeBono ? (int)$activeBono['sessions_remaining'] : null;
                $p['bono_name']          = $activeBono ? $activeBono['bono_name'] : null;

                if ($p['attendance'] === 'present')      $counts['present']++;
                elseif ($p['attendance'] === 'absent')   $counts['absent']++;
                elseif ($p['attendance'] === 'pending')  $counts['pending']++;
                else                                     $counts['other']++;
            }
            unset($p);

            $s['players']       = $players;
            $s['player_counts'] = $counts;
        }
        unset($s);

        return $sessions;
    }

    /**
     * Devuelve todas las sesiones de una semana, agrupadas por día.
     * $weekOffset: 0 = semana actual, -1 = semana anterior, etc.
     * $search: filtra por nombre de alumno o entrenador (parcial, case-insensitive).
     */
    public function getWeekSessions(int $weekOffset = 0, string $search = ''): array
    {
        $monday = new \DateTime('monday this week');
        if ($weekOffset !== 0) {
            $monday->modify(($weekOffset > 0 ? '+' : '') . $weekOffset . ' weeks');
        }
        $sunday = clone $monday;
        $sunday->modify('+6 days');

        $weekStart = $monday->format('Y-m-d');
        $weekEnd   = $sunday->format('Y-m-d');

        // Obtener sesiones del período
        $sessions = $this->db->table('class_sessions cs')
            ->select('cs.id, cs.title, cs.session_date, cs.start_time, cs.end_time, cs.status, cs.lista_pasada_at, cs.lista_pasada_by, u.name AS lista_pasada_by_name')
            ->join('users u', 'u.id = cs.lista_pasada_by', 'left')
            ->where('cs.session_date >=', $weekStart)
            ->where('cs.session_date <=', $weekEnd)
            ->where('cs.status !=', 'cancelled')
            ->orderBy('cs.session_date', 'ASC')
            ->orderBy('cs.start_time', 'ASC')
            ->get()->getResultArray();

        $today  = date('Y-m-d');
        $search = strtolower(trim($search));

        foreach ($sessions as &$s) {
            $sid = (int)$s['id'];

            $s['coaches'] = $this->db->table('class_session_coaches csc')
                ->select('csc.user_id, u.name')
                ->join('users u', 'u.id = csc.user_id')
                ->where('csc.session_id', $sid)
                ->orderBy('u.name')
                ->get()->getResultArray();

            $players = $this->db->table('class_session_players csp')
                ->select('csp.id AS csp_id, csp.user_id, csp.attendance, csp.absence_reason, csp.absence_notes, csp.bono_deducted_at, u.name, u.email')
                ->join('users u', 'u.id = csp.user_id')
                ->where('csp.session_id', $sid)
                ->orderBy('u.name')
                ->get()->getResultArray();

            // Enriquecer con bono activo
            foreach ($players as &$p) {
                $activeBono = $this->db->table('player_bonos pb')
                    ->select('pb.sessions_remaining, bt.name AS bono_name')
                    ->join('bono_types bt', 'bt.id = pb.bono_type_id')
                    ->where('pb.player_id', (int)$p['user_id'])
                    ->where('pb.sessions_remaining >', 0)
                    ->groupStart()
                        ->where('pb.expires_at IS NULL')
                        ->orWhere('pb.expires_at >=', $today)
                    ->groupEnd()
                    ->orderBy('pb.created_at', 'ASC')
                    ->get()->getRowArray();
                $p['sessions_remaining'] = $activeBono ? (int)$activeBono['sessions_remaining'] : null;
                $p['bono_name']          = $activeBono ? $activeBono['bono_name'] : null;
            }
            unset($p);

            $s['players'] = $players;

            // Contadores
            $counts = ['present' => 0, 'absent' => 0, 'pending' => 0];
            foreach ($players as $p) {
                if (isset($counts[$p['attendance']])) $counts[$p['attendance']]++;
                else $counts['pending']++;
            }
            $s['player_counts'] = $counts;
        }
        unset($s);

        // Filtrar por búsqueda (alumno o entrenador)
        if ($search !== '') {
            $sessions = array_filter($sessions, function ($s) use ($search) {
                foreach ($s['players'] as $p) {
                    if (str_contains(strtolower($p['name']), $search)) return true;
                }
                foreach ($s['coaches'] as $c) {
                    if (str_contains(strtolower($c['name']), $search)) return true;
                }
                return false;
            });
            $sessions = array_values($sessions);
        }

        // Agrupar por día
        $byDay = [];
        foreach ($sessions as $s) {
            $day = $s['session_date'];
            if (!isset($byDay[$day])) $byDay[$day] = [];
            $byDay[$day][] = $s;
        }

        // Asegurar todos los días de la semana (incluso sin sesiones)
        $result = [];
        $cursor = clone $monday;
        for ($i = 0; $i < 7; $i++) {
            $key = $cursor->format('Y-m-d');
            $result[$key] = $byDay[$key] ?? [];
            $cursor->modify('+1 day');
        }

        return [
            'week_start' => $weekStart,
            'week_end'   => $weekEnd,
            'week_offset' => $weekOffset,
            'by_day'     => $result,
        ];
    }

    /**
     * Marca una sesión como "lista pasada" por el admin.
     * También guarda asistencia si se pasa un attendanceMap.
     */
    public function markListaPasada(int $sessionId, int $adminId, array $attendanceMap = [], array $absenceReasons = [], array $absenceNotes = []): array
    {
        if (!empty($attendanceMap)) {
            $this->updateAttendance($sessionId, $attendanceMap, $absenceReasons, $absenceNotes);
        }

        $this->sessionModel->update($sessionId, [
            'lista_pasada_at' => date('Y-m-d H:i:s'),
            'lista_pasada_by' => $adminId,
        ]);

        return ['success' => true];
    }

    public function completarDiaRapido(string $date, int $adminId): array
    {
        $sessions = $this->db->table('class_sessions')
            ->select('id')
            ->where('session_date', $date)
            ->where('status !=', 'cancelled')
            ->where('lista_pasada_at IS NULL')
            ->get()->getResultArray();

        $done = 0;
        foreach ($sessions as $s) {
            $sid     = (int)$s['id'];
            $players = $this->db->table('class_session_players')
                ->select('user_id')
                ->where('session_id', $sid)
                ->get()->getResultArray();

            $attendanceMap = [];
            foreach ($players as $p) {
                $attendanceMap[(int)$p['user_id']] = 'present';
            }

            $this->markListaPasada($sid, $adminId, $attendanceMap);
            $done++;
        }

        return ['success' => true, 'sessions_completed' => $done];
    }

    public function cancelSession(int $id): bool
    {
        return (bool)$this->sessionModel->update($id, ['status' => 'cancelled']);
    }

    // ────────────────────────────────────────────────────────────────
    //  Eliminar
    // ────────────────────────────────────────────────────────────────

    public function deleteSession(int $id): bool
    {
        $this->db->table('class_session_coaches')->where('session_id', $id)->delete();
        $this->db->table('class_session_players')->where('session_id', $id)->delete();
        return (bool)$this->sessionModel->delete($id);
    }

    // ────────────────────────────────────────────────────────────────
    //  Entrenadores
    // ────────────────────────────────────────────────────────────────

    public function addCoach(int $sessionId, int $userId): array
    {
        if ($this->coachModel->where('session_id', $sessionId)->where('user_id', $userId)->first()) {
            return ['success' => false, 'error' => 'El entrenador ya está asignado a esta sesión.'];
        }
        $this->coachModel->insert(['session_id' => $sessionId, 'user_id' => $userId]);
        return ['success' => true];
    }

    public function removeCoach(int $sessionId, int $userId): bool
    {
        $this->db->table('class_session_coaches')
            ->where('session_id', $sessionId)
            ->where('user_id', $userId)
            ->delete();
        return true;
    }

    public function getCoachesForSession(int $sessionId): array
    {
        return $this->db->table('class_session_coaches csc')
            ->select('csc.session_id, csc.user_id, u.name, u.email')
            ->join('users u', 'u.id = csc.user_id')
            ->where('csc.session_id', $sessionId)
            ->orderBy('u.name')
            ->get()->getResultArray();
    }

    // ────────────────────────────────────────────────────────────────
    //  Jugadores
    // ────────────────────────────────────────────────────────────────

    public function addPlayer(int $sessionId, array $data): array
    {
        $userId = (int)($data['user_id'] ?? 0);
        if (!$userId) return ['success' => false, 'error' => 'Usuario no válido.'];

        if ($this->playerModel->where('session_id', $sessionId)->where('user_id', $userId)->first()) {
            return ['success' => false, 'error' => 'El jugador ya está en esta sesión.'];
        }

        $session = $this->sessionModel->find($sessionId);
        $fmt = $session['class_format'] ?? 'individual';
        $maxPlayers = $fmt === 'pareja' ? 2 : 1;

        $currentCount = $this->db->table('class_session_players')
            ->where('session_id', $sessionId)
            ->countAllResults();
        if ($currentCount >= $maxPlayers) {
            $label = $maxPlayers === 1 ? '1 alumno (clase individual)' : '2 alumnos (clase en pareja)';
            return ['success' => false, 'error' => "Sesión completa: máximo {$label}."];
        }

        $now = date('Y-m-d H:i:s');
        $this->db->table('class_session_players')->insert([
            'id'         => $this->nextPlayerRowId(),
            'session_id' => $sessionId,
            'user_id'    => $userId,
            'coach_id'   => ($data['coach_id'] ?? '') ?: null,
            'attendance' => 'pending',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return ['success' => true];
    }

    public function removePlayer(int $sessionId, int $userId): bool
    {
        $this->db->table('class_session_players')
            ->where('session_id', $sessionId)
            ->where('user_id', $userId)
            ->delete();
        return true;
    }

    public function getPlayersForSession(int $sessionId): array
    {
        return $this->db->table('class_session_players csp')
            ->select('csp.*, u.name, u.email, coach.name AS coach_name')
            ->join('users u', 'u.id = csp.user_id')
            ->join('users coach', 'coach.id = csp.coach_id', 'left')
            ->where('csp.session_id', $sessionId)
            ->orderBy('u.name')
            ->get()->getResultArray();
    }

    // ────────────────────────────────────────────────────────────────
    //  Aviso de ausencia del alumno
    // ────────────────────────────────────────────────────────────────

    /**
     * El alumno indica que no puede asistir, opcionalmente con un motivo.
     * Se advierte si se notifica después de las 10:00 del día de la clase,
     * pero igualmente se registra el aviso (el rechazo de guardar es opcional
     * según la regla de negocio; aquí dejamos pasar con advertencia).
     */
    public function notifyAbsence(int $userId, int $sessionId, string $note): array
    {
        $player = $this->playerModel
            ->where('session_id', $sessionId)
            ->where('user_id', $userId)
            ->first();

        if (!$player) {
            return ['success' => false, 'error' => 'No estás asignado a esta sesión.'];
        }

        $session = $this->sessionModel->find($sessionId);
        if (!$session) {
            return ['success' => false, 'error' => 'Sesión no encontrada.'];
        }

        if ($session['status'] !== 'scheduled') {
            return ['success' => false, 'error' => 'No se puede notificar ausencia en una sesión que no está programada.'];
        }

        $now = new \DateTime();
        $sessionDate = $session['session_date'];
        $todayStr    = $now->format('Y-m-d');
        $lateNotice  = false;

        if ($sessionDate === $todayStr && $now->format('H:i') > '10:00') {
            $lateNotice = true;
        }

        $this->playerModel->update($player['id'], [
            'student_note'      => $note ?: null,
            'student_noted_at'  => date('Y-m-d H:i:s'),
        ]);

        return [
            'success'    => true,
            'lateNotice' => $lateNotice,
        ];
    }

    // ────────────────────────────────────────────────────────────────
    //  Confirmaciones de asistencia (mantenido por compatibilidad,
    //  redirige a notifyAbsence para jugadores)
    // ────────────────────────────────────────────────────────────────

    public function respondToSession(int $userId, int $sessionId, string $status): array
    {
        if (!in_array($status, ['confirmed', 'declined'])) {
            return ['success' => false, 'error' => 'Estado no válido.'];
        }

        $player = $this->playerModel
            ->where('session_id', $sessionId)
            ->where('user_id', $userId)
            ->first();

        if (!$player) {
            return ['success' => false, 'error' => 'No estás asignado a esta sesión.'];
        }

        $this->playerModel->update($player['id'], [
            'attendance'   => $status,
            'responded_at' => date('Y-m-d H:i:s'),
        ]);

        return ['success' => true];
    }

    // ────────────────────────────────────────────────────────────────
    //  Observaciones
    // ────────────────────────────────────────────────────────────────

    public function saveObservations(int $sessionId, array $data): bool
    {
        // Observaciones globales
        $update = [];
        if (array_key_exists('pre_notes', $data))  $update['pre_notes']  = $data['pre_notes']  ?: null;
        if (array_key_exists('post_notes', $data)) $update['post_notes'] = $data['post_notes'] ?: null;
        if (!empty($update)) $this->sessionModel->update($sessionId, $update);

        // Observaciones por jugador
        foreach ((array)($data['player_obs'] ?? []) as $userId => $obs) {
            $player = $this->playerModel
                ->where('session_id', $sessionId)
                ->where('user_id', (int)$userId)
                ->first();

            if ($player) {
                $pu = [];
                if (array_key_exists('pre', $obs))  $pu['pre_obs']  = $obs['pre']  ?: null;
                if (array_key_exists('post', $obs)) $pu['post_obs'] = $obs['post'] ?: null;
                if (!empty($pu)) $this->playerModel->update($player['id'], $pu);
            }
        }

        return true;
    }

    // ────────────────────────────────────────────────────────────────
    //  Control de asistencia (admin/coach marca presente/ausente)
    // ────────────────────────────────────────────────────────────────

    /**
     * Guarda asistencia y motivo de ausencia por jugador.
     * $attendanceMap: [userId => status]
     * $absenceReasons: [userId => reason]  (valor predefinido)
     * $absenceNotes: [userId => notes]     (texto libre adicional)
     */
    public function updateAttendance(int $sessionId, array $attendanceMap, array $absenceReasons = [], array $absenceNotes = []): bool
    {
        $valid = ['present', 'absent', 'pending', 'confirmed', 'declined'];

        foreach ($attendanceMap as $userId => $status) {
            if (!in_array($status, $valid)) continue;

            $player = $this->playerModel
                ->where('session_id', $sessionId)
                ->where('user_id', (int)$userId)
                ->first();

            if ($player) {
                $update = ['attendance' => $status];
                if ($status === 'absent') {
                    $update['absence_reason'] = ($absenceReasons[$userId] ?? '') ?: null;
                    $update['absence_notes']  = ($absenceNotes[$userId] ?? '') ?: null;
                } else {
                    $update['absence_reason'] = null;
                    $update['absence_notes']  = null;
                }
                $this->playerModel->update($player['id'], $update);
            }
        }

        return true;
    }

    // ────────────────────────────────────────────────────────────────
    //  Estadísticas
    // ────────────────────────────────────────────────────────────────

    public function getStats(int $userId, string $role): array
    {
        $isPlayer  = in_array($role, ['alumno', 'player']);
        $weekStart = date('Y-m-d', strtotime('monday this week'));
        $weekEnd   = date('Y-m-d', strtotime('sunday this week'));
        $mStart    = date('Y-m-01');
        $mEnd      = date('Y-m-t');

        if ($isPlayer) {
            $weekCount = (int)$this->db->table('class_sessions cs')
                ->join('class_session_players csp', 'csp.session_id = cs.id')
                ->where('csp.user_id', $userId)
                ->where('cs.session_date >=', $weekStart)
                ->where('cs.session_date <=', $weekEnd)
                ->countAllResults();

            $monthCount = (int)$this->db->table('class_sessions cs')
                ->join('class_session_players csp', 'csp.session_id = cs.id')
                ->where('csp.user_id', $userId)
                ->where('cs.session_date >=', $mStart)
                ->where('cs.session_date <=', $mEnd)
                ->countAllResults();
        } else {
            $weekCount = (int)$this->db->table('class_sessions')
                ->where('session_date >=', $weekStart)
                ->where('session_date <=', $weekEnd)
                ->where('status !=', 'cancelled')
                ->countAllResults();

            $monthCount = (int)$this->db->table('class_sessions')
                ->where('session_date >=', $mStart)
                ->where('session_date <=', $mEnd)
                ->where('status !=', 'cancelled')
                ->countAllResults();
        }

        // Jugadores únicos activos este mes
        $activePlayers = (int)$this->db->table('class_session_players csp')
                ->select('csp.user_id') // <--- SOLUCIÓN: Seleccionar solo una columna específica
                ->join('class_sessions cs', 'cs.id = csp.session_id')
                ->where('cs.session_date >=', $mStart)
                ->where('cs.session_date <=', $mEnd)
                ->where('cs.status !=', 'cancelled')
                ->groupBy('csp.user_id')
                ->countAllResults();

        // Asistencia media (sesiones completadas últimas 4 semanas)
        $since    = date('Y-m-d', strtotime('-4 weeks'));
        $present  = (int)$this->db->table('class_session_players csp')
            ->join('class_sessions cs', 'cs.id = csp.session_id')
            ->where('cs.status', 'completed')
            ->where('cs.session_date >=', $since)
            ->where('csp.attendance', 'present')
            ->countAllResults();
        $total    = (int)$this->db->table('class_session_players csp')
            ->join('class_sessions cs', 'cs.id = csp.session_id')
            ->where('cs.status', 'completed')
            ->where('cs.session_date >=', $since)
            ->countAllResults();

        $avgAttendance = ($total > 0) ? round(($present / $total) * 100) : null;

        return [
            'this_week'      => $weekCount,
            'this_month'     => $monthCount,
            'active_players' => $activePlayers,
            'avg_attendance' => $avgAttendance,
        ];
    }

    // ────────────────────────────────────────────────────────────────
    //  Opciones para selectores
    // ────────────────────────────────────────────────────────────────

    public function getCoachOptions(): array
    {
        return $this->db->table('users')
            ->select('id, name, email')
            ->whereIn('role', ['coach', 'admin', 'superadmin'])
            ->where('status', 'active')
            ->orderBy('name')
            ->get()->getResultArray();
    }

    public function getPlayerOptions(): array
    {
        return $this->db->table('users')
            ->select('id, name, email')
            ->whereIn('role', ['alumno', 'player'])
            ->where('status', 'active')
            ->orderBy('name')
            ->get()->getResultArray();
    }

    public function getLocationOptions(): array
    {
        return $this->db->table('locations')
            ->select('id, name, type, address')
            ->where('active', 1)
            ->orderBy('name')
            ->get()->getResultArray();
    }

    public function getAllOptions(): array
    {
        return [
            'coaches'   => $this->getCoachOptions(),
            'players'   => $this->getPlayerOptions(),
            'locations' => $this->getLocationOptions(),
        ];
    }

    // ────────────────────────────────────────────────────────────────
    //  Helpers internos
    // ────────────────────────────────────────────────────────────────

    private function syncCoaches(int $sessionId, array $userIds): void
    {
        $this->db->table('class_session_coaches')->where('session_id', $sessionId)->delete();

        foreach (array_unique(array_filter(array_map('intval', (array)$userIds))) as $uid) {
            $this->db->table('class_session_coaches')->insert([
                'session_id' => $sessionId,
                'user_id'    => $uid,
            ]);
            if ($this->db->affectedRows() === 0) {
                log_message('error', 'syncCoaches: insert failed for session=' . $sessionId . ' user=' . $uid . ' | ' . $this->db->error()['message']);
            }
        }
    }

    private function syncPlayers(int $sessionId, array $userIds, array $coachMap): void
    {
        $this->db->table('class_session_players')->where('session_id', $sessionId)->delete();

        $now = date('Y-m-d H:i:s');
        foreach (array_unique(array_filter(array_map('intval', (array)$userIds))) as $uid) {
            $coachId = isset($coachMap[$uid]) ? ((int)$coachMap[$uid] ?: null) : null;
            $this->db->table('class_session_players')->insert([
                'id'         => $this->nextPlayerRowId(),
                'session_id' => $sessionId,
                'user_id'    => $uid,
                'coach_id'   => $coachId,
                'attendance' => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            if ($this->db->affectedRows() === 0) {
                log_message('error', 'syncPlayers: insert failed for session=' . $sessionId . ' user=' . $uid . ' | ' . $this->db->error()['message']);
            }
        }
    }

    private function nextPlayerRowId(): int
    {
        $row = $this->db->query('SELECT COALESCE(MAX(id), 0) + 1 AS next_id FROM class_session_players')->getRowArray();
        return (int)($row['next_id'] ?? 1);
    }

    private function statusColor(string $status): string
    {
        return [
            'scheduled' => '#3b82f6',
            'completed' => '#10b981',
            'cancelled' => '#6b7280',
        ][$status] ?? '#3b82f6';
    }
}
