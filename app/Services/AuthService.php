<?php

namespace App\Services;

use App\Models\UserModel;

class AuthService
{
    protected $session;
    protected $userModel;

    public function __construct()
    {
        $this->session = session();
        $this->userModel = new UserModel();
    }

    public function attempt(string $email, string $password): bool
    {
        $user = $this->userModel->where('email', $email)->first();

        if (!$user || !password_verify($password, $user['password'])) {
            return false;
        }

        $this->session->regenerate();

        $this->session->set([
            'id' => $user['id'],
            'name' => $user['name'],
            'role' => $user['role'],
            'isLoggedIn' => true
        ]);

        return true;
    }

    public function logout(): void
    {
        $this->session->destroy();
    }

    public function user()
    {
        return [
            'id' => $this->session->get('id'),
            'name' => $this->session->get('name'),
            'role' => $this->session->get('role'),
        ];
    }

    public function check(): bool
    {
        return $this->session->get('isLoggedIn') === true;
    }
}