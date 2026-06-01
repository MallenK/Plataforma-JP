<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use App\Services\ClasesService;

class TestPlayerInsert extends BaseCommand
{
    protected $group       = 'Tests';
    protected $name        = 'test:player-insert';
    protected $description = 'Test syncPlayers (crear sesión con jugadores) via ClasesService.';

    public function run(array $params)
    {
        $db      = \Config\Database::connect();
        $service = new ClasesService();

        CLI::write('=== TEST syncPlayers vía createSession ===', 'yellow');

        // Obtener 2 alumnos activos
        $players = $db->query("SELECT id, name FROM users WHERE role IN ('alumno','player') AND status = 'active' LIMIT 2")->getResultArray();
        if (count($players) < 1) { CLI::error('No hay alumnos activos.'); return; }

        $playerIds = array_column($players, 'id');
        CLI::write('Jugadores de test: ' . implode(', ', array_map(fn($p) => "{$p['name']} (id={$p['id']})", $players)), 'cyan');

        // Obtener un coach activo
        $coach = $db->query("SELECT id, name FROM users WHERE role IN ('coach','admin','superadmin') AND status = 'active' LIMIT 1")->getRowArray();
        $coachId = $coach ? $coach['id'] : null;

        // Crear sesión de test
        $result = $service->createSession([
            'type'         => 'single',
            'title'        => 'TEST_SESION_' . time(),
            'session_date' => date('Y-m-d'),
            'start_time'   => '23:00',
            'end_time'     => '23:59',
            'player_ids'   => $playerIds,
            'coach_ids'    => $coachId ? [$coachId] : [],
        ], 2);

        if (!$result['success']) {
            CLI::error('createSession FAILED: ' . ($result['error'] ?? 'unknown'));
            return;
        }

        $sessionId = (int)$result['id'];
        CLI::write("createSession OK → session_id={$sessionId}", 'green');

        // Verificar jugadores
        $storedPlayers = $db->query("SELECT id, user_id, attendance FROM class_session_players WHERE session_id={$sessionId}")->getResultArray();
        CLI::write("Jugadores guardados: " . count($storedPlayers), count($storedPlayers) > 0 ? 'green' : 'red');
        foreach ($storedPlayers as $sp) {
            CLI::write("  id={$sp['id']} user_id={$sp['user_id']} attendance={$sp['attendance']}", 'green');
        }

        if (count($storedPlayers) === 0) {
            CLI::error("BUG: syncPlayers NO guardó jugadores!");
        } else {
            CLI::write("syncPlayers OK ✓", 'green');
        }

        // Cleanup: eliminar la sesión de test
        $service->deleteSession($sessionId);
        CLI::write("Sesión de test eliminada.", 'yellow');
    }
}
