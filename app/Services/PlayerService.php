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
            ->where('role', 'alumno')
            ->where('status', 'active')
            ->findAll();
    }

    // Players + estado de perfil
    public function getPlayersWithProfile()
    {
        return $this->userModel
            ->select('users.*, player_profiles.id as profile_id')
            ->join('player_profiles', 'player_profiles.player_id = users.id', 'left')
            ->where('users.role', 'alumno')
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
}