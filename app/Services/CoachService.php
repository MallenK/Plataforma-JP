<?php

namespace App\Services;

use App\Models\UserModel;

class CoachService
{
    protected $userModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
    }

    /**
     * Lista todos los entrenadores activos con contadores de sesiones y alumnos.
     */
    public function getCoaches(): array
    {
        $db = \Config\Database::connect();

        return $db->table('users u')
            ->select('u.id, u.name, u.email, u.status, u.created_at,
                      COUNT(DISTINCT s.id)  AS sessions_count,
                      COUNT(DISTINCT pm.player_id) AS players_count')
            ->join('sessions s',        's.coach_id = u.id',  'left')
            ->join('player_metrics pm', 'pm.coach_id = u.id', 'left')
            ->where('u.role', 'coach')
            ->where('u.status', 'active')
            ->groupBy('u.id')
            ->orderBy('u.name', 'ASC')
            ->get()->getResultArray();
    }

    /**
     * Crea un entrenador nuevo en users con role = 'coach'.
     * Devuelve ['success' => bool, 'userId' => int|null, 'errors' => array].
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
     * Perfil completo del entrenador:
     *   - Datos de users
     *   - Últimas 10 sesiones que dirige
     *   - Jugadores con los que ha trabajado (distintos, con conteo de evaluaciones)
     *   - Últimas 5 evaluaciones registradas
     */
    public function getFullProfile(int $id): ?array
    {
        $coach = $this->userModel
            ->select('id, name, email, role, status, created_at, updated_at')
            ->where('id', $id)
            ->where('role', 'coach')
            ->first();

        if (!$coach) {
            return null;
        }

        $db = \Config\Database::connect();

        // Sesiones que dirige (últimas 10)
        $coach['sessions'] = $db->table('sessions s')
            ->select('s.id, s.title, s.start_datetime, s.end_datetime, s.status, l.name AS location_name')
            ->join('locations l', 'l.id = s.location_id', 'left')
            ->where('s.coach_id', $id)
            ->orderBy('s.start_datetime', 'DESC')
            ->limit(10)
            ->get()->getResultArray();

        // Jugadores con los que ha trabajado (distintos, con conteo de evaluaciones)
        $coach['players'] = $db->table('player_metrics pm')
            ->select('u.id, u.name, u.email, u.status, COUNT(pm.id) AS evals_count')
            ->join('users u', 'u.id = pm.player_id')
            ->where('pm.coach_id', $id)
            ->groupBy('u.id')
            ->orderBy('u.name', 'ASC')
            ->get()->getResultArray();

        // Últimas 5 evaluaciones
        $coach['evaluations'] = $db->table('player_metrics pm')
            ->select('pm.id, pm.date, pm.evaluation, pm.notes, u.name AS player_name')
            ->join('users u', 'u.id = pm.player_id')
            ->where('pm.coach_id', $id)
            ->orderBy('pm.date', 'DESC')
            ->limit(5)
            ->get()->getResultArray();

        return $coach;
    }
}
