<?php

namespace App\Libraries;

/**
 * Web Push nativo (RFC 8030 + RFC 8291 aes128gcm + VAPID RFC 8292) usando solo
 * ext-openssl + ext-curl. Sin Composer: así se despliega a Hostinger copiando
 * app/ como siempre.
 *
 * Funciona con todos los servicios de push: FCM (Chrome/Edge/Opera/Brave/
 * Samsung), Mozilla autopush (Firefox) y Apple (Safari macOS 13+ / iOS 16.4+,
 * este último solo con la PWA instalada en la pantalla de inicio).
 */
class WebPush
{
    private const SPKI_P256_PREFIX = '3059301306072a8648ce3d020106082a8648ce3d030107034200';
    private const SEC1_P256_PREFIX = '30770201010420';
    private const SEC1_P256_MIDDLE = 'a00a06082a8648ce3d030107a14403420004';

    public function __construct(
        private string $publicKey,   // base64url, 65 bytes sin comprimir
        private string $privateKey,  // base64url, 32 bytes
        private string $subject      // mailto: o https:
    ) {
    }

    public static function fromEnv(): ?self
    {
        $pub  = (string) env('vapid.publicKey', '');
        $priv = (string) env('vapid.privateKey', '');
        if ($pub === '' || $priv === '') {
            return null;
        }
        return new self($pub, $priv, (string) env('vapid.subject', 'mailto:info@jppreparation.com'));
    }

    public function publicKey(): string
    {
        return $this->publicKey;
    }

    // ── Claves VAPID ─────────────────────────────────────────────────────

