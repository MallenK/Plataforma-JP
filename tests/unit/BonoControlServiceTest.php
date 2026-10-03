<?php

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\BonoControlService as Ctl;
use App\Services\BonoLedgerService as Ledger;
use App\Models\NotificationModel;

/**
 * TICKET-013 — Ampliación de caducidad (15 / 30 / 60 días / fecha personalizada)
 * y etiquetas del libro de movimientos. Parte pura, sin BD.
 */
final class BonoControlServiceTest extends CIUnitTestCase
{
    private const TODAY = '2026-10-02';

    public function testPresetsSumanDiasSobreLaCaducidadVigente(): void
    {
        $this->assertSame('2026-10-25', Ctl::computeExtension('2026-10-10', 15, null, self::TODAY)['date']);
        $this->assertSame('2026-11-09', Ctl::computeExtension('2026-10-10', 30, null, self::TODAY)['date']);
        $this->assertSame('2026-12-09', Ctl::computeExtension('2026-10-10', 60, null, self::TODAY)['date']);
    }

    public function testBonoYaCaducadoAmpliaDesdeHoyNoDesdeElPasado(): void
    {
        // Caducó el 25/09: +15 cuenta desde hoy (02/10), no desde el 25/09.
        $this->assertSame('2026-10-17', Ctl::computeExtension('2026-09-25', 15, null, self::TODAY)['date']);
    }

    public function testPresetNoValidoSeRechaza(): void
    {
        $r = Ctl::computeExtension('2026-10-10', 45, null, self::TODAY);
        $this->assertFalse($r['ok']);
    }

    public function testBonoSinCaducidadNoSeAmplia(): void
    {
        $r = Ctl::computeExtension(null, 30, null, self::TODAY);
        $this->assertFalse($r['ok']);
        $this->assertStringContainsString('no tiene fecha de caducidad', $r['error']);
    }

    public function testFechaPersonalizadaValida(): void
    {
        $r = Ctl::computeExtension('2026-10-10', 'custom', '2026-12-31', self::TODAY);
        $this->assertTrue($r['ok']);
        $this->assertSame('2026-12-31', $r['date']);
    }

    public function testFechaPersonalizadaDebeSerPosteriorALaActual(): void
    {
        $this->assertFalse(Ctl::computeExtension('2026-10-10', 'custom', '2026-10-10', self::TODAY)['ok']);
        $this->assertFalse(Ctl::computeExtension('2026-10-10', 'custom', '2026-10-05', self::TODAY)['ok']);
    }

    public function testFechaPersonalizadaVaciaOBasuraSeRechaza(): void
    {
        $this->assertFalse(Ctl::computeExtension('2026-10-10', 'custom', null, self::TODAY)['ok']);
        $this->assertFalse(Ctl::computeExtension('2026-10-10', 'custom', 'no-es-fecha', self::TODAY)['ok']);
    }

    public function testFechaPersonalizadaNoPuedePasarDeDosAnios(): void
    {
        $this->assertFalse(Ctl::computeExtension('2026-10-10', 'custom', '2029-01-01', self::TODAY)['ok']);
    }

    public function testBonoCaducadoNoPermiteFechaPersonalizadaEnElPasado(): void
    {
        // Caducó el 25/09; 28/09 es posterior a la caducidad pero ya pasó: se rechaza.
        $this->assertFalse(Ctl::computeExtension('2026-09-25', 'custom', '2026-09-28', self::TODAY)['ok']);
    }

    public function testLibroEtiquetaTodosLosTiposYTolerarDesconocidos(): void
    {
        foreach ([Ledger::GRANTED, Ledger::DEDUCTED, Ledger::REFUNDED, Ledger::DEBT_SETTLED, Ledger::DEBT_RESOLVED,
                  Ledger::EXTENDED, Ledger::ADJUSTED, Ledger::EXPIRY_ALERT, Ledger::ASSIGNED] as $t) {
            [$label, $icon, $tone] = Ledger::label($t);
            $this->assertNotSame($t, $label, "Falta etiqueta para $t");
            $this->assertStringStartsWith('bi-', $icon);
        }
        $this->assertSame('algo_raro', Ledger::label('algo_raro')[0]);
    }

    public function testNotificacionDeBonoEnlazaAlDetalleDelBono(): void
    {
        $link = NotificationModel::sourceLink(['source_type' => NotificationModel::SOURCE_BONO, 'source_id' => 7]);
        $this->assertSame('bonos/7', $link['path']);
        $kept = NotificationModel::prepareSource(['source_type' => 'bono', 'source_id' => 7], true);
        $this->assertSame('bono', $kept['source_type']);
    }
}
