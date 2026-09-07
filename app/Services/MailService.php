<?php

namespace App\Services;

class MailService
{
    /** Remitente por defecto si no hay MAIL_FROM en el .env. */
    private const DEFAULT_FROM = 'Tu Plataforma <noreply@tuplataforma.example>';

    /**
     * Envía un email usando la API de Resend.
     *
     * La API key y el remitente se leen desde el .env para no
     * exponer credenciales en el código fuente.
     *
     * @param  string $to      Destinatario (email)
     * @param  string $subject Asunto del mensaje
     * @param  string $body    Cuerpo HTML del mensaje
     * @param  array  $context Metadatos opcionales para el registro en email_log:
     *                         ['sender_id' => int, 'recipient_id' => int|null,
     *                          'recipient_type' => 'individual'|'group',
     *                          'recipient_group' => string|null]
     * @return bool            true si el envío fue exitoso
     */
    public function send(string $to, string $subject, string $body, array $context = []): bool
    {
        // Lee la API key desde .env — nunca hardcodeada en el código
        $apiKey = env('RESEND_API_KEY');
        $from   = env('MAIL_FROM', self::DEFAULT_FROM) ?: self::DEFAULT_FROM;

        if (!$apiKey) {
            log_message('error', 'MailService: RESEND_API_KEY no está configurada en .env');
            $this->logEmail($to, $subject, $body, 'failed', 'RESEND_API_KEY ausente', $context);
            return false;
        }

        $data = [
            'from'    => $from,
            'to'      => [$to],
            'subject' => $subject,
            'html'    => $body,
        ];

        $response = null;
        $httpCode = 0;
        $curlErr  = null;

        try {
            $ch = curl_init();

            curl_setopt($ch, CURLOPT_URL, 'https://api.resend.com/emails');
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 15);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $apiKey,
                'Content-Type: application/json',
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

            if (curl_errno($ch)) {
                $curlErr = curl_error($ch);
            }

            curl_close($ch);
        } catch (\Throwable $e) {
            log_message('error', 'MailService: excepción inesperada al enviar — ' . $e->getMessage());
            $this->logEmail($to, $subject, $body, 'failed', 'Excepción: ' . $e->getMessage(), $context);
            return false;
        }

        if ($curlErr !== null) {
            log_message('error', 'MailService: error de cURL — ' . $curlErr);
            $this->logEmail($to, $subject, $body, 'failed', 'cURL: ' . $curlErr, $context);
            return false;
        }

        // Resend devuelve 200/201 en éxito
        if ($httpCode < 200 || $httpCode >= 300) {
            log_message('error', 'MailService: respuesta inesperada de Resend — HTTP ' . $httpCode . ' — ' . $response);
            $this->logEmail($to, $subject, $body, 'failed', 'HTTP ' . $httpCode . ' — ' . $response, $context);
            return false;
        }

