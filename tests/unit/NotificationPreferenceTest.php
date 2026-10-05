<?php

use App\Models\NotificationModel;
use App\Models\NotificationPreferenceModel;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Clasificación de notificaciones en categorías de preferencias (lógica pura, sin BD).
 * El filtrado real por usuario se prueba en el flujo completo (ver docs/deploy).
 */
final class NotificationPreferenceTest extends CIUnitTestCase
{
    public function testSourceTypeDeterminesCategory(): void
    {
        $map = [
            NotificationModel::SOURCE_CONVERSATION => NotificationPreferenceModel::CAT_MENSAJES,
            NotificationModel::SOURCE_CLASS        => NotificationPreferenceModel::CAT_CLASES,
            NotificationModel::SOURCE_BONO         => NotificationPreferenceModel::CAT_BONOS,
            NotificationModel::SOURCE_TICKET       => NotificationPreferenceModel::CAT_TICKETS,
        ];
        foreach ($map as $source => $cat) {
            $this->assertSame($cat, NotificationPreferenceModel::categoryFor(['source_type' => $source, 'source_id' => 1]));
        }
    }

    public function testNotificationWithoutSourceIsAnAnnouncement(): void
    {
        $this->assertSame('avisos', NotificationPreferenceModel::categoryFor(['title' => 'Hola']));
        $this->assertSame('avisos', NotificationPreferenceModel::categoryFor(['source_type' => null]));
    }

    public function testExplicitCategoryWinsOverSource(): void
    {
        // Avisos de bono que no tienen origen enlazable
        $this->assertSame('bonos', NotificationPreferenceModel::categoryFor(['category' => 'bonos']));
        $this->assertSame('bonos', NotificationPreferenceModel::categoryFor(['category' => 'bonos', 'source_type' => 'class']));
    }

    public function testUnknownExplicitCategoryIsIgnored(): void
    {
        $this->assertSame('clases', NotificationPreferenceModel::categoryFor(['category' => 'inventada', 'source_type' => 'class']));
        $this->assertSame('avisos', NotificationPreferenceModel::categoryFor(['category' => 'inventada']));
    }

    public function testEverySourceTypeHasACategoryDefined(): void
    {
        $cats = NotificationPreferenceModel::categories();
        foreach (['mensajes', 'clases', 'bonos', 'tickets', 'avisos'] as $c) {
            $this->assertArrayHasKey($c, $cats);
            $this->assertNotEmpty($cats[$c]['label']);
        }
    }
}
