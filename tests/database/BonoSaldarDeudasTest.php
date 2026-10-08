<?php

use App\Services\BonoControlService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * Clases dadas sin bono ("deudas"): se saldan SIEMPRE a mano y con el bono que
 * elige el admin. Nada se descuenta solo al emitir o asignar un bono.
 *
 * Esquema mínimo creado a mano (ver nota en MensajesInactiveUserTest).
 */
final class BonoSaldarDeudasTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dropSchema();
        $this->createSchema();
        foreach (['class_session_players', 'class_sessions', 'player_bonos', 'bono_types', 'users', 'academy_settings'] as $t) {
            $this->db->table($t)->truncate();
        }
        $this->db->table('users')->insert(['id' => 10, 'name' => 'Ana']);
        $this->db->table('bono_types')->insertBatch([
            ['id' => 1, 'name' => 'Bono A'],
            ['id' => 2, 'name' => 'Bono B'],
        ]);
        $this->db->table('academy_settings')->insert([
            'setting_key' => 'bono_control_since', 'setting_value' => '2020-01-01', 'setting_type' => 'string',
        ]);
        // Dos clases ya dadas (cerradas, presente) sin descontar.
        foreach ([1 => '2026-09-01', 2 => '2026-09-08'] as $id => $date) {
            $this->db->table('class_sessions')->insert(['id' => $id, 'status' => 'completed', 'title' => "Clase {$id}", 'session_date' => $date, 'start_time' => '17:00']);
            $this->db->table('class_session_players')->insert(['session_id' => $id, 'user_id' => 10, 'attendance' => 'present']);
        }
    }

    /** Tablas propias de este test: se crean y se borran para no chocar con otros tests (BD en memoria compartida). */
    private function dropSchema(): void
    {
        $forge = \Config\Database::forge($this->DBGroup);
        foreach (['class_session_players', 'class_sessions', 'player_bonos', 'bono_types', 'users', 'academy_settings'] as $t) {
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
        $make = function (string $table, array $fields) use ($forge) {
            if ($this->db->tableExists($table)) {
                return;
            }
            $forge->addField($fields);
            $forge->addKey('id', true);
            $forge->createTable($table, true);
        };
        $make('users', [
            'id'   => ['type' => 'INT', 'auto_increment' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
        ]);
        $make('bono_types', [
            'id'   => ['type' => 'INT', 'auto_increment' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 100],
        ]);
        $make('player_bonos', [
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
        $make('class_sessions', [
            'id'           => ['type' => 'INT', 'auto_increment' => true],
            'status'       => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'title'        => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'session_date' => ['type' => 'DATE', 'null' => true],
            'start_time'   => ['type' => 'VARCHAR', 'constraint' => 8, 'null' => true],
        ]);
        $make('class_session_players', [
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
            'bono_coverage'         => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'bono_resolution'       => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
        ]);
        $make('academy_settings', [
            'id'            => ['type' => 'INT', 'auto_increment' => true],
            'setting_key'   => ['type' => 'VARCHAR', 'constraint' => 100],
            'setting_value' => ['type' => 'TEXT', 'null' => true],
            'setting_type'  => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'updated_by'    => ['type' => 'INT', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
    }

    private function bono(int $id, int $type, int $remaining): void
    {
        $this->db->table('player_bonos')->insert([
            'id' => $id, 'player_id' => 10, 'bono_type_id' => $type,
            'sessions_total' => 5, 'sessions_remaining' => $remaining,
            'start_date' => date('Y-m-d'), 'created_at' => date('Y-m-d H:i:s', strtotime("+{$id} seconds")),
        ]);
    }

    private function remaining(int $id): int
    {
        return (int) $this->db->table('player_bonos')->where('id', $id)->get()->getRow()->sessions_remaining;
    }

    public function testSaldaConElBonoElegidoYNoToca_elOtro(): void
    {
        $this->bono(1, 1, 5);
        $this->bono(2, 2, 5);

        $r = (new BonoControlService())->settleWithBono(10, null, 2);

        $this->assertSame(2, $r['settled']);
        $this->assertSame(0, $r['remaining_debts']);
        $this->assertSame(5, $this->remaining(1), 'el otro bono no se toca');
        $this->assertSame(3, $this->remaining(2));
        $rows = $this->db->table('class_session_players')->get()->getResultArray();
        foreach ($rows as $row) {
            $this->assertSame(2, (int) $row['bono_deducted_from_id']);
        }
    }

    public function testLimiteDeClasesASaldar(): void
    {
        $this->bono(1, 1, 5);

        $r = (new BonoControlService())->settleWithBono(10, null, 1, 1);

        $this->assertSame(1, $r['settled']);
        $this->assertSame(1, $r['remaining_debts']);
        $this->assertSame(4, $this->remaining(1));
    }

    public function testSinSaldoSuficienteSaldaLasQueCaben(): void
    {
        $this->bono(1, 1, 1);

        $r = (new BonoControlService())->settleWithBono(10, null, 1);

        $this->assertSame(1, $r['settled']);
        $this->assertSame(1, $r['remaining_debts']);
        $this->assertSame(0, $this->remaining(1));
    }

    public function testSaldarUnaClaseConElBonoElegido(): void
    {
        $this->bono(1, 1, 5);
        $this->bono(2, 2, 5);
        $svc = new BonoControlService();
        $csp = $svc->openDebts(10)[0]['csp_id'];

        $res = $svc->settleOne((int) $csp, 1);

        $this->assertTrue($res['success']);
        $this->assertSame(4, $this->remaining(1));
        $this->assertSame(5, $this->remaining(2));
        $this->assertCount(1, $svc->openDebts(10));
        $this->assertFalse($svc->settleOne((int) $csp, 1)['success'], 'una deuda ya saldada no se vuelve a saldar');
    }

    public function testEmitirOAsignarUnBonoYaNoSaldaDeudasSolo(): void
    {
        $src = file_get_contents(APPPATH . 'Controllers/BonosController.php');

        // Único sitio donde se salda: la acción manual de la ficha del bono.
        $this->assertSame(1, substr_count($src, '->settleWithBono('), 'solo saldarDeudas() puede saldar');
        $this->assertStringContainsString('function saldarDeudas', $src);
        $this->assertStringNotContainsString('settleDebtsMessage', $src);
    }
}
