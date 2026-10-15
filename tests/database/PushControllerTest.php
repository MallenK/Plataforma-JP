<?php

use App\Controllers\PushController;
use App\Libraries\Push\PushTransport;
use App\Libraries\WebPush;
use App\Services\PushService;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\URI;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\App as AppConfig;

/**
 * Endpoints /push/subscribe, /push/unsubscribe y /push/test: códigos de respuesta,
 * validación de entrada y efecto en BD. Esquema a mano (ver MensajesInactiveUserTest).
 */
final class PushControllerTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = false;

    private const TABLES = ['push_subscriptions', 'notification_recipients', 'notifications', 'notification_preferences'];

    /** Hash que el controlador "guardaría en sesión". */
    public static ?string $rememberedHash = null;
    public static bool $forgot = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dropSchema();
        $this->createSchema();
        PushService::setTransport(new class implements PushTransport {
            public function sendMany(array $targets, string|array $payloads, int $ttl = 86400, string $urgency = 'normal', int $timeout = 6): array
            {
                return array_fill(0, count($targets), 201);
            }
        });
        PushService::resetQueue();
        self::$rememberedHash = null;
        self::$forgot = false;
    }

    protected function tearDown(): void
    {
        PushService::setTransport(null);
        PushService::resetQueue();
        $this->dropSchema();
        parent::tearDown();
    }

    // ── subscribe ────────────────────────────────────────────────────────

    public function testSubscribeStoresTheDeviceAndRemembersItForLogout(): void
    {
        $res = $this->controller(5, $this->subscriptionBody())->subscribe();

        $this->assertSame(200, $res->getStatusCode());
        $this->assertTrue($this->body($res)['ok']);
        $this->assertSame(1, $this->db->table('push_subscriptions')->where('user_id', 5)->countAllResults());
        $this->assertSame(hash('sha256', 'https://fcm.googleapis.com/fcm/send/abc'), self::$rememberedHash);
    }

    public function testSubscribeAlwaysReturnsAFreshCsrfToken(): void
    {
        $res = $this->controller(5, $this->subscriptionBody())->subscribe();
        $this->assertArrayHasKey('csrf', $this->body($res));
    }

    public function testSubscribeWithoutVapidKeysIs503(): void
    {
        PushService::setTransport(null);
        putenv('vapid.publicKey=');

        $res = $this->controller(5, $this->subscriptionBody())->subscribe();

        $this->assertSame(503, $res->getStatusCode());
        $this->assertSame(0, $this->db->table('push_subscriptions')->countAllResults());
    }

    public function testMalformedJsonIs400NotA500(): void
    {
        $res = $this->controller(5, '{esto no es json')->subscribe();
        $this->assertSame(400, $res->getStatusCode());
    }

    public function testMissingSubscriptionIs422(): void
    {
        $res = $this->controller(5, json_encode(['nada' => 1]))->subscribe();
        $this->assertSame(422, $res->getStatusCode());
    }

    public function testForbiddenEndpointIs422AndNothingIsStored(): void
    {
        $res = $this->controller(5, $this->subscriptionBody('https://evil.example.com/x'))->subscribe();

        $this->assertSame(422, $res->getStatusCode());
        $this->assertSame(0, $this->db->table('push_subscriptions')->countAllResults());
        $this->assertNull(self::$rememberedHash);
    }

    public function testInvalidKeysIs422(): void
    {
        $res = $this->controller(5, json_encode(['subscription' => ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc', 'keys' => ['p256dh' => 'corta', 'auth' => 'x']]]))->subscribe();
        $this->assertSame(422, $res->getStatusCode());
    }

    public function testKeysOfTheWrongTypeDoNotCrash(): void
    {
        $res = $this->controller(5, json_encode(['subscription' => ['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc', 'keys' => 'texto']]))->subscribe();
        $this->assertSame(422, $res->getStatusCode());
    }

    // ── unsubscribe ──────────────────────────────────────────────────────

    public function testUnsubscribeRemovesOnlyTheCallersDevice(): void
    {
        $this->controller(5, $this->subscriptionBody())->subscribe();

        $other = $this->controller(6, json_encode(['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc']))->unsubscribe();
        $this->assertSame(200, $other->getStatusCode());
        $this->assertSame(1, $this->db->table('push_subscriptions')->countAllResults(), 'otro usuario no puede borrarla');

        $this->controller(5, json_encode(['endpoint' => 'https://fcm.googleapis.com/fcm/send/abc']))->unsubscribe();
        $this->assertSame(0, $this->db->table('push_subscriptions')->countAllResults());
        $this->assertTrue(self::$forgot);
    }

    public function testUnsubscribeToleratesGarbage(): void
    {
        $res = $this->controller(5, 'basura')->unsubscribe();
        $this->assertSame(200, $res->getStatusCode());
    }

    // ── test ─────────────────────────────────────────────────────────────

    public function testTestPushWithoutADeviceIs409(): void
    {
        $res = $this->controller(5, '{}')->test();
        $this->assertSame(409, $res->getStatusCode());
        $this->assertSame(0, $this->db->table('notifications')->countAllResults());
    }

    public function testTestPushCreatesARealNotificationForTheUser(): void
    {
        $this->controller(5, $this->subscriptionBody())->subscribe();

        $res = $this->controller(5, '{}')->test();

        $this->assertSame(200, $res->getStatusCode());
        $this->assertSame(1, $this->db->table('notification_recipients')->where('recipient_id', 5)->countAllResults());
    }

    // ── Utilidades ───────────────────────────────────────────────────────

    private function subscriptionBody(string $endpoint = 'https://fcm.googleapis.com/fcm/send/abc'): string
    {
        return json_encode(['subscription' => [
            'endpoint' => $endpoint,
            'keys'     => ['p256dh' => WebPush::generateVapidKeys()['publicKey'], 'auth' => WebPush::b64uEncode(random_bytes(16))],
        ]]);
    }

    private function body(\CodeIgniter\HTTP\ResponseInterface $res): array
    {
        return json_decode($res->getBody(), true);
    }

    private function controller(int $userId, string $jsonBody): PushController
    {
        $ctrl = new class ($userId) extends PushController {
            public function __construct(private int $fakeUserId)
            {
                parent::__construct();
            }
            protected function currentUserId(): ?int
            {
                return $this->fakeUserId;
            }
            protected function currentRole(): ?string
            {
                return 'player';
            }
            protected function rememberEndpoint(string $hash): void
            {
                PushControllerTest::$rememberedHash = $hash;
            }
            protected function forgetEndpoint(): void
            {
                PushControllerTest::$forgot = true;
            }
        };

        $request = new IncomingRequest(new AppConfig(), new URI('http://localhost/push/subscribe'), $jsonBody, new UserAgent());
        $request->setMethod('POST');
        $ctrl->initController($request, service('response'), service('logger'));

        return $ctrl;
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
        $make  = static function (string $table, array $fields, array $unique = []) use ($forge): void {
            $forge->addField($fields);
            $forge->addKey('id', true);
            foreach ($unique as $u) {
                $forge->addUniqueKey($u);
            }
            $forge->createTable($table, true);
        };

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
        $make('push_subscriptions', [
            'id'            => ['type' => 'INT', 'auto_increment' => true],
            'user_id'       => ['type' => 'INT'],
            'endpoint'      => ['type' => 'TEXT'],
            'endpoint_hash' => ['type' => 'CHAR', 'constraint' => 64],
            'p256dh'        => ['type' => 'VARCHAR', 'constraint' => 255],
            'auth'          => ['type' => 'VARCHAR', 'constraint' => 255],
            'user_agent'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'failures'      => ['type' => 'TINYINT', 'default' => 0],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'last_used_at'  => ['type' => 'DATETIME', 'null' => true],
        ], ['endpoint_hash']);
    }
}
