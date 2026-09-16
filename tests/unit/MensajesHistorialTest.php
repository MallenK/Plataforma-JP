<?php

use App\Models\MessageModel;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * TICKET-009 — en un chat largo no se podía hacer scroll y solo llegaban los
 * 50 últimos mensajes. Ahora el historial se carga por bloques al subir.
 *
 * La paginación (qué bloque y si quedan más) se prueba en puro; el scroll
 * no es testeable con render, así que se verifica que app.css y la vista
 * conservan las piezas que lo arreglan (verificado además en navegador).
 */
final class MensajesHistorialTest extends CIUnitTestCase
{
    private function rows(int ...$ids): array
    {
        return array_map(static fn ($id) => ['id' => $id, 'body' => "msg {$id}"], $ids);
    }

    public function testBloqueConMasPorCargarVuelveEnOrdenCronologico(): void
    {
        // La consulta pide limit + 1 filas, de la más reciente a la más antigua.
        $page = MessageModel::buildPage($this->rows(10, 9, 8, 7), 3);

        $this->assertSame([8, 9, 10], array_column($page['messages'], 'id'));
        $this->assertTrue($page['has_more']);
    }

    public function testUltimoBloqueNoTieneMas(): void
    {
        $page = MessageModel::buildPage($this->rows(3, 2, 1), 3);

        $this->assertSame([1, 2, 3], array_column($page['messages'], 'id'));
        $this->assertFalse($page['has_more']);
    }

    public function testConversacionVacia(): void
    {
        $this->assertSame(['messages' => [], 'has_more' => false], MessageModel::buildPage([], 30));
    }

    public function testTamanoDeBloqueAcotado(): void
    {
        $this->assertSame(1, MessageModel::clampPageSize(0));
        $this->assertSame(1, MessageModel::clampPageSize(-5));
        $this->assertSame(30, MessageModel::clampPageSize(30));
        $this->assertSame(MessageModel::MAX_PAGE_SIZE, MessageModel::clampPageSize(100000));
    }

    public function testElEndpointDeHistorialExisteYRequiereSesion(): void
    {
        $this->assertTrue(method_exists(\App\Controllers\MensajesController::class, 'ajaxHistory'));

        $routes = file_get_contents(APPPATH . 'Config/Routes.php');
        $this->assertMatchesRegularExpression(
            "#routes->get\('mensajes/\(:num\)/historial', 'MensajesController::ajaxHistory/\\\$1', \[\s*'filter' => 'auth',#",
            $routes
        );
    }

    public function testAlAbrirYaNoSeCarganLos50UltimosFijos(): void
    {
        $controller = file_get_contents(APPPATH . 'Controllers/MensajesController.php');
        $this->assertStringNotContainsString('getForConversation(', $controller);
        $this->assertStringContainsString("'has_more' => \$page['has_more']", $controller);
    }

    public function testElScrollDelChatNoSeDesbordaHaciaArriba(): void
    {
        // Sin flex-shrink: 0, min-height: 100% + justify-content: flex-end
        // mandaba los mensajes que no cabían por encima del contenedor.
        $block = $this->cssBlock('.chat-messages-inner');
        $this->assertStringContainsString('flex-shrink: 0', $block);

        $this->assertStringContainsString('overflow-y: auto', $this->cssBlock('.chat-messages'));
        $this->assertStringContainsString('overflow-anchor: none', $this->cssBlock('.chat-messages'));
    }

    public function testLaVistaCargaPorBloquesEImagenesEnDiferido(): void
    {
        $html = file_get_contents(APPPATH . 'Views/mensajes/index.php');

        $this->assertStringContainsString("'/historial?before=' + oldestMsgId", $html);
        $this->assertStringContainsString('loading="lazy"', $html);
        $this->assertStringContainsString('&read_from=', $html);
    }

    private function cssBlock(string $selector): string
    {
        $css = file_get_contents(FCPATH . 'assets/css/app.css');
        $pos = strpos($css, $selector . ' {');
        $this->assertNotFalse($pos, "No se encontró la regla {$selector} en app.css");
        return substr($css, $pos, strpos($css, '}', $pos) - $pos);
    }
}
