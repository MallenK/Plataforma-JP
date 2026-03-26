<?php

namespace App\Services;

use App\Models\UserModel;

class AuthService
{
    protected $session;
    protected $userModel;
    protected int $maxAttempts = 10;
    protected int $lockTime = 300; // 5 min

    public function __construct()
    {
        $this->session = session();
        $this->userModel = new UserModel();
    }

    public function attempt(string $email, string $password)
    {
        if (!$email || !$password) {
            return 'Datos incompletos';
        }

        $ip = $_SERVER['REMOTE_ADDR'];
        $key = 'login_attempts_' . $ip;

        $attempts = $this->session->get($key) ?? [
            'count' => 0,
            'last_attempt' => time()
        ];

        // Bloqueo 5 intentos / 5 minutos
        if ($attempts['count'] >= 5 && (time() - $attempts['last_attempt']) < 300) {
            return 'Demasiados intentos. Intenta más tarde';
        }

        $user = $this->userModel->where('email', $email)->first();

        if (!$user) {
            $this->registerFailedAttempt($key, $attempts);
            return 'Usuario no encontrado';
        }

        if (!password_verify($password, $user['password'])) {
            $this->registerFailedAttempt($key, $attempts);
            return 'Password incorrecto';
        }

        // Reset intentos
        $this->session->remove($key);

        $this->session->regenerate();

        $this->session->set([
            'id' => $user['id'],
            'name' => $user['name'],
            'role' => $user['role'],
            'isLoggedIn' => true
        ]);

        return true;
    }

    private function registerFailedAttempt($key, $attempts)
    {
        $attempts['count']++;
        $attempts['last_attempt'] = time();
        $this->session->set($key, $attempts);
    }

    
    public function createPasswordReset(string $email)
    {
        $user = $this->userModel->where('email', $email)->first();

        if (!$user) {
            return 'Si el email existe, recibirás instrucciones';
        }

        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $db = \Config\Database::connect();

        // eliminar tokens antiguos
        $db->table('password_resets')->where('email', $email)->delete();

        $db->table('password_resets')->insert([
            'email' => $email,
            'token' => $token,
            'expires_at' => $expires
        ]);

        // ⚠️ Aquí luego irá email real
        log_message('error', 'RESET LINK: ' . base_url('/reset-password?token=' . $token));

        return true;
    }

    public function resetPassword(string $token, string $password)
    {
        $db = \Config\Database::connect();

        $record = $db->table('password_resets')
            ->where('token', $token)
            ->get()
            ->getRowArray();

        if (!$record) {
            return 'Token inválido';
        }

        if (strtotime($record['expires_at']) < time()) {
            return 'Token expirado';
        }

        $this->userModel
            ->where('email', $record['email'])
            ->set(['password' => $password])
            ->update();

        // borrar token usado
        $db->table('password_resets')->where('token', $token)->delete();

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