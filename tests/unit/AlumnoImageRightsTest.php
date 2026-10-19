<?php

use CodeIgniter\Test\CIUnitTestCase;
use App\Models\PlayerProfileModel;

/**
 * "Derechos de imagen firmados" — booleano por alumno (player_profiles).
 * Lo puede cambiar cualquier rol (el alumno, solo el suyo) con un interruptor
 * que guarda al instante (POST /alumnos/:id/derechos-imagen), en la ficha y
 * en el perfil; el checkbox de la pantalla de edición sigue existiendo.
 *
 * @internal
 */
final class AlumnoImageRightsTest extends CIUnitTestCase
{
    private function file(string $rel): string
    {
        return file_get_contents(APPPATH . $rel);
    }

    // ── Modelo / servicio / controlador ──────────────────────────

    public function testCampoEnElModeloYEnElSelectDelServicio(): void
    {
        $this->assertContains('image_rights_signed', (new PlayerProfileModel())->allowedFields);

        $svc = $this->file('Services/PlayerService.php');
        $this->assertStringContainsString('player_profiles.image_rights_signed', $svc,
            'getFullProfile debe traer image_rights_signed');
    }

    public function testUpdateGuardaElCampo(): void
    {
        $c = $this->file('Controllers/AlumnosController.php');
        $this->assertStringContainsString(
            "'image_rights_signed' => \$this->request->getPost('image_rights_signed') ? 1 : 0",
            $c
        );
    }

    public function testHayEndpointDeInterruptorParaCualquierRol(): void
    {
        $routes = $this->file('Config/Routes.php');
        $pos = strpos($routes, "'AlumnosController::setImageRights/");
        $this->assertNotFalse($pos, 'falta la ruta del interruptor');
        $slice = preg_replace('/\s+/', ' ', substr($routes, $pos, 120));
        $this->assertStringContainsString("'filter' => 'auth', ]", $slice,
            'la ruta solo exige sesión (auth): cualquier rol puede cambiarlo');
        $this->assertStringContainsString('function setImageRights', $this->file('Services/PlayerService.php'));
    }

    public function testElAlumnoSoloCambiaElSuyo(): void
    {
        $c = $this->file('Controllers/AlumnosController.php');
        $pos = strpos($c, 'function setImageRights');
        $this->assertNotFalse($pos);
        $body = substr($c, $pos, 900);
        $this->assertStringContainsString("currentRole() === 'player'", $body);
        $this->assertStringContainsString('(int) $this->currentUserId()', $body);
        $this->assertStringContainsString('setStatusCode(403)', $body);
    }

    public function testServicioAuditaYCreaElPerfilSiNoExiste(): void
    {
        $svc = $this->file('Services/PlayerService.php');
        $pos = strpos($svc, 'function setImageRights');
        $body = substr($svc, $pos, 1400);
        $this->assertStringContainsString('profileModel->insert', $body);
        $this->assertStringContainsString('AuditService::record', $body);
        $this->assertStringContainsString("role', 'player'", $body);
    }

    // ── Vista de edición ─────────────────────────────────────────

    public function testEditTieneElCheckbox(): void
    {
        $v = $this->file('Views/alumnos/edit.php');
        $this->assertStringContainsString('name="image_rights_signed" value="1"', $v);
        $this->assertStringContainsString("!empty(\$alumno['image_rights_signed']) ? 'checked' : ''", $v);
        $this->assertStringContainsString('Derechos de imagen firmados', $v);
        // no ocupa todo el ancho del div
        $pos   = strpos($v, 'Derechos de imagen firmados');
        $label = substr($v, strrpos(substr($v, 0, $pos), '<label'), 260);
        $this->assertStringContainsString('display:inline-flex', $label);
        $this->assertStringContainsString('max-width:', $label);
    }

    // ── Ficha y perfil: interruptor que guarda solo ──────────────

    public function testElParcialEsUnInterruptorSinBotonDeGuardar(): void
    {
        $v = $this->file('Views/alumnos/_derechos_imagen.php');
        $this->assertStringContainsString('class="img-rights-check"', $v);
        $this->assertStringNotContainsString(' disabled', substr($v, 0, strpos($v, '<script')));
        $this->assertStringNotContainsString('type="submit"', $v);
        $this->assertStringContainsString("addEventListener('change'", $v);
        $this->assertStringContainsString('csrf_field()', $v);
        $this->assertStringContainsString('derechos-imagen', $v);
    }

    public function testShowYPerfilUsanElInterruptor(): void
    {
        $show = $this->file('Views/alumnos/show.php');
        $posFila = strpos($show, 'id="derechos-imagen"');
        $this->assertNotFalse($posFila);
        $this->assertStringContainsString("view('alumnos/_derechos_imagen'", substr($show, $posFila, 700));
        $this->assertStringNotContainsString('<input type="checkbox" disabled', $show);

        $perfil = $this->file('Views/perfil/index.php');
        $this->assertStringContainsString("view('alumnos/_derechos_imagen'", $perfil);
    }
}
