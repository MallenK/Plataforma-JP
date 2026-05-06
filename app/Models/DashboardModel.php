<?php

namespace App\Models;

use App\Models\UserModel;
use App\Models\PlayerBonoModel;

class DashboardModel
{
    protected $userModel;
    protected PlayerBonoModel $bonoModel;

    public function __construct()
    {
        $this->userModel = new UserModel();
        $this->bonoModel = new PlayerBonoModel();
    }

    public function getAdminStats(): array
    {
        $bonoStats = $this->bonoModel->getStats();

        // Alertas accionables: bonos agotados + bonos con 1 sesión + por vencer en 7 días
        $alertas = (int)($bonoStats['depleted'] ?? 0)
                 + (int)($bonoStats['low_sessions'] ?? 0)
                 + (int)($bonoStats['expiring_soon'] ?? 0);

        return [
            'alumnos'      => $this->userModel->countAlumnos(),
            'entrenadores' => $this->userModel->countEntrenadores(),
            'alertas'      => $alertas,
            'bonos_depleted'     => (int)($bonoStats['depleted'] ?? 0),
            'bonos_low_sessions' => (int)($bonoStats['low_sessions'] ?? 0),
            'bonos_expiring'     => (int)($bonoStats['expiring_soon'] ?? 0),
        ];
    }
}
