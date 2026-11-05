<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Pestaña Ayuda de Finanzas: vídeos tutoriales (solo admin, lista blanca) y manual didáctico.
 */
final class FinanzasAyudaManualTest extends CIUnitTestCase
{
    private function controllerSource(): string
    {
        return file_get_contents(APPPATH . 'Controllers/FinanzasController.php');
    }

    public function testLosVideosSeSirvenSoloDesdeUnaListaBlanca(): void
    {
        $src = $this->controllerSource();

        $this->assertStringContainsString('isset(self::MANUAL_VIDEOS[$name])', $src, 'nunca se sirve un nombre que no esté en la lista');
        preg_match_all("/'(finanzas-[a-z0-9-]+\.mp4)'\s*=>/", $src, $m);
        $this->assertCount(7, $m[1], 'general + 6 de sector');
        foreach ($m[1] as $file) {
            $this->assertMatchesRegularExpression('/^finanzas-[a-z0-9-]+\.mp4$/', $file);
        }
    }

    public function testLaRutaDeVideoEstaBajoLosFiltrosDeFinanzas(): void
    {
        $routes = file_get_contents(APPPATH . 'Config/Routes.php');

        $this->assertMatchesRegularExpression("#ayuda/video/\(:segment\)'\s*,\s*'FinanzasController::manualVideo/\\\$1'#", $routes);
        // Está dentro del grupo de Finanzas (mismo bloque que las capturas del manual).
        $this->assertLessThan(strpos($routes, "ayuda/video/(:segment)"), strpos($routes, "ayuda/img/(:segment)"));
    }

    public function testElVideoAdmiteRangosParaPoderAvanzar(): void
    {
        $src = $this->controllerSource();

        $this->assertStringContainsString("'Accept-Ranges', 'bytes'", $src);
        $this->assertStringContainsString("'Content-Range'", $src);
        $this->assertStringContainsString('416', $src);
    }

    public function testElManualTieneGuiasYReglasDeUso(): void
    {
        $manual = file_get_contents(APPPATH . 'Views/finanzas/_manual.php');

        foreach (['g-vender', 'g-saldar', 'g-cobrar', 'g-deben', 'g-gasto', 'g-cargo', 'g-anular', 'g-alumno', 'g-devolver', 'g-revision', 'g-cierre'] as $id) {
            $this->assertStringContainsString('id="' . $id . '"', $manual, "falta la guía $id");
        }
        $this->assertStringContainsString('id="fm-auto"', $manual);
        $this->assertStringContainsString('id="fm-uso"', $manual, 'reglas de uso');
        $this->assertStringContainsString('id="fm-videos"', $manual);
        // Todo enlace interno del índice apunta a un ancla que existe.
        preg_match_all('/href="#([a-z0-9-]+)"/', $manual, $links);
        foreach (array_unique($links[1]) as $anchor) {
            $this->assertStringContainsString('id="' . $anchor . '"', $manual, "enlace roto: #$anchor");
        }
    }

    public function testLaConfiguracionExplicaElDescuentoAutomatico(): void
    {
        $cfg = file_get_contents(APPPATH . 'Views/finanzas/config.php');

        foreach (['Si está ACTIVADO', 'Si está DESACTIVADO', 'Reglas de uso', '¿Y si se descuenta por error?', 'Devolver bono', 'Falta sin justificar'] as $txt) {
            $this->assertStringContainsString($txt, $cfg);
        }
        // Las horas del texto se actualizan con el campo y arrancan con el valor guardado.
        $this->assertStringContainsString('js-hours', $cfg);
        $this->assertStringContainsString('name="fin_auto_deduct"', $cfg, 'el formulario no cambia');
        $this->assertStringContainsString('name="fin_notice_hours"', $cfg);
    }
}
