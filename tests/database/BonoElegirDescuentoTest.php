<?php

use App\Services\ClasesService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Descuento de bono con VARIOS bonos activos: no hay elección automática.
 *
 * - Con más de un bono usable, descontar sin elegir devuelve `needs_choice`
 *   y no toca ningún saldo.
 * - Con `bono_id` solo baja ese bono (y se guarda en `bono_deducted_from_id`).
 * - No se puede usar el bono de otro alumno ni uno caducado.
 * - "Cambiar bono" devuelve la sesión al de origen y la descuenta del nuevo.
 *
 * Esquema mínimo creado a mano (ver nota en MensajesInactiveUserTest).
 */
final class BonoElegirDescuentoTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = false;

    private int $sessionId = 1;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dropSchema();
        $this->createSchema();
        foreach (['class_session_players', 'player_bonos', 'bono_types', 'class_sessions'] as $t) {
            $this->db->table($t)->truncate();
        }
        $this->db->table('class_sessions')->insert(['id' => $this->sessionId, 'status' => 'scheduled']);
        $this->db->table('bono_types')->insertBatch([
            ['id' => 1, 'name' => 'Bono 3 sesiones'],
            ['id' => 2, 'name' => 'Bono 5 mensual'],
        ]);
    }

    /** Tablas propias de este test: se crean y se borran para no chocar con otros tests (BD en memoria compartida). */
    private function dropSchema(): void
    {
        $forge = \Config\Database::forge($this->DBGroup);
        foreach (['class_session_players', 'player_bonos', 'bono_types', 'class_sessions'] as $t) {
            $forge->dropTable($t, true);
        }
    }

    protected function tearDown(): void
    {
        $this->dropSchema();
        parent::tearDown();
    }

    private function createSchema(): void
    {
        $forge = \Config\Database::forge($this->DBGroup);

        if (! $this->db->tableExists('class_sessions')) {
            $forge->addField([
                'id'     => ['type' => 'INT', 'auto_increment' => true],
                'status' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('class_sessions', true);
        }
        if (! $this->db->tableExists('bono_types')) {
            $forge->addField([
                'id'   => ['type' => 'INT', 'auto_increment' => true],
                'name' => ['type' => 'VARCHAR', 'constraint' => 100],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('bono_types', true);
        }
        if (! $this->db->tableExists('player_bonos')) {
            $forge->addField([
                'id'                 => ['type' => 'INT', 'auto_increment' => true],
                'player_id'          => ['type' => 'INT', 'null' => true],
                'bono_type_id'       => ['type' => 'INT'],
                'sessions_total'     => ['type' => 'INT'],
                'sessions_remaining' => ['type' => 'INT'],
                'start_date'         => ['type' => 'DATE', 'null' => true],
                'expires_at'         => ['type' => 'DATE', 'null' => true],
                'notes'              => ['type' => 'TEXT', 'null' => true],
                'created_by'         => ['type' => 'INT', 'null' => true],
                'created_at'         => ['type' => 'DATETIME', 'null' => true],
                'updated_at'         => ['type' => 'DATETIME', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('player_bonos', true);
        }
        if (! $this->db->tableExists('class_session_players')) {
            $forge->addField([
                'id'                    => ['type' => 'INT', 'auto_increment' => true],
                'session_id'            => ['type' => 'INT'],
                'user_id'               => ['type' => 'INT'],
                'coach_id'              => ['type' => 'INT', 'null' => true],
                'attendance'            => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
                'responded_at'          => ['type' => 'DATETIME', 'null' => true],
                'pre_obs'               => ['type' => 'TEXT', 'null' => true],
                'post_obs'              => ['type' => 'TEXT', 'null' => true],
                'absence_reason'        => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'absence_notes'         => ['type' => 'TEXT', 'null' => true],
                'student_note'          => ['type' => 'TEXT', 'null' => true],
                'student_noted_at'      => ['type' => 'DATETIME', 'null' => true],
                'bono_deducted_at'      => ['type' => 'DATETIME', 'null' => true],
                'bono_deducted_from_id' => ['type' => 'INT', 'null' => true],
            ]);
            $forge->addKey('id', true);
            $forge->createTable('class_session_players', true);
        }
    }

    private function bono(int $id, ?int $player, int $type, int $remaining, ?string $expires = null): void
    {
        $this->db->table('player_bonos')->insert([
            'id' => $id, 'player_id' => $player, 'bono_type_id' => $type,
            'sessions_total' => 5, 'sessions_remaining' => $remaining,
            'start_date' => date('Y-m-d'), 'expires_at' => $expires,
            'created_at' => date('Y-m-d H:i:s', strtotime("+{$id} seconds")),
        ]);
    }

    private function enroll(int $userId): void
    {
        $this->db->table('class_session_players')->insert([
            'session_id' => $this->sessionId, 'user_id' => $userId, 'attendance' => 'present',
        ]);
    }

    private function remaining(int $bonoId): int
    {
        return (int) $this->db->table('player_bonos')->where('id', $bonoId)->get()->getRow()->sessions_remaining;
    }

    private function fromId(int $userId): ?int
    {
        $v = $this->db->table('class_session_players')->where('user_id', $userId)->get()->getRow()->bono_deducted_from_id;
        return $v === null ? null : (int) $v;
    }

    public function testConVariosBonosExigeElegirYNoTocaNingunSaldo(): void
    {
        $this->bono(1, 10, 1, 2);
        $this->bono(2, 10, 2, 1);
        $this->enroll(10);

        $r = (new ClasesService())->deductBonoForPlayer($this->sessionId, 10);

        $this->assertFalse($r['success']);
        $this->assertTrue($r['needs_choice'] ?? false);
        $this->assertSame(2, $this->remaining(1));
        $this->assertSame(1, $this->remaining(2));
        $this->assertNull($this->fromId(10));
    }

    public function testConBonoElegidoSoloBajaEseBono(): void
    {
        $this->bono(1, 10, 1, 2);
        $this->bono(2, 10, 2, 4);
        $this->enroll(10);

        $r = (new ClasesService())->deductBonoForPlayer($this->sessionId, 10, null, 2);

        $this->assertTrue($r['success']);
        $this->assertSame(2, $r['bono_id']);
        $this->assertSame(3, $r['sessions_remaining']);
        $this->assertSame(2, $this->remaining(1), 'el bono antiguo no se toca');
        $this->assertSame(3, $this->remaining(2));
        $this->assertSame(2, $this->fromId(10));
    }

    public function testConUnSoloBonoNoHaceFaltaElegir(): void
    {
        $this->bono(1, 10, 1, 2);
        $this->enroll(10);

        $r = (new ClasesService())->deductBonoForPlayer($this->sessionId, 10);

        $this->assertTrue($r['success']);
        $this->assertSame(1, $this->remaining(1));
        $this->assertSame(1, $this->fromId(10));
    }

    public function testNoSePuedeUsarElBonoDeOtroAlumno(): void
    {
        $this->bono(1, 10, 1, 2);
        $this->bono(2, 99, 2, 4);
        $this->enroll(10);

        $r = (new ClasesService())->deductBonoForPlayer($this->sessionId, 10, null, 2);

        $this->assertFalse($r['success']);
        $this->assertSame(4, $this->remaining(2));
        $this->assertSame(2, $this->remaining(1));
    }

    public function testNoSePuedeUsarUnBonoCaducado(): void
    {
        $this->bono(1, 10, 1, 2, date('Y-m-d', strtotime('-1 day')));
        $this->bono(2, 10, 2, 4);
        $this->enroll(10);

        $r = (new ClasesService())->deductBonoForPlayer($this->sessionId, 10, null, 1);

        $this->assertFalse($r['success']);
        $this->assertSame(2, $this->remaining(1));
    }

    public function testCambiarBonoDevuelveAlOrigenYDescuentaDelNuevo(): void
    {
        $this->bono(1, 10, 1, 2);
        $this->bono(2, 10, 2, 4);
        $this->enroll(10);
        $svc = new ClasesService();
        $svc->deductBonoForPlayer($this->sessionId, 10, null, 1);
        $this->assertSame(1, $this->remaining(1));

        $r = $svc->changeBonoForPlayer($this->sessionId, 10, 2);

        $this->assertTrue($r['success']);
        $this->assertSame(2, $this->remaining(1), 'vuelve al bono de origen');
        $this->assertSame(3, $this->remaining(2));
        $this->assertSame(2, $this->fromId(10));
        $this->assertSame(2, $r['to']['id']);
    }

    public function testCambiarAlMismoBonoOAUnoNoDisponibleNoCambiaNada(): void
    {
        $this->bono(1, 10, 1, 2);
        $this->bono(2, 99, 2, 4);
        $this->enroll(10);
        $svc = new ClasesService();
        $svc->deductBonoForPlayer($this->sessionId, 10, null, 1);

        $this->assertFalse($svc->changeBonoForPlayer($this->sessionId, 10, 1)['success']);
        $this->assertFalse($svc->changeBonoForPlayer($this->sessionId, 10, 2)['success']);
        $this->assertSame(1, $this->remaining(1));
        $this->assertSame(4, $this->remaining(2));
        $this->assertSame(1, $this->fromId(10));
    }
}
