<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Finanzas — ayudas «?» (tooltips): accesibles, escapadas y sin huecos.
 */
final class FinHelpTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper('finhelp');
    }

    public function testAyudaEsUnBotonAccesibleConTooltip(): void
    {
        $h = fin_help('cobrado');
        $this->assertStringContainsString('<button type="button" class="fin-help"', $h);
        $this->assertStringContainsString('data-bs-toggle="tooltip"', $h);
        $this->assertStringContainsString('aria-label="Ayuda: ', $h);
    }

    public function testTextoEscapado(): void
    {
        $h = fin_help('x', 'Texto con "comillas" y <etiquetas>');
        $this->assertStringNotContainsString('<etiquetas>', $h);
        $this->assertStringNotContainsString('"comillas"', $h);
    }

    public function testClaveDesconocidaNoPintaNada(): void
    {
        $this->assertSame('', fin_help('no-existe'));
    }

    public function testTodasLasAyudasUsadasEnLasVistasExisten(): void
    {
        $texts = fin_help_texts();
        $used  = [];
        foreach (glob(APPPATH . 'Views/{finanzas,bonos}/*.php', GLOB_BRACE) as $f) {
            preg_match_all("/fin_help\\('([a-z_]+)'\\s*[,)]/", file_get_contents($f), $m);
            $used = array_merge($used, $m[1]);
        }
        // claves construidas dinámicamente en movimientos: mov_<tipo>
        foreach (array_keys(\App\Services\FinanceReportService::MOVE_TYPES) as $t) {
            $used[] = 'mov_' . $t;
        }
        $this->assertNotEmpty($used);
        foreach (array_unique($used) as $k) {
            $this->assertArrayHasKey($k, $texts, "Falta el texto de ayuda «{$k}»");
        }
    }
}
