<?php

use App\Libraries\Push\PushTransport;
use App\Libraries\WebPush;
use App\Models\NotificationModel;
use App\Models\NotificationPreferenceModel;
use App\Services\PushService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Flujo completo del push contra BD (SQLite en memoria) con un transporte falso:
 * crear notificación → preferencias → suscripciones → envío → qué se hace con cada respuesta.
 *
 * Esquema mínimo creado a mano (ver nota en MensajesInactiveUserTest).
 */
final class PushDeliveryTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = false;

    private const TABLES = ['push_subscriptions', 'notification_recipients', 'notifications', 'notification_preferences'];

    private FakePushTransport $transport;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dropSchema();
        $this->createSchema();

        $this->transport = new FakePushTransport();
        PushService::setTransport($this->transport);
        PushService::resetQueue();
    }

    protected function tearDown(): void
    {
        PushService::setTransport(null);
        PushService::resetQueue();
        $this->dropSchema();
        parent::tearDown();
    }

    // ── Suscripciones ────────────────────────────────────────────────────

    public function testSubscribeCreatesOneRowAndReassignsToTheNewUser(): void
    {
        $svc = new PushService();
        $ep  = $this->endpoint('a');

        $hash = $svc->subscribe(1, $ep, $this->p256dh(), $this->auth(), 'UA-1');
        $this->assertSame(hash('sha256', $ep), $hash);
        $this->assertSame(1, $svc->countForUser(1));

        // Otro usuario inicia sesión en el mismo navegador: la fila se reasigna, no se duplica.
        $svc->subscribe(2, $ep, $this->p256dh(), $this->auth(), 'UA-1');
        $this->assertSame(0, $svc->countForUser(1));
        $this->assertSame(1, $svc->countForUser(2));
        $this->assertSame(1, $this->db->table('push_subscriptions')->countAllResults());
    }

    public function testSubscribingTwiceKeepsASingleRowAndResetsFailures(): void
    {
        $svc = new PushService();
        $ep  = $this->endpoint('a');
        $svc->subscribe(1, $ep, $this->p256dh(), $this->auth());
        $this->db->table('push_subscriptions')->update(['failures' => 3]);

        $svc->subscribe(1, $ep, $this->p256dh(), $this->auth());

        $rows = $this->db->table('push_subscriptions')->get()->getResultArray();
        $this->assertCount(1, $rows);
        $this->assertSame(0, (int) $rows[0]['failures']);
    }

    /** @dataProvider invalidSubscriptions */
    public function testSubscribeRejectsInvalidInput(string $endpoint, bool $badKeys): void
    {
        $this->expectException(\InvalidArgumentException::class);
        (new PushService())->subscribe(1, $endpoint, $badKeys ? 'corta' : $this->p256dh(), $badKeys ? 'x' : $this->auth());
    }

    public static function invalidSubscriptions(): array
    {
        return [
            'endpoint fuera de la lista blanca' => ['https://evil.example.com/push', false],
            'endpoint http'                     => ['http://fcm.googleapis.com/fcm/send/x', false],
            'claves inválidas'                  => ['https://fcm.googleapis.com/fcm/send/x', true],
        ];
    }

    public function testUnsubscribeOnlyRemovesTheOwnersSubscription(): void
    {
        $svc  = new PushService();
        $hash = $svc->subscribe(1, $this->endpoint('a'), $this->p256dh(), $this->auth());

        $svc->unsubscribeByHash($hash, 2); // otro usuario: no puede borrarla
        $this->assertSame(1, $svc->countForUser(1));

        $svc->unsubscribeByHash($hash, 1);
        $this->assertSame(0, $svc->countForUser(1));
    }

    public function testLogoutStyleUnsubscribeByHashWithoutUserRemovesIt(): void
    {
        $svc  = new PushService();
        $hash = $svc->subscribe(1, $this->endpoint('a'), $this->p256dh(), $this->auth());
        $svc->unsubscribeByHash($hash);
        $this->assertSame(0, $svc->countForUser(1));
    }

    // ── Envío ligado a notificaciones ────────────────────────────────────

    public function testNotificationIsPushedToRecipientsButNeverToTheSender(): void
    {
        $this->subscribeUsers([1, 2, 3]);

        $id = $this->notify(1, [1, 2, 3]); // el 1 es el remitente Y figura entre los destinatarios

        $this->assertGreaterThan(0, $id);
        $this->assertCount(2, $this->transport->sent);
        $endpoints = array_column($this->transport->sent, 'endpoint');
        $this->assertNotContains($this->endpoint('u1'), $endpoints);

        $payload = json_decode($this->transport->sent[0]['body'], true);
        $this->assertSame($id, $payload['id']);
        $this->assertSame("notificaciones/{$id}/ir", $payload['url']);
        $this->assertSame(1, $payload['unread']);
    }

    public function testPushIsSentWithHighUrgencySoLockedPhonesGetItAtOnce(): void
    {
        $this->subscribeUsers([2]);

        $this->notify(1, [2]);

        $this->assertSame(['high'], $this->transport->urgencies);
    }

    public function testSuccessfulDeliveryResetsFailureCounter(): void
    {
        $this->subscribeUsers([2]);
        $this->db->table('push_subscriptions')->update(['failures' => 3]);

        $this->notify(1, [2]);

        $this->assertSame(0, (int) $this->db->table('push_subscriptions')->get()->getRow()->failures);
    }

    public function testGoneSubscriptionIsDeletedImmediately(): void
    {
        $this->subscribeUsers([2, 3]);
        $this->transport->codeFor($this->endpoint('u2'), 410);

        $this->notify(1, [2, 3]);

        $left = array_column($this->db->table('push_subscriptions')->get()->getResultArray(), 'user_id');
        $this->assertSame([3], array_map('intval', $left));
    }

    public function testTemporaryOutageNeverDeletesNorCounts(): void
    {
        $this->subscribeUsers([2, 3]);
        $this->db->table('push_subscriptions')->update(['failures' => 4]);
        $this->transport->defaultCode = 503;

        $this->notify(1, [2, 3]);

        $rows = $this->db->table('push_subscriptions')->get()->getResultArray();
        $this->assertCount(2, $rows);
        $this->assertSame([4, 4], array_map(static fn ($r) => (int) $r['failures'], $rows));
    }

    public function testRejectedSubscriptionIsDeletedOnTheFifthFailure(): void
    {
        $this->subscribeUsers([2, 3]);
        $this->transport->codeFor($this->endpoint('u2'), 400);

        for ($i = 1; $i <= 4; $i++) {
            $this->notify(1, [2, 3]);
        }
        $this->assertSame(4, (int) $this->db->table('push_subscriptions')->where('user_id', 2)->get()->getRow()->failures);

        $this->notify(1, [2, 3]);
        $this->assertSame(0, $this->db->table('push_subscriptions')->where('user_id', 2)->countAllResults());
        $this->assertSame(1, $this->db->table('push_subscriptions')->where('user_id', 3)->countAllResults());
    }

    public function testBadVapidConfigurationDoesNotWipeSubscriptions(): void
    {
        $this->subscribeUsers([2, 3, 4]);
        $this->db->table('push_subscriptions')->update(['failures' => 4]);
        $this->transport->defaultCode = 403; // todos los servicios rechazan la firma

        $this->notify(1, [2, 3, 4]);

        $this->assertSame(3, $this->db->table('push_subscriptions')->countAllResults(), 'Un VAPID mal puesto no puede vaciar la tabla');
    }

    public function testNothingIsSentForNotificationsThatNoLongerExist(): void
    {
        $this->subscribeUsers([2]);

        // Simula una transacción revertida: se encola un id que no está en BD.
        PushService::queueForNotification(9999, ['sender_id' => 1, 'title' => 'x', 'body' => 'y'], [2]);

        $this->assertSame([], $this->transport->sent);
    }

    public function testATransportThatThrowsNeverBreaksTheCaller(): void
    {
        $this->subscribeUsers([2]);
        $this->transport->throw = true;

        $id = $this->notify(1, [2]); // no debe lanzar

        $this->assertGreaterThan(0, $id);
        $this->assertSame(1, $this->db->table('push_subscriptions')->countAllResults());
    }

    public function testNoPushWhenNoTransportIsConfigured(): void
    {
        PushService::setTransport(null);
        putenv('vapid.publicKey=');
        $this->subscribeUsers([2]);

        $id = $this->notify(1, [2]);

        $this->assertGreaterThan(0, $id, 'La campanita sigue funcionando sin push');
        $this->assertSame([], $this->transport->sent);
    }

    // ── Preferencias ─────────────────────────────────────────────────────

    public function testUserWhoMutedPushStillGetsInAppButNoPush(): void
    {
        $this->subscribeUsers([2, 3]);
        (new NotificationPreferenceModel())->saveForUser(2, ['avisos' => ['in_app' => '1']]); // push apagado

        $id = $this->notify(1, [2, 3]);

        $this->assertSame(2, $this->db->table('notification_recipients')->where('notification_id', $id)->countAllResults());
        $this->assertSame([$this->endpoint('u3')], array_column($this->transport->sent, 'endpoint'));
    }

    public function testUserWhoMutedInAppGetsNeitherRowNorPush(): void
    {
        $this->subscribeUsers([2, 3]);
        (new NotificationPreferenceModel())->saveForUser(2, ['avisos' => ['push' => '1']]); // in_app apagado fuerza push apagado

        $id = $this->notify(1, [2, 3]);

        $recipients = array_column($this->db->table('notification_recipients')->where('notification_id', $id)->get()->getResultArray(), 'recipient_id');
        $this->assertSame([3], array_map('intval', $recipients));
        $this->assertSame([$this->endpoint('u3')], array_column($this->transport->sent, 'endpoint'));
    }

    public function testIfEveryoneMutedTheCategoryNoNotificationIsCreated(): void
    {
        $this->subscribeUsers([2]);
        (new NotificationPreferenceModel())->saveForUser(2, []);

        $this->assertSame(0, $this->notify(1, [2]));
        $this->assertSame(0, $this->db->table('notifications')->countAllResults());
        $this->assertSame([], $this->transport->sent);
    }

    public function testPreferencesAreByCategory(): void
    {
        $this->subscribeUsers([2]);
        // Apaga SOLO "bonos"; el resto sigue activo.
        $all = [];
        foreach (array_keys(NotificationPreferenceModel::categories()) as $c) {
            $all[$c] = ['in_app' => '1', 'push' => '1'];
        }
        unset($all['bonos']);
        (new NotificationPreferenceModel())->saveForUser(2, $all);

        $this->assertSame(0, $this->notify(1, [2], ['source_type' => NotificationModel::SOURCE_BONO, 'source_id' => 5]));
        $this->assertGreaterThan(0, $this->notify(1, [2], ['source_type' => NotificationModel::SOURCE_CLASS, 'source_id' => 5]));
    }

    public function testTestNotificationIgnoresPreferences(): void
    {
        (new NotificationPreferenceModel())->saveForUser(2, []); // todo apagado

        $id = (new NotificationModel())->createWithRecipients(['sender_id' => 2, 'type' => 'individual', 'title' => 'Prueba', 'body' => 'x'], [2], false);

        $this->assertGreaterThan(0, $id);
    }

    public function testMissingPreferencesTableMeansEverythingEnabled(): void
    {
        \Config\Database::forge($this->DBGroup)->dropTable('notification_preferences', true);
        $this->subscribeUsers([2]);

        $id = $this->notify(1, [2]);

        $this->assertGreaterThan(0, $id, 'Una preferencia nunca debe perder un aviso');
        $this->assertCount(1, $this->transport->sent);
    }

    public function testSavePreferencesForcesPushOffWhenInAppIsOffAndIgnoresUnknownCategories(): void
    {
        $model = new NotificationPreferenceModel();
        $model->saveForUser(7, ['mensajes' => ['push' => '1'], 'inventada' => ['in_app' => '1', 'push' => '1']]);

        $prefs = $model->forUser(7);
        $this->assertSame(['in_app' => false, 'push' => false], $prefs['mensajes']);
        $this->assertArrayNotHasKey('inventada', $prefs);
        $this->assertSame(5, $this->db->table('notification_preferences')->where('user_id', 7)->countAllResults());
    }

    public function testSavingPreferencesTwiceUpdatesInsteadOfDuplicating(): void
    {
        $model = new NotificationPreferenceModel();
        $model->saveForUser(7, ['avisos' => ['in_app' => '1', 'push' => '1']]);
        $model->saveForUser(7, ['avisos' => ['in_app' => '1']]);

        $this->assertSame(5, $this->db->table('notification_preferences')->where('user_id', 7)->countAllResults());
        $this->assertSame(['in_app' => true, 'push' => false], $model->forUser(7)['avisos']);
    }

    // ── Utilidades ───────────────────────────────────────────────────────

    /** @param array<string,mixed> $extra */
    private function notify(int $sender, array $recipients, array $extra = []): int
    {
        return (new NotificationModel())->createWithRecipients(
            ['sender_id' => $sender, 'type' => 'individual', 'title' => 'Hola', 'body' => 'Mensaje'] + $extra,
            $recipients
        );
    }

    /** @param list<int> $userIds */
    private function subscribeUsers(array $userIds): void
    {
        $svc = new PushService();
        foreach ($userIds as $id) {
            $svc->subscribe($id, $this->endpoint('u' . $id), $this->p256dh(), $this->auth());
        }
    }

    private function endpoint(string $suffix): string
    {
        return 'https://fcm.googleapis.com/fcm/send/' . $suffix;
    }

    private function p256dh(): string
    {
        return WebPush::generateVapidKeys()['publicKey'];
    }

    private function auth(): string
    {
        return WebPush::b64uEncode(random_bytes(16));
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

/** Transporte falso: registra lo "enviado" y responde con el código que cada test decida. */
final class FakePushTransport implements PushTransport
{
    /** @var list<array{endpoint: string, body: string}> */
    public array $sent = [];
    public int $defaultCode = 201;
    /** @var list<string> urgencia de cada llamada a sendMany */
    public array $urgencies = [];
    public bool $throw = false;
    /** @var array<string,int> */
    private array $codes = [];

    public function codeFor(string $endpoint, int $code): void
    {
        $this->codes[$endpoint] = $code;
    }

    public function sendMany(array $targets, string|array $payloads, int $ttl = 86400, string $urgency = 'normal', int $timeout = 6): array
    {
        if ($this->throw) {
            throw new \RuntimeException('curl explotó');
        }
        $this->urgencies[] = $urgency;
        $out = [];
        foreach ($targets as $i => $t) {
            $this->sent[] = ['endpoint' => $t['endpoint'], 'body' => is_array($payloads) ? $payloads[$i] : $payloads];
            $out[] = $this->codes[$t['endpoint']] ?? $this->defaultCode;
        }
        return $out;
    }
}
