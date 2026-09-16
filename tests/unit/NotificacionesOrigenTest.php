<?php

use App\Models\NotificationModel;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * TICKET-010 — las notificaciones no llevaban a su origen (ticket o
 * conversación): NotificationModel no tenía source_type/source_id en
 * allowedFields y CodeIgniter los descartaba en silencio; además los avisos
 * de TicketsController ni siquiera los enviaban.
 */
final class NotificacionesOrigenTest extends CIUnitTestCase
{
    public function testElModeloPermiteGuardarElOrigen(): void
    {
        $fields = (new ReflectionProperty(NotificationModel::class, 'allowedFields'))
            ->getDefaultValue();

        $this->assertContains('source_type', $fields);
        $this->assertContains('source_id', $fields);
    }

    public function testOrigenValidoSeConserva(): void
    {
        $data = NotificationModel::prepareSource(
            ['title' => 'x', 'source_type' => 'ticket', 'source_id' => '15'],
            true
        );

        $this->assertSame('ticket', $data['source_type']);
        $this->assertSame(15, $data['source_id']);
    }

    public function testSinColumnasSeEnviaIgualPeroSinEnlace(): void
    {
        $data = NotificationModel::prepareSource(
            ['title' => 'x', 'source_type' => 'conversation', 'source_id' => 37],
            false
        );

        $this->assertSame(['title' => 'x'], $data);
    }

    public function testOrigenInvalidoSeDescarta(): void
    {
        foreach ([
            ['source_type' => 'clase', 'source_id' => 3],
            ['source_type' => 'ticket', 'source_id' => 0],
            ['source_type' => 'ticket', 'source_id' => -4],
            ['source_type' => 'ticket'],
        ] as $source) {
            $this->assertSame(['title' => 'x'], NotificationModel::prepareSource(['title' => 'x'] + $source, true));
        }
    }

    public function testEnlaceAlOrigen(): void
    {
        $this->assertSame('tickets/15', NotificationModel::sourceLink(['source_type' => 'ticket', 'source_id' => '15'])['path']);
        $this->assertSame('mensajes?conv=37', NotificationModel::sourceLink(['source_type' => 'conversation', 'source_id' => 37])['path']);

        $this->assertNull(NotificationModel::sourceLink(['source_type' => null, 'source_id' => null]));
        $this->assertNull(NotificationModel::sourceLink(['source_type' => 'ticket', 'source_id' => 0]));
        $this->assertNull(NotificationModel::sourceLink(['source_type' => 'otro', 'source_id' => 5]));
    }

    public function testTodosLosAvisosDeTicketsLlevanOrigen(): void
    {
        $src = file_get_contents(APPPATH . 'Controllers/TicketsController.php');

        $calls   = substr_count($src, '->createWithRecipients(');
        $sources = substr_count($src, "'source_type' => NotificationModel::SOURCE_TICKET");

        $this->assertGreaterThanOrEqual(4, $calls);
        $this->assertSame($calls, $sources, 'Cada notificación de TicketsController debe enlazar al ticket.');
    }

    public function testLosAvisosDeMensajesLlevanOrigen(): void
    {
        $src = file_get_contents(APPPATH . 'Controllers/MensajesController.php');

        $this->assertStringContainsString("'source_type' => NotificationModel::SOURCE_CONVERSATION", $src);
        $this->assertStringContainsString("'source_type' => NotificationModel::SOURCE_TICKET", $src);
    }

    public function testMigracionIdempotenteYSinProblemasDeCollation(): void
    {
        $migration = file_get_contents(APPPATH . 'Database/Migrations/2026-09-16-000001_AddSourceToNotifications.php');
        $this->assertStringContainsString("fieldExists('source_type', 'notifications')", $migration);

        $sql = file_get_contents(ROOTPATH . 'docs/deploy/migraciones_notificaciones_origen.sql');
        $this->assertStringContainsString('ADD COLUMN IF NOT EXISTS `source_type`', $sql);
        $this->assertStringContainsString('ADD COLUMN IF NOT EXISTS `source_id`', $sql);

        // Mismo relleno en la migración y en el SQL de producción.
        foreach ([$migration, $sql] as $backfill) {
            $this->assertStringContainsString('CAST(n.title AS BINARY)', $backfill);
            $this->assertStringContainsString("n.title LIKE 'Nuevo mensaje de %'", $backfill);
            // Nº de ticket reutilizado: nunca enlazar a algo posterior al aviso.
            $this->assertStringContainsString('n.created_at >= t.created_at', $backfill);
            $this->assertStringContainsString('n.created_at >= c.created_at', $backfill);
        }
    }

    public function testLaCampanitaYElCentroDeNotificacionesEnlazanAlOrigen(): void
    {
        $navbar = file_get_contents(APPPATH . 'Views/components/navbar.php');
        $this->assertStringContainsString("BASE + 'tickets/' + sourceId", $navbar);
        $this->assertStringContainsString("BASE + 'mensajes?conv=' + sourceId", $navbar);

        $center = file_get_contents(APPPATH . 'Views/notificaciones/index.php');
        $this->assertStringContainsString('NotificationModel::sourceLink($n)', $center);
    }
}
