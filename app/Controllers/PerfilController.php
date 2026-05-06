<?php

namespace App\Controllers;

class PerfilController extends BaseController
{
    /**
     * Muestra el perfil del usuario autenticado.
     *
     * Si se pasa un $id y el usuario es admin/superadmin, muestra ese perfil.
     * En cualquier otro caso muestra el perfil propio.
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

        return view('perfil/index', [
            'user'  => $user,
            'title' => 'Mi perfil',
        ]);
    }

    /**
     * Actualiza datos personales del perfil.
     *
     * Reglas:
     *  - Self: puede editar su propio nombre y email
     *  - Admin/superadmin: puede editar nombre, email y staff_title de cualquier usuario
     *  - Nadie puede editar al superadmin protegido (id=2 / email maestro)
     */
    public function update(?int $id = null)
    {
        $actorId = $this->currentUserId();
        if (!$actorId) {
            return redirect()->to('/login');
        }

        $targetId = $id ?: $actorId;
        $isSelf   = ($targetId === $actorId);

        if (!$isSelf && !$this->isAdmin()) {
            return redirect()->to('/perfil')->with('error', 'No tienes permiso para editar este perfil.');
        }

        if ($this->isProtectedUser($targetId)) {
            return redirect()->to('/perfil/' . $targetId)
                ->with('error', 'Este perfil está protegido y no puede modificarse desde la plataforma.');
        }

        $userModel = new \App\Models\UserModel();
        $target    = $userModel->find($targetId);
        if (!$target) {
            return redirect()->to('/perfil')->with('error', 'Usuario no encontrado.');
        }

        $name  = trim((string)$this->request->getPost('name'));
        $email = strtolower(trim((string)$this->request->getPost('email')));

        if ($name === '' || mb_strlen($name) < 3) {
            return redirect()->back()->withInput()->with('error', 'El nombre debe tener mínimo 3 caracteres.');
        }
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return redirect()->back()->withInput()->with('error', 'El email no es válido.');
        }

        // Email único (excluye al propio usuario)
        $emailTaken = $userModel->where('email', $email)
            ->where('id !=', $targetId)
            ->first();
        if ($emailTaken) {
            return redirect()->back()->withInput()->with('error', 'Ese email ya está en uso por otro usuario.');
        }

        $update = [
            'name'  => $name,
            'email' => $email,
        ];

        // staff_title solo lo edita un admin sobre roles staff
        if ($this->isAdmin() && in_array($target['role'] ?? '', ['staff', 'coach', 'admin'], true)) {
            $staffTitle = trim((string)$this->request->getPost('staff_title'));
            $update['staff_title'] = $staffTitle === '' ? null : mb_substr($staffTitle, 0, 100);
        }

        // Update directo vía query builder para evitar el callback de hashPassword
        $db = \Config\Database::connect();
        $ok = $db->table('users')->where('id', $targetId)->update($update);

        if (!$ok) {
            return redirect()->back()->withInput()->with('error', 'No se pudo guardar el perfil.');
        }

        // Si es el propio usuario, refrescamos los datos en sesión
        if ($isSelf) {
            session()->set([
                'name'  => $name,
                'email' => $email,
            ]);
        }

        return redirect()->to('/perfil/' . $targetId)->with('success', 'Perfil actualizado correctamente.');
    }
}
