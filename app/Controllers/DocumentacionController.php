<?php

namespace App\Controllers;

class DocumentacionController extends BaseController
{
    /**
     * Muestra la sección de documentación.
     *
     * Admin/superadmin pueden ver documentación de cualquier usuario por $id.
     * El resto accede al contenido general de documentación.
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

        return view('documentacion/index', [
            'user'  => $user,
            'title' => 'Documentación',
        ]);
    }
}
