<?php

use App\Services\ClasesService as C;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Finanzas 2.0 — regla de la academia: falta sin justificar, o aviso con
 * menos de N horas, descuenta la sesión automáticamente (reversible).
 */
final class AutoDeductRuleTest extends CIUnitTestCase
{
    private const SESSION = ['session_date' => '2026-10-15', 'start_time' => '19:00:00'];

    public function testAvisoTardioYATiempo(): void
    {
        $this->assertTrue(C::isLateNotice('2026-10-15 08:00:00', '2026-10-15', '19:00:00', 24));   // 11 h antes
        $this->assertTrue(C::isLateNotice('2026-10-14 19:00:01', '2026-10-15', '19:00:00', 24));   // 23:59:59 antes
        $this->assertFalse(C::isLateNotice('2026-10-14 19:00:00', '2026-10-15', '19:00:00', 24));  // justo 24 h
        $this->assertFalse(C::isLateNotice('2026-10-12 10:00:00', '2026-10-15', '19:00:00', 24));
        $this->assertTrue(C::isLateNotice(null, '2026-10-15', '19:00:00', 24));                    // sin hora de aviso
        $this->assertTrue(C::isLateNotice('2026-10-15 20:00:00', '2026-10-15', '19:00:00', 24));   // avisó después de empezar
    }

    public function testHorasConfigurables(): void
    {
        $this->assertFalse(C::isLateNotice('2026-10-15 08:00:00', '2026-10-15', '19:00:00', 6));
        $this->assertTrue(C::isLateNotice('2026-10-15 14:00:00', '2026-10-15', '19:00:00', 6));
    }

    public function testMotivoDelDescuento(): void
    {
        $this->assertSame('Falta sin justificar', C::autoDeductReason(['attendance' => 'unjustified'], self::SESSION, 24));
        $this->assertSame('Aviso con menos de 24 h', C::autoDeductReason(['attendance' => 'declined', 'student_noted_at' => '2026-10-15 10:00:00'], self::SESSION, 24));
        $this->assertNull(C::autoDeductReason(['attendance' => 'declined', 'student_noted_at' => '2026-10-13 10:00:00'], self::SESSION, 24));
        $this->assertNull(C::autoDeductReason(['attendance' => 'declined', 'student_noted_at' => null, 'responded_at' => '2026-10-10 09:00:00'], self::SESSION, 24));
        foreach (['present', 'absent', 'confirmed', 'pending'] as $att) {
            $this->assertNull(C::autoDeductReason(['attendance' => $att], self::SESSION, 24), $att);
        }
    }

    public function testQueConsumeSesion(): void
    {
        $this->assertTrue(C::rowConsumesBono(['attendance' => 'present'], self::SESSION, 24));
        $this->assertTrue(C::rowConsumesBono(['attendance' => 'unjustified'], self::SESSION, 24));
        $this->assertTrue(C::rowConsumesBono(['attendance' => 'declined', 'student_noted_at' => '2026-10-15 12:00:00'], self::SESSION, 24));
        $this->assertFalse(C::rowConsumesBono(['attendance' => 'declined', 'student_noted_at' => '2026-10-11 12:00:00'], self::SESSION, 24));
        $this->assertFalse(C::rowConsumesBono(['attendance' => 'absent'], self::SESSION, 24));
    }
}
