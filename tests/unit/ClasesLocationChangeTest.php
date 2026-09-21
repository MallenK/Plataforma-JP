<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Cambio rápido del campo/instalación de una sesión desde su ficha, sin
 * pasar por el formulario completo de "Editar sesión" — pedido explícito
 * del cliente porque es algo que hace cada día.
 *
 * Igual que en ClasesResponsableTest: solo cableado (rutas, controller,
 * vista), sin BD real (ver CLAUDE.md sobre DatabaseTestTrait pendiente).
 */
final class ClasesLocationChangeTest extends CIUnitTestCase
{
    public function testRutaDeCambioDeCampoExisteConElFiltroCorrecto(): void
    {
        $this->assertTrue(method_exists(\App\Controllers\ClasesController::class, 'changeLocation'));

        $routes = file_get_contents(APPPATH . 'Config/Routes.php');
        $this->assertMatchesRegularExpression(
            "/routes->post\('clases\/\(:num\)\/campo', 'ClasesController::changeLocation\/\\\$1', \[\s*'filter' => \['auth', 'role:superadmin,admin,staff'\]/",
            $routes,
            'Un coach no debe poder cambiar el campo de una sesión ajena por esta vía.'
        );
    }

    public function testLaFichaDeClaseTieneElModalDeCambiarCampo(): void
    {
        $html = file_get_contents(APPPATH . 'Views/clases/show.php');
        $this->assertStringContainsString('modalChangeLocation', $html);
        $this->assertStringContainsString("action=\"/clases/<?= \$session['id'] ?>/campo\"", $html);
        // Debe ofrecer tanto la lista de instalaciones como el texto libre,
        // igual que el formulario completo de creación/edición.
        $this->assertStringContainsString('name="location_id"', $html);
        $this->assertStringContainsString('name="location_custom"', $html);
    }

    public function testElBotonCambiarCampoSoloParaAdminStaffYSesionProgramada(): void
    {
        $html = file_get_contents(APPPATH . 'Views/clases/show.php');
        $this->assertStringContainsString(
            "if (\$isAdminRole && \$session['status'] === 'scheduled'):",
            $html
        );
        $this->assertStringContainsString("onclick=\"openModal('modalChangeLocation')\"", $html);
    }
}
