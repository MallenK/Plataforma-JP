<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Ejemplos de alumnos con VARIOS bonos activos a la vez (v1.31.0), para poder
 * enseñar y probar el flujo nuevo en PPR / demo.
 *
 * SOLO actúa en entornos etiquetados (APP_ENV_LABEL no vacío: PPR, demo…). En
 * producción real la etiqueta está vacía y la migración no hace NADA, así que
 * nunca inventa bonos a alumnos reales. Es de datos, no de estructura.
 *
 * Qué hace: coge hasta EJEMPLOS alumnos activos que hoy tienen UN solo bono con
 * saldo y vigente, y les añade otro (a uno, dos más) con saldo y caducidad
 * distintos. Cada bono queda marcado con "[ejemplo:multibono]" en `notes` y con
 * su alta en el libro de movimientos, para poder retirarlo (down()) sin tocar nada
 * más. Idempotente: si ya hay ejemplos, no vuelve a crearlos. Determinista (sin azar).
 */
class SeedMultiBonoExamples extends Migration
{
    public const MARK = '[ejemplo:multibono]';

    /** Alumnos de ejemplo y bonos extra de cada uno (el último recibe 2 extra → 3 bonos). */
    private const EXTRAS = [1, 1, 1, 1, 1, 2];

    public function up(): void
    {
        $label = trim((string) env('APP_ENV_LABEL', ''));
        if ($label === '') {
            return; // producción real (o local sin etiqueta): no se siembra nada
        }
        if (!$this->db->tableExists('player_bonos') || !$this->db->tableExists('bono_types')) {
            return;
        }
        if ($this->db->table('player_bonos')->like('notes', self::MARK)->countAllResults() > 0) {
            return; // ya sembrado
        }

        $today = date('Y-m-d');
        $now   = date('Y-m-d H:i:s');

        $types = $this->db->table('bono_types')->where('active', 1)->orderBy('id', 'ASC')->get()->getResultArray();
        $admin = $this->db->table('users')->whereIn('role', ['admin', 'superadmin'])->where('status', 'active')->orderBy('id', 'ASC')->get(1)->getRowArray();
        if (empty($types) || !$admin) {
            return;
        }

        // Alumnos activos con exactamente UN bono con saldo y vigente.
        $candidates = $this->db->table('player_bonos pb')
            ->select('pb.player_id')
            ->join('users u', 'u.id = pb.player_id')
            ->where('u.role', 'player')->where('u.status', 'active')
            ->where('pb.sessions_remaining >', 0)
            ->groupStart()->where('pb.expires_at IS NULL')->orWhere('pb.expires_at >=', $today)->groupEnd()
            ->groupBy('pb.player_id')
            ->having('COUNT(*) = 1')
            ->orderBy('pb.player_id', 'ASC')
            ->limit(count(self::EXTRAS))
            ->get()->getResultArray();

        if (count($candidates) < 3) {
            return; // entorno casi vacío: mejor no sembrar
        }

        $typeCount = count($types);
        $n = 0;
        foreach ($candidates as $i => $c) {
            $pid  = (int) $c['player_id'];
            $have = array_map('intval', array_column(
                $this->db->table('player_bonos')->select('bono_type_id')->where('player_id', $pid)->get()->getResultArray(),
                'bono_type_id'
            ));

            for ($k = 0; $k < self::EXTRAS[$i]; $k++) {
                // Un tipo distinto a los que ya tiene (si hay), rotando de forma determinista.
                $type = null;
                for ($t = 0; $t < $typeCount; $t++) {
                    $try = $types[($i + $k + $t) % $typeCount];
                    if (!in_array((int) $try['id'], $have, true) && (int) $try['sessions'] >= 2) {
                        $type = $try;
                        break;
                    }
                }
                $type ??= $types[($i + $k) % $typeCount];
                $have[] = (int) $type['id'];

                $total     = max(2, (int) $type['sessions']);
                $remaining = max(1, (int) ceil($total * (($n % 3) + 1) / 4));   // 25 %, 50 % o 75 % del bono
                $start     = date('Y-m-d', strtotime('-' . (5 + $n * 4) . ' days'));
                $expires   = date('Y-m-d', strtotime($start . ' +' . max(60, (int) $type['validity_days']) . ' days'));
                if ($expires < date('Y-m-d', strtotime('+30 days'))) {
                    $expires = date('Y-m-d', strtotime('+' . (45 + $n * 10) . ' days'));
                }

                $this->db->table('player_bonos')->insert([
                    'player_id' => $pid, 'bono_type_id' => (int) $type['id'],
                    'sessions_total' => $total, 'sessions_remaining' => min($remaining, $total),
                    'start_date' => $start, 'expires_at' => $expires,
                    'notes' => self::MARK . ' segundo bono de ejemplo',
                    'created_by' => (int) $admin['id'], 'created_at' => $start . ' 10:00:00', 'updated_at' => $now,
                ]);
                $bonoId = (int) $this->db->insertID();

                if ($this->db->tableExists('bono_movements')) {
                    $this->db->table('bono_movements')->insert([
                        'player_id' => $pid, 'bono_id' => $bonoId, 'type' => 'granted', 'delta' => $total,
                        'note' => $type['name'], 'actor_id' => (int) $admin['id'], 'created_at' => $start . ' 10:00:00',
                    ]);
                    if ($total > $remaining) {
                        $this->db->table('bono_movements')->insert([
                            'player_id' => $pid, 'bono_id' => $bonoId, 'type' => 'deducted', 'delta' => -($total - $remaining),
                            'note' => 'Sesiones ya consumidas (ejemplo)', 'actor_id' => (int) $admin['id'], 'created_at' => $now,
                        ]);
                    }
                }
                $n++;
            }
        }
    }

    public function down(): void
    {
        $ids = array_column(
            $this->db->table('player_bonos')->select('id')->like('notes', self::MARK)->get()->getResultArray(),
            'id'
        );
        if (!$ids) {
            return;
        }
        if ($this->db->tableExists('bono_movements')) {
            $this->db->table('bono_movements')->whereIn('bono_id', $ids)->delete();
        }
        $this->db->table('player_bonos')->whereIn('id', $ids)->delete();
    }
}
