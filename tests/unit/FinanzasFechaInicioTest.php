<?php

use App\Services\FinanceReportService as R;
use App\Services\FinanceService as F;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Finanzas — fecha de inicio (`fin_start_date`, por defecto 1/10/2026): lo
 * anterior no cuenta. Reglas puras del recorte de periodos y meses.
 */
final class FinanzasFechaInicioTest extends CIUnitTestCase
{
    private const START = '2026-10-01';

    public function testPorDefectoEmpiezaElUnoDeOctubre(): void
    {
        $this->assertSame('2026-10-01', F::DEFAULT_START);
    }

    public function testFechaDeInicioValida(): void
    {
        $this->assertSame('2026-10-01', F::validStartDate('2026-10-01'));
        $this->assertNull(F::validStartDate('2026-02-30'));
        $this->assertNull(F::validStartDate('01/10/2026'));
        $this->assertNull(F::validStartDate(''));
        $this->assertNull(F::validStartDate(null));
    }

    public function testPeriodoPosteriorNoSeToca(): void
    {
        $p = R::clampToStart(R::period('2026-10', null, null, '2026-10-16'), self::START);
        $this->assertSame('2026-10-01', $p['from']);
        $this->assertSame('2026-10-31', $p['to']);
        $this->assertFalse($p['clamped']);
        $this->assertFalse($p['before_start']);
        $this->assertSame(self::START, $p['start']);
    }

    public function testRangoQueEmpiezaAntesSeRecorta(): void
    {
        $p = R::clampToStart(R::period(null, '2026-09-01', '2026-10-15', '2026-10-16'), self::START);
        $this->assertSame('2026-10-01', $p['from']);
        $this->assertSame('2026-10-15', $p['to']);
        $this->assertTrue($p['clamped']);
        $this->assertFalse($p['before_start']);
    }

    public function testPeriodoEnteroAnteriorNoDevuelveNada(): void
    {
        $p = R::clampToStart(R::period('2026-09', null, null, '2026-10-16'), self::START);
        $this->assertTrue($p['before_start']);
        // from > to: las consultas BETWEEN no devuelven filas
        $this->assertGreaterThan($p['to'], $p['from']);
    }

    public function testMesesDesdeElDeInicio(): void
    {
        $months = R::lastMonths(5, '2026-11-20');   // 2026-11 … 2026-07
        $this->assertSame(['2026-11', '2026-10'], R::monthsSince($months, self::START));
        $this->assertSame(['2026-11', '2026-10', '2026-09'], R::monthsSince($months, '2026-09-15'));
    }
}
