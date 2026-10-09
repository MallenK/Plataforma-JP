<?php

declare(strict_types=1);

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Coach puede descontar/devolver/cambiar bonos desde Pasar lista (como admin);
 * staff sigue sin poder. El límite "solo sus sesiones" lo aplica guardBonoAction().
 *
 * @internal
 */
final class CoachBonosPasarListaTest extends CIUnitTestCase
{
    public function testBonoRoutesAllowCoachButNotStaff(): void
    {
        $routes = file_get_contents(APPPATH . 'Config/Routes.php');

        foreach (['descontar-bono', 'devolver-bono', 'cambiar-bono'] as $frag) {
            $this->assertMatchesRegularExpression(
                "#jugadores/\(:num\)/{$frag}'[^;]*role:superadmin,admin,coach'#s",
                $routes,
                "La ruta {$frag} debe permitir coach"
            );
            $this->assertDoesNotMatchRegularExpression(
                "#jugadores/\(:num\)/{$frag}'[^;]*role:[^']*staff#s",
                $routes,
                "La ruta {$frag} no debe permitir staff"
            );
        }
    }

    public function testControllerGatesBonosToAdminAndCoach(): void
    {
        $src = file_get_contents(APPPATH . 'Controllers/ClasesController.php');
        $this->assertStringContainsString(
            "in_array(\$this->currentRole(), ['superadmin', 'admin', 'coach'], true)",
            $src
        );
        $this->assertStringContainsString('isAssignedOrAdmin($session)', $src);
    }
}
