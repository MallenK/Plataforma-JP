<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * SOLO PARA DESARROLLO / PPR / DEMO. Nunca contra producción.
 *
 * Reparte los bonos con saldo y vigentes de los alumnos que YA tienen alguno
 * en un 60 % con un único bono y un 40 % con dos o más (de ese 40 %: ~75 %
 * con 2, ~20 % con 3 y ~5 % con 4). No crea alumnos ni toca los bonos
 * existentes: solo AÑADE bonos extra a alumnos que ya tienen uno.
 *
 * Cada bono extra queda marcado con "[seed:multibono]" en `notes` y con su
 * movimiento en el libro (alta + consumo ya hecho), de modo que el seeder es
 * idempotente: borra lo suyo y lo regenera. Reproducible (semilla fija).
 *
 * Ejecutar:  docker exec jp_app php spark db:seed MultiBonoSeeder
 */
class MultiBonoSeeder extends Seeder
{
    public const MARK          = '[seed:multibono]';
    private const SHARE_MULTI  = 0.40;
    private const SHARE_THREE  = 0.20;   // dentro del grupo multi
    private const SHARE_FOUR   = 0.05;   // dentro del grupo multi

    public function run()
    {
        $db    = \Config\Database::connect();
        $today = date('Y-m-d');
        $now   = date('Y-m-d H:i:s');

        if (ENVIRONMENT === 'production') {
            throw new \RuntimeException('MultiBonoSeeder no se ejecuta en producción.');
        }

        $admin = $db->table('users')->whereIn('role', ['admin', 'superadmin'])->where('status', 'active')->get(1)->getRowArray();
        $types = $db->table('bono_types')->where('active', 1)->get()->getResultArray();
        if (!$admin || !$types) {
            throw new \RuntimeException('Hace falta un admin activo y al menos un tipo de bono activo.');
        }

        // 1) Limpiar lo de una ejecución anterior.
        $old = array_column($db->table('player_bonos')->select('id')->like('notes', self::MARK)->get()->getResultArray(), 'id');
        if ($old) {
            $db->table('bono_movements')->whereIn('bono_id', $old)->delete();
            $db->table('player_bonos')->whereIn('id', $old)->delete();
        }

        // 2) Alumnos con bono vigente y con saldo, y cuántos tiene cada uno.
        $rows = $db->table('player_bonos pb')
            ->select('pb.player_id, COUNT(*) AS n')
            ->join('users u', 'u.id = pb.player_id')
            ->where('u.role', 'player')->where('u.status', 'active')
            ->where('pb.sessions_remaining >', 0)
            ->groupStart()->where('pb.expires_at IS NULL')->orWhere('pb.expires_at >=', $today)->groupEnd()
            ->groupBy('pb.player_id')->get()->getResultArray();

        $holders = count($rows);
        if ($holders === 0) {
            echo "No hay alumnos con bono vigente: nada que repartir.\n";
            return;
        }
        $alreadyMulti = 0;
        $singles      = [];
        foreach ($rows as $r) {
            if ((int) $r['n'] >= 2) {
                $alreadyMulti++;
            } else {
                $singles[] = (int) $r['player_id'];
            }
        }

        // 3) Cuántos hay que convertir en multi.
        mt_srand(20261008);
        $targetMulti = (int) round($holders * self::SHARE_MULTI);
        $convert     = max(0, min(count($singles), $targetMulti - $alreadyMulti));
        shuffle($singles);
        $chosen = array_slice($singles, 0, $convert);

        $n4 = $convert > 0 ? max(1, (int) round($convert * self::SHARE_FOUR)) : 0;
        $n3 = (int) round($convert * self::SHARE_THREE);
        $extra = [];                                  // player_id => bonos a añadir
        foreach ($chosen as $i => $pid) {
            $extra[$pid] = $i < $n4 ? 3 : ($i < $n4 + $n3 ? 2 : 1);
        }

        // 4) Crear los bonos extra.
        $created = 0;
        foreach ($extra as $pid => $count) {
            $have = array_map('intval', array_column(
                $db->table('player_bonos')->select('bono_type_id')->where('player_id', $pid)->get()->getResultArray(),
                'bono_type_id'
            ));
            for ($k = 0; $k < $count; $k++) {
                $pool = array_values(array_filter(
                    $types,
                    fn($t) => !in_array((int) $t['id'], $have, true) && (int) $t['validity_days'] >= 14
                ));
                $type   = $pool ? $pool[mt_rand(0, count($pool) - 1)] : $types[mt_rand(0, count($types) - 1)];
                $have[] = (int) $type['id'];

                $total     = max(2, (int) $type['sessions']);
                $remaining = mt_rand(1, $total);
                $startDays = mt_rand(0, 30);
                $start     = date('Y-m-d', strtotime("-{$startDays} days"));
                $expires   = date('Y-m-d', strtotime($start . ' +' . max(14, (int) $type['validity_days']) . ' days'));
                if ($expires < date('Y-m-d', strtotime('+7 days'))) {
                    $expires = date('Y-m-d', strtotime('+' . mt_rand(10, 40) . ' days'));
                }

                $db->table('player_bonos')->insert([
                    'player_id' => $pid, 'bono_type_id' => (int) $type['id'],
                    'sessions_total' => $total, 'sessions_remaining' => $remaining,
                    'start_date' => $start, 'expires_at' => $expires,
                    'notes' => self::MARK . ' bono extra de prueba',
                    'created_by' => (int) $admin['id'], 'created_at' => $start . ' 10:00:00', 'updated_at' => $now,
                ]);
                $bonoId = (int) $db->insertID();

                $db->table('bono_movements')->insert([
                    'player_id' => $pid, 'bono_id' => $bonoId, 'type' => 'granted', 'delta' => $total,
                    'note' => $type['name'], 'actor_id' => (int) $admin['id'], 'created_at' => $start . ' 10:00:00',
                ]);
                if ($total > $remaining) {
                    $db->table('bono_movements')->insert([
                        'player_id' => $pid, 'bono_id' => $bonoId, 'type' => 'deducted', 'delta' => -($total - $remaining),
                        'note' => 'Sesiones ya consumidas (dato de prueba)', 'actor_id' => (int) $admin['id'], 'created_at' => $now,
                    ]);
                }
                $created++;
            }
        }

        // 5) Resumen del reparto resultante.
        $dist = [];
        foreach ($db->query(
            "SELECT t.n, COUNT(*) AS alumnos FROM (
                SELECT pb.player_id, COUNT(*) AS n FROM player_bonos pb JOIN users u ON u.id = pb.player_id
                WHERE u.role = 'player' AND pb.sessions_remaining > 0 AND (pb.expires_at IS NULL OR pb.expires_at >= CURDATE())
                GROUP BY pb.player_id) t GROUP BY t.n ORDER BY t.n"
        )->getResultArray() as $r) {
            $dist[(int) $r['n']] = (int) $r['alumnos'];
        }
        $total = array_sum($dist);
        echo "Bonos extra creados: {$created}\n";
        foreach ($dist as $n => $a) {
            printf("  %d bono(s) con saldo: %3d alumnos (%.0f %%)\n", $n, $a, $total ? $a * 100 / $total : 0);
        }
        $multi = $total - ($dist[1] ?? 0);
        printf("  -> unico: %.0f %% · dos o mas: %.0f %%\n", $total ? ($dist[1] ?? 0) * 100 / $total : 0, $total ? $multi * 100 / $total : 0);
    }
}
