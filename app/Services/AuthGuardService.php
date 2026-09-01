<?php

namespace App\Services;

use App\Models\AuthEventModel;
use App\Models\SettingsModel;

/**
 * Toda la lógica de defensa del sistema de autenticación:
 *  - Registro de eventos (auth_events) para rate-limit y auditoría.
 *  - Bloqueo por fuerza bruta en login (por cuenta y por IP).
 *  - Límite de peticiones de recuperación de contraseña.
 *  - Generación de contraseñas temporales fuertes.
 *  - Validación de contraseñas nuevas (política + lista de comunes +
 *    distinta de la actual).
 *
 * No mantiene estado: todo se calcula contra la tabla auth_events.
 */
class AuthGuardService
{
    /** Hash bcrypt real y fijo para igualar el tiempo cuando el email no existe. */
    private const DUMMY_HASH = '$2y$10$FThjua2Iwe7PjrPSlDj/TO4Q1xXiVSTWHiklYkYbPf4YEoDBdKPB6';

    /** Bloqueo de IP (password-spraying): N fallos en $ipWindow min → bloqueo $ipLock min. */
    private const IP_FAIL_THRESHOLD = 20;
    private const IP_WINDOW_MIN     = 15;
    private const IP_LOCK_MIN       = 60;

    /** Recuperación de contraseña. */
    private const RESET_MIN_INTERVAL_SEC = 90;   // 1 email por email/90s
    private const RESET_EMAIL_PER_HOUR   = 5;
    private const RESET_IP_PER_HOUR      = 15;
    private const RESET_ATTEMPT_IP_MAX   = 10;   // POST fallidos a /reset-password por IP / 15 min

    protected AuthEventModel $events;
    protected SettingsModel $settings;

    public function __construct(?AuthEventModel $events = null, ?SettingsModel $settings = null)
    {
        $this->events   = $events   ?? new AuthEventModel();
        $this->settings = $settings ?? new SettingsModel();
    }

    /** Settings de seguridad con defaults si la tabla no está disponible. */
    private function secSettings(): array
    {
        try {
            return $this->settings->getAll();
        } catch (\Throwable $e) {
            return [];
        }
    }

    private function secInt(string $key, int $default): int
    {
        try {
            $v = $this->settings->get($key, $default);
            return (int) ($v ?? $default);
        } catch (\Throwable $e) {
            return $default;
        }
    }

    // ────────────────────────────────────────────────────────────────
    //  Registro de eventos
    // ────────────────────────────────────────────────────────────────

