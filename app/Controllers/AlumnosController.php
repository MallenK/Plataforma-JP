<?php

namespace App\Controllers;

/**
 * AlumnosController — muestra el perfil de un alumno concreto.
 *
 * Nota: el listado general de alumnos va en PlayerController::index().
 * Este controller gestiona la vista de perfil individual.
 */
class AlumnosController extends BaseController
{
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

        return view('perfil/index', [
            'title' => 'Perfil — JP Preparation',
            'user'  => $user,
        ]);
    }
}