    /** @return array{publicKey: string, privateKey: string} base64url */
    public static function generateVapidKeys(): array
    {
        $res = openssl_pkey_new(['curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC]);
        if ($res === false) {
            throw new \RuntimeException('openssl no pudo generar la clave EC P-256.');
        }
        $ec = openssl_pkey_get_details($res)['ec'];

        return [
            'publicKey'  => self::b64uEncode("\x04" . self::pad32($ec['x']) . self::pad32($ec['y'])),
            'privateKey' => self::b64uEncode(self::pad32($ec['d'])),
        ];
    }

    // ── Cifrado del payload (RFC 8291) ───────────────────────────────────

    /**
     * Devuelve el cuerpo aes128gcm listo para enviar.
     *
     * $ephemeral y $salt solo se inyectan en los tests; en producción se generan.
     *
     * @param array{private: string, public: string}|null $ephemeral raw 32 B / 65 B
     */
    public static function encrypt(
        string $payload,
        string $uaPublicB64,
        string $authSecretB64,
        ?array $ephemeral = null,
        ?string $salt = null
    ): string {
        $uaPublic   = self::b64uDecode($uaPublicB64);
        $authSecret = self::b64uDecode($authSecretB64);

        if (strlen($uaPublic) !== 65 || $uaPublic[0] !== "\x04") {
            throw new \InvalidArgumentException('p256dh inválida.');
        }
        if (strlen($authSecret) < 16) {
            throw new \InvalidArgumentException('auth inválida.');
        }
        if (strlen($payload) > 3993) {
            throw new \InvalidArgumentException('Payload demasiado grande para un solo registro.');
        }

        if ($ephemeral === null) {
            $keys   = self::generateVapidKeys();
            $asPriv = self::b64uDecode($keys['privateKey']);
            $asPub  = self::b64uDecode($keys['publicKey']);
        } else {
            $asPriv = $ephemeral['private'];
            $asPub  = $ephemeral['public'];
        }
        $salt ??= random_bytes(16);

        $shared = self::ecdh($asPriv, $asPub, $uaPublic);

        $ikm = hash_hkdf('sha256', $shared, 32, "WebPush: info\0" . $uaPublic . $asPub, $authSecret);
        $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);

        $tag    = '';
        $cipher = openssl_encrypt($payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
        if ($cipher === false) {
            throw new \RuntimeException('Fallo cifrando el payload.');
        }

        return $salt . pack('N', 4096) . chr(65) . $asPub . $cipher . $tag;
    }

    private static function ecdh(string $privRaw, string $pubRaw, string $peerPubRaw): string
    {
        $priv = openssl_pkey_get_private(self::privatePem($privRaw, $pubRaw));
        $peer = openssl_pkey_get_public(self::pem('PUBLIC KEY', hex2bin(self::SPKI_P256_PREFIX) . $peerPubRaw));
        if ($priv === false || $peer === false) {
            throw new \RuntimeException('Claves EC no válidas.');
        }
        $secret = openssl_pkey_derive($peer, $priv, 32);
        if ($secret === false) {
            throw new \RuntimeException('ECDH fallido.');
        }
        return str_pad($secret, 32, "\0", STR_PAD_LEFT);
    }

    // ── VAPID (RFC 8292) ─────────────────────────────────────────────────

    public function vapidHeader(string $endpoint, ?int $now = null): string
    {
        $parts = parse_url($endpoint);
        $aud   = ($parts['scheme'] ?? 'https') . '://' . ($parts['host'] ?? '') . (isset($parts['port']) ? ':' . $parts['port'] : '');

        $signing = self::b64uEncode(json_encode(['typ' => 'JWT', 'alg' => 'ES256']))
            . '.' . self::b64uEncode(json_encode(['aud' => $aud, 'exp' => ($now ?? time()) + 12 * 3600, 'sub' => $this->subject]));

        $key = openssl_pkey_get_private(self::privatePem(self::b64uDecode($this->privateKey), self::b64uDecode($this->publicKey)));
        if ($key === false || !openssl_sign($signing, $der, $key, OPENSSL_ALGO_SHA256)) {
            throw new \RuntimeException('No se pudo firmar el JWT VAPID.');
        }

        return 'vapid t=' . $signing . '.' . self::b64uEncode(self::derToRaw($der)) . ', k=' . $this->publicKey;
    }

    /** ECDSA DER → r||s (64 bytes) que exige JWS ES256. */
    public static function derToRaw(string $der): string
    {
        $off = 2 + ((ord($der[1]) & 0x80) ? (ord($der[1]) & 0x7f) : 0);
        $rLen = ord($der[$off + 1]);
        $r    = substr($der, $off + 2, $rLen);
        $off += 2 + $rLen;
        $sLen = ord($der[$off + 1]);
        $s    = substr($der, $off + 2, $sLen);

        return str_pad(ltrim($r, "\0"), 32, "\0", STR_PAD_LEFT) . str_pad(ltrim($s, "\0"), 32, "\0", STR_PAD_LEFT);
    }

    // ── Envío ────────────────────────────────────────────────────────────

    /**
     * Envía en paralelo. Cada $targets[i] = ['endpoint','p256dh','auth'].
     * $payloads: un único texto para todos o uno por destino (mismo índice).
     * Devuelve, en el mismo orden, el código HTTP (0 = fallo de red/cifrado).
     *
     * @param list<array{endpoint: string, p256dh: string, auth: string}> $targets
     * @param string|list<string> $payloads
     * @return list<int>
     */
    public function sendMany(array $targets, string|array $payloads, int $ttl = 86400, string $urgency = 'normal', int $timeout = 6): array
    {
        $multi   = curl_multi_init();
        $handles = [];
        $codes   = array_fill(0, count($targets), 0);

        foreach ($targets as $i => $t) {
            try {
                $body = self::encrypt(is_array($payloads) ? $payloads[$i] : $payloads, $t['p256dh'], $t['auth']);
                $ch   = curl_init($t['endpoint']);
                curl_setopt_array($ch, [
                    CURLOPT_POST           => true,
                    CURLOPT_POSTFIELDS     => $body,
                    CURLOPT_RETURNTRANSFER => true,
                    CURLOPT_TIMEOUT        => $timeout,
                    CURLOPT_CONNECTTIMEOUT => 4,
                    CURLOPT_HTTPHEADER     => [
                        'Authorization: ' . $this->vapidHeader($t['endpoint']),
                        'Content-Encoding: aes128gcm',
                        'Content-Type: application/octet-stream',
                        'Content-Length: ' . strlen($body),
                        'TTL: ' . $ttl,
                        'Urgency: ' . $urgency,
                    ],
                ]);
                curl_multi_add_handle($multi, $ch);
                $handles[$i] = $ch;
            } catch (\Throwable $e) {
                log_message('warning', '[WebPush] no se pudo preparar el envío: ' . $e->getMessage());
            }
        }

        do {
            $status = curl_multi_exec($multi, $running);
            if ($running) {
                curl_multi_select($multi, 1.0);
            }
        } while ($running && $status === CURLM_OK);

        foreach ($handles as $i => $ch) {
            $codes[$i] = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
            curl_multi_remove_handle($multi, $ch);
            curl_close($ch);
        }
        curl_multi_close($multi);

        return $codes;
    }

    // ── Utilidades ───────────────────────────────────────────────────────

    public static function b64uEncode(string $bin): string
    {
        return rtrim(strtr(base64_encode($bin), '+/', '-_'), '=');
    }

    public static function b64uDecode(string $s): string
    {
        return (string) base64_decode(strtr($s, '-_', '+/') . str_repeat('=', (4 - strlen($s) % 4) % 4));
    }

    private static function pad32(string $n): string
    {
        return str_pad($n, 32, "\0", STR_PAD_LEFT);
    }

    private static function pem(string $label, string $der): string
    {
        return "-----BEGIN $label-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END $label-----\n";
    }

    private static function privatePem(string $privRaw, string $pubRaw): string
    {
        return self::pem('EC PRIVATE KEY', hex2bin(self::SEC1_P256_PREFIX) . $privRaw . hex2bin(self::SEC1_P256_MIDDLE) . substr($pubRaw, 1));
    }
}
