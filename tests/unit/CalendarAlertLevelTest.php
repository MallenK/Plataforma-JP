<?php

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\ClasesService;

/**
 * Nivel de alerta del calendario: azul (asignada, sin lista) / verde (lista pasada) /
 * naranja (aviso) / gris (cancelada). Solo asistencia y estado de la clase — NO bonos.
 * Lógica pura: ClasesService::alertLevel() no toca la BD.
 */
final class CalendarAlertLevelTest extends CIUnitTestCase
{
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
        return ClasesService::alertLevel($over + $base);
    }

    private function level(array $over = []): string
    {
        return $this->alert($over)['level'];
    }

    public function testAssignedWithoutListIsBlue(): void
    {
        $this->assertSame('pending', $this->level());
        $this->assertSame('#3b82f6', ClasesService::ALERT_COLORS['pending']);
    }

    public function testPastWithoutListStaysBlue(): void
    {
        $this->assertSame('pending', $this->level(['date' => '2026-10-01', 'end_time' => '10:00:00']));
    }

    public function testListTakenIsGreen(): void
    {
        $this->assertSame('ok', $this->level(['list_taken' => true, 'attendances' => ['present']]));
        $this->assertSame('ok', $this->level(['status' => 'completed', 'attendances' => ['present', 'present']]));
    }

    public function testStudentAbsenceNoticeIsOrangeBeforeList(): void
    {
        $r = $this->alert(['absence_notice' => true]);
        $this->assertSame('warn', $r['level']);
        $this->assertSame('#f59e0b', ClasesService::ALERT_COLORS['warn']);
        $this->assertStringContainsString('avisado', $r['reason']);
    }

    public function testRecordedAbsencesAreOrangeAfterList(): void
    {
        $this->assertSame('warn', $this->level(['list_taken' => true, 'attendances' => ['present', 'absent']]));
        $this->assertSame('warn', $this->level(['list_taken' => true, 'attendances' => ['unjustified']]));
    }

    public function testAttendanceBeforeListIsIgnored(): void
    {
        $this->assertSame('pending', $this->level(['attendances' => ['unjustified']]));
    }

    public function testNoCoachIsOrange(): void
    {
        $this->assertSame('warn', $this->level(['has_coach' => false]));
    }

    public function testCancelledIsGreyEvenWithProblems(): void
    {
        $this->assertSame('cancelled', $this->level(['status' => 'cancelled', 'has_coach' => false, 'absence_notice' => true]));
    }
}
