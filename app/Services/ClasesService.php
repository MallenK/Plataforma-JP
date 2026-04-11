<?php

namespace App\Services;

use App\Models\ClassModel;
use App\Models\ClassSessionModel;
use App\Models\ClassSessionCoachModel;
use App\Models\ClassSessionPlayerModel;

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
        ]);
    }

    private function createRecurring(array $data, int $userId): array
    {
        $days = array_map('intval', (array)($data['recurrence_days'] ?? []));

        if (empty($days) || empty($data['recurrence_start']) || empty($data['recurrence_end'])) {
            return ['success' => false, 'error' => 'Faltan datos de recurrencia (días, inicio o fin).'];
        }

        // Guardar plantilla
        $classId = (int)$this->classModel->insert([
            'title'                   => trim($data['title']),
            'description'             => ($data['description'] ?? '') ?: null,
            'type'                    => 'recurring',
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

    public function quickCreate(array $data, int $userId): array
    {
        if (empty(trim($data['title'] ?? '')) || empty($data['session_date'] ?? '') || empty($data['start_time'] ?? '')) {
            return ['success' => false, 'error' => 'Título, fecha y hora de inicio son obligatorios.'];
        }

        $id = $this->insertSingle($data, $userId);
        if (!$id) {
            return ['success' => false, 'error' => 'Error al crear la sesión.'];
        }

        $this->syncCoaches($id, $data['coach_ids'] ?? []);
        $this->syncPlayers($id, $data['player_ids'] ?? [], []);

        $session = $this->sessionModel->find($id);
        return [
            'success' => true,
            'id'      => $id,
            'title'   => $session['title'],
            'date'    => $session['session_date'],
        ];
    }

    // ────────────────────────────────────────────────────────────────
    //  Leer
    // ────────────────────────────────────────────────────────────────

    public function getSessionsForCalendar(int $year, int $month, int $userId, string $role): array
    {
        $isPlayer = in_array($role, ['alumno', 'player']);

        if ($isPlayer) {
                $weekCount = (int)$this->db->table('class_sessions cs')
                    ->select('cs.id') // <-- Agregado por seguridad
                    ->join('class_session_players csp', 'csp.session_id = cs.id')
                    ->where('csp.user_id', $userId)
                    ->where('cs.session_date >=', $weekStart)
                    ->where('cs.session_date <=', $weekEnd)
                    ->countAllResults();

                $monthCount = (int)$this->db->table('class_sessions cs')
                    ->select('cs.id') // <-- Agregado por seguridad
                    ->join('class_session_players csp', 'csp.session_id = cs.id')
                    ->where('csp.user_id', $userId)
                    ->where('cs.session_date >=', $mStart)
                    ->where('cs.session_date <=', $mEnd)
                    ->countAllResults();
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
            ->select('csc.id, csc.user_id, u.name, u.email')
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

        $this->playerModel->insert([
            'session_id' => $sessionId,
            'user_id'    => $userId,
            'coach_id'   => ($data['coach_id'] ?? '') ?: null,
            'attendance' => 'pending',
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
    //  Confirmaciones de asistencia
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

    public function updateAttendance(int $sessionId, array $attendanceMap): bool
    {
        $valid = ['present', 'absent', 'pending', 'confirmed', 'declined'];

        foreach ($attendanceMap as $userId => $status) {
            if (!in_array($status, $valid)) continue;

            $player = $this->playerModel
                ->where('session_id', $sessionId)
                ->where('user_id', (int)$userId)
                ->first();

            if ($player) {
                $this->playerModel->update($player['id'], ['attendance' => $status]);
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
            $this->coachModel->insert(['session_id' => $sessionId, 'user_id' => $uid]);
        }
    }

    private function syncPlayers(int $sessionId, array $userIds, array $coachMap): void
    {
        $this->db->table('class_session_players')->where('session_id', $sessionId)->delete();

        foreach (array_unique(array_filter(array_map('intval', (array)$userIds))) as $uid) {
            $coachId = isset($coachMap[$uid]) ? ((int)$coachMap[$uid] ?: null) : null;
            $this->playerModel->insert([
                'session_id' => $sessionId,
                'user_id'    => $uid,
                'coach_id'   => $coachId,
                'attendance' => 'pending',
            ]);
        }
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
