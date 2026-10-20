<?php

use App\Services\CoachRankingService as RK;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Partes puras de los rankings de entrenadores (sin BD).
 *
 * @internal
 */
final class CoachRankingServiceTest extends CIUnitTestCase
{
    public function testPeriodDefaultsToLast90Days(): void
    {
        $p = RK::period([], '2026-10-20');
        $this->assertSame('90', $p['key']);
        $this->assertSame('2026-07-23', $p['from']);
        $this->assertSame('2026-10-20', $p['to']);
    }

    public function testPeriodPresets(): void
    {
        $this->assertSame(['2026-10-01', '2026-10-31'], [RK::period(['p' => 'mes'], '2026-10-20')['from'], RK::period(['p' => 'mes'], '2026-10-20')['to']]);
        $ant = RK::period(['p' => 'mes_ant'], '2026-10-20');
        $this->assertSame(['2026-09-01', '2026-09-30'], [$ant['from'], $ant['to']]);
        $this->assertSame('2026-01-01', RK::period(['p' => 'anio'], '2026-10-20')['from']);
        $this->assertSame('2026-09-21', RK::period(['p' => '30'], '2026-10-20')['from']);
        $this->assertSame('2000-01-01', RK::period(['p' => 'todo'], '2026-10-20')['from']);
    }

    public function testPeriodRangeIsValidatedAndOrdered(): void
    {
        $p = RK::period(['desde' => '2026-09-30', 'hasta' => '2026-09-01'], '2026-10-20');
        $this->assertSame('rango', $p['key']);
        $this->assertSame(['2026-09-01', '2026-09-30'], [$p['from'], $p['to']]);

        // Basura en el rango → cae al periodo por defecto, nunca a una fecha rara.
        $bad = RK::period(['desde' => "2026-09-01' OR 1=1", 'hasta' => 'x', 'p' => 'inventado'], '2026-10-20');
        $this->assertSame('90', $bad['key']);
    }

    public function testRate(): void
    {
        $this->assertSame(84.4, RK::rate(92, 109));
        $this->assertSame(50.0, RK::rate(1, 2));
        $this->assertNull(RK::rate(3, 0));
        $this->assertSame(0.0, RK::rate(0, 10));
    }

    public function testTopOrdersFiltersAndBreaksTiesByName(): void
    {
        $rows = [
            ['name' => 'Zoe',  'done' => 10, 'rate' => 90.0],
            ['name' => 'Ana',  'done' => 10, 'rate' => 95.0],
            ['name' => 'Bea',  'done' => 3,  'rate' => 100.0],
            ['name' => 'Null', 'done' => 8,  'rate' => null],
        ];
        $this->assertSame(['Ana', 'Zoe', 'Null'], array_column(RK::top($rows, 'done', 3), 'name'));
        // Sin base mínima no entra (Bea tiene 3 clases) y el null nunca entra.
        $this->assertSame(['Ana', 'Zoe'], array_column(RK::top($rows, 'rate', 3, true, fn($r) => $r['done'] >= 5), 'name'));
        $this->assertSame(['Bea'], array_column(RK::top($rows, 'done', 1, false), 'name'));
    }

    public function testHeatLevel(): void
    {
        $this->assertSame(0, RK::heatLevel(0, 10));
        $this->assertSame(0, RK::heatLevel(5, 0));
        $this->assertSame(1, RK::heatLevel(1, 100));
        $this->assertSame(4, RK::heatLevel(10, 10));
        $this->assertSame(2, RK::heatLevel(5, 10));
    }

    public function testPositionsRankAgainstTheTeamWithMinimumsAndTies(): void
    {
        $mk = fn($id, $done, $att, $unclosed, $students = 10, $closed = 10) => [
            'id' => $id, 'name' => "C$id", 'done' => $done, 'hours' => (float) $done, 'students' => $students,
            'att_rate' => $att, 'loyal_rate' => 50.0, 'on_time_rate' => 80.0, 'closed' => $closed, 'unclosed' => $unclosed,
        ];
        $rows = [$mk(1, 40, 90.0, 2), $mk(2, 40, 95.0, 0), $mk(3, 3, 100.0, 5), $mk(4, 10, 80.0, 2)];

        $p1 = RK::positions($rows, 1);
        $this->assertSame(1, $p1['done']['pos']);          // empatado en cabeza con el 2
        $this->assertSame(2, $p1['att_rate']['pos']);      // el 3 no entra (solo 3 clases)
        $this->assertSame(3, $p1['att_rate']['of']);
        $this->assertSame(1, $p1['unclosed']['pos'] === 1 || $p1['unclosed']['pos'] === 2 ? 1 : 0);
        $this->assertSame(2, $p1['unclosed']['pos']);      // menos sin cerrar es mejor: el 2 (0) va primero

        $p3 = RK::positions($rows, 3);
        $this->assertNull($p3['att_rate']['pos']);         // sin base mínima no puntúa
        $this->assertSame(100.0, $p3['att_rate']['value']);
    }
}
