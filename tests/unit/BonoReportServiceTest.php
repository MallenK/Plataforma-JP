<?php

use App\Services\BonoReportService as R;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Reglas de alerta del informe de bonos por alumno (parte pura, sin BD).
 */
final class BonoReportServiceTest extends CIUnitTestCase
{
    private const TODAY = '2026-10-08';

    private function alerts(array $row): array
    {
        return R::alertsFor($row + ['bonos_usable' => 0, 'saldo' => 0, 'scheduled' => 0, 'debts' => 0, 'next_expiry' => null], self::TODAY);
    }

    public function testAlumnoSanoNoTieneAlertas(): void
    {
        $this->assertSame([], $this->alerts(['bonos_usable' => 1, 'saldo' => 8, 'scheduled' => 3]));
    }

    public function testVariosBonosConSaldoAvisaSolapados(): void
    {
        $this->assertContains(R::ALERT_OVERLAP, $this->alerts(['bonos_usable' => 2, 'saldo' => 9]));
        $this->assertNotContains(R::ALERT_OVERLAP, $this->alerts(['bonos_usable' => 1, 'saldo' => 9]));
    }

    public function testSaldoBajoSoloSiTieneAlgunBono(): void
    {
        $this->assertContains(R::ALERT_LOW, $this->alerts(['bonos_usable' => 2, 'saldo' => 2]));
        $this->assertContains(R::ALERT_LOW, $this->alerts(['bonos_usable' => 1, 'saldo' => 1]));
        $this->assertNotContains(R::ALERT_LOW, $this->alerts(['bonos_usable' => 1, 'saldo' => 3]));
        $this->assertNotContains(R::ALERT_LOW, $this->alerts(['bonos_usable' => 0, 'saldo' => 0]), 'sin bono no es "saldo bajo"');
    }

    public function testSinSaldoConClasesProgramadas(): void
    {
        $this->assertSame([R::ALERT_EMPTY], $this->alerts(['bonos_usable' => 0, 'saldo' => 0, 'scheduled' => 2]));
    }

    public function testClasesPorEncimaDelSaldo(): void
    {
        $a = $this->alerts(['bonos_usable' => 1, 'saldo' => 4, 'scheduled' => 5]);
        $this->assertContains(R::ALERT_OVER, $a);
        $this->assertNotContains(R::ALERT_EMPTY, $a);
        $this->assertNotContains(R::ALERT_OVER, $this->alerts(['bonos_usable' => 1, 'saldo' => 5, 'scheduled' => 5]));
    }

    public function testClasesSinBono(): void
    {
        $this->assertContains(R::ALERT_DEBT, $this->alerts(['bonos_usable' => 1, 'saldo' => 6, 'debts' => 1]));
    }

    public function testCaducidadProximaSoloDentroDeLaVentana(): void
    {
        $this->assertContains(R::ALERT_EXPIRING, $this->alerts(['bonos_usable' => 1, 'saldo' => 6, 'next_expiry' => '2026-10-12']));
        $this->assertNotContains(R::ALERT_EXPIRING, $this->alerts(['bonos_usable' => 1, 'saldo' => 6, 'next_expiry' => '2026-12-01']));
        $this->assertNotContains(R::ALERT_EXPIRING, $this->alerts(['bonos_usable' => 1, 'saldo' => 6, 'next_expiry' => '2026-10-01']), 'ya caducado: no es "pronto"');
    }

    public function testTodasLasAlertasTienenEtiqueta(): void
    {
        $labels = R::alertLabels();
        foreach ([R::ALERT_OVERLAP, R::ALERT_LOW, R::ALERT_EMPTY, R::ALERT_OVER, R::ALERT_DEBT, R::ALERT_EXPIRING] as $k) {
            $this->assertArrayHasKey($k, $labels);
            $this->assertCount(3, $labels[$k]);
        }
    }
}
