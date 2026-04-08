<?php

namespace App\Services;

class MailService
{
    /**
     * Envía un email usando la API de Resend.
     *
     * La API key y el remitente se leen desde el .env para no
     * exponer credenciales en el código fuente.
     *
     * @param  string $to      Destinatario (email)
     * @param  string $subject Asunto del mensaje
     * @param  string $body    Cuerpo HTML del mensaje
     * @return bool            true si el envío fue exitoso
     */
    public function send(string $to, string $subject, string $body): bool
    {
        // Lee la API key desde .env — nunca hardcodeada en el código
        $apiKey = env('RESEND_API_KEY');
        $from = env('MAIL_FROM', 'JP Preparation <onboarding@resend.dev>');

        if (!$apiKey) {
            log_message('error', 'MailService: RESEND_API_KEY no está configurada en .env');
            return false;
        }

        $data = [
            'from'    => $from,
            'to'      => [$to],
            'subject' => $subject,
            'html'    => $body,
        ];

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, 'https://api.resend.com/emails');
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Authorization: Bearer ' . $apiKey,
            'Content-Type: application/json',
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if (curl_errno($ch)) {
            log_message('error', 'MailService: error de cURL — ' . curl_error($ch));
            curl_close($ch);
            return false;
        }

        curl_close($ch);

        // Resend devuelve 200/201 en éxito
        if ($httpCode < 200 || $httpCode >= 300) {
            log_message('error', 'MailService: respuesta inesperada de Resend — HTTP ' . $httpCode . ' — ' . $response);
            return false;
        }

        return true;
    }
}
