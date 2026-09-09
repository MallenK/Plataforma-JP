<?php

use CodeIgniter\Test\CIUnitTestCase;
use App\Models\PlayerProfileModel;

/**
 * "Derechos de imagen firmados" — booleano por alumno (player_profiles),
 * editable solo por admin / superadmin desde la ficha y la pantalla de
 * edición. En la ficha se muestra arriba del todo como dato destacado.
 *
 * @internal
 */
final class AlumnoImageRightsTest extends CIUnitTestCase
{
    private function file(string $rel): string
    {
        return file_get_contents(APPPATH . $rel);
    }

    // ── Modelo / servicio / controlador / ruta ────────────────────

    public function testCampoEnElModeloYEnElSelectDelServicio(): void
    {
        $this->assertContains('image_rights_signed', (new PlayerProfileModel())->allowedFields);

        $svc = $this->file('Services/PlayerService.php');
        $this->assertStringContainsString('player_profiles.image_rights_signed', $svc,
            'getFullProfile debe traer image_rights_signed');
        $this->assertStringContainsString('public function setImageRights(int $playerId, bool $signed)', $svc);
    }

    public function testControladorGuardaElCampoYTieneElToggle(): void
    {
        $c = $this->file('Controllers/AlumnosController.php');
        // update() incluye el campo en profileData
        $this->assertStringContainsString(
            "'image_rights_signed' => \$this->request->getPost('image_rights_signed') ? 1 : 0",
            $c
        );
        // toggle rápido desde la ficha
        $this->assertStringContainsString('public function updateImageRights(int $id)', $c);
        // 404 si el usuario no es player
        $this->assertMatchesRegularExpression("/updateImageRights.+role'\\] \\?\\? ''\\) !== 'player'/s", $c);
    }

    public function testRutaSoloAdminYSuperadmin(): void
    {
        $r = $this->file('Config/Routes.php');
        $slice = substr($r, strpos($r, 'derechos-imagen'), 200);
        $this->assertStringContainsString('AlumnosController::updateImageRights/$1', $slice);
        $this->assertStringContainsString("'filter' => ['auth', 'role:superadmin,admin']", $slice);
    }

    // ── Vista de edición ─────────────────────────────────────────

    public function testEditTieneElCheckbox(): void
    {
        $v = $this->file('Views/alumnos/edit.php');
        $this->assertStringContainsString('name="image_rights_signed" value="1"', $v);
        $this->assertStringContainsString("!empty(\$alumno['image_rights_signed']) ? 'checked' : ''", $v);
        $this->assertStringContainsString('Derechos de imagen firmados', $v);
    }

    // ── Ficha del alumno ─────────────────────────────────────────

    public function testShowPintaLaFilaDentroDeLaTarjetaDeIdentidadYSegunRol(): void
    {
        $v = $this->file('Views/alumnos/show.php');

        // La fila va DENTRO de la tarjeta de identidad (misma lista que ID /
        // Miembro desde / Categoría…), no en un banner destacado aparte.
        $posFila = strpos($v, 'id="derechos-imagen"');
        $posRow  = strpos($v, '<div class="row g-3">');
        $this->assertNotFalse($posFila);
        $this->assertGreaterThan($posRow, $posFila, 'la fila va dentro del contenido, no en un banner arriba');
        // usa el mismo patrón de fila que el resto de datos de la tarjeta
        $slice = substr($v, $posFila, 400);
        $this->assertStringContainsString('d-flex justify-content-between align-items-center', $slice);
        $this->assertStringContainsString('text-transform:uppercase', $slice);

        // admin → formulario que se envía al cambiar el checkbox
        $this->assertStringContainsString("action=\"<?= base_url('alumnos/' . \$alumno['id'] . '/derechos-imagen') ?>\"", $v);
        $this->assertStringContainsString('onchange="this.form.submit()"', $v);
        // no-admin → checkbox deshabilitado
        $this->assertMatchesRegularExpression('/else:.+<input type="checkbox" disabled/s', $v);
        // ya no hay banner con acento de color a todo el ancho
        $this->assertStringNotContainsString('dato destacado, arriba del todo', $v);
    }
}
