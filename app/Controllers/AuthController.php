<?php

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\DocumentService;
use App\Models\UserModel;

class AuthController extends BaseController
{
    public function login()
    {
        if (session()->get('isLoggedIn')) {
            return redirect()->to('/dashboard');
        }

        return view('auth/login');
    }


    public function register()
    {
        if (session()->get('isLoggedIn')) {
            return redirect()->to('/dashboard');
        }

        return view('auth/register');
    }

    public function registerPost()
    {
        $validation = \Config\Services::validation();

        $rules = [
            'name' => 'required|min_length[3]',
            'email' => 'required|valid_email|is_unique[users.email]',
            'password' => 'required|min_length[6]',
            'confirm_password' => 'matches[password]'
        ];

        if (!$this->validate($rules)) {
            return $this->response->setJSON([
                'status' => 'error',
                'errors' => $validation->getErrors()
            ])->setStatusCode(400);
        }

        $model = new UserModel();

        $userId = $model->insert([
            'name' => $this->request->getPost('name'),
            'email' => strtolower(trim($this->request->getPost('email'))),
            'password' => $this->request->getPost('password'),
            'role' => 'player'
        ], true);

        if ($userId) {
            (new DocumentService())->getOrCreatePersonalFolder((int)$userId);
        }

        return $this->response->setJSON([
            'status' => 'success'
        ]);
    }

    public function loginPost()
    {
        $validation = \Config\Services::validation();

        // No se valida longitud de contraseña en el login para no revelar la
        // política; una entrada corta simplemente fallará la autenticación.
        $rules = [
            'email'    => 'required|valid_email',
            'password' => 'required',
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
                'minLength'      => max(8, (int)($policy['sec_min_password'] ?? 8)),
                'requireUpper'   => (bool)($policy['sec_require_upper']   ?? false),
                'requireNumbers' => (bool)($policy['sec_require_numbers'] ?? false),
                'requireSpecial' => (bool)($policy['sec_require_special'] ?? false),
            ],
        ]);
    }

    public function resetPasswordPost()
    {
        $token = $this->request->getPost('token');
        $password = $this->request->getPost('password');

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
        $uid   = session()->get('id');
        $email = null;
        if ($uid) {
            $u = (new \App\Models\UserModel())->find($uid);
            $email = $u['email'] ?? null;
        }
        (new \App\Services\AuthGuardService())->record('logout', $email, $uid ? (int) $uid : null);

        session()->destroy();
        return redirect()->to('/login');
    }
}