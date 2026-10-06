<?php

use App\Services\PushService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Reglas puras del push: qué endpoints se aceptan (anti-SSRF) y qué lleva el payload
 * (mismo destino que la campanita). El envío real y la BD se prueban a mano.
 */
final class PushServiceTest extends CIUnitTestCase
{
    /** @dataProvider allowedEndpoints */
    public function testAcceptsRealBrowserPushServices(string $endpoint): void
    {
        $this->assertTrue(PushService::endpointAllowed($endpoint));
    }

    public static function allowedEndpoints(): array
    {
        return [
            'chrome/edge/opera/samsung' => ['https://fcm.googleapis.com/fcm/send/abc:APA91b'],
            'firefox'                   => ['https://updates.push.services.mozilla.com/wpush/v2/gAAAA'],
            'firefox android'           => ['https://push.services.mozilla.com/wpush/v2/gAAAA'],
            'safari'                    => ['https://web.push.apple.com/QGx'],
            'edge windows'              => ['https://wns2-par02p.notify.windows.com/w/?token=BQY'],
        ];
    }

    /** @dataProvider forbiddenEndpoints */
    public function testRejectsAnythingElse(string $endpoint): void
    {
        $this->assertFalse(PushService::endpointAllowed($endpoint));
    }

    public static function forbiddenEndpoints(): array
    {
        return [
            'http plano'          => ['http://fcm.googleapis.com/fcm/send/abc'],
            'red interna'         => ['https://127.0.0.1/admin'],
            'metadatos cloud'     => ['https://169.254.169.254/latest/meta-data'],
            'localhost'           => ['https://localhost/x'],
            'sufijo engañoso'     => ['https://evilfcm.googleapis.com.attacker.com/x'],
            'host que solo acaba' => ['https://notfcm.googleapis.com.evil.io/x'],
            'credenciales'        => ['https://user:pw@fcm.googleapis.com/x'],
            'puerto raro'         => ['https://fcm.googleapis.com:8443/x'],
            'sin esquema'         => ['fcm.googleapis.com/x'],
            'vacío'               => [''],
        ];
    }

    public function testPayloadPointsToTheNotificationRedirectAndTrimsText(): void
    {
        $p = PushService::buildPayload(42, [
            'title'       => 'Nuevo mensaje de <b>Ana</b>',
            'body'        => str_repeat('hola ', 100),
            'source_type' => 'conversation',
            'source_id'   => 7,
        ]);

        $this->assertSame(42, $p['id']);
        $this->assertSame('notificaciones/42/ir', $p['url']);
        $this->assertSame('conversation', $p['kind']);
        $this->assertSame('Nuevo mensaje de Ana', $p['title']);
        $this->assertLessThanOrEqual(140, mb_strlen($p['body']));
        $this->assertStringEndsWith('…', $p['body']);
    }

    public function testPayloadWithoutSourceIsGeneralAndHasDefaultTitle(): void
    {
        $p = PushService::buildPayload(5, ['title' => '', 'body' => 'x']);
        $this->assertSame('general', $p['kind']);
        $this->assertSame('JP Preparation', $p['title']);
    }
}
