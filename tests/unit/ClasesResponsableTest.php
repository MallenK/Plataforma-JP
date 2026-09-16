<?php

use App\Models\NotificationModel;
use App\Services\ClasesService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * TICKET-011 — calendario por entrenador/staff (admin puede filtrar por
 * persona) y cambio de responsable de una clase, reflejado para el
 * entrenador (calendario + notificación).
 *
 * La parte con BD real (filtro por responsable, changeResponsible() con su
 * transacción y el arrastre de alumnos, notificaciones) se verificó a mano
 * contra la BD de Docker y en navegador (Playwright): ver el propio ticket
 * para el detalle. Aquí solo la lógica pura y el cableado de rutas/vistas.
 */
final class ClasesResponsableTest extends CIUnitTestCase
{
    // ── parseScopeParam(): un solo parámetro ?scope= para todo ──────

    public function testScopeAllPorDefecto(): void
    {
        foreach ([null, '', 'all', 'algo-que-no-existe'] as $raw) {
            $this->assertSame(['onlyMine' => false, 'responsableFilter' => null], ClasesService::parseScopeParam($raw));
        }
    }

    public function testScopeMine(): void
    {
        $this->assertSame(['onlyMine' => true, 'responsableFilter' => null], ClasesService::parseScopeParam('mine'));
    }

    public function testScopeNone(): void
    {
        $this->assertSame(['onlyMine' => false, 'responsableFilter' => 'none'], ClasesService::parseScopeParam('none'));
    }

    public function testScopeCoachYStaffConId(): void
    {
        $this->assertSame(['onlyMine' => false, 'responsableFilter' => '12'], ClasesService::parseScopeParam('coach:12'));
        $this->assertSame(['onlyMine' => false, 'responsableFilter' => '7'], ClasesService::parseScopeParam('staff:7'));
    }

    public function testScopeConIdInvalidoSeIgnora(): void
    {
        // Sin dígitos, o con basura detrás → no hay filtro (nunca 500 por un
        // valor manipulado a mano en la URL).
        foreach (['coach:', 'coach:abc', 'coach:12x', 'staff:'] as $raw) {
            $this->assertNull(ClasesService::parseScopeParam($raw)['responsableFilter'], $raw);
        }
    }

    // ── attachResponsable() / buildPage-style helpers: vía reflexión ──
    // (privado; se prueba a través de getSessionsForCalendar en la
    // verificación manual con BD real, documentada en el ticket)

    // ── Cableado: rutas, controller, notificaciones ──────────────────

    public function testRutaDeCambioDeResponsableExisteConElFiltroCorrecto(): void
    {
        $this->assertTrue(method_exists(\App\Controllers\ClasesController::class, 'changeResponsible'));

        $routes = file_get_contents(APPPATH . 'Config/Routes.php');
        $this->assertMatchesRegularExpression(
            "/routes->post\('clases\/\(:num\)\/responsable', 'ClasesController::changeResponsible\/\\\$1', \[\s*'filter' => \['auth', 'role:superadmin,admin,staff'\]/",
            $routes,
            'Un coach no debe poder cambiar el responsable de una sesión ajena por esta vía.'
        );
    }

    public function testNotificationModelConoceElOrigenClase(): void
    {
        $this->assertSame('class', NotificationModel::SOURCE_CLASS);
        $this->assertSame(
            ['path' => 'clases/9', 'label' => 'Ver clase', 'icon' => 'bi-calendar3'],
            NotificationModel::sourceLink(['source_type' => 'class', 'source_id' => 9])
        );
    }

    public function testLosEventosDelCalendarioLlevanElResponsable(): void
    {
        // getSessionsForCalendar() debe añadir responsable_id/responsable_name
        // a cada evento (lo pinta el calendario) — verificado en el código
        // fuente para no depender de una BD real en la suite (ver CLAUDE.md,
        // "la integración real con BD necesitaría montar DatabaseTestTrait").
        $src = file_get_contents(APPPATH . 'Services/ClasesService.php');
        $this->assertStringContainsString("'responsable_id'   => \$c['id'] ?? null,", $src);
        $this->assertStringContainsString("'responsable_name' => \$c['name'] ?? null,", $src);
    }

    public function testLaVistaDeClasesMuestraElSelectorYElNombreDelResponsable(): void
    {
        $html = file_get_contents(APPPATH . 'Views/clases/index.php');
        $this->assertStringContainsString('id="cal-scope-select"', $html);
        $this->assertStringContainsString('ev.responsable_name', $html);
        // El selector se recuerda igual que el toggle que sustituye (v1.7.0).
        $this->assertStringContainsString("localStorage.getItem('jp_cal_scope')", $html);
    }

    public function testLaFichaDeClaseTieneElModalDeCambiarResponsable(): void
    {
        $html = file_get_contents(APPPATH . 'Views/clases/show.php');
        $this->assertStringContainsString('modalChangeResponsible', $html);
        $this->assertStringContainsString("action=\"/clases/<?= \$session['id'] ?>/responsable\"", $html);
        // El alcance "esta y las siguientes" solo se ofrece si hay más de una
        // sesión futura en la serie — nunca se aplica a toda la serie sin que
        // se elija a propósito.
        $this->assertStringContainsString('seriesFutureCount', $html);
    }
}
