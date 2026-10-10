<?php

use App\Services\FinanceService as F;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Finanzas 2.0 — reglas puras de la cuenta del alumno: reparto de cobros,
 * descuentos y estado de cuenta con saldo acumulado.
 */
final class FinanceServiceTest extends CIUnitTestCase
{
    public function testCobroExactoPagaElCargoMasAntiguo(): void
    {
        $r = F::allocate(22500, [10 => 22500, 11 => 32000]);
        $this->assertSame([10 => 22500], $r['allocations']);
        $this->assertSame(0, $r['left']);
    }

    public function testCobroParcialYaPlazos(): void
    {
        $r = F::allocate(10000, [10 => 22500]);
        $this->assertSame([10 => 10000], $r['allocations']);
        $this->assertSame(0, $r['left']);
    }

    public function testCobroQueCubreVariosCargosYDejaSaldoAFavor(): void
    {
        $r = F::allocate(60000, [10 => 22500, 11 => 32000]);
        $this->assertSame([10 => 22500, 11 => 32000], $r['allocations']);
        $this->assertSame(5500, $r['left']);
    }

    public function testCargoPreferidoVaPrimero(): void
    {
        $r = F::allocate(32000, [10 => 22500, 11 => 32000], 11);
        $this->assertSame([11 => 32000], $r['allocations']);
    }

    public function testSinCargosPendientesTodoQuedaAFavor(): void
    {
        $this->assertSame(['allocations' => [], 'left' => 5000], F::allocate(5000, []));
    }

    public function testDescuentos(): void
    {
        $this->assertSame(0, F::parseDiscount('', 22500));
        $this->assertSame(2250, F::parseDiscount('10%', 22500));
        $this->assertSame(1500, F::parseDiscount('15', 22500));
        $this->assertSame(1550, F::parseDiscount('15,50', 22500));
        $this->assertSame(22500, F::parseDiscount('100%', 22500));     // cortesía
        $this->assertSame(22500, F::parseDiscount('300', 22500));      // nunca más que la tarifa
        $this->assertSame(0, F::parseDiscount('abc', 22500));
    }

    public function testEstadoDeCuentaConSaldoAcumuladoYAnulados(): void
    {
        $lines = F::statement([
            ['date' => '2026-10-08', 'kind' => 'payment', 'amount' => 32000, 'seq' => 2],
            ['date' => '2026-09-14', 'kind' => 'charge',  'amount' => 22500, 'seq' => 1],
            ['date' => '2026-10-08', 'kind' => 'charge',  'amount' => 32000, 'seq' => 2],
            ['date' => '2026-09-14', 'kind' => 'payment', 'amount' => 22500, 'seq' => 1],
            ['date' => '2026-10-09', 'kind' => 'charge',  'amount' => 4500,  'seq' => 3, 'voided' => true],
        ]);
        $this->assertSame([22500, 0, 32000, 0, 0], array_column($lines, 'balance'));
        $this->assertSame('charge', $lines[0]['kind']);   // el cargo antes que su cobro el mismo día
    }
}
