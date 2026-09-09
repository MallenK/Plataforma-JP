<?php

use CodeIgniter\Test\CIUnitTestCase;
use App\Models\PlayerProfileModel;

/**
 * "Derechos de imagen firmados" — booleano por alumno (player_profiles).
 * Se cambia SOLO desde la pantalla de edición del alumno (admin /
 * superadmin). En la ficha se muestra como una fila más de la tarjeta de
 * identidad, con la casilla siempre deshabilitada y un tooltip que remite
 * a la pantalla de edición.
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

    public function testNoHayToggleRapidoNiRutaPropia(): void
    {
        // El único punto de cambio es /alumnos/:id/editar; no hay endpoint
        // suelto para el toggle desde la ficha.
        $this->assertStringNotContainsString('updateImageRights', $this->file('Controllers/AlumnosController.php'));
        $this->assertStringNotContainsString('derechos-imagen', $this->file('Config/Routes.php'));
        $this->assertStringNotContainsString('setImageRights', $this->file('Services/PlayerService.php'));
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

    // ── Ficha del alumno ─────────────────────────────────────────

    public function testShowPintaLaFilaDeshabilitadaConTooltip(): void
    {
        $v = $this->file('Views/alumnos/show.php');

        // La fila va DENTRO de la tarjeta de identidad, no en un banner aparte.
        $posFila = strpos($v, 'id="derechos-imagen"');
        $posRow  = strpos($v, '<div class="row g-3">');
        $this->assertNotFalse($posFila);
        $this->assertGreaterThan($posRow, $posFila, 'la fila va dentro del contenido, no en un banner arriba');

        $slice = substr($v, $posFila, 1000);
        $this->assertStringContainsString('d-flex justify-content-between align-items-center', $slice);
        $this->assertStringContainsString('text-transform:uppercase', $slice);

        // casilla SIEMPRE deshabilitada (también para admin) + tooltip a edición
        $this->assertStringContainsString('<input type="checkbox" disabled', $slice);
        $this->assertStringNotContainsString('onchange="this.form.submit()"', $slice);
        $this->assertStringNotContainsString('<form', $slice);
        $this->assertMatchesRegularExpression('/title="[^"]*edici[oó]n/u', $slice);
    }
}
