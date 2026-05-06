<?php

namespace App\Controllers;

class DashboardController extends BaseController
{
    
    public function index()
    {
        $userId = $this->currentUserId();
        $role   = $this->currentRole();

        // Alumnos sin ficha → redirigir a crear perfil
        if ($role === 'alumno') {
            if (!$this->playerService->hasProfile($userId)) {
                return redirect()->to('/alumno');
            }
        }

        // Bienvenida solo la primera vez en la vida del usuario
        $showWelcome = false;
        $userRow     = $this->currentUserFromDB();
        if ($userRow && empty($userRow['welcomed_at'])) {
            $showWelcome = true;
            \Config\Database::connect()
                ->table('users')
                ->where('id', $userId)
                ->update(['welcomed_at' => date('Y-m-d H:i:s')]);
        }

        return view('dashboard/index', [
            'title'       => 'Dashboard — JP Preparation',
            'showWelcome' => $showWelcome,
        ]);
    }

    public function getStats()
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setStatusCode(403);
        }

        if (!in_array(session('role'), ['admin', 'superadmin'])) {
            return $this->jsonResponse(['error' => 'No autorizado'], 403);
        }

        $dashboardModel = new \App\Models\DashboardModel();

        return $this->jsonResponse($dashboardModel->getAdminStats());
    }
}
