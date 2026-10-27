<?php

use CodeIgniter\Test\CIUnitTestCase;
use App\Models\NotificationModel;
use App\Models\NotificationPreferenceModel;
use App\Services\OrganizacionService as S;
use App\Services\OrgNoticeService;

/**
 * TICKET-014 — reglas puras de Organización: acceso por rol, visibilidad
 * equipo/personal, permisos de edición/movimiento y normalización de entradas.
 */
final class OrganizacionServiceTest extends CIUnitTestCase
{
    public function testAccessIsEveryRoleExceptPlayer(): void
    {
        foreach (['superadmin', 'admin', 'staff', 'coach'] as $r) {
            $this->assertTrue(S::canAccess($r), $r);
        }
        foreach (['player', 'alumno', '', null] as $r) {
            $this->assertFalse(S::canAccess($r), (string) $r);
        }
    }

    public function testPersonalItemsAreOnlyVisibleToCreator(): void
    {
        $item = ['visibility' => 'personal', 'created_by' => 5];
        $this->assertTrue(S::canSee($item, 5));
        $this->assertFalse(S::canSee($item, 6));
        $this->assertFalse(S::canEdit($item, 6, 'superadmin'), 'ni el superadmin ve lo personal ajeno');
        $this->assertFalse(S::canMove($item, 6, 'admin'));
    }

    public function testTeamItemEditRules(): void
    {
        $item = ['visibility' => 'team', 'created_by' => 5, 'assigned_to' => 7];
        $this->assertTrue(S::canEdit($item, 5, 'coach'));
        $this->assertTrue(S::canEdit($item, 9, 'admin'));
        $this->assertFalse(S::canEdit($item, 9, 'coach'));
        $this->assertFalse(S::canEdit($item, 7, 'staff'), 'el asignado no edita todo…');
        $this->assertTrue(S::canMove($item, 7, 'staff'), '…pero sí mueve su tarea');
        $this->assertFalse(S::canMove($item, 8, 'staff'));
    }

    public function testNormalizers(): void
    {
        $this->assertSame('en_curso', S::normalizeStatus('en_curso'));
        $this->assertSame('pendiente', S::normalizeStatus('inventado'));
        $this->assertSame('normal', S::normalizePriority('x'));
        $this->assertSame('personal', S::normalizeVisibility('personal'));
        $this->assertSame('team', S::normalizeVisibility('lo-que-sea'));
        $this->assertSame('2026-10-27', S::normalizeDate('2026-10-27'));
        $this->assertNull(S::normalizeDate('2026-02-31'));
        $this->assertNull(S::normalizeDate(''));
        $this->assertSame('09:05', S::normalizeTime('09:05:30'));
        $this->assertNull(S::normalizeTime('25:00'));
    }

    public function testNoticeLabels(): void
    {
        $this->assertSame('28/10 a las 18:30', OrgNoticeService::whenLabel('2026-10-28 18:30:00', false));
        $this->assertSame('28/10 (todo el día)', OrgNoticeService::whenLabel('2026-10-28 00:00:00', true));
        $this->assertSame('hoy', OrgNoticeService::dueLabel('2026-10-27', '2026-10-27'));
        $this->assertSame('mañana', OrgNoticeService::dueLabel('2026-10-28', '2026-10-27'));
        $this->assertSame('30/10', OrgNoticeService::dueLabel('2026-10-30', '2026-10-27'));
    }

    public function testOrganizacionCategoryIsHiddenFromPlayers(): void
    {
        $this->assertArrayHasKey('organizacion', NotificationPreferenceModel::categories('coach'));
        $this->assertArrayHasKey('organizacion', NotificationPreferenceModel::categories());
        $this->assertArrayNotHasKey('organizacion', NotificationPreferenceModel::categories('player'));
        $this->assertSame('organizacion', NotificationPreferenceModel::categoryFor(['source_type' => 'org_task']));
        $this->assertSame('organizacion', NotificationPreferenceModel::categoryFor(['source_type' => 'org_event']));
    }

    public function testOrganizacionNotificationLinks(): void
    {
        $this->assertSame('organizacion?tarea=7', NotificationModel::sourceLink(['source_type' => 'org_task', 'source_id' => 7])['path']);
        $this->assertSame('organizacion?evento=9', NotificationModel::sourceLink(['source_type' => 'org_event', 'source_id' => 9])['path']);
        $kept = NotificationModel::prepareSource(['source_type' => 'org_task', 'source_id' => 7], true);
        $this->assertSame('org_task', $kept['source_type']);
    }

    public function testEveryOrganizacionRouteHasAuthAndRoleFilter(): void
    {
        $src = file_get_contents(APPPATH . 'Config/Routes.php');
        $this->assertMatchesRegularExpression(
            "/group\\('organizacion',\\s*\\['filter'\\s*=>\\s*\\['auth',\\s*'role:superadmin,admin,staff,coach'\\]\\]/",
            $src
        );
    }
}
