<?php

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\ClasesService;

/**
 * Buscador de clases (nombre de clase / entrenador / jugador).
 *
 * `ClasesService::search()` corta antes de tocar la BD si la consulta
 * tiene menos de 2 caracteres, así que ese caso se testea sin @group db.
 */
final class ClasesSearchTest extends CIUnitTestCase
{
    private function search(string $q): array
    {
        $svc = (new \ReflectionClass(ClasesService::class))->newInstanceWithoutConstructor();
        return $svc->search($q, 1, 'admin');
    }

    public function testConsultaVaciaDevuelveVacio(): void
    {
        $this->assertSame([], $this->search(''));
    }

    public function testConsultaDeUnCaracterDevuelveVacio(): void
    {
        $this->assertSame([], $this->search('a'));
        $this->assertSame([], $this->search('  x  ')); // se recorta → 1 char
    }

    public function testElControladorExponeElEndpoint(): void
    {
        $this->assertTrue(
            method_exists(\App\Controllers\ClasesController::class, 'buscar'),
            'ClasesController::buscar() debe existir'
        );

        $routes = file_get_contents(APPPATH . 'Config/Routes.php');
        $this->assertStringContainsString("clases/api/buscar", $routes);
    }

    public function testLaVistaMonteElBuscador(): void
    {
        $html = file_get_contents(APPPATH . 'Views/clases/index.php');
        $this->assertStringContainsString('id="cal-search-input"', $html);
        $this->assertStringContainsString('/clases/api/buscar?q=', $html);
        // Debe buscar también por nombre de entrenador y de jugador (no solo título).
        $this->assertMatchesRegularExpression('/->like\(.cs\.title./', file_get_contents(APPPATH . 'Services/ClasesService.php'));
        $this->assertMatchesRegularExpression('/orLike\(.uc\.name./', file_get_contents(APPPATH . 'Services/ClasesService.php'));
        $this->assertMatchesRegularExpression('/orLike\(.up\.name./', file_get_contents(APPPATH . 'Services/ClasesService.php'));
    }
}
