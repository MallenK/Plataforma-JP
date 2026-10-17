<?php

use App\Filters\FinanceFilter;
use App\Services\FinanceAccessService as FA;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Acceso a Finanzas POR PERSONA: reglas puras de FinanceAccessService y que las
 * rutas con dinero lleven el filtro `finance:*` (si alguien añade una ruta de
 * Finanzas sin filtro, este test avisa).
 */
final class FinanceAccessTest extends CIUnitTestCase
{
    private function user(int $id, string $role = 'admin', string $status = 'active', string $email = 'x@y.z'): array
    {
        return ['id' => (string) $id, 'role' => $role, 'status' => $status, 'email' => $email];
    }

    // ── Niveles ─────────────────────────────────────────────────────

    public function testElSuperadminRaizSiempreTieneAccesoTotal(): void
    {
        $this->assertSame(FA::LEVEL_FULL, FA::levelFor($this->user(2, 'superadmin'), []));
    }

    public function testElRaizSeReconocePorEmailAunqueCambieElId(): void
    {
        $this->assertSame(FA::LEVEL_FULL, FA::levelFor($this->user(99, 'superadmin', 'active', ' SergiMallenWeb@gmail.com '), []));
    }

    public function testUnAdminSinPermisoSoloVeLaRevision(): void
    {
        $this->assertSame(FA::LEVEL_REVIEW, FA::levelFor($this->user(7, 'admin'), []));
    }

    public function testUnSuperadminQueNoEsElRaizTampocoVeTodoPorDefecto(): void
    {
        $this->assertSame(FA::LEVEL_REVIEW, FA::levelFor($this->user(8, 'superadmin'), [5]));
    }

    public function testQuienEstaEnLaListaVeTodo(): void
    {
        $this->assertSame(FA::LEVEL_FULL, FA::levelFor($this->user(5, 'admin'), [3, 5]));
    }

    public function testElIdEnLaListaNoBastaSiYaNoEsAdmin(): void
    {
        foreach (['coach', 'staff', 'player', 'alumno', ''] as $role) {
            $this->assertSame(FA::LEVEL_NONE, FA::levelFor($this->user(5, $role), [5]), "rol «{$role}»");
        }
    }

    public function testUnUsuarioDeBajaPierdeTodoAcceso(): void
    {
        $this->assertSame(FA::LEVEL_NONE, FA::levelFor($this->user(5, 'admin', 'inactive'), [5]));
        $this->assertSame(FA::LEVEL_NONE, FA::levelFor($this->user(2, 'superadmin', 'inactive'), []));
    }

    public function testSinUsuarioNoHayAcceso(): void
    {
        $this->assertSame(FA::LEVEL_NONE, FA::levelFor(null, [1, 2, 3]));
    }

    public function testSatisfiesRespetaLaJerarquia(): void
    {
        $this->assertTrue(FA::satisfies(FA::LEVEL_FULL, 'full'));
        $this->assertTrue(FA::satisfies(FA::LEVEL_FULL, 'review'));
        $this->assertTrue(FA::satisfies(FA::LEVEL_REVIEW, 'review'));
        $this->assertFalse(FA::satisfies(FA::LEVEL_REVIEW, 'full'));
        $this->assertFalse(FA::satisfies(FA::LEVEL_NONE, 'review'));
        $this->assertFalse(FA::satisfies(FA::LEVEL_FULL, 'cualquier-cosa'), 'un nivel pedido desconocido deniega');
    }

    // ── Lista de ids ────────────────────────────────────────────────

    public function testParseIdsLimpiaDuplicadosBasuraYCeros(): void
    {
        $this->assertSame([1, 5, 7], FA::parseIds('1, 5;7  x 5 0'));
        $this->assertSame([], FA::parseIds(null));
        $this->assertSame([], FA::parseIds(''));
        $this->assertSame([], FA::parseIds('abc'));
    }

    // ── Cableado: ninguna ruta de Finanzas sin filtro por persona ───

    private function routesSource(): string
    {
        return file_get_contents(APPPATH . 'Config/Routes.php');
    }

