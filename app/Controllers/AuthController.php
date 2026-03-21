<?php

namespace App\Controllers;

use App\Models\UserModel;

class AuthController extends BaseController
{
    public function login()
    {
        return view('auth/login');
    }

    public function register()
    {
        return view('auth/register');
    }

    public function registerPost()
    {
        $model = new UserModel();

        $data = [
            'name'     => $this->request->getPost('name'),
            'email'    => $this->request->getPost('email'),
            'password' => $this->request->getPost('password'),
            'role'     => 'player'
        ];

        if (!$model->insert($data)) {
            return redirect()->back()
                ->withInput()
                ->with('errors', $model->errors());
        }

        return redirect()->to('/login')->with('success', 'Usuario creado correctamente');
    }

    public function loginPost()
    {
        $session = session();
        $model = new UserModel();

        $email = $this->request->getPost('email');
        $password = $this->request->getPost('password');

        $user = $model->where('email', $email)->first();

        if (!$user || !password_verify($password, $user['password'])) {
            return redirect()->back()->with('error', 'Credenciales incorrectas');
        }

        $session->set([
            'user_id'   => $user['id'],
            'user_name' => $user['name'],
            'role'      => $user['role'],
            'isLoggedIn'=> true
        ]);

        return redirect()->to('/dashboard');
    }

    public function logout()
    {
        session()->destroy();
        return redirect()->to('/login');
    }
}