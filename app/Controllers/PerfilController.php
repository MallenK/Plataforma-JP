<?php

namespace App\Controllers;

use App\Controllers\BaseController;
use App\Models\UserModel;

class PerfilController extends BaseController
{
    public function index($id = null)
    {
        $session = session();

        $currentUserId = $session->get('id');
        $role = $session->get('role');

        log_message('debug', '--- PERFIL DEBUG START ---');
        log_message('debug', 'Session data: ' . json_encode($session->get()));
        log_message('debug', 'CurrentUserId: ' . $currentUserId);
        log_message('debug', 'Role: ' . $role);
        log_message('debug', 'Requested ID: ' . ($id ?? 'NULL'));

        $userModel = new UserModel();

        // 🔐 Lógica de acceso
        if ($role === 'admin' && $id) {
            $user = $userModel->find($id);
            log_message('debug', 'Admin fetching user by ID: ' . $id);
        } else {
            $user = $userModel->find($currentUserId);
            log_message('debug', 'Fetching current user by ID: ' . $currentUserId);
        }

        log_message('debug', 'User result: ' . json_encode($user));
        log_message('debug', '--- PERFIL DEBUG END ---');

        if (!$user) {
            log_message('error', 'User NOT FOUND');
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return view('perfil/index', [
            'user' => $user,
            'title' => 'Perfil'
        ]);
    }
}