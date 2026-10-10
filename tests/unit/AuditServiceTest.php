<?php

use App\Services\AuditService as A;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * «Nada se borra» — partes puras del registro de auditoría.
 */
final class AuditServiceTest extends CIUnitTestCase
{
    public function testChangesSoloDevuelveLoQueCambia(): void
    {
        [$b, $a] = A::changes(
            ['id' => '7', 'sessions_remaining' => '3', 'expires_at' => '2026-10-31', 'notes' => null],
            ['sessions_remaining' => 2, 'expires_at' => '2026-10-31', 'notes' => null]
        );
        $this->assertSame(['sessions_remaining' => '3'], $b);
        $this->assertSame(['sessions_remaining' => 2], $a);
    }

    public function testChangesComparaComoTextoSinFalsosPositivos(): void
    {
        [$b, $a] = A::changes(['price' => '45.00', 'active' => '1'], ['price' => '45.00', 'active' => 1]);
        $this->assertSame([], $b);
        $this->assertSame([], $a);
    }

    public function testChangesDistingueNullDeCadenaVacia(): void
    {
        [$b, $a] = A::changes(['notes' => null], ['notes' => '']);
        $this->assertSame(['notes' => null], $b);
        $this->assertSame(['notes' => ''], $a);
    }

    public function testChangesCampoNuevoCuentaComoCambio(): void
    {
        [$b, $a] = A::changes([], ['voided_at' => '2026-10-10 10:00:00']);
        $this->assertSame(['voided_at' => null], $b);
        $this->assertSame(['voided_at' => '2026-10-10 10:00:00'], $a);
    }

    public function testSanitizeQuitaSecretos(): void
    {
        $row = A::sanitize(['id' => 3, 'name' => 'X', 'password' => '$2y$10$abc', 'token' => 't']);
        $this->assertSame(['id' => 3, 'name' => 'X'], $row);
        $this->assertNull(A::sanitize(null));
    }

    public function testEtiquetas(): void
    {
        $this->assertSame('Anulación', A::label(A::VOID));
        $this->assertSame('Borrado bloqueado', A::label(A::BLOCKED));
        $this->assertSame('otra', A::label('otra'));
    }
}
