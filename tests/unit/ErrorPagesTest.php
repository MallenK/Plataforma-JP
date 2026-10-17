<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Páginas de error: todas pasan por el mismo diseño (errors/_shell.php), están en
 * español, no dependen de CDN ni de la sesión y cada tipo de error dice lo que toca.
 */
final class ErrorPagesTest extends CIUnitTestCase
{
    private function render(string $view, array $data = []): string
    {
        return view($view, $data);
    }

    /** Vista de error de CodeIgniter con las variables que el manejador le inyecta. */
    private function renderCiView(string $file, array $vars = []): string
    {
        extract($vars, EXTR_SKIP);
        ob_start();
        include APPPATH . 'Views/errors/html/' . $file;
        return (string) ob_get_clean();
    }

    public function testCadaVistaDeErrorUsaElDisenoUnico(): void
    {
        foreach (['error_400', 'error_403', 'error_404', 'error_503', 'production', 'production_reportable'] as $f) {
            $src = file_get_contents(APPPATH . "Views/errors/html/{$f}.php");
            $this->assertStringContainsString("errors/_shell", $src, "{$f}.php debe usar el diseño único");
            $this->assertStringNotContainsString('lang(\'Errors.', $src, "{$f}.php ya no debe mostrar los textos en inglés de CodeIgniter");
        }
        $this->assertStringContainsString('errors/_shell', file_get_contents(APPPATH . 'Views/errors/error_403.php'));
    }

    public function test404EstaEnEspanolConSalida(): void
    {
        $html = $this->renderCiView('error_404.php', ['message' => 'Can\'t find a route']);
        $this->assertStringContainsString('lang="es"', $html);
        $this->assertStringContainsString('404', $html);
        $this->assertStringContainsString('No encontramos esa página', $html);
        $this->assertStringContainsString('Fuera de juego', $html);
        $this->assertMatchesRegularExpression('#href="[^"]*(login|dashboard)"#', $html, 'siempre hay un enlace de salida');
    }

    public function test403PorRolMuestraElMotivoYLaAlternativa(): void
    {
        $html = $this->render('errors/error_403', [
            'role' => 'admin', 'reason' => 'Las finanzas completas son solo para las personas autorizadas.',
            'altUrl' => '/finanzas/revision', 'altLabel' => 'Ir a la revisión de clases',
        ]);
        $this->assertStringContainsString('No tienes acceso a esta sección', $html);
        $this->assertStringContainsString('solo para las personas autorizadas', $html);
        $this->assertStringContainsString('Tu rol:', $html);
        $this->assertStringContainsString('Ir a la revisión de clases', $html);
        // esc(…, 'attr') codifica las barras (&#x2F;): el navegador las decodifica en el href
        $this->assertStringContainsString('href="/finanzas/revision"', html_entity_decode($html, ENT_QUOTES));
    }

    public function test403NoPintaHtmlInyectadoEnElMotivoNiElRol(): void
    {
        $html = $this->render('errors/error_403', ['role' => '<script>alert(1)</script>', 'reason' => '<img src=x onerror=alert(1)>']);
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('<img src=x onerror=alert(1)>', $html);
    }

    public function test500LlevaLaReferenciaYElBotonReintentar(): void
    {
        $html = $this->renderCiView('production_reportable.php', ['ref' => 'A3F9C2']);
        $this->assertStringContainsString('500', $html);
        $this->assertStringContainsString('A3F9C2', $html);
        $this->assertStringContainsString('Reintentar', $html);
        $this->assertStringContainsString('Algo ha fallado', $html);
    }

    public function test500EscapaLaReferencia(): void
    {
        $html = $this->renderCiView('production_reportable.php', ['ref' => '"><script>x()</script>']);
        $this->assertStringNotContainsString('<script>x()</script>', $html);
    }

    public function testElCsrfCaducadoSeExplicaYPermiteRecargar(): void
    {
        $html = $this->renderCiView('error_403.php', [
            'exception' => new \CodeIgniter\Security\Exceptions\SecurityException('x', 403),
            'message'   => 'x',
        ]);
        $this->assertStringContainsString('Esta página ha caducado', $html);
        $this->assertStringContainsString('Reintentar', $html);
    }

    public function testElDisenoEsAutonomo(): void
    {
        $src = file_get_contents(APPPATH . 'Views/errors/_shell.php');
        $this->assertStringNotContainsString('bootstrap', $src, 'sin dependencia de Bootstrap/CDN');
        $this->assertStringNotContainsString('bootstrap-icons', $src);
        $this->assertStringContainsString('prefers-reduced-motion', $src, 'respeta reducir movimiento');
        $this->assertStringContainsString('noindex', $src);
    }
}
