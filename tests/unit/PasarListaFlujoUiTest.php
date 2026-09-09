<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Fija el comportamiento del flujo "pasar lista / cerrar / reabrir / bono"
 * que vive en el markup + JS inline de las vistas y en Routes.php.
 * (La lógica de BD se cubre aparte; aquí bloqueamos regresiones de UI.)
 *
 * @internal
 */
final class PasarListaFlujoUiTest extends CIUnitTestCase
{
    private function lista(): string
    {
        return file_get_contents(APPPATH . 'Views/clases/pasar_lista.php');
    }

    private function semanal(): string
    {
        return file_get_contents(APPPATH . 'Views/clases/pasar_lista_semanal.php');
    }

    private function routes(): string
    {
        return file_get_contents(APPPATH . 'Config/Routes.php');
    }

    // ── Rutas ───────────────────────────────────────────────────────

    public function testRutasCierreReabrirYBonoDeclaradas(): void
    {
        $r = $this->routes();
        $this->assertMatchesRegularExpression(
            '#clases/\(:num\)/reabrir.+ClasesController::reabrirSesion#s',
            $r,
            'Falta la ruta POST /clases/:id/reabrir'
        );
        $this->assertMatchesRegularExpression(
            '#jugadores/\(:num\)/devolver-bono.+ClasesController::refundBono#s',
            $r,
            'Falta la ruta POST .../devolver-bono'
        );
        // Ambas con filtro de rol (no rutas abiertas).
        foreach (['reabrir', 'devolver-bono'] as $frag) {
            $slice = substr($r, strpos($r, $frag), 220);
            $this->assertStringContainsString('role:superadmin,admin,staff,coach', $slice, "Ruta {$frag} sin filtro de rol");
        }
    }

    // ── Un solo gesto: guardar y cerrar ─────────────────────────────

    public function testBotonGuardarYCerrarEnviaFlagCerrar(): void
    {
        $v = $this->lista();
        $this->assertStringContainsString('name="cerrar" value="1"', $v);
        $this->assertStringContainsString('name="cerrar" value="0"', $v);
        $this->assertStringContainsString('Guardar y cerrar', $v);
        $this->assertStringContainsString('Guardar sin cerrar', $v);
        // Ya no existe el botón antiguo de dos pasos.
        $this->assertStringNotContainsString('id="btn-cerrar-sesion"', $v);
        $this->assertStringNotContainsString('id="form-cerrar"', $v);
    }

    public function testConfirmDeCierreAvisaDePendientesYBonoSinDescontar(): void
    {
        $v = $this->lista();
        $this->assertStringContainsString("data-bulk", $v);
        // El confirm cuenta selects en 'pending' y botones .pl-deduct visibles.
        $this->assertStringContainsString("if (s.value === 'pending') pending++", $v);
        $this->assertStringContainsString(".pl-deduct", $v);
        $this->assertStringContainsString('con bono sin descontar', $v);
    }

    // ── Reabrir reversible ─────────────────────────────────────────

    public function testSesionCerradaMuestraReabrirYNoPermiteEditar(): void
    {
        $v = $this->lista();
        $this->assertStringContainsString('id="form-reabrir"', $v);
        $this->assertStringContainsString('/reabrir', $v);
        $this->assertStringContainsString('Reabrir sesión', $v);
        // Los selects se deshabilitan si está cerrada.
        $this->assertStringContainsString('$isClosed ? \'disabled\' : \'\'', $v);
    }

    // ── Marcar todos: un botón por estado de asistencia ────────────

    public function testMarcarTodosGeneraUnBotonPorEstadoYSoloConVariosAlumnos(): void
    {
        $v = $this->lista();
        // Se genera desde el catálogo (present/absent/unjustified), no hardcode de 2.
        $this->assertMatchesRegularExpression(
            "#foreach \\(\\\$groups\\['asistencia'\\] as \\\$val => \\\$label\\).{0,200}data-bulk=\"<\\?= \\\$val \\?>\"#s",
            $v
        );
        $this->assertStringContainsString('count($players) > 1', $v);
        $this->assertStringContainsString('Marcar todos como:', $v);
    }

