<?php

namespace App\Models;

use App\Models\UserModel;

class DashboardModel
{
    protected $userModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
    }

    public function getAdminStats(): array
    {
        return [
            'alumnos' => $this->userModel->countAlumnos(),
            'entrenadores' => $this->userModel->countEntrenadores(),
        ];
    }
}   