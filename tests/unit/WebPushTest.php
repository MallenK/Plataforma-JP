<?php

use App\Libraries\WebPush;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Cifrado Web Push (RFC 8291) y firma VAPID (RFC 8292). Lógica pura: sin red ni BD.
 * El "navegador" receptor se implementa aquí aparte, con la clave privada del
 * suscriptor, para comprobar que lo que enviamos es descifrable de verdad.
 */
final class WebPushTest extends CIUnitTestCase
{
    private function decrypt(string $body, string $uaPrivRaw, string $uaPubRaw, string $auth): string|false
    {
        $salt  = substr($body, 0, 16);
        $rs    = unpack('N', substr($body, 16, 4))[1];
        $idLen = ord($body[20]);
        $asPub = substr($body, 21, $idLen);
        $ct    = substr($body, 21 + $idLen);

        $this->assertSame(4096, $rs);
        $this->assertSame(65, $idLen);

        $priv = openssl_pkey_get_private(self::sec1($uaPrivRaw, $uaPubRaw));
        $peer = openssl_pkey_get_public(self::spki($asPub));
        $ecdh = openssl_pkey_derive($peer, $priv, 32);

        $ikm   = hash_hkdf('sha256', $ecdh, 32, "WebPush: info\0" . $uaPubRaw . $asPub, $auth);
        $cek   = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
        $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);

        return openssl_decrypt(substr($ct, 0, -16), 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, substr($ct, -16));
    }

    private static function pem(string $label, string $der): string
    {
        return "-----BEGIN $label-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END $label-----\n";
    }

    private static function sec1(string $priv, string $pub): string
    {
        return self::pem('EC PRIVATE KEY', hex2bin('30770201010420') . $priv . hex2bin('a00a06082a8648ce3d030107a14403420004') . substr($pub, 1));
    }

    private static function spki(string $pub): string
    {
        return self::pem('PUBLIC KEY', hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $pub);
    }

    public function testEncryptedPayloadRoundTrips(): void
    {
        $ua   = WebPush::generateVapidKeys(); // un par EC cualquiera hace de navegador
        $auth = random_bytes(16);
        $msg  = json_encode(['title' => 'Nuevo mensaje de María', 'body' => 'Mañana ⚽ a las 18:00'], JSON_UNESCAPED_UNICODE);

        $body = WebPush::encrypt($msg, $ua['publicKey'], WebPush::b64uEncode($auth));

        $plain = $this->decrypt($body, WebPush::b64uDecode($ua['privateKey']), WebPush::b64uDecode($ua['publicKey']), $auth);
        $this->assertSame($msg . "\x02", $plain);
    }

    public function testEncryptIsRandomisedPerCall(): void
    {
        $ua = WebPush::generateVapidKeys();
        $a  = WebPush::encrypt('x', $ua['publicKey'], WebPush::b64uEncode(random_bytes(16)));
        $b  = WebPush::encrypt('x', $ua['publicKey'], WebPush::b64uEncode(random_bytes(16)));
        $this->assertNotSame($a, $b);
    }

    public function testRejectsMalformedSubscriptionKeys(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        WebPush::encrypt('x', WebPush::b64uEncode('corta'), WebPush::b64uEncode(random_bytes(16)));
    }

    public function testRejectsOversizedPayload(): void
    {
        $ua = WebPush::generateVapidKeys();
        $this->expectException(\InvalidArgumentException::class);
        WebPush::encrypt(str_repeat('a', 4000), $ua['publicKey'], WebPush::b64uEncode(random_bytes(16)));
    }

    public function testVapidHeaderIsAValidEs256Jwt(): void
    {
        $keys = WebPush::generateVapidKeys();
        $push = new WebPush($keys['publicKey'], $keys['privateKey'], 'mailto:test@example.com');

        $header = $push->vapidHeader('https://fcm.googleapis.com/fcm/send/abc', 1_800_000_000);
        $this->assertMatchesRegularExpression('/^vapid t=([\w-]+)\.([\w-]+)\.([\w-]+), k=' . preg_quote($keys['publicKey'], '/') . '$/', $header);

        preg_match('/t=([\w-]+)\.([\w-]+)\.([\w-]+),/', $header, $m);
        $claims = json_decode(WebPush::b64uDecode($m[2]), true);
        $this->assertSame('https://fcm.googleapis.com', $claims['aud']);
        $this->assertSame('mailto:test@example.com', $claims['sub']);
        $this->assertSame(1_800_000_000 + 43200, $claims['exp']);

        // La firma raw r||s (64 B) se convierte a DER y se verifica con la pública.
        $raw = WebPush::b64uDecode($m[3]);
        $this->assertSame(64, strlen($raw));
        $der = self::rawToDer($raw);
        $pub = openssl_pkey_get_public(self::spki(WebPush::b64uDecode($keys['publicKey'])));
        $this->assertSame(1, openssl_verify($m[1] . '.' . $m[2], $der, $pub, OPENSSL_ALGO_SHA256));
    }

    public function testDerToRawKeepsFixedLength(): void
    {
        // r corto (31 B) y s con bit alto (33 B con 0x00 delante)
        $r   = str_repeat("\x01", 31);
        $s   = "\x00" . str_repeat("\xff", 32);
        $der = "\x30" . chr(2 + 31 + 2 + 33) . "\x02" . chr(31) . $r . "\x02" . chr(33) . $s;

        $raw = WebPush::derToRaw($der);
        $this->assertSame(64, strlen($raw));
        $this->assertSame("\x00" . $r, substr($raw, 0, 32));
        $this->assertSame(str_repeat("\xff", 32), substr($raw, 32));
    }

    private static function rawToDer(string $raw): string
    {
        $int = static function (string $n): string {
            $n = ltrim($n, "\0");
            if ($n === '' || ord($n[0]) > 0x7f) {
                $n = "\0" . $n;
            }
            return "\x02" . chr(strlen($n)) . $n;
        };
        $body = $int(substr($raw, 0, 32)) . $int(substr($raw, 32));
        return "\x30" . chr(strlen($body)) . $body;
    }
}
