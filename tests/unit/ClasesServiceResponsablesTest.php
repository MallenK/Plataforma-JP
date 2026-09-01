<?php

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\ClasesService;

/**
 * Regresión: los admin y el staff también pueden impartir clases.
 *
 * Antes, ClasesService::getCoachOptions() y getStaffOptions() filtraban
 * exclusivamente por role='coach' / role='staff', de modo que un admin o
 * superadmin nunca aparecía en el selector de responsable de una sesión
 * y, por tanto, no se le podían asignar clases.
 *
 * Estos tests fijan el contrato de roles asignables como responsable.
 */
final class ClasesServiceResponsablesTest extends CIUnitTestCase
{
    public function testResponsableTecnicoIncluyeCoachAdminYSuperadmin(): void
    {
        $roles = ClasesService::RESPONSABLE_TECNICO_ROLES;

        $this->assertContains('coach', $roles);
        $this->assertContains('admin', $roles, 'Un admin debe poder impartir clases');
        $this->assertContains('superadmin', $roles, 'Un superadmin debe poder impartir clases');
    }

    public function testResponsableStaffIncluyeStaffAdminYSuperadmin(): void
    {
        $roles = ClasesService::RESPONSABLE_STAFF_ROLES;

        $this->assertContains('staff', $roles, 'El staff debe poder tener clases asignadas');
        $this->assertContains('admin', $roles);
        $this->assertContains('superadmin', $roles);
    }

    public function testLosAlumnosNuncaSonResponsablesDeSesion(): void
    {
        $this->assertNotContains('player', ClasesService::RESPONSABLE_TECNICO_ROLES);
        $this->assertNotContains('alumno', ClasesService::RESPONSABLE_TECNICO_ROLES);
        $this->assertNotContains('player', ClasesService::RESPONSABLE_STAFF_ROLES);
        $this->assertNotContains('alumno', ClasesService::RESPONSABLE_STAFF_ROLES);
    }

    /**
     * Las consultas de opciones deben usar las constantes de roles
     * (whereIn) y no un where('role', ...) de un único valor.
     */
    public function testLasConsultasDeOpcionesUsanElWhitelistDeRoles(): void
    {
        $src = file_get_contents(APPPATH . 'Services/ClasesService.php');

        $this->assertStringContainsString(
            "whereIn('role', self::RESPONSABLE_TECNICO_ROLES)",
            $src,
            'getCoachOptions() debe filtrar por el whitelist de roles técnicos'
        );
        $this->assertStringContainsString(
            "whereIn('role', self::RESPONSABLE_STAFF_ROLES)",
            $src,
            'getStaffOptions() debe filtrar por el whitelist de roles de staff'
        );
    }
}
