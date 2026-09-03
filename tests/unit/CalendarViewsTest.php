<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Regresión del calendario (TICKET-007). Cubre cosas que solo viven en el
 * markup/JS inline de las vistas (no testeable con render):
 *
 *  1. Vista Mes: las columnas se declaran con `minmax(0, 1fr)` para que la
 *     chip se recorte con ellipsis y las 7 columnas queden iguales en móvil.
 *  2. Vistas Semana/Día (clases solapadas, `CalOverlap.slotHtml`):
 *     - En móvil (`max-width: 768px`) → 1 columna: con 2+ clases en la franja
 *       se muestra directamente el botón "Ver todas".
 *     - En escritorio: Semana admite 2 columnas, Día hasta 6.
 *  3. Dashboard: el rango horario de Semana/Día se amplía si hay clases fuera
 *     de 07–20 (antes las de las 20:00 no se veían).
 *
 * El código de las vistas está duplicado en dashboard/index.php y
 * clases/index.php, así que se comprueban las dos donde aplica.
 */
final class CalendarViewsTest extends CIUnitTestCase
{
    private const DASHBOARD = APPPATH . 'Views/dashboard/index.php';
    private const CLASES    = APPPATH . 'Views/clases/index.php';

    /** @return array<string, array{0:string}> */
    public static function vistas(): array
    {
        return [
            'dashboard' => [self::DASHBOARD],
            'clases'    => [self::CLASES],
        ];
    }

    private function leer(string $ruta): string
    {
        $html = file_get_contents($ruta);
        $this->assertNotFalse($html, "No se pudo leer {$ruta}");

        return $html;
    }

    /**
     * @dataProvider vistas
     */
    public function testColumnasDelMesUsanMinmax(string $ruta): void
    {
        $html = $this->leer($ruta);

        foreach (['cal-month-headers', 'cal-month-grid'] as $selector) {
            $this->assertMatchesRegularExpression(
                '/\.' . $selector . '\s*\{[^}]*grid-template-columns:\s*repeat\(7,\s*minmax\(0,\s*1fr\)\)/s',
                $html,
                "{$selector} debe usar repeat(7, minmax(0, 1fr))"
            );
        }

        $this->assertDoesNotMatchRegularExpression(
            '/\.cal-month-(?:headers|grid)\s*\{[^}]*grid-template-columns:\s*repeat\(7,\s*1fr\)/s',
            $html,
            'La cuadrícula mensual no debe volver a repeat(7, 1fr)'
        );
    }

    /**
     * @dataProvider vistas
     */
    public function testSlotColapsaEnMovilYLimitaColumnasEnEscritorio(string $ruta): void
    {
        $html = $this->leer($ruta);

        // Móvil → 1 columna (botón "Ver todas" con 2+ clases); escritorio → opts.
        $this->assertStringContainsString("matchMedia('(max-width: 768px)')", $html);
        $this->assertStringContainsString('var maxCols = isMobile ? 1 : (opts.maxCols || 6)', $html);

        // Texto del botón colapsado.
        $this->assertStringContainsString("Ver todas &middot; ' + evts.length", $html);

        // Semana pide 2 columnas, Día hasta 6.
        $this->assertMatchesRegularExpression('/slotHtml\([^;]*maxCols:\s*2\s*\}/s', $html, 'Semana debe pedir maxCols: 2');
        $this->assertMatchesRegularExpression('/slotHtml\([^;]*maxCols:\s*6\s*\}/s', $html, 'Día debe pedir maxCols: 6');
    }

    public function testDashboardAmpliaElRangoHorarioConClasesTardias(): void
    {
        $html = $this->leer(self::DASHBOARD);

        // Helper de rango dinámico y su uso en Semana y Día.
        $this->assertStringContainsString('hourRange(evts)', $html);
        $this->assertSame(
            2,
            substr_count($html, 'this.hourRange('),
            'hourRange debe usarse en renderWeek y renderDay'
        );
        // Ya no debe quedar el rango fijo que ocultaba las clases de las 20:00.
        $this->assertStringNotContainsString('const HS=7, HE=20', $html);
    }
}
