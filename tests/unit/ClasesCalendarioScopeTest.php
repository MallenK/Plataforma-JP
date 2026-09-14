<?php

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\ClasesService;

/**
 * Toggle "Todas" / "Mis clases" del calendario (Clases y Dashboard).
 *
 * Un admin/superadmin que también tiene clases asignadas como responsable
 * no tenía forma de aislar su propio calendario del de gestión completa.
 * ClasesService::getSessionsForCalendar() gana un flag $onlyMine que, para
 * admin/superadmin, activa el mismo filtro por class_session_coaches que
 * ya usan coach/staff (sin flag, o para cualquier otro rol, no cambia
 * nada). Ver docs/clases/PROPUESTA-calendario-dual-admin-coach.md.
 *
 * shouldFilterCalendarByOwnSessions() es la decisión pura (sin BD); estos
 * tests fijan su contrato para las 4 combinaciones relevantes de rol.
 *
 * @internal
 */
final class ClasesCalendarioScopeTest extends CIUnitTestCase
{
    public function testCoachSiempreVeSoloLoSuyoIndependientementeDelToggle(): void
    {
        $this->assertTrue(ClasesService::shouldFilterCalendarByOwnSessions('coach', false));
        $this->assertTrue(ClasesService::shouldFilterCalendarByOwnSessions('coach', true));
    }

    public function testStaffSiempreVeSoloLoSuyoIndependientementeDelToggle(): void
    {
        $this->assertTrue(ClasesService::shouldFilterCalendarByOwnSessions('staff', false));
        $this->assertTrue(ClasesService::shouldFilterCalendarByOwnSessions('staff', true));
    }

    public function testAdminVeTodoPorDefectoYSoloLoSuyoConElToggle(): void
    {
        $this->assertFalse(ClasesService::shouldFilterCalendarByOwnSessions('admin', false));
        $this->assertTrue(ClasesService::shouldFilterCalendarByOwnSessions('admin', true));
    }

    public function testSuperadminVeTodoPorDefectoYSoloLoSuyoConElToggle(): void
    {
        $this->assertFalse(ClasesService::shouldFilterCalendarByOwnSessions('superadmin', false));
        $this->assertTrue(ClasesService::shouldFilterCalendarByOwnSessions('superadmin', true));
    }

    /**
     * El toggle es exclusivo de admin/superadmin: no tiene sentido (ni se
     * expone en la UI) para alumno, y no debería colar nada raro si algún
     * día llega scope=mine desde un cliente para ese rol — el jugador ya
     * tiene su propia rama de filtrado en getSessionsForCalendar().
     */
    public function testElToggleNoAfectaAOtrosRoles(): void
    {
        $this->assertFalse(ClasesService::shouldFilterCalendarByOwnSessions('player', true));
        $this->assertFalse(ClasesService::shouldFilterCalendarByOwnSessions('alumno', true));
    }
}
