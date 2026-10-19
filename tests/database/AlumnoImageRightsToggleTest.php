<?php

use App\Services\PlayerService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Interruptor «Derechos de imagen»: PlayerService::setImageRights guarda en BD,
 * crea el perfil si falta y rechaza a quien no es alumno.
 * Esquema mínimo a mano (ver nota en MensajesInactiveUserTest).
 */
final class AlumnoImageRightsToggleTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = false;

    private const TABLES = ['player_profiles', 'users'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->dropSchema();
        $forge = \Config\Database::forge($this->DBGroup);
        $forge->addField([
            'id'   => ['type' => 'INT', 'auto_increment' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'role' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('users', true);
        $forge->addField([
            'id'                  => ['type' => 'INT', 'auto_increment' => true],
            'player_id'           => ['type' => 'INT'],
            'image_rights_signed' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
        ]);
        $forge->addKey('id', true);
        $forge->createTable('player_profiles', true);

        $this->db->table('users')->insertBatch([
            ['id' => 10, 'name' => 'Ana',   'role' => 'player'],
            ['id' => 11, 'name' => 'Coach', 'role' => 'coach'],
        ]);
    }

    private function dropSchema(): void
    {
        $forge = \Config\Database::forge($this->DBGroup);
        foreach (self::TABLES as $t) {
            $forge->dropTable($t, true);
        }
    }

    protected function tearDown(): void
    {
        $this->dropSchema();
        parent::tearDown();
    }

    private function value(int $playerId): ?int
    {
        $row = $this->db->table('player_profiles')->where('player_id', $playerId)->get()->getRowArray();
        return $row ? (int) $row['image_rights_signed'] : null;
    }

    public function testCreaElPerfilSiNoExisteYLoMarca(): void
    {
        $this->assertNull($this->value(10));
        (new PlayerService())->setImageRights(10, true);
        $this->assertSame(1, $this->value(10));
    }

    public function testDesmarcaYVuelveAMarcar(): void
    {
        $svc = new PlayerService();
        $svc->setImageRights(10, true);
        $svc->setImageRights(10, false);
        $this->assertSame(0, $this->value(10));
        $svc->setImageRights(10, true);
        $this->assertSame(1, $this->value(10));
        $this->assertSame(1, $this->db->table('player_profiles')->where('player_id', 10)->countAllResults(), 'no duplica filas');
    }

    public function testRechazaAQuienNoEsAlumnoOInexistente(): void
    {
        $svc = new PlayerService();
        foreach ([11, 999] as $id) {
            try {
                $svc->setImageRights($id, true);
                $this->fail("debía lanzar DomainException para {$id}");
            } catch (\DomainException $e) {
                $this->assertNull($this->value($id));
            }
        }
    }
}
