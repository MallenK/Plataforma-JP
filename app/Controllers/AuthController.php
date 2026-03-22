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
            'email' => $this->request->getPost('email'),
            'password' => $this->request->getPost('password'),
            'role' => 'player'
        ]);

        return $this->response->setJSON([
            'status' => 'success'
        ]);
    }

    public function loginPost()
    {
        $auth = new AuthService();

        $email = $this->request->getPost('email');
        $password = $this->request->getPost('password');

        if (!$auth->attempt($email, $password)) {
            return $this->response->setJSON([
                'status' => 'error',
                'error' => 'Credenciales incorrectas'
            ])->setStatusCode(401);
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