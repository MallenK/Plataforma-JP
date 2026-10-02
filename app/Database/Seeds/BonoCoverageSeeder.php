<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use App\Models\UserModel;

/**
 * SOLO PARA PPR / DESARROLLO. Reproduce TICKET-013 con alumnos 100 % ficticios
 * (dominio @test.jppreparation.local). NO toca ningún alumno real: el caso
 * real "Aaron Alonso" no se modifica ni se replica con sus datos.
 *
 * Escenarios (todos con prefijo "TEST Cobertura"):
 *  1. Bono 2 sesiones vigente        → una serie de 1 mes (5 clases) NO cabe: solo 2 cubiertas.
 *  2. Nunca ha tenido bono           → la serie se crea entera, marcada sin cubrir.
 *  3. Bono de 4 que caduca en 5 días → alerta de caducidad + "caduca antes" en la serie.
 *  4. Dos clases ya dadas sin bono   → DEUDA; al emitirle un bono se salda sola.
 *
 * Ejecutar:  docker compose exec app php spark db:seed BonoCoverageSeeder
 * Idempotente: borra y regenera solo SUS clases/bonos/movimientos (prefijo TEST Cobertura).
 *
 * Después, para ver el flujo: entra como admin → Nueva clase → serie recurrente
 * (jueves, 1 mes) con "TEST Cobertura 1 (bono 2)" y mira el panel de cobertura.
 */
class BonoCoverageSeeder extends Seeder
{
    private const DOMAIN   = '@test.jppreparation.local';
    private const PASSWORD = 'Test1234!';
    private const PREFIX   = 'TEST Cobertura';

    public function run()
    {
        $db    = \Config\Database::connect();
        $admin = $db->table('users')->whereIn('role', ['admin', 'superadmin'])->where('status', 'active')->get(1)->getRowArray();
        if (!$admin) {
            throw new \RuntimeException('Hace falta al menos un admin activo.');
        }
        $adminId = (int) $admin['id'];
        $type    = $db->table('bono_types')->where('active', 1)->get(1)->getRowArray()
            ?: $db->table('bono_types')->get(1)->getRowArray();
        if (!$type) {
            throw new \RuntimeException('Hace falta al menos un tipo de bono.');
        }

        $p1 = $this->ensureUser('cobertura.1', self::PREFIX . ' 1 (bono 2)');
        $p2 = $this->ensureUser('cobertura.2', self::PREFIX . ' 2 (nunca tuvo bono)');
        $p3 = $this->ensureUser('cobertura.3', self::PREFIX . ' 3 (bono caduca pronto)');
        $p4 = $this->ensureUser('cobertura.4', self::PREFIX . ' 4 (deuda)');

        $this->reset($db, [$p1, $p2, $p3, $p4]);

        $now = date('Y-m-d H:i:s');
        $today = date('Y-m-d');
        $bono = function (int $pid, int $sessions, string $expires) use ($db, $type, $adminId, $today, $now) {
            $db->table('player_bonos')->insert([
                'player_id' => $pid, 'bono_type_id' => $type['id'], 'sessions_total' => $sessions, 'sessions_remaining' => $sessions,
                'start_date' => $today, 'expires_at' => $expires, 'created_by' => $adminId, 'created_at' => $now, 'updated_at' => $now,
            ]);
            $id = (int) $db->insertID();
            $db->table('bono_movements')->insert([
                'player_id' => $pid, 'bono_id' => $id, 'type' => 'granted', 'delta' => $sessions,
                'note' => 'Seeder TICKET-013', 'actor_id' => $adminId, 'created_at' => $now,
            ]);
        };

        // 1) bono de 2 sesiones vigente
        $bono($p1, 2, date('Y-m-d', strtotime('+90 days')));
        // 3) bono de 4 que caduca en 5 días
        $bono($p3, 4, date('Y-m-d', strtotime('+5 days')));

        // 4) dos clases ya dadas (hoy) sin bono → deuda
        foreach ([1, 2] as $n) {
            $db->table('class_sessions')->insert([
                'title' => self::PREFIX . " clase dada sin bono {$n}", 'session_date' => $today, 'start_time' => sprintf('%02d:00:00', 9 + $n),
                'end_time' => sprintf('%02d:00:00', 10 + $n), 'status' => 'completed', 'created_by' => $adminId,
                'class_format' => 'individual', 'session_type' => 'coach', 'created_at' => $now, 'updated_at' => $now,
            ]);
            $sid = (int) $db->insertID();
            $rowId = (int) $db->query('SELECT COALESCE(MAX(id),0)+1 n FROM class_session_players')->getRowArray()['n'];
            $db->table('class_session_players')->insert([
                'id' => $rowId, 'session_id' => $sid, 'user_id' => $p4, 'attendance' => 'present', 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        echo "\nSeeder TICKET-013 listo.\n"
            . "  Alumnos (contraseña " . self::PASSWORD . "): cobertura.1..4" . self::DOMAIN . "\n"
            . "  Aviso de caducidad (alumno 3): se genera al abrir el Dashboard como admin (máx. 1 vez/hora).\n"
            . "  Prueba: Nueva clase → recurrente (jueves, 1 mes) con el alumno 1 → panel de cobertura.\n"
            . "  Prueba: Bonos → Asignar bono al alumno 4 → se saldan solas 2 deudas.\n";
    }

    private function ensureUser(string $slug, string $name): int
    {
        $users = new UserModel();
        $email = $slug . self::DOMAIN;
        $u = $users->where('email', $email)->first();
        if ($u) {
            return (int) $u['id'];
        }
        $id = $users->insert(['name' => $name, 'email' => $email, 'password' => self::PASSWORD, 'role' => 'player', 'status' => 'active'], true);
        if (!$id) {
            throw new \RuntimeException("No se pudo crear {$email}: " . implode(' ', $users->errors()));
        }
        return (int) $id;
    }

    /** Borra SOLO lo generado por este seeder para esos alumnos de prueba. */
    private function reset($db, array $playerIds): void
    {
        $sids = array_column($db->table('class_session_players')->select('session_id')->whereIn('user_id', $playerIds)->get()->getResultArray(), 'session_id');
        $sids = array_merge($sids, array_column($db->table('class_sessions')->like('title', self::PREFIX)->get()->getResultArray(), 'id'));
        $sids = array_values(array_unique(array_map('intval', $sids)));
        if ($sids) {
            $db->table('class_session_players')->whereIn('session_id', $sids)->delete();
            $db->table('class_session_coaches')->whereIn('session_id', $sids)->delete();
            $db->table('class_sessions')->whereIn('id', $sids)->delete();
        }
        $db->table('classes')->like('title', self::PREFIX)->delete();
        $db->table('bono_movements')->whereIn('player_id', $playerIds)->delete();
        $db->table('player_bonos')->whereIn('player_id', $playerIds)->delete();
    }
}
