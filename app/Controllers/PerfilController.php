<?php

namespace App\Controllers;

class PerfilController extends BaseController
{
    /**
     * Muestra el perfil del usuario autenticado.
     *
     * Si se pasa un $id y el usuario es admin/superadmin, muestra ese perfil.
     * En cualquier otro caso muestra el perfil propio.
     *
     * Refactorizado: usa currentUserId() y currentUserFromDB() de BaseController
     * en lugar de duplicar la lógica de sesión en cada controller.
     */
    public function index(?int $id = null)
    {
        // Admin/superadmin pueden ver el perfil de cualquier usuario por ID
        if ($this->isAdmin() && $id) {
            $user = (new \App\Models\UserModel())->find($id);
        } else {
            // Cualquier otro rol solo puede ver su propio perfil
            $user = $this->currentUserFromDB();
        }

        if (!$user) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return view('perfil/index', [
            'user'  => $user,
            'title' => 'Mi perfil',
        ]);
    }
}
