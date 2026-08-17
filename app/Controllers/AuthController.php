<?php

namespace App\Controllers;

use App\Services\AuthService;

class AuthController extends BaseController
{
    public function login()
    {
        if (session()->get('isLoggedIn')) {
            return redirect()->to('/dashboard');
        }

        return view('auth/login');
    }


    public function loginPost()
    {
        $validation = \Config\Services::validation();

        $rules = [
            'email' => 'required|valid_email',
            'password' => 'required|min_length[6]'
        ];

        if (!$this->validate($rules)) {
            return $this->response->setJSON([
                'status' => 'error',
                'errors' => $validation->getErrors()
            ])->setStatusCode(400);
        }

        $email = strtolower(trim($this->request->getPost('email')));
        $password = $this->request->getPost('password');

        $auth = new AuthService();

        $result = $auth->attempt($email, $password);

        if ($result !== true) {
            return $this->response->setJSON([
                'status' => 'error',
                'error' => $result
            ])->setStatusCode(401);
        }

        return $this->response->setJSON([
            'status' => 'success'
        ]);
    }


    public function forgotPassword()
    {
        return view('auth/forgot_password');
    }

    public function forgotPasswordPost()
    {
        $email = strtolower(trim($this->request->getPost('email')));

        $auth = new \App\Services\AuthService();
        $auth->createPasswordReset($email);

        return $this->response->setJSON([
            'status' => 'success'
        ]);
    }

    public function resetPassword()
    {
        $token  = $this->request->getGet('token');
        $policy = (new \App\Models\SettingsModel())->getAll();

        return view('auth/reset_password', [
            'token'  => $token,
            'policy' => [
                'minLength'      => (int)($policy['sec_min_password']   ?? 8),
                'requireUpper'   => (bool)($policy['sec_require_upper']   ?? false),
                'requireNumbers' => (bool)($policy['sec_require_numbers'] ?? false),
                'requireSpecial' => (bool)($policy['sec_require_special'] ?? false),
            ],
        ]);
    }

    public function resetPasswordPost()
    {
        $token           = $this->request->getPost('token');
        $password        = $this->request->getPost('password');
        $passwordConfirm = $this->request->getPost('password_confirm');

        if ($password !== $passwordConfirm) {
            return $this->response->setJSON([
                'status' => 'error',
                'error'  => 'Las contraseñas no coinciden.'
            ])->setStatusCode(400);
        }

        $auth = new \App\Services\AuthService();
        $result = $auth->resetPassword($token, $password);

        if ($result !== true) {
            return $this->response->setJSON([
                'status' => 'error',
                'error' => $result
            ])->setStatusCode(400);
        }

        return $this->response->setJSON([
            'status' => 'success'
        ]);
    }




    public function logout()
    {
        session()->destroy();
        return redirect()->to('/login');
    }
}