        $this->logEmail($to, $subject, $body, 'sent', null, $context);
        return true;
    }

    /**
     * Email de bienvenida / confirmación de alta en la plataforma.
     * Se envía al crear un alumno, entrenador o miembro del staff.
     *
     * @param string      $to           Email del nuevo usuario
     * @param string      $name         Nombre del nuevo usuario
     * @param string      $role         Rol asignado (player|coach|staff|admin)
     * @param string|null $tempPassword Contraseña temporal generada (si aplica)
     * @param int         $createdBy    Id del usuario que crea la cuenta (para email_log)
     */
    public function sendWelcomeEmail(string $to, string $name, string $role, ?string $tempPassword = null, int $createdBy = 0): bool
    {
        return $this->send(
            $to,
            'Tu cuenta en Tu Plataforma está lista',
            $this->buildWelcomeEmailHtml($name, $to, $role, $tempPassword),
            [
                'sender_id'      => $createdBy,
                'recipient_type' => 'individual',
            ]
        );
    }

    /**
     * Aviso de seguridad: la contraseña de la cuenta se acaba de cambiar.
     * Se envía tras un reset por email, un cambio desde el perfil o un
     * reset forzado por un admin.
     */
    public function sendPasswordChangedEmail(string $to, string $name, string $ip, string $whenIso): bool
    {
        helper('url');
        $when = date('d/m/Y H:i', strtotime($whenIso) ?: time());
        $body = '
            <div style="font-family:sans-serif;max-width:480px;margin:auto;color:#0f172a">
                <h2 style="margin-bottom:4px">Hola, ' . esc($name) . '</h2>
                <p style="margin-top:0">La contraseña de tu cuenta de
                   <strong>Tu Plataforma</strong> se ha cambiado
                   el <strong>' . esc($when) . '</strong>
                   (IP ' . esc($ip) . ').</p>
                <p><strong>Si has sido tú, no tienes que hacer nada.</strong></p>
                <p style="color:#b91c1c">Si <u>no</u> has sido tú, tu cuenta puede estar
                   comprometida: entra cuanto antes usando
                   <a href="' . rtrim(base_url(), '/') . '/forgot-password">¿Olvidaste tu contraseña?</a>
                   y avisa al equipo de Tu Plataforma.</p>
                <p style="color:#888;font-size:12px;margin-top:24px">
                    Este es un aviso automático de seguridad.
                </p>
            </div>';

        return $this->send($to, 'Tu contraseña de Tu Plataforma ha cambiado', $body, [
            'sender_id'      => 0,
            'recipient_type' => 'individual',
        ]);
    }

    /**
     * HTML del email de bienvenida.
     */
    public function buildWelcomeEmailHtml(string $name, string $email, string $role, ?string $tempPassword): string
    {
        $roleLabels = [
            'player'     => 'alumno/a',
            'coach'      => 'entrenador/a',
            'staff'      => 'staff',
            'admin'      => 'administrador/a',
            'superadmin' => 'administrador/a',
        ];
        helper('url');
        $roleLabel = $roleLabels[$role] ?? 'usuario/a';
        $loginUrl  = rtrim(base_url(), '/') . '/login';
        $forgotUrl = rtrim(base_url(), '/') . '/forgot-password';

        $credentialsBlock = '';
        if ($tempPassword !== null && $tempPassword !== '') {
            $credentialsBlock = '
                <p style="margin:16px 0 6px">Estos son tus datos de acceso:</p>
                <table style="border-collapse:collapse;font-size:14px;margin-bottom:12px">
                    <tr><td style="padding:4px 12px 4px 0;color:#64748b">Email</td>
                        <td style="padding:4px 0;font-weight:600">' . esc($email) . '</td></tr>
                    <tr><td style="padding:4px 12px 4px 0;color:#64748b">Contraseña</td>
                        <td style="padding:4px 0;font-weight:600;font-family:monospace">' . esc($tempPassword) . '</td></tr>
                </table>
                <p style="color:#64748b;font-size:13px;margin:0 0 16px">
                    Es una contraseña temporal: la plataforma te pedirá que elijas
                    una propia la primera vez que entres.
                </p>';
        } else {
            $credentialsBlock = '
                <p style="margin:16px 0">Para acceder por primera vez, define tu contraseña
                   desde <a href="' . $forgotUrl . '">¿Olvidaste tu contraseña?</a> usando este mismo email.</p>';
        }

        return '
            <div style="font-family:sans-serif;max-width:480px;margin:auto;color:#0f172a">
                <h2 style="margin-bottom:4px">Hola, ' . esc($name) . '</h2>
                <p style="margin-top:0">Se ha creado tu cuenta de <strong>' . esc($roleLabel) . '</strong>
                   en la plataforma de <strong>Tu Plataforma</strong>.</p>
                ' . $credentialsBlock . '
                <a href="' . $loginUrl . '"
                   style="display:inline-block;padding:12px 24px;background:#020617;color:#fff;
                          text-decoration:none;border-radius:6px;margin:8px 0">
                    Entrar a la plataforma
                </a>
                <p style="color:#888;font-size:12px;margin-top:24px">
                    Si no esperabas este correo, puedes ignorarlo o avisar al equipo de Tu Plataforma.
                </p>
            </div>
        ';
    }

    /**
     * Registra el envío (o el fallo) en la tabla email_log.
     * Es best-effort: cualquier error de registro se traga para no
     * afectar al flujo de envío.
     */
    private function logEmail(string $to, string $subject, string $body, string $status, ?string $error, array $context): void
    {
        try {
            $db = \Config\Database::connect();
            if (!$db->tableExists('email_log')) {
                return;
            }

            $db->table('email_log')->insert([
                'sender_id'       => (int)($context['sender_id'] ?? 0),
                'recipient_type'  => ($context['recipient_type'] ?? 'individual') === 'group' ? 'group' : 'individual',
                'recipient_id'    => $context['recipient_id'] ?? null,
                'recipient_group' => $context['recipient_group'] ?? null,
                'subject'         => mb_substr($subject . ' — <' . $to . '>', 0, 255),
                'message'         => $body,
                'status'          => $status === 'sent' ? 'sent' : 'failed',
                'error_msg'       => $error,
                'created_at'      => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            log_message('warning', 'MailService: no se pudo registrar en email_log — ' . $e->getMessage());
        }
    }
}
