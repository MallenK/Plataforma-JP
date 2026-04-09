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
     * Perfil completo de un alumno: datos de users + player_profiles
     * + planes + métricas recientes + asistencia reciente.
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

        $user['plans'] = $db->table('player_plans pp')
            ->select('pp.*, pl.name as plan_name, pl.sessions_count, pl.price')
            ->join('plans pl', 'pl.id = pp.plan_id')
            ->where('pp.player_id', $id)
            ->orderBy('pp.created_at', 'DESC')
            ->get()->getResultArray();

        $user['metrics'] = $db->table('player_metrics')
            ->where('player_id', $id)
            ->orderBy('date', 'DESC')
            ->limit(5)
            ->get()->getResultArray();

        $user['attendance'] = $db->table('session_attendance sa')
            ->select('sa.*, s.title as session_title, s.start_datetime')
            ->join('sessions s', 's.id = sa.session_id')
            ->where('sa.player_id', $id)
            ->orderBy('s.start_datetime', 'DESC')
            ->limit(10)
            ->get()->getResultArray();

        return $user;
    }
}