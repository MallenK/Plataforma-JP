<?php

namespace App\Services;

class MailService
{
    public function send($to, $subject, $body)
    {
        $apiKey = 're_ivw6y5KV_AHDW1iTHeU7MNcywnSe6ysEh';

        $data = [
            "from" => "JP Preparation <onboarding@resend.dev>",
            "to" => [$to],
            "subject" => $subject,
            "html" => $body
        ];

        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, "https://api.resend.com/emails");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "Authorization: Bearer $apiKey",
            "Content-Type: application/json"
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));

        $response = curl_exec($ch);

        if (curl_errno($ch)) {
            log_message('error', 'MAIL ERROR: ' . curl_error($ch));
            return false;
        }

        curl_close($ch);

        return true;
    }
}