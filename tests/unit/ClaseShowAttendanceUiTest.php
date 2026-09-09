<?php

use CodeIgniter\Test\CIUnitTestCase;
use App\Controllers\ClasesController;

/**
 * Ficha de sesión (clases/show.php): botón de asistencia coherente,
 * reabrir/reactivar visibles, resumen con bonos descontados.
 *
 * @internal
 */
final class ClaseShowAttendanceUiTest extends CIUnitTestCase
{
    private function show(): string
    {
        return file_get_contents(APPPATH . 'Views/clases/show.php');
    }

    public function testEtiquetaDelBotonDeAsistenciaSegunEstado(): void
    {
        $v = $this->show();
        $this->assertStringContainsString("\$listaDone", $v);
        $this->assertStringContainsString("? 'Revisar asistencia' : 'Pasar lista'", $v);
        // Se usa la variable, no textos sueltos distintos.
        $this->assertStringContainsString('<?= $listaLabel ?>', $v);
        $this->assertStringNotContainsString('>Gestionar asistencia<', $v);
    }

    public function testBotonDeAsistenciaVisibleParaCanManageNoSoloAdmin(): void
    {
        $v = $this->show();
        // El enlace a /lista ya no está envuelto en un check admin/superadmin.
        $this->assertStringNotContainsString(
            "in_array(session('role'), ['admin', 'superadmin'])): ?>\n        <a href=\"/clases/<?= \$session['id'] ?>/lista\"",
            $v
        );
        $this->assertStringContainsString('btn-jp btn-jp-primary btn-jp-sm', $v);
        // Sin el morado inline de antes.
        $this->assertStringNotContainsString('background:#ede9fe;color:#5b21b6;border:1px solid #c4b5fd', $v);
    }

    public function testReabrirYReactivarSegunEstado(): void
    {
        $v = $this->show();
        $this->assertStringContainsString("\$session['status'] === 'completed'", $v);
        $this->assertStringContainsString('Reabrir sesión', $v);
        $this->assertStringContainsString("\$session['status'] === 'cancelled'", $v);
        $this->assertStringContainsString('Reactivar sesión', $v);
        $this->assertStringContainsString('/reabrir', $v);
    }

    public function testResumenMuestraBonosDescontados(): void
    {
        $v = $this->show();
        $this->assertStringContainsString('$bonoDeducted', $v);
        $this->assertStringContainsString("!empty(\$p['bono_deducted_at'])", $v);
        $this->assertStringContainsString('Bono descontado', $v);
    }

    public function testHintDeEstadoDeSesion(): void
    {
        $v = $this->show();
        $this->assertStringContainsString('$statusHint', $v);
        $this->assertStringContainsString('Puedes reabrirla', $v);
    }

    // ── plural() del controlador (lógica pura) ─────────────────────

    public function testPluralHelperDelControlador(): void
    {
        $ctrl = new class extends ClasesController {
            public function __construct() {}
            public function p(int $n, string $a, string $b): string { return $this->plural($n, $a, $b); }
        };
        // @phpstan-ignore-line — subclase de test
        $this->assertSame('bono', $ctrl->p(1, 'bono', 'bonos'));
        $this->assertSame('bonos', $ctrl->p(2, 'bono', 'bonos'));
        $this->assertSame('bonos', $ctrl->p(0, 'bono', 'bonos'));
    }
}