    public function record(string $type, ?string $identifier, ?int $userId = null, array $meta = []): void
    {
        $ip = $this->ip();
        $ua = mb_substr((string) service('request')->getUserAgent()->getAgentString(), 0, 255) ?: null;

        try {
            $this->events->insert([
                'event_type' => $type,
                'identifier' => $identifier ? mb_strtolower(trim($identifier)) : null,
                'user_id'    => $userId,
                'ip_address' => $ip,
                'user_agent' => $ua,
                'meta'       => empty($meta) ? null : json_encode($meta, JSON_UNESCAPED_UNICODE),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'AuthGuardService::record — ' . $e->getMessage());
        }

        // Espejo de los eventos relevantes en la tabla logs (histórico general).
        if (in_array($type, ['lockout', 'admin_pwreset', 'pwchange_success', 'pwreset_success'], true)) {
            try {
                \Config\Database::connect()->table('logs')->insert([
                    'user_id'    => $userId,
                    'action'     => 'auth:' . $type,
                    'entity'     => 'auth',
                    'entity_id'  => null,
                    'created_at' => date('Y-m-d H:i:s'),
                    'data'       => json_encode(array_merge(['ip' => $ip], $meta), JSON_UNESCAPED_UNICODE),
                ]);
            } catch (\Throwable $e) {
                // best-effort
            }
        }
    }

    // ────────────────────────────────────────────────────────────────
    //  Bloqueo de login
    // ────────────────────────────────────────────────────────────────

    /**
     * @return array{scope:string, retryAfter:int}|null  null = no bloqueado
     */
    public function loginLockState(string $email, ?string $ip = null): ?array
    {
        $email = mb_strtolower(trim($email));
        $ip  ??= $this->ip();

        $threshold = max(3, $this->secInt('sec_lockout_threshold', 5));
        $windowMin = max(1, $this->secInt('sec_lockout_minutes', 15));

        // ── Por cuenta ──────────────────────────────────────────────
        $lastSuccess = $this->events->lastAt('login_success', 'identifier', $email);
        $accountFails = $this->events->countRecent('login_fail', 'identifier', $email, $windowMin, $lastSuccess);

        if ($accountFails >= $threshold) {
            $lastFail = $this->events->lastAt('login_fail', 'identifier', $email);
            $retry    = $this->retryAfter($lastFail, $windowMin);
            if ($retry > 0) {
                $this->recordLockoutOnce($email, null);
                return ['scope' => 'account', 'retryAfter' => $retry];
            }
        }

        // ── Por IP (password-spraying) ─────────────────────────────
        $ipFails = $this->events->countRecent('login_fail', 'ip_address', $ip, self::IP_WINDOW_MIN);
        if ($ipFails >= self::IP_FAIL_THRESHOLD) {
            $lastFail = $this->events->lastAt('login_fail', 'ip_address', $ip);
            $retry    = $this->retryAfter($lastFail, self::IP_LOCK_MIN);
            if ($retry > 0) {
                $this->recordLockoutOnce(null, $ip);
                return ['scope' => 'ip', 'retryAfter' => $retry];
            }
        }

        return null;
    }

    /**
     * Ejecuta password_verify contra un hash — real si $hash lo es, o
     * contra un dummy si es null — para que el login tarde lo mismo
     * exista o no la cuenta.
     */
    public function verifyConstantTime(string $password, ?string $hash): bool
    {
        if ($hash === null || $hash === '') {
            password_verify($password, self::DUMMY_HASH);
            return false;
        }
        return password_verify($password, $hash);
    }

    // ────────────────────────────────────────────────────────────────
    //  Recuperación de contraseña
    // ────────────────────────────────────────────────────────────────

    /**
     * @return int|null  segundos a esperar antes de poder pedir otro
     *                    email, o null si se puede enviar ya.
     */
    public function resetRequestThrottle(string $email, ?string $ip = null): ?int
    {
        $email = mb_strtolower(trim($email));
        $ip  ??= $this->ip();

        $lastReq = $this->events->lastAt('pwreset_request', 'identifier', $email);
        if ($lastReq !== null) {
            $elapsed = time() - strtotime($lastReq);
            if ($elapsed < self::RESET_MIN_INTERVAL_SEC) {
                return self::RESET_MIN_INTERVAL_SEC - $elapsed;
            }
        }

        if ($this->events->countRecent('pwreset_request', 'identifier', $email, 60) >= self::RESET_EMAIL_PER_HOUR) {
            return 3600;
        }
        if ($this->events->countRecent('pwreset_request', 'ip_address', $ip, 60) >= self::RESET_IP_PER_HOUR) {
            return 3600;
        }

        return null;
    }

    /** true = demasiados POST fallidos a /reset-password desde esta IP. */
    public function resetAttemptThrottled(?string $ip = null): bool
    {
        $ip ??= $this->ip();
        return $this->events->countRecent('pwreset_fail', 'ip_address', $ip, self::IP_WINDOW_MIN) >= self::RESET_ATTEMPT_IP_MAX;
    }

    /** Límite para la re-autenticación en "cambiar mi contraseña". */
    public function passwordChangeThrottled(int $userId): bool
    {
        return $this->events->countRecent('pwchange_fail', 'identifier', 'uid:' . $userId, self::IP_WINDOW_MIN) >= 5;
    }

    // ────────────────────────────────────────────────────────────────
    //  Contraseñas
    // ────────────────────────────────────────────────────────────────

    /**
     * Contraseña temporal fuerte: $len chars de un alfabeto sin
     * caracteres ambiguos, con al menos una minúscula, una mayúscula y
     * un dígito. Sin prefijo/sufijo fijo.
     */
    public function generateTempPassword(int $len = 14): string
    {
        $lower = 'abcdefghjkmnpqrstuvwxyz';
        $upper = 'ABCDEFGHJKMNPQRSTUVWXYZ';
        $digit = '23456789';
        $all   = $lower . $upper . $digit;

        $len = max(12, $len);
        $chars = [
            $lower[random_int(0, strlen($lower) - 1)],
            $upper[random_int(0, strlen($upper) - 1)],
            $digit[random_int(0, strlen($digit) - 1)],
        ];
        for ($i = count($chars); $i < $len; $i++) {
            $chars[] = $all[random_int(0, strlen($all) - 1)];
        }

        // Barajado con Fisher-Yates usando random_int (CSPRNG).
        for ($i = count($chars) - 1; $i > 0; $i--) {
            $j = random_int(0, $i);
            [$chars[$i], $chars[$j]] = [$chars[$j], $chars[$i]];
        }

        return implode('', $chars);
    }

    /** ¿Está la contraseña en la lista de prohibidas? (normaliza a minúsculas) */
    public function isCommonPassword(string $password): bool
    {
        return in_array(mb_strtolower(trim($password)), $this->commonList(), true);
    }

    /**
     * Valida una contraseña nueva contra:
     *  - la política configurada (longitud, mayúscula, número, especial)
     *  - la lista de contraseñas comunes
     *  - que no sea igual a la actual (si se pasa $currentHash)
     *
     * @return string|null  null si es válida, o el mensaje de error.
     */
    public function validateNewPassword(string $password, ?string $currentHash = null): ?string
    {
        $s              = $this->secSettings();
        $minLen         = max(8, (int) ($s['sec_min_password'] ?? 8));
        $requireUpper   = (bool) ($s['sec_require_upper']   ?? false);
        $requireNumbers = (bool) ($s['sec_require_numbers'] ?? false);
        $requireSpecial = (bool) ($s['sec_require_special'] ?? false);

        if (mb_strlen($password) < $minLen) {
            return "La contraseña debe tener al menos {$minLen} caracteres.";
        }
        if ($requireUpper && !preg_match('/[A-Z]/', $password)) {
            return 'La contraseña debe incluir al menos una letra mayúscula.';
        }
        if ($requireNumbers && !preg_match('/[0-9]/', $password)) {
            return 'La contraseña debe incluir al menos un número.';
        }
        if ($requireSpecial && !preg_match('/[!@#$%^&*()\-_=+\[\]{};:\'",.<>?\/\\\\|`~]/', $password)) {
            return 'La contraseña debe incluir al menos un carácter especial (!@#$...).';
        }
        if ($this->isCommonPassword($password)) {
            return 'Esa contraseña es demasiado común. Elige una menos previsible.';
        }
        if ($currentHash !== null && $currentHash !== '' && password_verify($password, $currentHash)) {
            return 'La nueva contraseña debe ser distinta de la actual.';
        }

        return null;
    }

    // ────────────────────────────────────────────────────────────────
    //  Helpers internos
    // ────────────────────────────────────────────────────────────────

    public function ip(): string
    {
        return service('request')->getIPAddress();
    }

    private function retryAfter(?string $lastEventTime, int $windowMin): int
    {
        if ($lastEventTime === null) {
            return 0;
        }
        $unlockAt = strtotime($lastEventTime) + $windowMin * 60;
        return max(0, $unlockAt - time());
    }

    private function recordLockoutOnce(?string $email, ?string $ip): void
    {
        // Evita spamear un 'lockout' por cada request bloqueado: solo lo
        // registra si no hay uno reciente para el mismo sujeto.
        $col   = $email !== null ? 'identifier' : 'ip_address';
        $value = $email ?? $ip ?? '';
        $recent = $this->events->countRecent('lockout', $col, $value, self::IP_WINDOW_MIN);
        if ($recent === 0) {
            $this->record('lockout', $email, null, ['by' => $email !== null ? 'account' : 'ip', 'ip' => $ip]);
        }
    }

    /** @var string[]|null */
    private static ?array $common = null;

    private function commonList(): array
    {
        if (self::$common !== null) {
            return self::$common;
        }

        $path = APPPATH . 'Data/common-passwords.txt';
        self::$common = [];

        if (is_file($path)) {
            foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = trim($line);
                if ($line === '' || $line[0] === '#') {
                    continue;
                }
                self::$common[] = mb_strtolower($line);
            }
        }

        return self::$common;
    }
}
