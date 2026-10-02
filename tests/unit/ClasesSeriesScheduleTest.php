<?php

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\ClasesService as Svc;

/**
 * TICKET-013 — Cómo se define una serie de clases (parte pura, sin BD):
 * patrón (días + inicio + nº de clases y/o fecha límite) o calendario clase a clase.
 * La vista previa de cobertura y la creación usan la MISMA función.
 */
final class ClasesSeriesScheduleTest extends CIUnitTestCase
{
    public function testPorNumeroDeClases(): void
    {
        $r = Svc::resolveSeriesDates(['recurrence_days' => [4], 'recurrence_start' => '2026-10-01', 'recurrence_count' => 4]);
        $this->assertTrue($r['ok']);
        $this->assertSame(['2026-10-01', '2026-10-08', '2026-10-15', '2026-10-22'], $r['dates']);
        $this->assertSame('2026-10-22', $r['end']);
        $this->assertNull($r['schedule']);
    }

    public function testPorFechaLimite(): void
    {
        $r = Svc::resolveSeriesDates(['recurrence_days' => [4], 'recurrence_start' => '2026-10-01', 'recurrence_end' => '2026-10-31']);
        $this->assertTrue($r['ok']);
        $this->assertCount(5, $r['dates']);
        $this->assertSame('2026-10-29', $r['end']);
    }

    public function testNumeroDeClasesYFechaLimiteGanaElPrimero(): void
    {
        $in = ['recurrence_days' => [4], 'recurrence_start' => '2026-10-01', 'recurrence_count' => 8, 'recurrence_end' => '2026-10-31'];
        $this->assertCount(5, Svc::resolveSeriesDates($in)['dates']);   // el límite llega antes
        $in['recurrence_count'] = 3;
        $this->assertCount(3, Svc::resolveSeriesDates($in)['dates']);   // manda el nº de clases
    }

    public function testErroresClaros(): void
    {
        $base = ['recurrence_days' => [4], 'recurrence_start' => '2026-10-01'];
        $this->assertFalse(Svc::resolveSeriesDates($base)['ok'], 'sin nº ni límite');
        $this->assertStringContainsString('cuántas clases', Svc::resolveSeriesDates($base)['error']);
        $this->assertFalse(Svc::resolveSeriesDates(['recurrence_start' => '2026-10-01', 'recurrence_count' => 3])['ok'], 'sin días');
        $this->assertFalse(Svc::resolveSeriesDates($base + ['recurrence_count' => 99])['ok'], 'más de 60');
        $this->assertFalse(Svc::resolveSeriesDates($base + ['recurrence_end' => '2026-09-01'])['ok'], 'límite antes del inicio');
        // Un límite que no contiene ningún día de los elegidos
        $r = Svc::resolveSeriesDates(['recurrence_days' => [1], 'recurrence_start' => '2026-10-01', 'recurrence_end' => '2026-10-02']);
        $this->assertFalse($r['ok'], 'rango sin ningún lunes');
    }

    public function testCalendarioClaseAClase(): void
    {
        $json = json_encode([
            ['date' => '2026-10-08', 'start' => '18:30', 'end' => '19:30'],
            ['date' => '2026-10-01', 'start' => '9:00',  'end' => ''],
        ]);
        $r = Svc::resolveSeriesDates(['custom_schedule' => $json]);
        $this->assertTrue($r['ok']);
        $this->assertSame(['2026-10-01', '2026-10-08'], $r['dates'], 'se ordena por fecha');
        $this->assertSame('09:00', $r['schedule'][0]['start']);
        $this->assertSame('10:00', $r['schedule'][0]['end'], 'sin hora de fin = inicio + 1 h');
        $this->assertSame('18:30', $r['schedule'][1]['start']);
        $this->assertSame([4], $r['days'], 'los días de la semana se deducen del calendario');
        $this->assertSame('2026-10-01', $r['start']);
        $this->assertSame('2026-10-08', $r['end']);
    }

    public function testCalendarioInvalidoSeRechazaConMensaje(): void
    {
        $bad = static fn(array $rows) => Svc::parseCustomSchedule(json_encode($rows));

        $this->assertFalse(Svc::parseCustomSchedule('no es json')['ok']);
        $this->assertFalse($bad([])['ok']);
        $this->assertStringContainsString('fecha válida', $bad([['date' => '2026-13-45', 'start' => '09:00']])['error']);
        $this->assertStringContainsString('hora de inicio', $bad([['date' => '2026-10-01', 'start' => '']])['error']);
        $this->assertStringContainsString('hora de fin', $bad([['date' => '2026-10-01', 'start' => '10:00', 'end' => '09:00']])['error']);
        $this->assertStringContainsString('mismo día', $bad([
            ['date' => '2026-10-01', 'start' => '09:00'], ['date' => '2026-10-01', 'start' => '17:00'],
        ])['error']);

        $many = array_map(fn($i) => ['date' => date('Y-m-d', strtotime("2026-10-01 +{$i} day")), 'start' => '09:00'], range(0, 60));
        $this->assertStringContainsString('máximo 60', $bad($many)['error']);
    }

    public function testRecurrenceDatesLimitedDirecto(): void
    {
        $this->assertSame([], \App\Services\BonoCoverageService::recurrenceDatesLimited([4], '2026-10-01', 0, null));
        $this->assertSame(['2026-10-01'], \App\Services\BonoCoverageService::recurrenceDatesLimited([4], '2026-10-01', 0, '2026-10-05'));
    }
}
