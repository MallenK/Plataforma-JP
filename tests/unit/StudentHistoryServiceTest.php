<?php

use App\Services\StudentHistoryService as H;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Historial completo del alumno — partes puras.
 */
final class StudentHistoryServiceTest extends CIUnitTestCase
{
    public function testFechaRealDelCargoOCobro(): void
    {
        // Registrado el mismo día: fecha y hora de registro
        $this->assertSame('2026-10-10 13:42:00', H::when('2026-10-10', '2026-10-10 13:42:00'));
        // Registrado otro día (arranque de Finanzas): la fecha real
        $this->assertSame('2026-09-14 00:00:00', H::when('2026-09-14', '2026-10-10 13:31:00'));
        $this->assertSame('2026-10-10 09:00:00', H::when(null, '2026-10-10 09:00:00'));
    }

    public function testDescribeCambiosDeAuditoria(): void
    {
        $this->assertSame('sesiones 5 → 4',
            H::describeChange('{"sessions_remaining":"5"}', '{"sessions_remaining":4}'));
        $this->assertSame('precio 100,00 € → 160,50 €; precio estimado 1 → 0',
            H::describeChange('{"price_cents":"10000","price_estimated":"1"}', '{"price_cents":16050,"price_estimated":0}'));
        $this->assertSame('', H::describeChange(null, null));
    }

    public function testFilasCsv(): void
    {
        $rows = H::toCsvRows([
            ['at' => '2026-10-10 13:42:00', 'cat' => 'economico', 'event' => 'Cobro', 'detail' => 'Bizum', 'cents' => 2000, 'who' => 'Ana', 'link' => null],
            ['at' => '2026-10-11 09:05:00', 'cat' => 'clases', 'event' => 'Clase', 'detail' => 'Presente', 'cents' => null, 'who' => null, 'link' => null],
        ]);
        $this->assertSame(['Fecha', 'Hora', 'Categoría', 'Evento', 'Detalle', 'Importe (€)', 'Hecho por'], $rows[0]);
        $this->assertSame(['10/10/2026', '13:42', 'Económico', 'Cobro', 'Bizum', '20,00', 'Ana'], $rows[1]);
        $this->assertSame(['11/10/2026', '09:05', 'Clases', 'Clase', 'Presente', '', ''], $rows[2]);
    }
}
