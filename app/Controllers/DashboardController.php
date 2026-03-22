<?php

namespace App\Controllers;

class DashboardController extends BaseController
{
    public function index()
    {
        return view('dashboard/index', [
            'title' => 'Dashboard'
        ]);
    }


    public function getStats()
    {
        if (!$this->request->isAJAX()) {
            return $this->response->setStatusCode(403);
        }

        if (session('role') !== 'admin') {
            return $this->response->setJSON([
                'error' => 'No autorizado'
            ]);
        }

        $dashboardModel = new \App\Models\DashboardModel();

        return $this->response->setJSON(
            $dashboardModel->getAdminStats()
        );
    }
}