    public function testTodaRutaDeFinanzasLlevaElFiltroFinance(): void
    {
        $src = $this->routesSource();

        // Rutas sueltas: $routes->get|post('finanzas…', …, [ … ]);
        preg_match_all("#\\\$routes->(?:get|post)\\('(finanzas[^']*|pendientes|bonos/informe|bonos/\\(:num\\)/precio)'.*?\\]\\);#s", $src, $m, PREG_SET_ORDER);
        $this->assertNotEmpty($m, 'no se encontraron rutas de Finanzas: ¿cambió el formato de Routes.php?');
        foreach ($m as $route) {
            $this->assertStringContainsString("'finance:", $route[0], "La ruta «{$route[1]}» no lleva filtro finance:*");
        }

        // El grupo con todas las pestañas con importes
        $this->assertMatchesRegularExpression("#group\\('finanzas',\\s*\\['filter' => \\[[^\\]]*'finance:full'#", $src);
    }

    public function testSoloLaRevisionDeClasesAdmiteElNivelRevision(): void
    {
        $src = $this->routesSource();
        preg_match_all("#\\\$routes->(?:get|post)\\('([^']+)'[^;]*?'finance:review'#s", $src, $m);
        sort($m[1]);
        $this->assertSame(
            ['finanzas/revision', 'finanzas/revision/cerrar-clases', 'finanzas/revision/eliminar-vacias'],
            $m[1],
            'Solo /finanzas/revision (y sus dos acciones de clases) puede abrirse sin acceso total: '
            . '/finanzas y cualquier otra ruta con importes deben ser finance:full'
        );
    }

    public function testElIndiceDeFinanzasExigeAccesoTotal(): void
    {
        $src = $this->routesSource();
        $this->assertMatchesRegularExpression("#get\\('finanzas',\\s*'FinanzasController::index',\\s*\\[[^\\]]*'finance:full'#s", $src);
        $this->assertMatchesRegularExpression("#get\\('pendientes',[^;]*?'finance:full'#s", $src);
    }

    public function testSinAccesoTotalLaRevisionSoloMuestraClases(): void
    {
        $ctl = file_get_contents(APPPATH . 'Controllers/FinanzasController.php');
        $this->assertStringContainsString("'debts'      => \$full ? \$control->openDebts(", $ctl);
        $this->assertStringContainsString("'preControl' => \$full ? \$control->unreflected(", $ctl);

        $view = file_get_contents(APPPATH . 'Views/finanzas/revision.php');
        $this->assertStringContainsString("<?php if (\$finFull): // bonos y dinero", $view);
        $this->assertStringNotContainsString("\$groups['Bonos']", $view);

        $dash = file_get_contents(APPPATH . 'Controllers/DashboardController.php');
        $this->assertStringContainsString("\$pendingReview['debts']       = 0;", $dash);
    }

    public function testElCambioDeAccesosEsSoloDelSuperadminRaiz(): void
    {
        $src = $this->routesSource();
        $this->assertMatchesRegularExpression("#finanzas/configuracion/acceso'[^;]*'finance:master'#s", $src);
    }

    public function testElFiltroEstaRegistradoYFallaCerrado(): void
    {
        $this->assertStringContainsString("'finance' => \\App\\Filters\\FinanceFilter::class", file_get_contents(APPPATH . 'Config/Filters.php'));
        $this->assertTrue(class_exists(FinanceFilter::class));

        $svc = file_get_contents(APPPATH . 'Services/FinanceAccessService.php');
        $this->assertStringContainsString('se deniega', $svc, 'ante un error de BD el acceso debe denegarse');
        // El nivel sale de la fila de BD, no del rol guardado en sesión
        $this->assertStringContainsString("->table('users')", $svc);
    }

    public function testElControllerNoEnviaDineroSinAccesoTotal(): void
    {
        $ctl = file_get_contents(APPPATH . 'Controllers/FinanzasController.php');
        $this->assertStringContainsString("'estimated'  => \$full ? \$rev->estimatedPriceBonos() : []", $ctl);
        $this->assertStringContainsString("'unused'     => \$full ? \$rev->expiredUnused() : []", $ctl);

        $bonos = file_get_contents(APPPATH . 'Controllers/BonosController.php');
        $this->assertStringContainsString('$finFull && $this->request->getPost(\'pay_now\')', $bonos);
    }
}
