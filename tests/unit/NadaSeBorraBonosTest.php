<?php

use App\Services\BonoControlService as S;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * «Nada se borra» (v1.33.0) — reglas puras de bonos: precio congelado,
 * motivo obligatorio y validación de la edición.
 */
final class NadaSeBorraBonosTest extends CIUnitTestCase
{
    private const BONO = [
        'id' => 10, 'sessions_total' => '5', 'sessions_remaining' => '3',
        'expires_at' => '2026-10-31', 'notes' => null, 'voided_at' => null,
    ];

    public function testPrecioCongeladoEnCentimosSinErroresDeRedondeo(): void
    {
        $this->assertSame(['price_list_cents' => 22500, 'price_cents' => 22500, 'price_estimated' => 0],
            S::priceSnapshot(['price' => '225.00']));
        $this->assertSame(1999, S::priceSnapshot(['price' => '19.99'])['price_cents']);
        $this->assertSame(0, S::priceSnapshot([])['price_cents']);
    }

    public function testMotivoMinimo(): void
    {
        $this->assertFalse(S::validReason(null));
        $this->assertFalse(S::validReason('  ok '));
        $this->assertTrue(S::validReason('Error al emitir'));
    }

    public function testCambiarSaldoExigeMotivo(): void
    {
        $r = S::buildBonoEdit(self::BONO, ['sessions_remaining' => '2', 'expires_at' => '2026-10-31', 'notes' => '']);
        $this->assertTrue($r['ok']);
        $this->assertTrue($r['needsReason']);
        $this->assertSame(['sessions_remaining' => 2], $r['data']);
    }

    public function testCambiarCaducidadExigeMotivo(): void
    {
        $r = S::buildBonoEdit(self::BONO, ['sessions_remaining' => '3', 'expires_at' => '2026-11-15']);
        $this->assertTrue($r['needsReason']);
        $this->assertSame(['expires_at' => '2026-11-15'], $r['data']);
    }

    public function testSoloNotasNoExigeMotivo(): void
    {
        $r = S::buildBonoEdit(self::BONO, ['sessions_remaining' => '3', 'expires_at' => '2026-10-31', 'notes' => 'Pagado en efectivo']);
        $this->assertFalse($r['needsReason']);
        $this->assertSame(['notes' => 'Pagado en efectivo'], $r['data']);
    }

    public function testSinCambiosNoHayNadaQueGuardar(): void
    {
        $r = S::buildBonoEdit(self::BONO, ['sessions_remaining' => '3', 'expires_at' => '2026-10-31', 'notes' => '']);
        $this->assertSame([], $r['data']);
        $this->assertFalse($r['needsReason']);
    }

    public function testSaldoFueraDeRangoSeRechaza(): void
    {
        $this->assertFalse(S::buildBonoEdit(self::BONO, ['sessions_remaining' => '6'])['ok']);
        $this->assertFalse(S::buildBonoEdit(self::BONO, ['sessions_remaining' => '-1'])['ok']);
    }

    public function testFechaInvalidaSeRechaza(): void
    {
        $this->assertFalse(S::buildBonoEdit(self::BONO, ['expires_at' => 'mañana'])['ok']);
    }

    public function testBonoAnuladoNoSeEdita(): void
    {
        $r = S::buildBonoEdit(['voided_at' => '2026-10-10 10:00:00'] + self::BONO, ['notes' => 'x']);
        $this->assertFalse($r['ok']);
    }
}
