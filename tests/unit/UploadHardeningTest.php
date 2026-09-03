<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Fija las decisiones de la auditoría de seguridad 2026-09 sobre subida de
 * archivos, para que una refactorización futura no reintroduzca el RCE:
 *  - lista blanca de EXTENSIÓN obligatoria (no basta el MIME),
 *  - adjuntos privados guardados FUERA de public/ (WRITEPATH),
 *  - servido siempre por controlador con resolución segura del path.
 */
final class UploadHardeningTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper('upload');
    }

    private function src(string $rel): string
    {
        return file_get_contents(APPPATH . $rel);
    }

    // ── upload_allowed_extension() ──────────────────────────────────

    public function testExtensionWhitelistAceptaFormatosEsperados(): void
    {
        $allowed = ['jpg', 'png', 'pdf', 'mp4'];
        $this->assertSame('jpg', upload_allowed_extension('jpg', $allowed));
        $this->assertSame('pdf', upload_allowed_extension('PDF', $allowed));
    }

    public function testExtensionWhitelistRechazaEjecutables(): void
    {
        $allowed = ['jpg', 'png', 'pdf', 'gif', 'mp4', 'txt'];
        foreach (['php', 'php5', 'phtml', 'phar', 'pht', 'sh', 'cgi', 'PHP'] as $bad) {
            $this->assertNull(upload_allowed_extension($bad, $allowed), "{$bad} no debe pasar");
        }
    }

    public function testExtensionWhitelistRechazaExtensionesDobles(): void
    {
        $this->assertNull(upload_allowed_extension('php.jpg', ['jpg']));
        $this->assertNull(upload_allowed_extension('', ['jpg']));
    }

    // ── upload_resolve_stored() ────────────────────────────────────

    public function testResolveStoredRechazaTraversalYRutasRaras(): void
    {
        $this->assertNull(upload_resolve_stored('../../.env'));
        $this->assertNull(upload_resolve_stored('uploads/mensajes/../../../etc/passwd'));
        $this->assertNull(upload_resolve_stored('/etc/passwd'));
        $this->assertNull(upload_resolve_stored('uploads/mensajes/'));
        $this->assertNull(upload_resolve_stored(null));
    }

    public function testResolveStoredEncuentraFicheroEnWritepath(): void
    {
        $dir = upload_private_dir('mensajes');
        @mkdir($dir, 0775, true);
        $name = 'test_' . bin2hex(random_bytes(6)) . '.txt';
        file_put_contents($dir . $name, 'ok');

        try {
            $resolved = upload_resolve_stored('uploads/mensajes/' . $name);
            $this->assertNotNull($resolved);
            $this->assertStringStartsWith(rtrim(WRITEPATH, '/\\'), $resolved);
        } finally {
            @unlink($dir . $name);
        }
    }

    // ── Wiring de los controllers (escaneo de código) ──────────────

    /**
     * @dataProvider uploadHandlers
     */
    public function testCadaHandlerValidaExtensionYGuardaFueraDePublic(string $file): void
    {
        $code = $this->src($file);
        $this->assertStringContainsString('upload_allowed_extension(', $code, "{$file}: sin lista blanca de extensión");
        $this->assertStringContainsString('upload_private_dir(', $code, "{$file}: no guarda fuera de public/");
        $this->assertStringNotContainsString("FCPATH . 'uploads/", $code, "{$file}: sigue escribiendo en el webroot");
        $this->assertStringContainsString('random_bytes(16)', $code, "{$file}: nombre de fichero no aleatorio");
    }

    public static function uploadHandlers(): array
    {
        return [
            ['Controllers/MensajesController.php'],
            ['Controllers/NotificacionesController.php'],
            ['Controllers/TicketsController.php'],
        ];
    }

    public function testDownloadsResuelvenElPathDeFormaSegura(): void
    {
        foreach (['MensajesController', 'NotificacionesController', 'TicketsController'] as $c) {
            $code = $this->src("Controllers/{$c}.php");
            $this->assertStringContainsString('upload_resolve_stored(', $code, "{$c}: download() no usa la resolución segura");
            $this->assertStringNotContainsString('FCPATH . $', $code, "{$c}: sigue concatenando FCPATH con datos de BD");
        }
    }
}
