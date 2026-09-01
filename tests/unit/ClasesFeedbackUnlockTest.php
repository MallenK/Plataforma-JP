<?php

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\ClasesService;

/**
 * El textarea "Después — Feedback" de la ficha de sesión estaba
 * bloqueado salvo que status === 'completed'. Debe poder escribirse
 * también cuando la clase se ha impartido (lista pasada) o hay algún
 * alumno marcado como presente.
 */
final class ClasesFeedbackUnlockTest extends CIUnitTestCase
{
    public function testBloqueadoEnSesionProgramadaSinAsistencia(): void
    {
        $session = [
            'status'          => 'scheduled',
            'lista_pasada_at' => null,
            'players'         => [
                ['attendance' => 'pending'],
                ['attendance' => 'confirmed'],
            ],
        ];
        $this->assertFalse(ClasesService::isFeedbackUnlocked($session));
    }

    public function testDesbloqueadoSiLaSesionEstaCompletada(): void
    {
        $session = ['status' => 'completed', 'players' => []];
        $this->assertTrue(ClasesService::isFeedbackUnlocked($session));
    }

    public function testDesbloqueadoSiSeHaPasadoLista(): void
    {
        $session = [
            'status'          => 'scheduled',
            'lista_pasada_at' => '2026-09-01 18:30:00',
            'players'         => [['attendance' => 'pending']],
        ];
        $this->assertTrue(ClasesService::isFeedbackUnlocked($session));
    }

    public function testDesbloqueadoSiAlgunAlumnoEstaPresente(): void
    {
        $session = [
            'status'          => 'scheduled',
            'lista_pasada_at' => null,
            'players'         => [
                ['attendance' => 'absent'],
                ['attendance' => 'present'],
            ],
        ];
        $this->assertTrue(ClasesService::isFeedbackUnlocked($session));
    }

    public function testToleraSesionSinClavesOpcionales(): void
    {
        $this->assertFalse(ClasesService::isFeedbackUnlocked(['status' => 'scheduled']));
    }

    public function testLaVistaUsaElHelperParaElBloqueo(): void
    {
        $view = file_get_contents(APPPATH . 'Views/clases/show.php');
        $this->assertStringContainsString('ClasesService::isFeedbackUnlocked($session)', $view);
        $this->assertStringNotContainsString("\$session['status'] !== 'completed' ? 'disabled'", $view);
    }
}
