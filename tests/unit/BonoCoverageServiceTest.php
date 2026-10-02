<?php

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\BonoCoverageService as Cov;

/**
 * TICKET-013 — Cobertura de bono de las clases (parte pura, sin BD).
 *
 * Caso real: a "Aaron Alonso" se le asignó un bono de 2 sesiones y se creó
 * una serie semanal de un mes (5 jueves). El sistema generó las 5 sin avisar.
 */
final class BonoCoverageServiceTest extends CIUnitTestCase
{
    private const THURSDAYS = ['2026-10-01', '2026-10-08', '2026-10-15', '2026-10-22', '2026-10-29'];

    private function statuses(array $alloc): array
    {
        return array_values(array_map(fn($a) => $a['status'], $alloc));
    }

    public function testRecurrenceDatesGeneraLosJuevesDeOctubre(): void
    {
        $this->assertSame(self::THURSDAYS, Cov::recurrenceDates([4], '2026-10-01', '2026-10-31'));
    }

    public function testRecurrenceDatesVariosDiasYOrden(): void
    {
        $this->assertSame(
            ['2026-10-05', '2026-10-07', '2026-10-12'],
            Cov::recurrenceDates([3, 1], '2026-10-05', '2026-10-13')
        );
    }

    public function testRecurrenceDatesRangoInvalidoNoRompe(): void
    {
        $this->assertSame([], Cov::recurrenceDates([1], 'no-fecha', '2026-10-13'));
        $this->assertSame([], Cov::recurrenceDates([], '2026-10-01', '2026-10-31'));
    }

    public function testRecurrenceDatesByCountDaLasPrimerasN(): void
    {
        // "Quiero 4 clases": jueves desde el 1/10 → 1, 8, 15, 22 (sin calcular fecha de fin).
        $this->assertSame(
            ['2026-10-01', '2026-10-08', '2026-10-15', '2026-10-22'],
            Cov::recurrenceDatesByCount([4], '2026-10-01', 4)
        );
    }

    public function testRecurrenceDatesByCountConVariosDias(): void
    {
        // Lun+Mié desde el lunes 5/10: 5, 7, 12
        $this->assertSame(['2026-10-05', '2026-10-07', '2026-10-12'], Cov::recurrenceDatesByCount([1, 3], '2026-10-05', 3));
    }

    public function testRecurrenceDatesByCountRespetaTopesYEntradasInvalidas(): void
    {
        $this->assertCount(Cov::MAX_SERIES_SESSIONS, Cov::recurrenceDatesByCount([1, 2, 3, 4, 5, 6, 7], '2026-10-01', 500));
        $this->assertSame([], Cov::recurrenceDatesByCount([4], '2026-10-01', 0));
        $this->assertSame([], Cov::recurrenceDatesByCount([], '2026-10-01', 3));
        $this->assertSame([], Cov::recurrenceDatesByCount([4], 'no-fecha', 3));
    }

    public function testCasoAaronBonoDeDosCubreSoloDosDeCinco(): void
    {
        $alloc = Cov::allocate(self::THURSDAYS, [], [['id' => 1, 'remaining' => 2, 'expires_at' => null]]);

        $this->assertSame(['covered', 'covered', 'uncovered', 'uncovered', 'uncovered'], $this->statuses($alloc));
        $s = Cov::summarize($alloc);
        $this->assertSame(5, $s['requested']);
        $this->assertSame(2, $s['covered']);
        $this->assertSame(3, $s['uncovered']);
        $this->assertSame('2026-10-15', $s['first_uncovered']);
    }

    public function testSinBonosTodoSinCubrir(): void
    {
        $alloc = Cov::allocate(self::THURSDAYS, [], []);
        $this->assertSame(['uncovered', 'uncovered', 'uncovered', 'uncovered', 'uncovered'], $this->statuses($alloc));
    }

    public function testDosBonosEnColaSumanSaldo(): void
    {
        $alloc = Cov::allocate(self::THURSDAYS, [], [
            ['id' => 1, 'remaining' => 2, 'expires_at' => null],
            ['id' => 2, 'remaining' => 4, 'expires_at' => null],
        ]);
        $this->assertSame(['covered', 'covered', 'covered', 'covered', 'covered'], $this->statuses($alloc));
        $this->assertSame(1, $alloc['2026-10-01']['bono_id']);
        $this->assertSame(2, $alloc['2026-10-15']['bono_id']);
    }

