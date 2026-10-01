<?php

use App\Models\NotificationModel;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Pantalla de detalle de una notificación (GET /notificaciones/:id) y su
 * enlace desde la campanita y el Centro de notificaciones.
 */
final class NotificacionDetalleTest extends CIUnitTestCase
{
    public function testLaRutaDeDetalleEstaRegistradaConFiltroAuth(): void
    {
        $routes = file_get_contents(APPPATH . 'Config/Routes.php');

        $this->assertMatchesRegularExpression(
            '~\$routes->get\(\'notificaciones/\(:num\)\', \'NotificacionesController::show/\$1\', \[\s*\'filter\' => \'auth\',~',
            $routes
        );
        $this->assertStringContainsString('GET  /notificaciones/:id ', $routes, 'La ruta debe documentarse en el bloque de comentarios.');
    }

    public function testElControladorLanza404SiNoHayPermisoYMarcaLeida(): void
    {
        $src = file_get_contents(APPPATH . 'Controllers/NotificacionesController.php');

        $this->assertStringContainsString('findForViewer($id, $userId)', $src);
        $this->assertStringContainsString('PageNotFoundException::forPageNotFound()', $src);
        $this->assertStringContainsString('$this->notifModel->markRead($userId, $id)', $src);
        $this->assertStringContainsString("view('notificaciones/show'", $src);
    }

    public function testLaCampanitaAbreElDetalleEnTodosLosCasos(): void
    {
        $navbar = file_get_contents(APPPATH . 'Views/components/navbar.php');

        $this->assertStringContainsString("window.location.href = BASE + 'notificaciones/' + id;", $navbar);
        // Ya no salta al origen según source_type: eso lo hace el botón del detalle.
        $this->assertStringNotContainsString("sourceType === 'conversation'", $navbar);
    }

    public function testLaListaYElDetalleEnlazanAlDetalleYAlOrigen(): void
    {
        $center = file_get_contents(APPPATH . 'Views/notificaciones/index.php');
        $this->assertStringContainsString("base_url('notificaciones/' . (int) \$n['id'])", $center);

        $show = file_get_contents(APPPATH . 'Views/notificaciones/show.php');
        $this->assertStringContainsString('NotificationModel::sourceLink($n)', $show);
        $this->assertStringContainsString("base_url('notificaciones')", $show);
        $this->assertStringContainsString("/download')", $show);
        $this->assertStringContainsString("nl2br(esc(\$n['body']))", $show);
        // .alert-jp es display:flex: no se usa en el detalle.
        $this->assertStringNotContainsString('alert-jp', $show);
    }

    public function testElModeloValidaDestinatarioORemitente(): void
    {
        $src = file_get_contents(APPPATH . 'Models/NotificationModel.php');

        $this->assertStringContainsString('function findForViewer(int $id, int $userId): ?array', $src);
        $this->assertStringContainsString('if (!$isRecipient && !$isSender)', $src);
        $this->assertStringContainsString("->where('recipient_id', \$userId)", $src);

        // Ids / usuarios no válidos se rechazan sin consultar la BD.
        $model = (new ReflectionClass(NotificationModel::class))->newInstanceWithoutConstructor();
        $this->assertNull($model->findForViewer(0, 5));
        $this->assertNull($model->findForViewer(5, 0));
        $this->assertNull($model->findForViewer(-1, -1));
    }

    public function testElEnlaceAlOrigenCubreLasTresClases(): void
    {
        $this->assertSame('Ver clase', NotificationModel::sourceLink(['source_type' => 'class', 'source_id' => 9])['label']);
        $this->assertSame('Ver ticket', NotificationModel::sourceLink(['source_type' => 'ticket', 'source_id' => 9])['label']);
        $this->assertSame('Ir a la conversación', NotificationModel::sourceLink(['source_type' => 'conversation', 'source_id' => 9])['label']);
        $this->assertNull(NotificationModel::sourceLink(['source_type' => null, 'source_id' => null]));
    }
}
