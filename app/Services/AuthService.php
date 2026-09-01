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
     *
     * El anti-fuerza-bruta y el registro de eventos viven en
     * App\Services\AuthGuardService (persistente en la tabla auth_events,
     * no en la sesión).
     *
     * @return true|string  true si éxito, string con el error si falla
     */
    public function attempt(string $email, string $password, bool $remember = false)
    {
        if (!$email || !$password) {
            return 'Datos incompletos';
        }

        $email = strtolower(trim($email));
        $guard = new AuthGuardService();

        // Bloqueo por fuerza bruta (por cuenta y por IP)
        $lock = $guard->loginLockState($email);
        if ($lock !== null) {
            $min  = max(1, (int) ceil($lock['retryAfter'] / 60));
            $unit = $min === 1 ? 'minuto' : 'minutos';
            return "Demasiados intentos. Inténtalo de nuevo en {$min} {$unit}.";
        }

        $user = $this->userModel->where('email', $email)->first();

        // Verificación en tiempo constante: se ejecuta un password_verify
        // aunque la cuenta no exista, para no filtrar su existencia.
        $passwordOk = $guard->verifyConstantTime($password, $user['password'] ?? null);

        if (!$user || !$passwordOk) {
            $guard->record('login_fail', $email, $user['id'] ?? null);
            return 'Credenciales incorrectas';
        }

        if (($user['status'] ?? 'active') !== 'active') {
            $guard->record('login_fail', $email, (int) $user['id'], ['reason' => 'inactive']);
            return 'Esta cuenta está desactivada. Contacta con el equipo de JP Preparation.';
        }

        // Login correcto: regenera sesión y registra el evento
        $this->session->regenerate();
        $guard->record('login_success', $email, (int) $user['id']);

        $pwStamp = $user['password_changed_at'] ?: ($user['created_at'] ?? date('Y-m-d H:i:s'));

        // Guardamos con la clave canónica 'id' — todos los controllers usan session('id')
        $this->session->set([
            'id'                   => $user['id'],
            'name'                 => $user['name'],
            'role'                 => $user['role'],
            'avatar'               => $user['avatar'] ?? null,
            'isLoggedIn'           => true,
            'last_activity'        => time(),
            'login_time'           => time(),
            // Marca de la última vez que cambió la contraseña; AuthFilter la
            // compara con la BD para cerrar sesiones tras un cambio.
            'pw_stamp'             => $pwStamp,
            'must_change_password' => (bool) ($user['must_change_password'] ?? false),
            // "Recuérdame": no exime del timeout, pero AuthFilter aplica un
            // margen de inactividad mucho más amplio (ver allí).
            'remember_me'          => $remember,
        ]);

        return true;
    }

    /**
     * Crea un token de recuperación de contraseña y envía el email.
     * El token se guarda hasheado (SHA-256); solo viaja en claro en la URL.
     * Aplica límite de peticiones (por email y por IP) sin revelarlo.
     *
     * @return true  siempre (no revela si el email existe ni si se limitó)
     */
    public function createPasswordReset(string $email)
    {
        $email = strtolower(trim($email));
        $guard = new AuthGuardService();

        $user      = $this->userModel->where('email', $email)->first();
        // El límite se calcula con las peticiones ANTERIORES; luego registramos esta.
        $throttled = $guard->resetRequestThrottle($email) !== null;
        $guard->record('pwreset_request', $email, $user['id'] ?? null);

        if (!$user || $throttled) {
            if ($throttled) {
                log_message('info', 'AuthService: petición de reset limitada para ' . $email);
            }
            return true;
        }

        $rawToken  = bin2hex(random_bytes(32));
        $tokenHash = hash('sha256', $rawToken);
        $expires   = date('Y-m-d H:i:s', strtotime('+1 hour'));

        $db = \Config\Database::connect();
        $db->table('password_resets')->where('email', $email)->delete();
        $db->table('password_resets')->insert([
            'user_id'    => $user['id'] ?? null,
            'email'      => $email,
            'token'      => $tokenHash,
            'expires_at' => $expires,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        $resetLink = base_url('/reset-password?token=' . $rawToken);
        $sent = (new MailService())->send(
            $email,
            'Recuperación de contraseña — JP Preparation',
            $this->buildResetEmailHtml($user['name'], $resetLink),
            [
                'sender_id'    => 0,
                'recipient_id' => (int) ($user['id'] ?? 0) ?: null,
            ]
        );

        if (!$sent) {
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
     * Valida el token (hasheado) y actualiza la contraseña del usuario.
     * En éxito: marca password_changed_at, borra TODOS los enlaces de
     * reset del email, notifica por correo e invalida las demás sesiones
     * (vía AuthFilter comparando pw_stamp).
     *
     * @return true|string  true si éxito, string con error si falla
     */
    public function resetPassword(string $token, string $password)
    {
        $guard = new AuthGuardService();
        $db    = \Config\Database::connect();

        if ($guard->resetAttemptThrottled()) {
            return 'Demasiados intentos. Inténtalo de nuevo dentro de unos minutos.';
        }

        $tokenHash = hash('sha256', (string) $token);
        $record = $db->table('password_resets')->where('token', $tokenHash)->get()->getRowArray();

        if (!$record) {
            $guard->record('pwreset_fail', null, null, ['reason' => 'bad_token']);
            return 'Token inválido';
        }

        if (strtotime($record['expires_at']) < time()) {
            $db->table('password_resets')->where('token', $tokenHash)->delete();
            $guard->record('pwreset_fail', $record['email'], (int) ($record['user_id'] ?? 0) ?: null, ['reason' => 'expired']);
            return 'El enlace ha expirado. Solicita uno nuevo';
        }

        $user = $this->userModel->where('email', $record['email'])->first();

        $err = $guard->validateNewPassword($password, $user['password'] ?? null);
        if ($err !== null) {
            $guard->record('pwreset_fail', $record['email'], (int) ($record['user_id'] ?? 0) ?: null, ['reason' => 'policy']);
            return $err;
        }

        $now = date('Y-m-d H:i:s');

        // El modelo aplica password_hash automáticamente via beforeUpdate callback
        $this->userModel
            ->where('email', $record['email'])
            ->set([
                'password'             => $password,
                'password_changed_at'  => $now,
                'must_change_password' => 0,
            ])
            ->update();

        // Invalida TODOS los enlaces de reset de ese email
        $db->table('password_resets')->where('email', $record['email'])->delete();
        $guard->record('pwreset_success', $record['email'], (int) ($record['user_id'] ?? 0) ?: null);

        if ($user) {
            (new MailService())->sendPasswordChangedEmail(
                $record['email'],
                $user['name'] ?? '',
                $guard->ip(),
                $now
            );
        }

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
