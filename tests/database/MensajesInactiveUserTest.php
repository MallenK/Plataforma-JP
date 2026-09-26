<?php

use App\Controllers\MensajesController;
use App\Models\UserModel;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\URI;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;
use Config\App as AppConfig;

/**
 * Investigación del ticket: "el admin no puede contestar a algunos padres/
 * jugadores en Mensajes".
 *
 * Reproduce el flujo real contra BD (MySQL real, grupo `tests`) de
 * MensajesController::ajaxOpenConversation y ::ajaxSend, con un admin
 * autenticado intentando hablar con un jugador cuyo `users.status` es
 * 'inactive' (p.ej. dado de baja desde Alumnos → AlumnosController::destroy(),
 * que hace baja lógica a status='inactive').
 *
 * Causa raíz encontrada: ambos endpoints bloquean con 403 en cuanto el otro
 * usuario de la conversación no tiene status='active', SIN excepción para
 * conversaciones ya existentes con historial. El admin ve la conversación en
 * la bandeja (ConversationModel::getForUser no filtra por status), pero en
 * cuanto pulsa para abrirla (POST /mensajes/open, que también se llama al
 * REABRIR una conversación ya existente) o intenta enviar (POST /mensajes/
 * send), recibe 403 "Este usuario no está activo." y no puede leer ni
 * responder el historial.
 *
 * Nota sobre el esquema de prueba: no usamos el runner de migraciones de
 * CodeIgniter (DatabaseTestTrait::$migrate) porque
 * 2026-04-16-000002_CreatePlayerBonos.php falla al migrar desde cero en
 * MySQL real (columna `player_bonos.player_id` UNSIGNED referenciando
 * `users.id` SIGNED — el mismo patrón de bug de signedness ya documentado en
 * CLAUDE.md para otras FKs a users.id), y ese bug es ajeno a este ticket. En
 * su lugar creamos a mano solo las 3 tablas que este flujo necesita
 * (users/conversations/messages), con las mismas columnas que sus
 * migraciones reales. No se modifica ningún fichero de la app.
 */
