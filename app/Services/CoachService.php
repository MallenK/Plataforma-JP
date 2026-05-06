<?php

namespace App\Services;

use App\Models\UserModel;

/**
 * CoachService
 *
 * Toda la lógica relacionada con entrenadores (y, por extensión, staff).
 * Las estadísticas de actividad (sesiones dirigidas, alumnos trabajados…)
 * se calculan SIEMPRE contra las tablas del módulo de clases:
 *   - class_sessions
 *   - class_session_coaches
 *   - class_session_players
 *
 * Las queries antiguas usaban tablas legacy (`sessions`, `player_metrics`)
 * que ya no se mantienen; por eso los KPIs daban 0.
 */
class CoachService
{
    protected $userModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
    }

    /**
     * Lista todos los entrenadores activos con contadores reales:
     *  - sessions_count: sesiones COMPLETADAS dirigidas
     *  - players_count:  alumnos distintos atendidos (attendance='present')
     */
    public function getCoaches(): array
    {
        $db = \Config\Database::connect();

        $coaches = $db->table('users u')
            ->select('u.id, u.name, u.email, u.status, u.avatar, u.staff_title, u.created_at')
            ->where('u.role', 'coach')
            ->where('u.status', 'active')
            ->orderBy('u.name', 'ASC')
            ->get()->getResultArray();

        if (empty($coaches)) {
            return [];
        }

        $ids = array_column($coaches, 'id');

        // Sesiones completadas dirigidas por cada coach
        $sessionsRows = $db->table('class_session_coaches csc')
            ->select('csc.user_id, COUNT(DISTINCT cs.id) AS cnt')
            ->join('class_sessions cs', 'cs.id = csc.session_id')
            ->whereIn('csc.user_id', $ids)
            ->where('cs.status', 'completed')
            ->groupBy('csc.user_id')
            ->get()->getResultArray();
        $sessionsByCoach = array_column($sessionsRows, 'cnt', 'user_id');

        // Alumnos distintos con attendance='present' en sesiones donde el coach estuvo
        $playersRows = $db->table('class_session_coaches csc')
            ->select('csc.user_id, COUNT(DISTINCT csp.user_id) AS cnt')
            ->join('class_session_players csp', 'csp.session_id = csc.session_id')
            ->join('class_sessions cs', 'cs.id = csc.session_id')
            ->whereIn('csc.user_id', $ids)
            ->where('cs.status', 'completed')
            ->where('csp.attendance', 'present')
            ->groupBy('csc.user_id')
            ->get()->getResultArray();
        $playersByCoach = array_column($playersRows, 'cnt', 'user_id');

        foreach ($coaches as &$c) {
            $c['sessions_count'] = (int)($sessionsByCoach[$c['id']] ?? 0);
            $c['players_count']  = (int)($playersByCoach[$c['id']] ?? 0);
        }
        return $coaches;
    }

    /**
     * Crea un entrenador nuevo en users con role = 'coach'.
     */
    public function createCoach(array $userData): array
    {
        $userId = $this->userModel->insert($userData, true);

        if ($userId === false) {
            return ['success' => false, 'userId' => null, 'errors' => $this->userModel->errors()];
        }

        return ['success' => true, 'userId' => $userId, 'errors' => []];
    }

    /**
     * Actualiza nombre, email y/o estado de un entrenador.
     */
    public function updateCoach(int $id, array $userData): bool
    {
        return (bool) $this->userModel->skipValidation(true)->update($id, $userData);
    }

    /**
     * Baja lógica: cambia status a 'inactive'.
     */
    public function deleteCoach(int $id): bool
    {
        return (bool) $this->userModel->update($id, ['status' => 'inactive']);
    }

    /**
     * Devuelve solo los KPIs de actividad de un usuario (coach o staff).
     * Útil para insertar en /perfil sin cargar todo el historial.
     *
     * @return array{sessions_count:int, upcoming_count:int, students_count:int}
     */
    public function getActivityStats(int $userId): array
    {
        $db = \Config\Database::connect();
        $today = date('Y-m-d');

        $sessionsCount = $db->table('class_session_coaches csc')
            ->join('class_sessions cs', 'cs.id = csc.session_id')
            ->where('csc.user_id', $userId)
            ->where('cs.status', 'completed')
            ->countAllResults();

        $upcomingCount = $db->table('class_session_coaches csc')
            ->join('class_sessions cs', 'cs.id = csc.session_id')
            ->where('csc.user_id', $userId)
            ->where('cs.status', 'scheduled')
            ->where('cs.session_date >=', $today)
            ->countAllResults();

        $studentsCount = (int)($db->table('class_session_coaches csc')
            ->select('COUNT(DISTINCT csp.user_id) AS cnt')
            ->join('class_session_players csp', 'csp.session_id = csc.session_id')
            ->join('class_sessions cs', 'cs.id = csc.session_id')
            ->where('csc.user_id', $userId)
            ->where('cs.status', 'completed')
            ->where('csp.attendance', 'present')
            ->get()->getRow('cnt') ?? 0);

        return [
            'sessions_count' => $sessionsCount,
            'upcoming_count' => $upcomingCount,
            'students_count' => $studentsCount,
        ];
    }

    /**
     * Perfil completo del entrenador:
     *   - Datos básicos
     *   - KPIs de actividad
     *   - Últimas 10 sesiones dirigidas (cualquier estado)
     *   - Próximas sesiones agendadas (hasta 10)
     *   - Alumnos trabajados (distintos, con conteo de clases asistidas)
     *   - Evaluaciones (placeholder; se reescribe cuando exista player_metrics nuevo)
     */
    public function getFullProfile(int $id): ?array
    {
        $coach = $this->userModel
            ->select('id, name, email, role, staff_title, status, avatar, created_at, updated_at')
            ->where('id', $id)
            ->whereIn('role', ['coach', 'staff', 'admin', 'superadmin'])
            ->first();

        if (!$coach) {
            return null;
        }

        $db = \Config\Database::connect();
        $today = date('Y-m-d');

        // KPIs
        $stats = $this->getActivityStats($id);
        $coach['sessions_count'] = $stats['sessions_count'];
        $coach['upcoming_count'] = $stats['upcoming_count'];
        $coach['players_count']  = $stats['students_count'];

        // Últimas sesiones dirigidas (cualquier estado, hasta 10)
        $coach['sessions'] = $db->table('class_session_coaches csc')
            ->select('cs.id, cs.title, cs.session_date, cs.start_time, cs.end_time,
                      cs.status, l.name AS location_name, cs.location_custom')
            ->join('class_sessions cs', 'cs.id = csc.session_id')
            ->join('locations l', 'l.id = cs.location_id', 'left')
            ->where('csc.user_id', $id)
            ->orderBy('cs.session_date', 'DESC')
            ->orderBy('cs.start_time', 'DESC')
            ->limit(10)
            ->get()->getResultArray();

        // Próximas sesiones (status='scheduled' y fecha futura)
        $coach['upcoming'] = $db->table('class_session_coaches csc')
            ->select('cs.id, cs.title, cs.session_date, cs.start_time, cs.end_time,
                      l.name AS location_name, cs.location_custom')
            ->join('class_sessions cs', 'cs.id = csc.session_id')
            ->join('locations l', 'l.id = cs.location_id', 'left')
            ->where('csc.user_id', $id)
            ->where('cs.status', 'scheduled')
            ->where('cs.session_date >=', $today)
            ->orderBy('cs.session_date', 'ASC')
            ->orderBy('cs.start_time', 'ASC')
            ->limit(10)
            ->get()->getResultArray();

        // Alumnos trabajados (distintos, con conteo de clases con asistencia 'present')
        $coach['players'] = $db->table('class_session_coaches csc')
            ->select('u.id, u.name, u.email, u.status, u.avatar,
                      COUNT(DISTINCT csp.session_id) AS classes_count')
            ->join('class_session_players csp', 'csp.session_id = csc.session_id')
            ->join('class_sessions cs', 'cs.id = csc.session_id')
            ->join('users u', 'u.id = csp.user_id')
            ->where('csc.user_id', $id)
            ->where('cs.status', 'completed')
            ->where('csp.attendance', 'present')
            ->groupBy('u.id')
            ->orderBy('classes_count', 'DESC')
            ->orderBy('u.name', 'ASC')
            ->get()->getResultArray();

        // Evaluaciones — placeholder hasta que se defina el nuevo player_metrics
        $coach['evaluations'] = [];

        return $coach;
    }
}
