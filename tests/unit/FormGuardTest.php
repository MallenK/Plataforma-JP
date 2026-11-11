<?php

use App\Services\RevisionService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Botones «blandos» (JPGuard): paridad de reglas con el servidor y comprobación de que cada pantalla
 * lo lleva puesto. El comportamiento del propio ayudante se prueba en tests/js/form-guard.test.cjs.
 *
 * @internal
 */
final class FormGuardTest extends CIUnitTestCase
{
    private function src(string $path): string
    {
        return file_get_contents(ROOTPATH . $path);
    }

    public function testImportesDelNavegadorYDelServidorCoinciden(): void
    {
        // El mismo fichero de casos lo usa tests/js/form-guard.test.cjs con la versión de JS.
        $cases = json_decode(file_get_contents(ROOTPATH . 'tests/fixtures/money-cases.json'), true);
        $this->assertNotEmpty($cases);
        foreach ($cases as [$input, $expected]) {
            $this->assertSame($expected, RevisionService::parseEuroToCents($input), 'servidor: «' . $input . '»');
        }
    }

    public function testElAyudanteSeCargaAntesQueLosIndicadoresDeCarga(): void
    {
        $base = $this->src('app/Views/layouts/base.php');
        $g = strpos($base, "assets/js/form-guard.js");
        $l = strpos($base, "assets/js/loaders.js");
        $this->assertNotFalse($g, 'base.php debe cargar form-guard.js');
        $this->assertLessThan($l, $g, 'form-guard.js debe ir ANTES de loaders.js para frenar el envío antes de marcar el botón como ocupado');
    }

    public function testElBotonNuncaSeDeshabilitaDeVerdad(): void
    {
        $js = $this->src('public/assets/js/form-guard.js');
        $this->assertStringContainsString("setAttribute('aria-disabled', 'true')", $js, 'se usa aria-disabled (botón blando)');
        $this->assertStringNotContainsString('.disabled = true', $js, 'nunca disabled real: no recibiría el clic que guía al usuario');
        $this->assertStringNotContainsString("setAttribute('disabled'", $js);
    }

    /** @return array<string,array{0:string,1:string}> */
    public static function pantallasProvider(): array
    {
        return [
            'pop-up nueva clase (JS)'          => ['public/assets/js/clase-modal.js', 'attachGuard'],
            'formulario nueva/editar clase'    => ['app/Views/clases/create.php', 'JPGuard.attach'],
            'emitir / asignar bono'            => ['app/Views/bonos/index.php', 'JPGuard.attach'],
            'pasar lista (aviso no bloqueante)' => ['app/Views/clases/pasar_lista.php', 'blocking: false'],
            'guardar persona externa (JS)'     => ['public/assets/js/externos-list.js', 'JPGuard.attach'],
        ];
    }

    /** @dataProvider pantallasProvider */
    public function testCadaPantallaDeLaFase1y2LlevaElAyudante(string $file, string $needle): void
    {
        $this->assertStringContainsString($needle, $this->src($file), $file);
    }

    public function testLosFormulariosDeDineroYAnularLoLlevan(): void
    {
        $ext = $this->src('app/Views/clases/_externos.php');
        $this->assertGreaterThanOrEqual(5, substr_count($ext, 'data-guard'), 'precio, cobro, anular, editar persona y añadir persona');
        $this->assertStringContainsString('data-guard-max="<?= (int) $e[\'pending_cents\'] ?>"', $ext, 'el cobro no puede superar lo pendiente');
        $this->assertStringContainsString('data-guard-min=', $ext, 'el precio no puede quedar por debajo de lo cobrado');
        $this->assertStringContainsString('data-guard-needs=', $ext, '«ya pagado» exige un precio mayor que 0');

        $show = $this->src('app/Views/externos/show.php');
        $this->assertSame(2, substr_count($show, 'data-guard') - substr_count($show, 'data-guard-changes'), 'ficha y convertir en alumno');

        foreach (['app/Views/finanzas/cobros.php', 'app/Views/finanzas/gastos.php', 'app/Views/finanzas/alumno.php'] as $f) {
            $this->assertStringContainsString('data-guard data-guard-hint="none"', $this->src($f), $f . ': anular exige motivo');
        }
        $this->assertStringContainsString('data-guard', $this->src('app/Views/bonos/show.php'));
    }

    public function testEditarClaseNoRompeElScriptNiExigeAlumnoSiNoLoTenia(): void
    {
        $v = $this->src('app/Views/clases/create.php');
        // Regresión: al editar no existen los bloques puntual/recurrente → toggleType lanzaba un error y los buscadores no funcionaban.
        $this->assertStringContainsString('if (!single || !recur || !lblS || !lblR) return;', $v);
        // Al editar solo se pide alumno si la clase ya los tenía (una clase solo con personas externas se puede editar).
        $this->assertStringContainsString('INITIAL_PLAYERS === 0 || n > 0', $v);
    }

    public function testLosScriptsEnLineaEsperanAlAyudante(): void
    {
        // form-guard.js se carga al final de la página: los scripts en línea deben esperar a que esté listo.
        foreach (['app/Views/clases/create.php', 'app/Views/bonos/index.php', 'app/Views/clases/pasar_lista.php'] as $f) {
            $this->assertStringContainsString("document.addEventListener('DOMContentLoaded', initGuard)", $this->src($f), $f);
        }
    }

    public function testLasReglasDeParticipantesEspejanAlServidor(): void
    {
        // El servidor exige entrenador Y (alumno o persona externa); el pop-up y el formulario lo anticipan.
        $svc = $this->src('app/Services/ClasesService.php');
        $this->assertStringContainsString('participantsError', $svc);
        foreach (['public/assets/js/clase-modal.js', 'app/Views/clases/create.php'] as $f) {
            $js = $this->src($f);
            $this->assertStringContainsString('un entrenador', $js, $f);
            $this->assertStringContainsString('un alumno', $js, $f);
            $this->assertStringContainsString('el nombre de la persona externa', $js, $f);
            $this->assertStringContainsString('una hora de fin posterior a la de inicio', $js, $f);
        }
    }
}
