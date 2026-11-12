<?php

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\ClasesService;
use App\Services\ClassNoticeService;
use App\Services\StudentHistoryService;

/**
 * Aviso de ausencia → petición de la familia → el equipo retira el aviso / reactiva la clase.
 * Regla del negocio: el alumno SOLO puede avisar o pedir; cambiar el estado lo hace siempre el equipo.
 * Piezas puras (sin BD) + que rutas y pantallas estén bien cableadas.
 */
final class AbsenceNoticeFlowTest extends CIUnitTestCase
{
    private function src(string $rel): string
    {
        return (string) file_get_contents(ROOTPATH . $rel);
    }

    private const TODAY = '2026-11-16';

    private function session(string $status, string $date = '2026-11-20'): array
    {
        return ['status' => $status, 'session_date' => $date];
    }

    public function testRecoverMessageIsRecognisedByItsPrefix(): void
    {
        $this->assertTrue(ClasesService::isRecoverMessage(ClasesService::RECOVER_PREFIX . '. «al final sí»'));
        $this->assertFalse(ClasesService::isRecoverMessage('Llegaré tarde a la clase.'));
        $this->assertFalse(ClasesService::isRecoverMessage(null));
    }

    public function testCanRequestRecoverOnCancelledFutureClass(): void
    {
        $this->assertTrue(ClasesService::canRequestRecover($this->session('cancelled'), ['attendance' => 'pending'], self::TODAY));
    }

    public function testCanRequestRecoverOnScheduledClassOnlyWithANotice(): void
    {
        $this->assertTrue(ClasesService::canRequestRecover($this->session('scheduled'), ['student_noted_at' => '2026-11-15 10:00:00'], self::TODAY));
        $this->assertTrue(ClasesService::canRequestRecover($this->session('scheduled'), ['student_note' => 'Enfermo'], self::TODAY));
        $this->assertFalse(ClasesService::canRequestRecover($this->session('scheduled'), ['attendance' => 'pending'], self::TODAY), 'sin aviso no hay nada que recuperar');
    }

    public function testCannotRequestRecoverOfPastOrClosedClassOrWhenNotEnrolled(): void
    {
        $this->assertFalse(ClasesService::canRequestRecover($this->session('cancelled', '2026-11-10'), ['x' => 1], self::TODAY), 'ya pasó');
        $this->assertFalse(ClasesService::canRequestRecover($this->session('completed'), ['student_noted_at' => '2026-11-15 10:00:00'], self::TODAY), 'cerrada');
        $this->assertFalse(ClasesService::canRequestRecover($this->session('cancelled'), null, self::TODAY), 'no está en la clase');
        $this->assertTrue(ClasesService::canRequestRecover($this->session('cancelled', self::TODAY), ['x' => 1], self::TODAY), 'la de hoy aún cuenta');
    }

    public function testNoticeTextsExplainWhatHappened(): void
    {
        $s = ['title' => 'Individual', 'session_date' => '2026-11-20', 'start_time' => '17:00:00'];
        $r = ClassNoticeService::reactivatedMessage($s, 'La familia lo pidió');
        $this->assertStringContainsString('reactivada', $r['title']);
        $this->assertStringContainsString('La familia lo pidió', $r['body']);

        $forStudent = ClassNoticeService::absenceWithdrawnMessage($s, 'Leo', 'viene', true);
        $this->assertStringContainsString('sigue en pie', $forStudent['title']);
        $this->assertStringNotContainsString('Motivo', $forStudent['body'], 'al alumno no se le enseña el motivo interno');

        $forStaff = ClassNoticeService::absenceWithdrawnMessage($s, 'Leo', 'viene', false);
        $this->assertStringContainsString('Leo', $forStaff['title']);
        $this->assertStringContainsString('Motivo: viene', $forStaff['body']);
    }

    public function testHistoryKeepsWhatTheStudentHadNoticedAfterTheWithdrawal(): void
    {
        $before = json_encode(['student_noted_at' => '2026-11-15 10:30:00', 'student_note' => 'Enfermo: fiebre',
                               'student_message' => ClasesService::RECOVER_PREFIX . '.', 'bono_deducted_at' => '2026-11-15 11:00:00']);
        $t = StudentHistoryService::describeNoticeWithdrawal($before, 'La familia confirma');
        $this->assertStringContainsString('15/11/2026 10:30', $t);
        $this->assertStringContainsString('fiebre', $t);
        $this->assertStringContainsString('pedido recuperar', $t);
        $this->assertStringContainsString('se devolvió la sesión', $t);
        $this->assertStringContainsString('motivo: La familia confirma', $t);
    }

    public function testRoutesAreProtectedByRole(): void
    {
        $r = $this->src('app/Config/Routes.php');
        $this->assertMatchesRegularExpression("#clases/\\(:num\\)/recuperar'.+?ClasesController::requestRecover.+?'role:player'#s", $r, 'solo el alumno pide');
        $this->assertMatchesRegularExpression("#clases/\\(:num\\)/retirar-aviso'.+?withdrawAbsenceNotice.+?role:superadmin,admin,staff,coach#s", $r, 'solo el equipo retira el aviso');
    }

    public function testStudentCannotChangeTheClassFromHisScreen(): void
    {
        $v = $this->src('app/Views/clases/_mi_clase.php');
        $this->assertStringContainsString('/recuperar', $v);
        $this->assertStringNotContainsString('/reabrir', $v, 'el alumno no reabre');
        $this->assertStringNotContainsString('/retirar-aviso', $v, 'el alumno no retira su aviso');
        $this->assertStringContainsString('tú no puedes deshacer el aviso', $v);
    }

    public function testTeamNeedsAReasonToReactivateAndToWithdraw(): void
    {
        $v = $this->src('app/Views/clases/show.php');
        $this->assertMatchesRegularExpression('#id="reactivate-reason"[^>]*required#', $v);
        $this->assertMatchesRegularExpression('#id="withdraw-reason"[^>]*required#', $v);
        $this->assertStringNotContainsString('data-ru-confirm="¿Reactivar esta sesión cancelada?"', $v, 'ya no se reactiva con un simple confirmar');

        $c = $this->src('app/Services/ClasesService.php');
        $this->assertStringContainsString("Indica el motivo para reactivar la clase.", $c, 'el servidor también lo exige');
        $this->assertStringContainsString("Indica el motivo para retirar el aviso.", $c);
    }

    public function testWithdrawnNoticesAreNeverDeletedWithoutATrace(): void
    {
        $c = $this->src('app/Services/ClasesService.php');
        $this->assertMatchesRegularExpression("#AuditService::record\\('student_notice'#", $c, 'lo retirado queda en auditoría');
        $h = $this->src('app/Services/StudentHistoryService.php');
        $this->assertStringContainsString("'student_notice'", $h, 'y sale en el historial del alumno');
    }
}
