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

        $guard       = new \App\Services\AuthGuardService();
        $newPassword = $guard->generateTempPassword();
        $now         = date('Y-m-d H:i:s');

        $ok = (bool) \Config\Database::connect()
            ->table('users')
            ->where('id', $id)
            ->update([
                'password'             => password_hash($newPassword, PASSWORD_BCRYPT),
                'password_changed_at'  => $now,
                'must_change_password' => 1,
                'updated_at'           => $now,
            ]);

        if (!$ok) {
            return redirect()->to('/perfil/' . $id)->with('error', 'No se pudo generar la nueva contraseña.');
        }

        $guard->record('admin_pwreset', $target['email'] ?? null, $id, ['by' => $actorId]);

        try {
            (new \App\Services\MailService())->sendPasswordChangedEmail(
                $target['email'] ?? '',
                $target['name'] ?? '',
                $guard->ip(),
                $now
            );
        } catch (\Throwable $e) {
            log_message('error', 'PerfilController::resetPassword aviso email — ' . $e->getMessage());
        }

        return redirect()->to('/perfil/' . $id)
            ->with('new_password', $newPassword)
            ->with('new_password_user', $target['name'] ?? '')
            ->with('success', 'Nueva contraseña generada. Cópiala ahora — no se mostrará otra vez. El usuario deberá cambiarla al entrar.');
    }

    /**
     * Formulario de "cambiar mi contraseña" (usuario autenticado).
     */
    public function changePasswordForm()
    {
        $policy = (new \App\Models\SettingsModel())->getAll();

        return view('perfil/change_password', [
            'title'  => 'Cambiar contraseña — JP Preparation',
            'forced' => (bool) session()->get('must_change_password'),
            'policy' => [
                'minLength'      => max(8, (int)($policy['sec_min_password']   ?? 8)),
                'requireUpper'   => (bool)($policy['sec_require_upper']   ?? false),
                'requireNumbers' => (bool)($policy['sec_require_numbers'] ?? false),
                'requireSpecial' => (bool)($policy['sec_require_special'] ?? false),
            ],
        ]);
    }

    /**
     * Procesa el cambio de contraseña propio. Requiere la contraseña
     * actual (re-autenticación). Limita los intentos con la actual mal.
     */
    public function changePassword()
    {
        $userId = (int) $this->currentUserId();
        if (!$userId) {
            return redirect()->to('/login');
        }

        $current = (string) $this->request->getPost('current_password');
        $new     = (string) $this->request->getPost('new_password');
        $confirm = (string) $this->request->getPost('new_password_confirm');

        $guard     = new \App\Services\AuthGuardService();
        $userModel = new \App\Models\UserModel();
        $user      = $userModel->find($userId);
        if (!$user) {
            return redirect()->to('/login');
        }

        if ($this->isProtectedUser($userId)) {
            return redirect()->to('/perfil')->with('error', 'Esta cuenta está protegida.');
        }

        if ($guard->passwordChangeThrottled($userId)) {
            return redirect()->back()->with('error', 'Demasiados intentos. Inténtalo de nuevo dentro de unos minutos.');
        }

        if (!password_verify($current, $user['password'])) {
            $guard->record('pwchange_fail', 'uid:' . $userId, $userId, ['reason' => 'bad_current']);
            return redirect()->back()->with('error', 'La contraseña actual no es correcta.');
        }

        if ($new !== $confirm) {
            return redirect()->back()->with('error', 'La nueva contraseña y su confirmación no coinciden.');
        }

        $err = $guard->validateNewPassword($new, $user['password']);
        if ($err !== null) {
            return redirect()->back()->with('error', $err);
        }

        $now = date('Y-m-d H:i:s');
        \Config\Database::connect()->table('users')->where('id', $userId)->update([
            'password'             => password_hash($new, PASSWORD_BCRYPT),
            'password_changed_at'  => $now,
            'must_change_password' => 0,
            'updated_at'           => $now,
        ]);

        // Este dispositivo sigue dentro; los demás se caerán (AuthFilter).
        session()->set([
            'pw_stamp'             => $now,
            'pw_check_at'          => time(),
            'login_time'           => time(),
            'must_change_password' => false,
        ]);

        $guard->record('pwchange_success', $user['email'] ?? null, $userId);

        try {
            (new \App\Services\MailService())->sendPasswordChangedEmail(
                $user['email'] ?? '',
                $user['name'] ?? '',
                $guard->ip(),
                $now
            );
        } catch (\Throwable $e) {
            log_message('error', 'PerfilController::changePassword aviso email — ' . $e->getMessage());
        }

        return redirect()->to('/perfil')->with('success', 'Contraseña actualizada. Las sesiones en otros dispositivos se cerrarán.');
    }
}
