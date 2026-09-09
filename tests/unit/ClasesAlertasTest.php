<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * En la sección de Clases no debe quedar ningún alert()/confirm()/prompt()
 * nativo del navegador: se sustituyen por el diálogo de la plataforma
 * (radix-ui.js — RadixUI.confirm / <form data-ru-confirm> / showAlert).
 *
 * @internal
 */
final class ClasesAlertasTest extends CIUnitTestCase
{
    /** @return array<string,array{0:string}> */
    public static function vistasClases(): array
    {
        $dir = APPPATH . 'Views/clases/';
        return [
            'show'                 => [$dir . 'show.php'],
            'pasar_lista'          => [$dir . 'pasar_lista.php'],
            'pasar_lista_semanal'  => [$dir . 'pasar_lista_semanal.php'],
            'index'                => [$dir . 'index.php'],
            'create'               => [$dir . 'create.php'],
            '_modal_create'        => [$dir . '_modal_create.php'],
        ];
    }

    /** Quita comentarios //… y /*…*&#47; para no dar falsos positivos. */
    private function stripComments(string $src): string
    {
        $src = preg_replace('#/\*.*?\*/#s', '', $src);
        return preg_replace('#(^|[^:])//[^\r\n]*#', '$1', $src);
    }

    /**
     * @dataProvider vistasClases
     */
    public function testSinDialogosNativosEnLasVistasDeClases(string $file): void
    {
        $src = $this->stripComments(file_get_contents($file));

        // Llamada BARE a confirm()/alert()/prompt() — no `window.`/`.`-prefijada
        // (se permite explícitamente `window.confirm` como red de seguridad si
        // el componente de la plataforma no cargara) y no parte de otra palabra
        // (showAlert, alertify…).
        $this->assertDoesNotMatchRegularExpression(
            '/(?<![\w.$])(confirm|alert|prompt)\s*\(/',
            $src,
            basename($file) . ' aún llama a un diálogo nativo del navegador sin pasar por la plataforma'
        );
        // Tampoco el patrón inline onclick="return confirm(...)".
        $this->assertStringNotContainsString('onclick="return confirm', $src);
    }

    public function testShowUsaConfirmacionDeclarativaDeLaPlataforma(): void
    {
        $v = file_get_contents(APPPATH . 'Views/clases/show.php');

        // Los 5 formularios sensibles llevan data-ru-confirm (cancelar,
        // reabrir, reactivar, eliminar sesión, quitar entrenador/staff).
        $this->assertSame(5, substr_count($v, 'data-ru-confirm='),
            'deben ser 5 formularios con data-ru-confirm en show.php');
        $this->assertStringContainsString('data-ru-confirm="¿Eliminar esta sesión permanentemente?"', $v);
        $this->assertStringContainsString('data-ru-confirm-danger', $v);
        $this->assertStringContainsString('data-ru-confirm-label="Reabrir"', $v);
        // acción destructiva = botón danger + confirmación danger
        $this->assertMatchesRegularExpression('/eliminar.+data-ru-confirm-danger/s', $v);
    }

    public function testPasarListaUsaElDialogoDeLaPlataforma(): void
    {
        $v = file_get_contents(APPPATH . 'Views/clases/pasar_lista.php');

        $this->assertStringContainsString('function askConfirm(opts)', $v);
        $this->assertStringContainsString('RadixUI.confirm(opts)', $v);
        // los 3 puntos que antes eran confirm() nativo
        $this->assertSame(3, substr_count($v, 'askConfirm({'),
            'descontar bono, devolver bono y "guardar y cerrar" usan askConfirm');
        // "guardar y cerrar": cuando hay avisos, se bloquea el envío y se
        // re-envía sólo si el usuario confirma.
        $this->assertStringContainsString('e.preventDefault();', $v);
        $this->assertStringContainsString("h.name = 'cerrar'; form.appendChild(h)", $v);
    }

    public function testDialogoDeConfirmacionFunciona(): void
    {
        $script = APPPATH . '../tests/js/radix-confirm.test.cjs';
        if (!is_file($script)) {
            $this->markTestSkipped('tests/js/radix-confirm.test.cjs no encontrado');
        }
        $node = trim((string) @shell_exec('command -v node 2>/dev/null'));
        if ($node === '') {
            $this->markTestSkipped('Node no disponible en el entorno');
        }
        if (!is_dir(dirname($script) . '/node_modules/jsdom')) {
            $this->markTestSkipped('jsdom no instalado — ejecuta: npm --prefix tests/js install');
        }

        $output = [];
        $code   = 0;
        exec(escapeshellarg($node) . ' ' . escapeshellarg($script) . ' 2>&1', $output, $code);
        $joined = implode("\n", $output);

        if ($code === 2) {
            $this->markTestSkipped('El test JS pidió skip: ' . $joined);
        }
        $this->assertSame(0, $code, "El test funcional de radix-ui.js falló:\n" . $joined);
        $this->assertStringContainsString('TODO OK', $joined);
    }
}