    public function testBulkFijaTodosLosAlumnosNoSoloLosPendientes(): void
    {
        $v = $this->lista();
        // La versión antigua solo tocaba sel.value === 'pending'.
        $this->assertStringContainsString("if (sel.value !== target) { sel.value = target;", $v);
        $this->assertStringNotContainsString("if (sel.value === 'pending') { sel.value = target;", $v);
    }

    // ── Devolver bono ─────────────────────────────────────────────

    public function testDevolverBonoPresenteEnMarkupYJs(): void
    {
        $v = $this->lista();
        $this->assertStringContainsString('Devolver bono', $v);
        $this->assertStringContainsString('pl-refund', $v);
        $this->assertStringContainsString('/devolver-bono', $v);
        // Delegación en el form (los botones se regeneran tras cada acción).
        $this->assertStringContainsString("form.addEventListener('click'", $v);
        $this->assertStringContainsString("ev.target.closest('.pl-refund')", $v);
    }

    public function testAvisoDeAutodevolucionEnLaFila(): void
    {
        $v = $this->lista();
        $this->assertStringContainsString('pl-row-hint', $v);
        $this->assertStringContainsString('se le devolverá el bono', $v);
    }

    public function testDescontarBonoEnviaLaAsistenciaElegida(): void
    {
        $v = $this->lista();
        // El fetch de descontar-bono adjunta { attendance: sel.value } para
        // que funcione sin haber pulsado "Guardar".
        $this->assertStringContainsString('{ attendance: sel ? sel.value :', $v);
        $this->assertStringContainsString('bonoRequest(url, btn, labelBusy, labelIdle, onOk, extraBody)', $v);
    }

    // ── Regresión CSRF (ver DeductBonoCsrfTest) ────────────────────

    public function testFetchDeBonoNuncaManaUnaCabeceraCsrfVacia(): void
    {
        $v = $this->lista();
        // Nunca 'X-CSRF-TOKEN': '' — siempre el hash real.
        $this->assertStringNotContainsString("'X-CSRF-TOKEN': ''", $v);
        $this->assertStringContainsString("'X-CSRF-TOKEN': '<?= csrf_hash() ?>'", $v);
        // El token también viaja en el cuerpo, con el nombre de campo real.
        $this->assertStringContainsString('var CSRF_NAME = <?= json_encode(csrf_token()) ?>', $v);
    }

    // ── Coherencia con el servicio (sin drift de listas) ──────────

    public function testLaVistaUsaLosHelpersDelServicioParaBonoYAusencia(): void
    {
        $v = $this->lista();
        $this->assertStringContainsString('ClasesService::attendanceIsAbsence($att)', $v);
        $this->assertStringContainsString('ClasesService::attendanceConsumesBono($att)', $v);
        // Ya no hay listas de estados hardcodeadas en PHP en esas dos variables.
        $this->assertStringNotContainsString("\$canDeduct = in_array(\$att, ['present', 'confirmed', 'unjustified']", $v);
    }

    // ── Ayuda: desplegable compacto, no barra a todo el ancho ─────

    public function testAyudaEsDesplegableEnLaCabeceraNoBarra(): void
    {
        foreach ([$this->lista(), $this->semanal()] as $v) {
            $this->assertStringContainsString('<details class="pl-help">', $v);
            $this->assertStringContainsString('pl-head-aside', $v);
            $this->assertStringContainsString('pl-help-chev', $v);
        }
        // Cierra al pulsar fuera / Escape.
        $this->assertStringContainsString("if (help.open && !help.contains(e.target)) help.open = false", $this->lista());
        $this->assertStringContainsString("e.key === 'Escape'", $this->semanal());
    }

    public function testVistaSemanalDistingueCerradaDeSinCerrar(): void
    {
        $v = $this->semanal();
        $this->assertStringContainsString('Lista pasada', $v);
        $this->assertStringContainsString('sin cerrar', $v);
        $this->assertStringContainsString('Cerrada', $v);
        $this->assertStringContainsString("=== 'completed'", $v);
    }
}
