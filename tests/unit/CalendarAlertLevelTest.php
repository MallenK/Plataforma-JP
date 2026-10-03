<?php

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\ClasesService;

/**
 * Nivel de alerta del calendario: azul (pendiente) / verde / naranja /
 * rojo suave / gris. Solo asistencia y estado de la clase — NO bonos.
 * Lógica pura: ClasesService::alertLevel() no toca la BD.
 */
final class CalendarAlertLevelTest extends CIUnitTestCase
{
    private const NOW = '2026-10-10 12:00:00';

    private function alert(array $over = []): array
    {
        $base = [
            'status'      => 'scheduled',
            'date'        => '2026-10-20',
            'end_time'    => '19:00:00',
            'has_coach'   => true,
            'list_taken'  => false,
            'attendances' => [],
        ];
        return ClasesService::alertLevel($over + $base, strtotime(self::NOW));
    }

    private function level(array $over = []): string
    {
        return $this->alert($over)['level'];
    }

    public function testFutureClassIsPendingBlue(): void
    {
        $this->assertSame('pending', $this->level());
        $this->assertSame('#3b82f6', ClasesService::ALERT_COLORS['pending']);
    }

    public function testPendingAttendanceBeforeListIsIgnored(): void
    {
        $this->assertSame('pending', $this->level(['attendances' => ['unjustified']]));
    }

    public function testNoCoachIsWarn(): void
    {
        $this->assertSame('warn', $this->level(['has_coach' => false]));
    }

    public function testCancelledIsGreyEvenWithProblems(): void
    {
        $this->assertSame('cancelled', $this->level(['status' => 'cancelled', 'has_coach' => false]));
    }

    public function testPastWithoutListSameDayIsWarn(): void
    {
        $this->assertSame('warn', $this->level(['date' => '2026-10-10', 'end_time' => '10:00:00']));
    }

    public function testPastWithoutListOver24hIsDanger(): void
    {
        $this->assertSame('danger', $this->level(['date' => '2026-10-08', 'end_time' => '10:00:00']));
    }

    public function testCompletedAllPresentIsOk(): void
    {
        $this->assertSame('ok', $this->level(['status' => 'completed', 'date' => '2026-10-01', 'attendances' => ['present', 'present']]));
    }

    public function testJustifiedAbsenceIsWarn(): void
    {
        $this->assertSame('warn', $this->level(['status' => 'completed', 'date' => '2026-10-01', 'attendances' => ['present', 'absent']]));
    }

    public function testUnjustifiedAbsenceIsDanger(): void
    {
        $r = $this->alert(['status' => 'completed', 'date' => '2026-10-01', 'attendances' => ['absent', 'unjustified']]);
        $this->assertSame('danger', $r['level']);
        $this->assertStringContainsString('no justificada', $r['reason']);
    }

    public function testListTakenButNotClosedIsOk(): void
    {
        $this->assertSame('ok', $this->level(['date' => '2026-10-01', 'list_taken' => true, 'attendances' => ['present']]));
    }
}
