<?php

namespace App\Services;

use App\Models\UserModel;
use App\Models\PlayerProfileModel;

class PlayerService
{
    protected $userModel;
    protected $profileModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
        $this->profileModel = new PlayerProfileModel();
    }

    // TODOS los players
    public function getAllPlayers()
    {
        return $this->userModel
            ->where('role', 'player')
            ->where('status', 'active')
            ->findAll();
    }

    // Players + estado de perfil
    public function getPlayersWithProfile()
    {
        return $this->userModel
            ->select('users.*, player_profiles.id as profile_id')
            ->join('player_profiles', 'player_profiles.player_id = users.id', 'left')
            ->where('users.role', 'player')
            ->where('users.status', 'active')
            ->findAll();
    }

    public function getProfile($playerId)
    {
        return $this->profileModel
            ->where('player_id', $playerId)
            ->first();
    }

    public function saveProfile($data)
    {
        $existing = $this->getProfile($data['player_id']);

        if ($existing) {
            return $this->profileModel
                ->update($existing['id'], $data);
        }

        return $this->profileModel->insert($data);
    }


    public function hasProfile($playerId)
    {
        return $this->profileModel
            ->where('player_id', $playerId)
            ->first() !== null;
    }

    /**
     * Crea un alumno nuevo: inserta en users y (si hay datos) en player_profiles.
     * Devuelve ['success' => bool, 'userId' => int|null, 'errors' => array].
     */
    public function createAlumno(array $userData, array $profileData): array
    {
        $userId = $this->userModel->insert($userData, true);

        if ($userId === false) {
            return ['success' => false, 'userId' => null, 'errors' => $this->userModel->errors()];
        }

        if (!empty($profileData)) {
            $profileData['player_id'] = $userId;
            $this->profileModel->insert($profileData);
        }

        return ['success' => true, 'userId' => $userId, 'errors' => []];
    }

    /**
     * Actualiza datos de usuario y perfil de un alumno existente.
     */
    public function updateAlumno(int $id, array $userData, array $profileData): bool
    {
        if (!empty($userData)) {
            $this->userModel->skipValidation(true)->update($id, $userData);
        }

        $existing = $this->profileModel->where('player_id', $id)->first();
        if ($existing) {
            $this->profileModel->update($existing['id'], $profileData);
        } else {
            $profileData['player_id'] = $id;
            $this->profileModel->insert($profileData);
        }

        return true;
    }

    /**
     * Baja lógica: cambia status a 'inactive'.
     */
    public function deleteAlumno(int $id): bool
    {
        return (bool) $this->userModel->update($id, ['status' => 'inactive']);
    }

    /**
     * KPIs de actividad de un alumno (clases asistidas + próximas).
     *
     * @return array{classes_count:int, upcoming_count:int, active_bonos:int}
     */
    public function getActivityStats(int $playerId): array
    {
        $db = \Config\Database::connect();
        $today = date('Y-m-d');

        $classesCount = $db->table('class_session_players csp')
            ->join('class_sessions cs', 'cs.id = csp.session_id')
            ->where('csp.user_id', $playerId)
            ->where('csp.attendance', 'present')
            ->where('cs.status', 'completed')
            ->countAllResults();

        $upcomingCount = $db->table('class_session_players csp')
            ->join('class_sessions cs', 'cs.id = csp.session_id')
            ->where('csp.user_id', $playerId)
            ->where('cs.status', 'scheduled')
            ->where('cs.session_date >=', $today)
            ->countAllResults();

        $activeBonos = $db->table('player_bonos')
            ->where('player_id', $playerId)
            ->where('sessions_remaining >', 0)
            ->groupStart()
                ->where('expires_at IS NULL')
                ->orWhere('expires_at >=', $today)
            ->groupEnd()
            ->countAllResults();

        return [
            'classes_count'  => $classesCount,
            'upcoming_count' => $upcomingCount,
            'active_bonos'   => $activeBonos,
        ];
    }

    /**
     * Perfil completo de un alumno:
     *   - Datos de users + player_profiles
     *   - KPIs de actividad
     *   - Bonos asignados (player_bonos + bono_types)
     *   - Métricas recientes (placeholder hasta el rediseño de player_metrics)
     *   - Asistencia reciente (class_session_players + class_sessions)
     *   - Próximas clases agendadas
     */
    public function getFullProfile(int $id): ?array
    {
        $user = $this->userModel
            ->select('users.*, player_profiles.id as profile_id, player_profiles.birth_date, player_profiles.height, player_profiles.weight, player_profiles.position, player_profiles.level, player_profiles.medical_notes')
            ->join('player_profiles', 'player_profiles.player_id = users.id', 'left')
            ->where('users.id', $id)
            ->where('users.role', 'player')
            ->first();

        if (!$user) {
            return null;
        }

        $db = \Config\Database::connect();
        $today = date('Y-m-d');

        // KPIs
        $stats = $this->getActivityStats($id);
        $user['classes_count']  = $stats['classes_count'];
        $user['upcoming_count'] = $stats['upcoming_count'];
        $user['active_bonos']   = $stats['active_bonos'];

        // Planes / Bonos — historial completo del alumno
        $user['plans'] = $db->table('player_bonos pb')
            ->select('pb.id, pb.sessions_total, pb.sessions_remaining, pb.start_date, pb.expires_at,
                      pb.notes, pb.created_at, bt.name AS bono_name, bt.price')
            ->join('bono_types bt', 'bt.id = pb.bono_type_id')
            ->where('pb.player_id', $id)
            ->orderBy('pb.created_at', 'DESC')
            ->get()->getResultArray();

        // Métricas — últimas 5 desde player_metrics (tabla ya existente,
        // ver app/Models/PlayerMetricModel.php para la plantilla del JSON).
        $user['metrics'] = (new \App\Models\PlayerMetricModel())->getRecentForPlayer($id, 5);

        // Asistencia reciente: clases COMPLETADAS donde el alumno tiene fila en class_session_players
        $user['attendance'] = $db->table('class_session_players csp')
            ->select('csp.attendance, csp.post_obs, cs.id AS session_id, cs.title AS session_title,
                      cs.session_date, cs.start_time, cs.end_time, l.name AS location_name, cs.location_custom')
            ->join('class_sessions cs', 'cs.id = csp.session_id')
            ->join('locations l', 'l.id = cs.location_id', 'left')
            ->where('csp.user_id', $id)
            ->where('cs.status', 'completed')
            ->orderBy('cs.session_date', 'DESC')
            ->orderBy('cs.start_time', 'DESC')
            ->limit(10)
            ->get()->getResultArray();

        // Próximas clases agendadas para el alumno
        $user['upcoming'] = $db->table('class_session_players csp')
            ->select('cs.id, cs.title, cs.session_date, cs.start_time, cs.end_time,
                      csp.attendance, l.name AS location_name, cs.location_custom')
            ->join('class_sessions cs', 'cs.id = csp.session_id')
            ->join('locations l', 'l.id = cs.location_id', 'left')
            ->where('csp.user_id', $id)
            ->where('cs.status', 'scheduled')
            ->where('cs.session_date >=', $today)
            ->orderBy('cs.session_date', 'ASC')
            ->orderBy('cs.start_time', 'ASC')
            ->limit(10)
            ->get()->getResultArray();

        return $user;
    }
}