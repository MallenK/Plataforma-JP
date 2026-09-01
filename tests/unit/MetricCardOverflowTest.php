<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Regresión visual: desbordamiento de texto en las tarjetas de métrica
 * de la ficha de alumno (POSICIÓN "Extremo/mediapunta" se salía de la
 * tarjeta y se solapaba con la contigua en móvil).
 *
 * No es testeable con render, así que se verifica que app.css conserva
 * las reglas de contención/partido de palabra.
 */
final class MetricCardOverflowTest extends CIUnitTestCase
{
    private string $css;

    protected function setUp(): void
    {
        parent::setUp();
        $this->css = file_get_contents(FCPATH . 'assets/css/app.css');
    }

    public function testMetricCardContieneElDesbordamiento(): void
    {
        $block = $this->ruleBlock('.metric-card');
        $this->assertStringContainsString('overflow: hidden', $block);
        $this->assertStringContainsString('min-width: 0', $block);
    }

    public function testMetricValuePermitePartirPalabrasLargas(): void
    {
        $block = $this->ruleBlock('.metric-value');
        $this->assertStringContainsString('overflow-wrap: anywhere', $block);
        $this->assertStringContainsString('word-break: break-word', $block);
    }

    public function testMetricIconNoSeEncoge(): void
    {
        $this->assertStringContainsString('flex-shrink: 0', $this->ruleBlock('.metric-icon'));
    }

    private function ruleBlock(string $selector): string
    {
        $pos = strpos($this->css, $selector . ' {');
        $this->assertNotFalse($pos, "No se encontró la regla {$selector} en app.css");
        $end = strpos($this->css, '}', $pos);
        return substr($this->css, $pos, $end - $pos);
    }
}
