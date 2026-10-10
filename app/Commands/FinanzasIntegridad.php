<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

/**
 * Informe de integridad de los datos económicos (v1.33.0). SOLO LECTURA.
 *
 * Lista datos huérfanos o que no cuadran (descuentos que apuntan a bonos que
 * ya no existen, bonos de tipos borrados, saldos que no coinciden con los
 * descuentos registrados…). Cuando salga limpio se podrán añadir claves
 * foráneas a la BD sin romper nada.
 *
 *   php spark finanzas:integridad            (resumen)
 *   php spark finanzas:integridad --detalle  (con los ids de cada caso)
 */
class FinanzasIntegridad extends BaseCommand
{
    protected $group       = 'Finanzas';
    protected $name        = 'finanzas:integridad';
    protected $description = 'Informe (solo lectura) de datos económicos huérfanos o que no cuadran.';
    protected $usage       = 'finanzas:integridad [--detalle]';

    /** Comprobaciones: etiqueta => SQL que devuelve una columna `id` por caso. */
    private const CHECKS = [
        'Descuentos que apuntan a un bono inexistente' =>
            "SELECT csp.id FROM class_session_players csp LEFT JOIN player_bonos pb ON pb.id = csp.bono_deducted_from_id
             WHERE csp.bono_deducted_from_id IS NOT NULL AND pb.id IS NULL",
        'Descuentos sin bono asociado (anteriores a v1.4)' =>
            "SELECT id FROM class_session_players WHERE bono_deducted_at IS NOT NULL AND bono_deducted_from_id IS NULL",
        'Asistencias de una sesión inexistente' =>
            "SELECT csp.id FROM class_session_players csp LEFT JOIN class_sessions cs ON cs.id = csp.session_id WHERE cs.id IS NULL",
        'Asistencias de un usuario inexistente' =>
            "SELECT csp.id FROM class_session_players csp LEFT JOIN users u ON u.id = csp.user_id WHERE u.id IS NULL",
        'Bonos de un tipo inexistente' =>
            "SELECT pb.id FROM player_bonos pb LEFT JOIN bono_types bt ON bt.id = pb.bono_type_id WHERE bt.id IS NULL",
        'Bonos de un alumno inexistente' =>
            "SELECT pb.id FROM player_bonos pb LEFT JOIN users u ON u.id = pb.player_id WHERE pb.player_id IS NOT NULL AND u.id IS NULL",
        'Bonos con saldo fuera de rango' =>
            "SELECT id FROM player_bonos WHERE sessions_remaining < 0 OR sessions_remaining > sessions_total",
        'Bonos cuyo consumo no cuadra con los descuentos registrados' =>
            "SELECT pb.id FROM player_bonos pb
             LEFT JOIN (SELECT bono_deducted_from_id AS bid, COUNT(*) AS n FROM class_session_players
                        WHERE bono_deducted_at IS NOT NULL AND bono_deducted_from_id IS NOT NULL GROUP BY bono_deducted_from_id) d
                    ON d.bid = pb.id
             WHERE pb.voided_at IS NULL AND (pb.sessions_total - pb.sessions_remaining) <> COALESCE(d.n, 0)",
        'Movimientos de bono que apuntan a un bono inexistente' =>
            "SELECT bm.id FROM bono_movements bm LEFT JOIN player_bonos pb ON pb.id = bm.bono_id WHERE bm.bono_id IS NOT NULL AND pb.id IS NULL",
        'Entrenadores de sesión inexistentes' =>
            "SELECT csc.session_id AS id FROM class_session_coaches csc LEFT JOIN users u ON u.id = csc.user_id WHERE u.id IS NULL",
        'Sesiones de una clase (serie) inexistente' =>
            "SELECT cs.id FROM class_sessions cs LEFT JOIN classes c ON c.id = cs.class_id WHERE cs.class_id IS NOT NULL AND c.id IS NULL",
    ];

    public function run(array $params): void
    {
        $detail = in_array('--detalle', $params, true) || array_key_exists('detalle', $params);
        $db     = Database::connect();
        $total  = 0;

        CLI::write('Integridad de los datos económicos (solo lectura)', 'light_cyan');
        CLI::newLine();

        foreach (self::CHECKS as $label => $sql) {
            try {
                $ids = array_column($db->query($sql)->getResultArray(), 'id');
            } catch (\Throwable $e) {
                CLI::write(sprintf('  %-62s %s', $label, 'no comprobable: ' . $e->getMessage()), 'yellow');
                continue;
            }
            $n = count($ids);
            $total += $n;
            CLI::write(sprintf('  %-62s %5d', $label, $n), $n ? 'yellow' : 'green');
            if ($detail && $n) {
                CLI::write('      ids: ' . implode(', ', array_slice($ids, 0, 200)) . ($n > 200 ? ' …' : ''), 'dark_gray');
            }
        }

        CLI::newLine();
        CLI::write($total === 0
            ? 'Todo cuadra. Se pueden añadir claves foráneas sin romper nada.'
            : "{$total} caso(s) por revisar. Nada se ha modificado.", $total === 0 ? 'green' : 'yellow');
    }
}
