<?php

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\ClasesService;

/**
 * El alumno debe avisar de su ausencia con al menos 24 h de antelación al
 * INICIO de la clase (antes: antes de las 10:00 del día de la clase).
 * El aviso tardío sigue registrándose; solo se advierte. Lógica pura, sin BD.
 */
final class ClasesAbsenceNoticeTest extends CIUnitTestCase
{
    private function session(string $date = '2026-09-29', string $start = '17:00:00'): array
    {
        return ['session_date' => $date, 'start_time' => $start];
    }

    public function testDeadlineIs24HoursBeforeStart(): void
    {
        $deadline = ClasesService::absenceNoticeDeadline($this->session());

        $this->assertSame('2026-09-28 17:00:00', $deadline->format('Y-m-d H:i:s'));
    }

    public function testDeadlineCrossesMonthBoundary(): void
    {
        $deadline = ClasesService::absenceNoticeDeadline($this->session('2026-10-01', '09:30'));

        $this->assertSame('2026-09-30 09:30:00', $deadline->format('Y-m-d H:i:s'));
    }

    public function testNotLateWellInAdvance(): void
    {
        $now = new DateTimeImmutable('2026-09-25 12:00:00');

        $this->assertFalse(ClasesService::isLateAbsenceNotice($this->session(), $now));
    }

    public function testNotLateExactlyAtDeadline(): void
    {
        $now = new DateTimeImmutable('2026-09-28 17:00:00');

        $this->assertFalse(ClasesService::isLateAbsenceNotice($this->session(), $now));
    }

    public function testLateOneMinuteAfterDeadline(): void
    {
        $now = new DateTimeImmutable('2026-09-28 17:01:00');

        $this->assertTrue(ClasesService::isLateAbsenceNotice($this->session(), $now));
    }

    public function testLateTheDayBeforeEvenInTheMorning(): void
    {
        // Con la regla antigua (10:00 del día de la clase) esto NO era tardío.
        $now = new DateTimeImmutable('2026-09-28 18:00:00');

        $this->assertTrue(ClasesService::isLateAbsenceNotice($this->session(), $now));
    }

    public function testLateTheSameDayOfTheClass(): void
    {
        $now = new DateTimeImmutable('2026-09-29 08:00:00');

        $this->assertTrue(ClasesService::isLateAbsenceNotice($this->session(), $now));
    }

    public function testInvalidSessionTimeNeverFlagsLate(): void
    {
        $this->assertNull(ClasesService::absenceNoticeDeadline(['session_date' => '2026-09-29', 'start_time' => '']));
        $this->assertFalse(ClasesService::isLateAbsenceNotice(['session_date' => '', 'start_time' => '17:00']));
    }

    // ── Notificación al equipo (entrenadores de la clase + admin/superadmin) ──

    public function testNotificationIncludesStudentClassDateAndReason(): void
    {
        $n = ClasesService::buildAbsenceNotification(
            ['title' => 'Anillo Calvet', 'session_date' => '2026-09-29', 'start_time' => '17:00:00'],
            'Ana Calvet',
            'Tengo médico',
            false
        );

        $this->assertStringContainsString('Ana Calvet', $n['title']);
        $this->assertStringContainsString('"Anillo Calvet"', $n['body']);
        $this->assertStringContainsString('29/09/2026 a las 17:00', $n['body']);
        $this->assertStringContainsString('Motivo: "Tengo médico".', $n['body']);
        $this->assertStringNotContainsString('tardío', $n['body']);
    }

    public function testNotificationFlagsLateNoticeAndMissingReason(): void
    {
        $n = ClasesService::buildAbsenceNotification(
            ['title' => 'Clase', 'session_date' => '2026-09-29', 'start_time' => '17:00'],
            'Ana',
            '  ',
            true
        );

        $this->assertStringContainsString('No ha indicado motivo', $n['body']);
        $this->assertStringContainsString('Aviso tardío', $n['body']);
        $this->assertStringContainsString('24 horas', $n['body']);
    }

    // ── Vista: panel de plazo claro (verde a tiempo / rojo fuera de plazo) ──

    public function testViewShowsClearDeadlinePanelWithoutBrokenSentence(): void
    {
        $view = file_get_contents(APPPATH . 'Views/clases/show.php');

        $this->assertStringContainsString('Recuerda: los avisos deben enviarse con al menos', $view);
        $this->assertStringContainsString('Aún estás a tiempo de avisar', $view);
        $this->assertStringContainsString('Ya no estás a tiempo de avisar', $view);
        $this->assertStringContainsString("'is-late' : 'is-ok'", $view);
        // La frase antigua partida ("…, es decir, antes del dd/mm a las") ya no existe.
        $this->assertStringNotContainsString('es decir, antes del', $view);
        // El panel no debe ser .alert-jp (flex: partía el texto en columnas).
        $this->assertStringNotContainsString('class="alert-jp" style="background:rgba(245,158,11', $view);
    }
}
