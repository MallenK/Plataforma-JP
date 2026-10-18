<?php

use App\Services\BonoCoverageService;
use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\Test\DatabaseTestTrait;

/**
 * "Pendiente de bono" se calcula EN VIVO con el saldo de hoy; no depende de la marca
 * guardada en `bono_coverage` (que quedaba vieja si el bono se emitía después de
 * apuntar al alumno a las clases).
 *
 * Esquema mínimo creado a mano (ver nota en MensajesInactiveUserTest).
 */
final class BonoCoberturaEnVivoTest extends CIUnitTestCase
{
    use DatabaseTestTrait;

    protected $migrate = false;

    private const TABLES = ['class_session_players', 'class_sessions', 'player_bonos', 'users', 'academy_settings'];

    protected function setUp(): void
    {
        parent::setUp();
        $this->dropSchema();
        $this->createSchema();
        $this->db->table('users')->insert(['id' => 10, 'name' => 'Ana']);
        $this->db->table('academy_settings')->insert([
            'setting_key' => 'bono_control_since', 'setting_value' => '2020-01-01', 'setting_type' => 'string',
        ]);
    }

    protected function tearDown(): void
    {
        $this->dropSchema();
        parent::tearDown();
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
        $make = function (string $table, array $fields) use ($forge) {
            $forge->addField($fields);
            $forge->addKey('id', true);
            $forge->createTable($table, true);
        };
        $make('users', [
            'id'   => ['type' => 'INT', 'auto_increment' => true],
            'name' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
        ]);
        $make('player_bonos', [
            'id'                 => ['type' => 'INT', 'auto_increment' => true],
            'player_id'          => ['type' => 'INT', 'null' => true],
            'sessions_remaining' => ['type' => 'INT'],
            'expires_at'         => ['type' => 'DATE', 'null' => true],
            'created_at'         => ['type' => 'DATETIME', 'null' => true],
        ]);
        $make('class_sessions', [
            'id'           => ['type' => 'INT', 'auto_increment' => true],
            'status'       => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'session_date' => ['type' => 'DATE', 'null' => true],
        ]);
        $make('class_session_players', [
            'id'               => ['type' => 'INT', 'auto_increment' => true],
            'session_id'       => ['type' => 'INT'],
            'user_id'          => ['type' => 'INT'],
            'attendance'       => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
            'bono_deducted_at' => ['type' => 'DATETIME', 'null' => true],
            'bono_coverage'    => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
            'bono_resolution'  => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true],
        ]);
        $make('academy_settings', [
            'id'            => ['type' => 'INT', 'auto_increment' => true],
            'setting_key'   => ['type' => 'VARCHAR', 'constraint' => 100],
            'setting_value' => ['type' => 'TEXT', 'null' => true],
            'setting_type'  => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],
        ]);
    }

    private function session(int $id, string $status, string $date, ?string $stored = null, ?string $deductedAt = null, string $attendance = 'pending'): void
    {
        $this->db->table('class_sessions')->insert(['id' => $id, 'status' => $status, 'session_date' => $date]);
        $this->db->table('class_session_players')->insert([
            'session_id' => $id, 'user_id' => 10, 'attendance' => $attendance,
            'bono_coverage' => $stored, 'bono_deducted_at' => $deductedAt,
        ]);
    }

    private function bono(int $remaining, ?string $expires = null): void
    {
        $this->db->table('player_bonos')->insert([
            'player_id' => 10, 'sessions_remaining' => $remaining, 'expires_at' => $expires, 'created_at' => '2026-10-11 12:00:00',
        ]);
    }

    public function testMarcaViejaUncoveredSeCorrigeConElSaldoDeHoy(): void
    {
        // Caso real: clases creadas ANTES del bono (marca guardada = uncovered), bono de 2 después.
        $this->session(1, 'completed', '2026-10-11', 'uncovered', '2026-10-11 20:57:10', 'present');
        $this->session(2, 'scheduled', '2026-10-18', 'uncovered');
        $this->session(3, 'scheduled', '2026-10-25', 'uncovered');
        $this->bono(2, '2026-11-10');

        $live = (new BonoCoverageService())->liveMarks([10], '2026-10-18');

        $this->assertSame([2 => 'covered', 3 => 'covered'], $live[10]);
    }

    public function testMasClasesQueSaldoDejaSinCubrirLasUltimas(): void
    {
        $this->session(2, 'scheduled', '2026-10-18');
        $this->session(3, 'scheduled', '2026-10-25');
        $this->session(4, 'scheduled', '2026-11-01');
        $this->bono(2, '2026-12-31');

        $live = (new BonoCoverageService())->liveMarks([10], '2026-10-18');

        $this->assertSame([2 => 'covered', 3 => 'covered', 4 => 'uncovered'], $live[10]);
    }

    public function testSinBonoTodoQuedaSinCubrir(): void
    {
        $this->session(2, 'scheduled', '2026-10-18', 'covered');

        $live = (new BonoCoverageService())->liveMarks([10], '2026-10-18');

        $this->assertSame([2 => 'uncovered'], $live[10], 'la marca guardada "covered" no manda si ya no hay saldo');
    }

    public function testElBonoQueCaducaAntesDeLaClaseEstaEnRiesgo(): void
    {
        $this->session(2, 'scheduled', '2026-10-18');
        $this->session(3, 'scheduled', '2026-11-20');
        $this->bono(2, '2026-11-10');

        $live = (new BonoCoverageService())->liveMarks([10], '2026-10-18');

        $this->assertSame('covered', $live[10][2]);
        $this->assertSame('at_risk', $live[10][3]);
    }

    public function testUnaDeudaAbiertaConsumeSaldoAntesQueLasFuturas(): void
    {
        // Clase ya dada sin descontar (deuda) + 2 futuras + bono de 2 → solo cabe una futura.
        $this->session(1, 'completed', '2026-10-11', null, null, 'present');
        $this->session(2, 'scheduled', '2026-10-18');
        $this->session(3, 'scheduled', '2026-10-25');
        $this->bono(2, '2026-12-31');

        $live = (new BonoCoverageService())->liveMarks([10], '2026-10-18');

        $this->assertSame([2 => 'covered', 3 => 'uncovered'], $live[10]);
    }

    public function testSinClasesFuturasDevuelveVacio(): void
    {
        $this->bono(2);

        $this->assertSame([], (new BonoCoverageService())->liveMarks([10], '2026-10-18'));
        $this->assertSame([], (new BonoCoverageService())->liveMarks([], '2026-10-18'));
    }
}
