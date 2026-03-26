<?php

namespace App\Controllers;

use App\Services\AuthService;
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
        log_message('error', "Hola: " . json_encode($this->request->getPost()));
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

        $model->insert([
            'name' => $this->request->getPost('name'),
            'email' => strtolower(trim($this->request->getPost('email'))),
            'password' => $this->request->getPost('password'),
            'role' => 'player'
        ]);

        return $this->response->setJSON([
            'status' => 'success'
        ]);
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
        $token = $this->request->getGet('token');
        return view('auth/reset_password', ['token' => $token]);
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
        session()->destroy();
        return redirect()->to('/login');
    }
}