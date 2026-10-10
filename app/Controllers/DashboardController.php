<?php

namespace App\Controllers;

class DashboardController extends BaseController
{
    
    public function index()
    {
        $userId = $this->currentUserId();
        $role   = $this->currentRole();

        // Alumnos sin ficha → redirigir a crear perfil
        if ($role === 'player') {
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

        $playerFullProfile = null;
        if ($role === 'player') {
            $playerFullProfile = $this->playerService->getFullProfile($userId);
        }

        $clasesService   = new \App\Services\ClasesService();
        $isAdminRole     = in_array($role, ['superadmin', 'admin']);
        if ($isAdminRole) {
            // TICKET-013: sin cron en Hostinger, los avisos de "bono a punto de
            // caducar" se disparan desde el uso normal (máx. 1 vez/hora, y un
            // solo aviso por bono). Nunca debe tumbar el dashboard.
            (new \App\Services\BonoControlService())->runExpiryAlerts();
        }
        // v1.33.0: aviso «Pendiente de revisar» (sesiones sin cerrar, clases sin
        // descontar, precios estimados, caducados sin usar). Nunca tumba el dashboard.
        $pendingReview = null;
        if ($isAdminRole) {
            try {
                $pendingReview = (new \App\Services\RevisionService())->counts();
            } catch (\Throwable $e) {
                log_message('error', 'Dashboard: RevisionService::counts falló: ' . $e->getMessage());
            }
        }
        $showScopeToggle = $isAdminRole && $clasesService->hasOwnAssignedSessions($userId);
        // Selector "Ver calendario de…" (TICKET-011), igual que en /clases.
        $responsableOptions = $isAdminRole ? $clasesService->getResponsableFilterOptions() : ['coaches' => [], 'staff' => []];

        return view('dashboard/index', [
            'title'              => 'Dashboard — JP Preparation',
            'showWelcome'        => $showWelcome,
            'playerFullProfile'  => $playerFullProfile,
            'showScopeToggle'    => $showScopeToggle,
            'responsableOptions' => $responsableOptions,
            'pendingReview'      => $pendingReview,
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