    public function testSesionesYaProgramadasOcupanSaldoPorFecha(): void
    {
        // Ya tiene una clase suelta el 30/09 (antes de la serie): ocupa 1 de las 2.
        $alloc = Cov::allocate(self::THURSDAYS, ['2026-09-30'], [['id' => 1, 'remaining' => 2, 'expires_at' => null]]);
        $this->assertSame(['covered', 'uncovered', 'uncovered', 'uncovered', 'uncovered'], $this->statuses($alloc));
    }

    public function testSesionYaProgramadaPosteriorNoQuitaLoAnterior(): void
    {
        // Una clase suelta el 20/10 entra DESPUÉS de las del 1 y 8: la serie conserva su orden.
        $alloc = Cov::allocate(self::THURSDAYS, ['2026-10-20'], [['id' => 1, 'remaining' => 3, 'expires_at' => null]]);
        $this->assertSame(['covered', 'covered', 'covered', 'uncovered', 'uncovered'], $this->statuses($alloc));
        // 1, 8, 15 cubiertas por orden de fecha; el 20/10 (existente) ocupa la 4ª... pero solo hay 3.
    }

    public function testDeudasAbiertasSaldanPrimero(): void
    {
        $alloc = Cov::allocate(self::THURSDAYS, [], [['id' => 1, 'remaining' => 2, 'expires_at' => null]], 1);
        $this->assertSame(['covered', 'uncovered', 'uncovered', 'uncovered', 'uncovered'], $this->statuses($alloc));
    }

    public function testBonoQueCaducaAntesQuedaEnRiesgoNoSinCubrir(): void
    {
        // Saldo 3 pero caduca el 10/10: las sesiones del 15, 22... tienen saldo, pero en riesgo (ampliable).
        $alloc = Cov::allocate(array_slice(self::THURSDAYS, 0, 3), [], [['id' => 1, 'remaining' => 3, 'expires_at' => '2026-10-10']]);
        $this->assertSame(['covered', 'covered', 'at_risk'], $this->statuses($alloc));
        $this->assertSame('2026-10-10', $alloc['2026-10-15']['expires_at']);
        $this->assertSame(1, Cov::summarize($alloc)['at_risk']);
    }

    public function testPrefiereElBonoVigenteAEsaFecha(): void
    {
        // A caduca el 10/10 con 1 sesión; B no caduca. El 15/10 debe ir a B, sin riesgo.
        $alloc = Cov::allocate(['2026-10-15'], [], [
            ['id' => 1, 'remaining' => 1, 'expires_at' => '2026-10-10'],
            ['id' => 2, 'remaining' => 3, 'expires_at' => null],
        ]);
        $this->assertSame('covered', $alloc['2026-10-15']['status']);
        $this->assertSame(2, $alloc['2026-10-15']['bono_id']);
    }

    public function testBonoSinSesionesRestantesNoCubreNada(): void
    {
        $alloc = Cov::allocate(['2026-10-01'], [], [['id' => 1, 'remaining' => 0, 'expires_at' => null]]);
        $this->assertSame('uncovered', $alloc['2026-10-01']['status']);
    }

    public function testPayloadExponeResumenYFechas(): void
    {
        $alloc = Cov::allocate(self::THURSDAYS, [], [['id' => 1, 'remaining' => 2, 'expires_at' => null]]);
        $payload = Cov::payload([7 => [
            'player_id' => 7, 'name' => 'Aaron', 'bonos' => [['id' => 1, 'name' => 'Bono 2', 'remaining' => 2, 'expires_at' => null]],
            'never_had_bono' => false, 'debt_count' => 0, 'allocation' => $alloc, 'summary' => Cov::summarize($alloc),
        ]]);
        $this->assertCount(1, $payload);
        $this->assertSame('Aaron', $payload[0]['name']);
        $this->assertSame(3, $payload[0]['summary']['uncovered']);
        $this->assertSame('uncovered', $payload[0]['dates']['2026-10-29']);
        $this->assertNull($payload[0]['expiring_soon']);
    }
}