final class MensajesInactiveUserTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = false; // ver nota de clase: esquema creado a mano

    protected function setUp(): void
    {
        parent::setUp();
        $this->createSchema();
        $this->db->table('messages')->truncate();
        $this->db->table('conversations')->truncate();
        $this->db->table('users')->truncate();
    }

    private function createSchema(): void
    {
        $forge = \Config\Database::forge($this->DBGroup);

        if (! $this->db->tableExists('users')) {
            $forge->addField([
                'id'         => ['type' => 'INT', 'auto_increment' => true],
                'name'       => ['type' => 'VARCHAR', 'constraint' => 150],
                'email'      => ['type' => 'VARCHAR', 'constraint' => 150],
                'password'   => ['type' => 'VARCHAR', 'constraint' => 255],
                'role'       => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
                'status'     => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true, 'default' => 'active'],
                'avatar'     => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
                'updated_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->addUniqueKey('email');
            $forge->createTable('users', true);
        }

        if (! $this->db->tableExists('conversations')) {
            $forge->addField([
                'id'              => ['type' => 'INT', 'auto_increment' => true],
                'user1_id'        => ['type' => 'INT'],
                'user2_id'        => ['type' => 'INT'],
                'created_at'      => ['type' => 'DATETIME', 'null' => true],
                'last_message_at' => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->addUniqueKey(['user1_id', 'user2_id']);
            $forge->createTable('conversations', true);
        }

        if (! $this->db->tableExists('messages')) {
            $forge->addField([
                'id'              => ['type' => 'INT', 'auto_increment' => true],
                'conversation_id' => ['type' => 'INT'],
                'sender_id'       => ['type' => 'INT'],
                'body'            => ['type' => 'TEXT', 'null' => true],
                'file_path'       => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
                'file_name'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'file_size'       => ['type' => 'INT', 'null' => true],
                'file_mime'       => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'read_at'         => ['type' => 'DATETIME', 'null' => true],
                'created_at'      => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('messages', true);
        }
    }

    private function makeUser(string $role, string $status, string $email): int
    {
        $model = new UserModel();
        return (int) $model->insert([
            'name'     => 'Test ' . $email,
            'email'    => $email,
            'password' => password_hash('Test1234!', PASSWORD_BCRYPT),
            'role'     => $role,
            'status'   => $status,
        ], true);
    }

    /**
     * Controller con currentUserId()/currentRole() forzados, igual que el
     * patrón ya usado en ClasesControllerAuthTest para probar controllers
     * sin depender de la sesión HTTP real.
     */
    private function makeController(string $role, int $userId, array $post = []): MensajesController
    {
        $ctrl = new class ($role, $userId) extends MensajesController {
            public function __construct(private string $fakeRole, private int $fakeUserId)
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
            protected function currentUser(): array
            {
                return ['id' => $this->fakeUserId, 'name' => 'Admin Test', 'role' => $this->fakeRole, 'avatar' => null];
            }
        };

        $request = new IncomingRequest(new AppConfig(), new URI('http://localhost/mensajes/open'), null, new UserAgent());
        $request->setMethod('POST');
        $request->setGlobal('post', $post); // el snapshot lazy de Superglobals no ve el $_POST puesto por el test
        $ctrl->initController($request, service('response'), service('logger'));

        return $ctrl;
    }

    private function jsonOf(\CodeIgniter\HTTP\ResponseInterface $response): array
    {
        return json_decode($response->getBody() ?? '{}', true) ?? [];
    }

    public function testAdminCanOpenConversationWithActivePlayer(): void
    {
        $adminId  = $this->makeUser('admin', 'active', 'admin.active@test.local');
        $playerId = $this->makeUser('player', 'active', 'player.active@test.local');

        $ctrl = $this->makeController('admin', $adminId, ['other_user_id' => (string) $playerId]);

        $response = $ctrl->ajaxOpenConversation();

        $this->assertSame(200, $response->getStatusCode());
        $body = $this->jsonOf($response);
        $this->assertArrayHasKey('conversation_id', $body);
    }

    /**
     * Una conversación NUEVA con un usuario de baja sigue rechazada.
     */
    public function testAdminCannotStartNewConversationWithInactivePlayer(): void
    {
        $adminId  = $this->makeUser('admin', 'active', 'admin.active2@test.local');
        $playerId = $this->makeUser('player', 'inactive', 'player.inactive@test.local');

        $ctrl = $this->makeController('admin', $adminId, ['other_user_id' => (string) $playerId]);

        $response = $ctrl->ajaxOpenConversation();

        $this->assertSame(403, $response->getStatusCode());
        $body = $this->jsonOf($response);
        $this->assertStringContainsString('no está activo', $body['error'] ?? '');
    }

    /**
     * Si la conversación YA EXISTÍA (con mensajes previos) antes de que el
     * jugador pasara a 'inactive', reabrirla también queda bloqueada: no es
     * solo que no se puedan crear conversaciones nuevas, es que las
     * existentes se vuelven inaccesibles para responder.
     */
    public function testExistingConversationStaysReachableOnceOtherUserGoesInactive(): void
    {
        $adminId  = $this->makeUser('admin', 'active', 'admin.active3@test.local');
        $playerId = $this->makeUser('player', 'active', 'player.tobeinactive@test.local');

        $convModel = new \App\Models\ConversationModel();
        $conv      = $convModel->findOrCreate($adminId, $playerId);
        $msgModel  = new \App\Models\MessageModel();
        $msgModel->insert([
            'conversation_id' => $conv['id'],
            'sender_id'       => $playerId,
            'body'            => 'Mensaje previo del jugador/padre',
            'created_at'      => date('Y-m-d H:i:s'),
        ]);

        // El jugador causa baja (AlumnosController::destroy → status='inactive').
        (new UserModel())->update($playerId, ['status' => 'inactive']);

        $ctrl = $this->makeController('admin', $adminId, ['other_user_id' => (string) $playerId]);

        $response = $ctrl->ajaxOpenConversation();

        // Se mira el cuerpo, no el código: service('response') es compartido y
        // arrastra el 403 de tests anteriores (setJSON no lo resetea).
        $this->assertArrayHasKey('conversation_id', $this->jsonOf($response));
        $this->assertArrayNotHasKey('error', $this->jsonOf($response));
    }

    /**
     * Enviar un mensaje sobre una conversación ya abierta (conversation_id
     * conocido) también se bloquea, no solo el "open" inicial.
     */
    public function testAdminCanSendInExistingConversationWithInactivePlayer(): void
    {
        $adminId  = $this->makeUser('admin', 'active', 'admin.active4@test.local');
        $playerId = $this->makeUser('player', 'inactive', 'player.inactive2@test.local');

        $convModel = new \App\Models\ConversationModel();
        $conv      = $convModel->findOrCreate($adminId, $playerId);

        $ctrl = $this->makeController('admin', $adminId, [
            'conversation_id' => (string) $conv['id'],
            'body'            => 'Respuesta del admin',
        ]);

        $response = $ctrl->ajaxSend();

        $this->assertTrue($this->jsonOf($response)['ok'] ?? false);
    }

    /**
     * Confirma que la regla jugador-jugador (MensajesController::canChat)
     * en sí NO es la causa: admin siempre puede chatear con cualquier rol
     * según esa función. El bloqueo viene solo del filtro de status.
     */
    public function testCanChatRuleAloneNeverBlocksAdmin(): void
    {
        $ctrl     = new \ReflectionClass(MensajesController::class);
        $method   = $ctrl->getMethod('canChat');
        $method->setAccessible(true);
        $instance = $ctrl->newInstanceWithoutConstructor();

        $this->assertTrue($method->invoke($instance, 'admin', 'player'));
        $this->assertTrue($method->invoke($instance, 'admin', 'alumno'));
        $this->assertTrue($method->invoke($instance, 'superadmin', 'player'));
    }
}
