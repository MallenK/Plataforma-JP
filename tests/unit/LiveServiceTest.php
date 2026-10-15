<?php

use App\Services\LiveService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Sondeo único (GET /live/ping): el sello de cambio de cada zona. Solo la parte pura;
 * las consultas se prueban contra la BD real (ver docs del PR).
 */
final class LiveServiceTest extends CIUnitTestCase
{
    public function testStampChangesWhenSomethingIsModifiedAddedOrDeleted(): void
    {
        $base = LiveService::stamp('2026-10-15 10:00:00', 5);

        $this->assertSame('2026-10-15 10:00:00|5', $base);
        $this->assertNotSame($base, LiveService::stamp('2026-10-15 10:05:00', 5), 'modificado');
        $this->assertNotSame($base, LiveService::stamp('2026-10-15 10:00:00', 6), 'añadido');
        $this->assertNotSame($base, LiveService::stamp('2026-10-15 10:00:00', 4), 'borrado (el máximo no cambia, el recuento sí)');
    }

    public function testStampIsStableForTheSameData(): void
    {
        $this->assertSame(
            LiveService::stamp('2026-10-15 10:00:00', 5, '2026-10-15 09:00:00', 2),
            LiveService::stamp('2026-10-15 10:00:00', 5, '2026-10-15 09:00:00', 2)
        );
    }

    public function testStampHandlesEmptyTables(): void
    {
        $this->assertSame('-|0', LiveService::stamp(null, 0));
        $this->assertSame('-|0', LiveService::stamp('0', 0));
        $this->assertSame('-|0/-|0', LiveService::stamp(null, 0, null, 0));
    }

    public function testSecondPairParticipatesInTheStamp(): void
    {
        $this->assertNotSame(
            LiveService::stamp('2026-10-15 10:00:00', 5, '2026-10-15 09:00:00', 2),
            LiveService::stamp('2026-10-15 10:00:00', 5, '2026-10-15 09:30:00', 2)
        );
    }
}
