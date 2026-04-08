<?php

namespace App\Controllers;

class ConfiguracionController extends BaseController
{
    /**
     * Muestra la página de configuración.
     *
     * Solo admin/superadmin pueden acceder a la configuración global.
     * El resto ve su propia configuración de cuenta (pendiente de implementar en Fase 3).
     *
     * Refactorizado: usa helpers de BaseController. Eliminados logs de debug.
     */
    public function index(?int $id = null)
    {
        if ($this->isAdmin() && $id) {
            $user = (new \App\Models\UserModel())->find($id);
        } else {
            $user = $this->currentUserFromDB();
        }

        if (!$user) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return view('configuracion/index', [
            'user'  => $user,
            'title' => 'Configuración',
        ]);
    }
}
