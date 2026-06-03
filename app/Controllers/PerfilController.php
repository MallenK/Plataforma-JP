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

        // Inyecta KPIs de actividad si el rol los aprovecha (staff/coach)
        if (in_array($user['role'] ?? '', ['staff', 'coach'], true)) {
            $stats = (new \App\Services\CoachService())->getActivityStats((int)$user['id']);
            $user['sessions_count'] = $stats['sessions_count'];
            $user['upcoming_count'] = $stats['upcoming_count'];
            $user['students_count'] = $stats['students_count'];
        }

        // Para alumnos: cargar perfil completo (stats, bonos, asistencia)
        $playerFullProfile = null;
        if ($user['role'] === 'player') {
            $playerFullProfile = (new \App\Services\PlayerService())->getFullProfile((int)$user['id']);
            if ($playerFullProfile) {
                // Mezclar stats de actividad en $user
                $user['classes_count']  = $playerFullProfile['classes_count']  ?? 0;
                $user['upcoming_count'] = $playerFullProfile['upcoming_count'] ?? 0;
                $user['active_bonos']   = $playerFullProfile['active_bonos']   ?? 0;
            }
        }

        $docService     = new \App\Services\DocumentService();
        $personalFolder = $docService->getOrCreatePersonalFolder((int)$user['id']);
        $documents      = $personalFolder ? $docService->getFolderFiles((int)$personalFolder['id']) : [];

        // Anotaciones para alumnos
        $annotations = [];
        if ($user['role'] === 'player') {
            $annModel = new \App\Models\PlayerAnnotationModel();
            // Admin ve todas; el propio alumno solo ve las públicas
            $types = $this->isAdmin() ? ['public', 'internal'] : ['public'];
            $annotations = $annModel->getForPlayer((int)$user['id'], $types);
        }

        return view('perfil/index', [
            'user'              => $user,
            'title'             => 'Mi perfil',
            'personalFolder'    => $personalFolder,
            'documents'         => $documents,
            'playerFullProfile' => $playerFullProfile,
            'annotations'       => $annotations,
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

        $redirectTo = ($isSelf && !$this->isAdmin()) ? '/perfil' : '/perfil/' . $targetId;
        return redirect()->to($redirectTo)->with('success', 'Perfil actualizado correctamente.');
    }

    /**
     * Genera una nueva contraseña aleatoria para un usuario.
     * Solo accesible por admin/superadmin. La contraseña se muestra
     * una única vez en pantalla (flashdata) para que el admin la
     * comunique al usuario afectado.
     *
     * Restricciones:
     *  - No se puede resetear la propia contraseña por esta vía (se usa /forgot-password)
     *  - No se puede resetear al superadmin protegido (id=2 / email maestro)
     */
    public function resetPassword(int $id)
    {
        if (!$this->isAdmin()) {
            return redirect()->to('/perfil')->with('error', 'No tienes permiso para esta acción.');
        }

        $actorId = $this->currentUserId();
        if ($id === $actorId) {
            return redirect()->to('/perfil/' . $id)
                ->with('error', 'Para cambiar tu propia contraseña usa el flujo de recuperación.');
        }

        if ($this->isProtectedUser($id)) {
            return redirect()->to('/perfil/' . $id)
                ->with('error', 'Este perfil está protegido y su contraseña no puede modificarse desde la plataforma.');
        }

        $userModel = new \App\Models\UserModel();
        $target    = $userModel->find($id);
        if (!$target) {
            return redirect()->to('/perfil')->with('error', 'Usuario no encontrado.');
        }

        $newPassword = 'Jp' . bin2hex(random_bytes(4)) . '!';

        $ok = (bool) \Config\Database::connect()
            ->table('users')
            ->where('id', $id)
            ->update([
                'password'   => password_hash($newPassword, PASSWORD_BCRYPT),
                'updated_at' => date('Y-m-d H:i:s'),
            ]);

        if (!$ok) {
            return redirect()->to('/perfil/' . $id)->with('error', 'No se pudo generar la nueva contraseña.');
        }

        return redirect()->to('/perfil/' . $id)
            ->with('new_password', $newPassword)
            ->with('new_password_user', $target['name'] ?? '')
            ->with('success', 'Nueva contraseña generada. Cópiala ahora — no se mostrará otra vez.');
    }
}
