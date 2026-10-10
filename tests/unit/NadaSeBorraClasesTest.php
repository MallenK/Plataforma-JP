<?php

use App\Services\ClasesService as C;
use App\Services\ConfiguracionService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * «Nada se borra» (v1.33.0) — reglas puras de borrado de sesiones y de
 * quitar alumnos. Solo se borra planificación futura sin nada registrado.
 */
final class NadaSeBorraClasesTest extends CIUnitTestCase
{
    private const TODAY = '2026-10-10';

    private function session(array $o = []): array
    {
        return $o + ['status' => 'scheduled', 'session_date' => '2026-10-20', 'lista_pasada_at' => null];
    }

    private function player(array $o = []): array
    {
        return $o + ['attendance' => 'pending', 'bono_deducted_at' => null];
    }

    public function testSesionFuturaSinNadaSePuedeBorrar(): void
    {
        $this->assertNull(C::sessionDeletionBlocker($this->session(), [$this->player(), $this->player(['attendance' => 'confirmed'])], self::TODAY));
        $this->assertNull(C::sessionDeletionBlocker($this->session(['session_date' => self::TODAY]), [], self::TODAY));
    }

    public function testSesionPasadaNoSeBorra(): void
    {
        $this->assertNotNull(C::sessionDeletionBlocker($this->session(['session_date' => '2026-10-09']), [], self::TODAY));
    }

    public function testSesionCerradaOCanceladaNoSeBorra(): void
    {
        $this->assertNotNull(C::sessionDeletionBlocker($this->session(['status' => 'completed']), [], self::TODAY));
        $this->assertNotNull(C::sessionDeletionBlocker($this->session(['status' => 'cancelled']), [], self::TODAY));
    }

    public function testListaPasadaNoSeBorra(): void
    {
        $this->assertNotNull(C::sessionDeletionBlocker($this->session(['lista_pasada_at' => '2026-10-09 18:00:00']), [], self::TODAY));
    }

    public function testAsistenciaAvisoOBonoBloquean(): void
    {
        foreach (['present', 'absent', 'unjustified', 'declined'] as $att) {
            $this->assertNotNull(C::sessionDeletionBlocker($this->session(), [$this->player(['attendance' => $att])], self::TODAY), $att);
        }
        $this->assertNotNull(C::sessionDeletionBlocker($this->session(), [$this->player(['bono_deducted_at' => '2026-10-09 10:00:00'])], self::TODAY));
    }

    public function testQuitarAlumnoDeSesionFuturaSinNada(): void
    {
        $this->assertNull(C::playerRemovalBlocker($this->session(), $this->player(), self::TODAY));
        $this->assertNull(C::playerRemovalBlocker($this->session(), null, self::TODAY));
    }

    public function testNoSeQuitaAlumnoConHistoricoOSesionPasada(): void
    {
        $this->assertNotNull(C::playerRemovalBlocker($this->session(), $this->player(['attendance' => 'declined']), self::TODAY));
        $this->assertNotNull(C::playerRemovalBlocker($this->session(['session_date' => '2026-10-01']), $this->player(), self::TODAY));
        $this->assertNotNull(C::playerRemovalBlocker($this->session(['status' => 'completed']), $this->player(), self::TODAY));
    }

    public function testResumenDeHistoricoDeUsuario(): void
    {
        $this->assertSame('72 clases impartidas, 1 movimientos de bono',
            ConfiguracionService::historySummary(['clases impartidas' => 72, 'movimientos de bono' => 1]));
        $this->assertSame('', ConfiguracionService::historySummary([]));
    }
}
