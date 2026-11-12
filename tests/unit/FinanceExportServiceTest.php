<?php

use App\Services\FinanceExportService;
use CodeIgniter\Test\CIUnitTestCase;

/** @internal */
final class FinanceExportServiceTest extends CIUnitTestCase
{
    public function testReadmeListsEverySetWithItsRowCount(): void
    {
        $sets = [
            'cargos' => ['title' => 'Cargos', 'columns' => ['id'], 'rows' => [[1], [2]]],
            'gastos' => ['title' => 'Gastos', 'columns' => ['id'], 'rows' => []],
        ];
        $txt = FinanceExportService::readme('2026-10-01', '2026-11-02', true, $sets);

        $this->assertStringContainsString('2026-10-01 a 2026-11-02', $txt);
        $this->assertStringContainsString('nombres anonimizados', $txt);
        $this->assertStringContainsString('cargos — Cargos (2 filas)', $txt);
        $this->assertStringContainsString('gastos — Gastos (0 filas)', $txt);
    }

    public function testExportRoutesRequireFullFinanceAccess(): void
    {
        $routes = file_get_contents(APPPATH . 'Config/Routes.php');
        $this->assertMatchesRegularExpression("/group\('finanzas', \['filter' => \['auth', 'role:superadmin,admin', 'finance:full'\]\][^{]*\{[^}]*exportar\/descargar/s", $routes);
    }
}
