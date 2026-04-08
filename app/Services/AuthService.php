<?php

namespace App\Services;

use App\Models\UserModel;

class AuthService
{
    protected $session;
    protected $userModel;

    public function __construct()
    {
        $this->session   = session();
        $this->userModel = new UserModel();
    }

    /**
     * Intenta autenticar al usuario con email y contraseña.
     * Aplica rate limiting por IP: máximo 5 intentos cada 5 minutos.
     *
     * @return true|string  true si éxito, string con el error si falla
     */
    public function attempt(string $email, string $password)
    {
        if (!$email || !$password) {
            return 'Datos incompletos';
        }

        // Usamos request()->getIPAddress() en lugar de $_SERVER directamente
        // para que CodeIgniter gestione proxies y cabeceras X-Forwarded-For
        $ip  = service('request')->getIPAddress();
        $key = 'login_attempts_' . $ip;

        $attempts = $this->session->get($key) ?? [
            'count'        => 0,
            'last_attempt' => time(),
        ];

        // Bloqueo tras 5 intentos fallidos en menos de 5 minutos
        if ($attempts['count'] >= 5 && (time() - $attempts['last_attempt']) < 300) {
            return 'Demasiados intentos. Intenta más tarde';
        }

        $user = $this->userModel->where('email', $email)->first();

        if (!$user) {
            $this->registerFailedAttempt($key, $attempts);
            return 'Credenciales incorrectas';
        }

        if (!password_verify($password, $user['password'])) {
            $this->registerFailedAttempt($key, $attempts);
            return 'Credenciales incorrectas';
        }

        // Login correcto: limpia intentos fallidos y regenera sesión
        $this->session->remove($key);
        $this->session->regenerate();

        // Guardamos con la clave canónica 'id' — todos los controllers deben usar session('id')
        $this->session->set([
            'id'          => $user['id'],
            'name'        => $user['name'],
            'role'        => $user['role'],
            'isLoggedIn'  => true,
        ]);

        return true;
    }

    /**
     * Registra un intento fallido de login para el rate limiting.
     */
    private function registerFailedAttempt(string $key, array $attempts): void
    {
        $attempts['count']++;
        $attempts['last_attempt'] = time();
        $this->session->set($key, $attempts);
    }

    /**
     * Crea un token de recuperación de contraseña y envía el email real.
     * El token expira en 1 hora.
     *
     * @return true|string  true si éxito, string con error si falla
     */
    public function createPasswordReset(string $email)
    {
        $user = $this->userModel->where('email', $email)->first();

        // Siempre devolvemos éxito para no revelar si el email existe
        if (!$user) {
            return true;
        }

        $token   = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $db = \Config\Database::connect();

        // Elimina tokens anteriores del mismo email antes de crear uno nuevo
        $db->table('password_resets')->where('email', $email)->delete();

        $db->table('password_resets')->insert([
            'email'      => $email,
            'token'      => $token,
            'expires_at' => $expires,
        ]);

        // Construye el enlace de reset y envía el email real via MailService
        $resetLink = base_url('/reset-password?token=' . $token);

        $mail = new MailService();
        $sent = $mail->send(
            $email,
            'Recuperación de contraseña — JP Preparation',
            $this->buildResetEmailHtml($user['name'], $resetLink)
        );

        if (!$sent) {
            // El error ya queda registrado en MailService; aquí solo lo propagamos
            log_message('error', 'AuthService: no se pudo enviar el email de reset a ' . $email);
        }

        return true;
    }

    /**
     * Genera el HTML del email de recuperación de contraseña.
     */
    private function buildResetEmailHtml(string $name, string $link): string
    {
        return '
            <div style="font-family:sans-serif;max-width:480px;margin:auto">
                <h2>Hola, ' . esc($name) . '</h2>
                <p>Recibimos una solicitud para restablecer tu contraseña en <strong>JP Preparation</strong>.</p>
                <p>Haz clic en el botón para continuar. El enlace expira en <strong>1 hora</strong>.</p>
                <a href="' . $link . '"
                   style="display:inline-block;padding:12px 24px;background:#020617;color:#fff;
                          text-decoration:none;border-radius:6px;margin:16px 0">
                    Restablecer contraseña
                </a>
                <p style="color:#888;font-size:12px">
                    Si no solicitaste esto, ignora este mensaje.<br>
                    El enlace expirará automáticamente.
                </p>
            </div>
        ';
    }

    /**
     * Valida el token y actualiza la contraseña del usuario.
     *
     * @return true|string  true si éxito, string con error si falla
     */
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
            return 'El enlace ha expirado. Solicita uno nuevo';
        }

        // El modelo aplica password_hash automáticamente via beforeUpdate callback
        $this->userModel
            ->where('email', $record['email'])
            ->set(['password' => $password])
            ->update();

        // Token de un solo uso: se elimina tras usarse
        $db->table('password_resets')->where('token', $token)->delete();

        return true;
    }

    /**
     * Devuelve los datos básicos del usuario en sesión.
     */
    public function user(): array
    {
        return [
            'id'   => $this->session->get('id'),
            'name' => $this->session->get('name'),
            'role' => $this->session->get('role'),
        ];
    }

    /**
     * Comprueba si hay un usuario autenticado en sesión.
     */
    public function check(): bool
    {
        return $this->session->get('isLoggedIn') === true;
    }

    /**
     * Destruye la sesión actual.
     */
    public function logout(): void
    {
        $this->session->destroy();
    }
}
