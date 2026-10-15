<?php

use App\Controllers\AjustesNotificacionesController;
use App\Controllers\NotificacionesController;
use App\Models\NotificationModel;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\URI;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\App as AppConfig;

/**
 * Los dos endpoints que usa el usuario al tocar un aviso o cambiar sus ajustes:
 *  - GET /notificaciones/:id/ir  (destino del clic en el push: marca leída y salta al origen)
 *  - POST /configuracion/notificaciones/save
 * Esquema a mano (ver MensajesInactiveUserTest).
 */
final class NotificationEndpointsTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = false;

    private const TABLES = ['notification_recipients', 'notifications', 'notification_preferences', 'users'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->dropSchema();
        $this->createSchema();
        $this->db->table('users')->insertBatch([
            ['id' => 1, 'name' => 'Admin', 'role' => 'admin'],
            ['id' => 2, 'name' => 'Ana', 'role' => 'player'],
            ['id' => 3, 'name' => 'Intruso', 'role' => 'player'],
        ]);
    }

    protected function tearDown(): void
    {
        $this->dropSchema();
        parent::tearDown();
    }

    // ── /notificaciones/:id/ir ───────────────────────────────────────────

    public function testTappingAPushMarksItReadAndJumpsToItsSource(): void
    {
        $id = $this->notification(['source_type' => NotificationModel::SOURCE_TICKET, 'source_id' => 7], [2]);

        $res = $this->go(2, $id);

        $this->assertStringEndsWith('/tickets/7', $res->getHeaderLine('Location'));
        $this->assertNotNull($this->readAt($id, 2), 'queda marcada como leída');
    }

    public function testEachSourceTypeJumpsToItsOwnScreen(): void
    {
        $cases = [
            NotificationModel::SOURCE_CONVERSATION => '/mensajes?conv=9',
            NotificationModel::SOURCE_CLASS        => '/clases/9',
            NotificationModel::SOURCE_BONO         => '/bonos/9',
        ];
        foreach ($cases as $type => $suffix) {
            $id = $this->notification(['source_type' => $type, 'source_id' => 9], [2]);
            $this->assertStringEndsWith($suffix, $this->go(2, $id)->getHeaderLine('Location'), $type);
        }
    }

    public function testNotificationWithoutSourceOpensItsDetail(): void
    {
        $id = $this->notification([], [2]);

        $this->assertStringEndsWith("/notificaciones/{$id}", $this->go(2, $id)->getHeaderLine('Location'));
    }

    public function testAStrangerCannotMarkSomeoneElsesNotificationNorLearnWhereItGoes(): void
    {
        $id = $this->notification(['source_type' => NotificationModel::SOURCE_TICKET, 'source_id' => 7], [2]);

        $res = $this->go(3, $id); // el 3 no es ni destinatario ni remitente

        $this->assertStringEndsWith('/notificaciones', $res->getHeaderLine('Location'));
        $this->assertNull($this->readAt($id, 2), 'la notificación del otro usuario sigue sin leer');
    }

    public function testUnknownNotificationGoesToTheCenter(): void
    {
        $this->assertStringEndsWith('/notificaciones', $this->go(2, 9999)->getHeaderLine('Location'));
    }

    public function testAlreadyReadStaysReadWithTheSameTimestamp(): void
    {
        $id = $this->notification([], [2]);
        $this->go(2, $id);
        $first = $this->readAt($id, 2);

        $this->go(2, $id);

        $this->assertSame($first, $this->readAt($id, 2));
    }

    // ── Guardar preferencias ─────────────────────────────────────────────

    public function testSavingPreferencesPersistsThemForTheCaller(): void
    {
        $this->save(2, ['pref' => ['mensajes' => ['in_app' => '1', 'push' => '1'], 'bonos' => ['in_app' => '1']]]);

        $rows = $this->db->table('notification_preferences')->where('user_id', 2)->get()->getResultArray();
        $by   = array_column($rows, null, 'category');
        $this->assertCount(5, $rows);
        $this->assertSame([1, 1], [(int) $by['mensajes']['in_app'], (int) $by['mensajes']['push']]);
        $this->assertSame([1, 0], [(int) $by['bonos']['in_app'], (int) $by['bonos']['push']]);
        $this->assertSame([0, 0], [(int) $by['avisos']['in_app'], (int) $by['avisos']['push']], 'lo no enviado = apagado');
        $this->assertSame(0, $this->db->table('notification_preferences')->where('user_id', 3)->countAllResults(), 'no toca a otros usuarios');
    }

    /** @dataProvider garbageInput */
    public function testSavingGarbageNeverCrashesNorStoresUnknownCategories(mixed $pref): void
    {
        $this->save(2, ['pref' => $pref]);

        $cats = array_column($this->db->table('notification_preferences')->where('user_id', 2)->get()->getResultArray(), 'category');
        sort($cats);
        $this->assertSame(['avisos', 'bonos', 'clases', 'mensajes', 'tickets'], $cats);
    }

    public static function garbageInput(): array
    {
        return [
            'texto'              => ['hola'],
            'categoría inventada' => [['inventada' => ['in_app' => '1'], 'mensajes' => 'texto']],
            'vacío'              => [[]],
            'valores raros'      => [['clases' => ['in_app' => ['a'], 'push' => null]]],
        ];
    }

    public function testNonAdminAlwaysReturnsToTheirOwnPage(): void
    {
        $res = $this->save(2, ['pref' => [], 'return_to' => 'section'], 'player');
        $this->assertStringEndsWith('/configuracion/notificaciones', $res->getHeaderLine('Location'));
    }

    public function testAdminCanReturnToTheSettingsTab(): void
    {
        $res = $this->save(1, ['pref' => [], 'return_to' => 'section'], 'admin');
        $this->assertStringEndsWith('/configuracion?section=notificaciones', $res->getHeaderLine('Location'));
    }

    // ── Utilidades ───────────────────────────────────────────────────────

    private function go(int $userId, int $notifId): \CodeIgniter\HTTP\ResponseInterface
    {
        $ctrl = new class ($userId) extends NotificacionesController {
            public function __construct(private int $fakeUserId)
            {
            }
            protected function currentUserId(): ?int
            {
                return $this->fakeUserId;
            }
        };
        $ctrl->initController($this->request('GET', []), service('response'), service('logger'));

        return $ctrl->go($notifId);
    }

    private function save(int $userId, array $post, string $role = 'player'): \CodeIgniter\HTTP\ResponseInterface
    {
        $ctrl = new class ($userId, $role) extends AjustesNotificacionesController {
            public function __construct(private int $fakeUserId, private string $fakeRole)
            {
            }
            protected function currentUserId(): ?int
            {
                return $this->fakeUserId;
            }
            protected function currentRole(): ?string
            {
                return $this->fakeRole;
            }
        };
        $ctrl->initController($this->request('POST', $post), service('response'), service('logger'));

        return $ctrl->save();
    }

    private function request(string $method, array $post): IncomingRequest
    {
        $r = new IncomingRequest(new AppConfig(), new URI('http://localhost/x'), null, new UserAgent());
        $r->setMethod($method);
        $r->setGlobal('post', $post);

        return $r;
    }

    /** @param list<int> $recipients */
    private function notification(array $extra, array $recipients): int
    {
        $this->db->table('notifications')->insert([
            'sender_id' => 1, 'type' => 'individual', 'title' => 'Hola', 'body' => 'x', 'created_at' => date('Y-m-d H:i:s'),
        ] + $extra);
        $id = (int) $this->db->insertID();
        foreach ($recipients as $r) {
            $this->db->table('notification_recipients')->insert(['notification_id' => $id, 'recipient_id' => $r, 'read_at' => null]);
        }

        return $id;
    }

    private function readAt(int $notifId, int $userId): ?string
    {
        $row = $this->db->table('notification_recipients')->where(['notification_id' => $notifId, 'recipient_id' => $userId])->get()->getRowArray();

        return $row['read_at'] ?? null;
    }

    private function dropSchema(): void
    {
        $forge = \Config\Database::forge($this->DBGroup);
        foreach (self::TABLES as $t) {
            $forge->dropTable($t, true);
        }
    }

    private function createSchema(): void
    {
        $forge = \Config\Database::forge($this->DBGroup);
        $make  = static function (string $table, array $fields) use ($forge): void {
            $forge->addField($fields);
            $forge->addKey('id', true);
            $forge->createTable($table, true);
        };

        $make('users', [
            'id'     => ['type' => 'INT', 'auto_increment' => true],
            'name'   => ['type' => 'VARCHAR', 'constraint' => 150],
            'role'   => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'avatar' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
        ]);
        $make('notifications', [
            'id'          => ['type' => 'INT', 'auto_increment' => true],
            'sender_id'   => ['type' => 'INT'],
            'type'        => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'title'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'body'        => ['type' => 'TEXT', 'null' => true],
            'file_path'   => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'file_name'   => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'file_size'   => ['type' => 'INT', 'null' => true],
            'source_type' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'source_id'   => ['type' => 'INT', 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $make('notification_recipients', [
            'id'              => ['type' => 'INT', 'auto_increment' => true],
            'notification_id' => ['type' => 'INT'],
            'recipient_id'    => ['type' => 'INT'],
            'read_at'         => ['type' => 'DATETIME', 'null' => true],
        ]);
        $make('notification_preferences', [
            'id'         => ['type' => 'INT', 'auto_increment' => true],
            'user_id'    => ['type' => 'INT'],
            'category'   => ['type' => 'VARCHAR', 'constraint' => 20],
            'in_app'     => ['type' => 'TINYINT', 'default' => 1],
            'push'       => ['type' => 'TINYINT', 'default' => 1],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
    }
}
