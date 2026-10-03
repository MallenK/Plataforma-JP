<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * TICKET-013 — Clases recurrentes y bonos conectados.
 *
 * 1. `bono_movements`: libro de movimientos (solo se añade, nunca se edita)
 *    de todo lo que le pasa al bono de un alumno: alta, descuento, devolución,
 *    deuda abierta/saldada/resuelta, ampliación de caducidad, etc.
 * 2. `class_session_players`: marca de cobertura al programar la clase
 *    (`bono_coverage`) y resolución de una deuda (`bono_resolution*`).
 * 3. Ajuste `bono_control_since`: fecha del punto de control. Lo anterior a
 *    esa fecha no se trata como deuda, solo se señala como "no reflejado".
 *    Se fija el día en que corre esta migración (en PROD, el día del despliegue).
 *
 * Sin FK dura (mismo criterio que bono_deducted_from_id): el código tolera
 * ids que apunten a filas ya borradas. users.id es INT con signo.
 * Idempotente: se puede volver a ejecutar.
 */
class CreateBonoMovementsAndCoverage extends Migration
{
    public function up(): void
    {
        $this->db->query(
            "CREATE TABLE IF NOT EXISTS `bono_movements` (
                `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `player_id`  INT NOT NULL,
                `bono_id`    INT UNSIGNED NULL DEFAULT NULL,
                `session_id` INT UNSIGNED NULL DEFAULT NULL,
                `type`       VARCHAR(24) NOT NULL,
                `delta`      INT NOT NULL DEFAULT 0,
                `note`       VARCHAR(255) NULL DEFAULT NULL,
                `actor_id`   INT NULL DEFAULT NULL,
                `created_at` DATETIME NOT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_bm_player` (`player_id`, `created_at`),
                KEY `idx_bm_bono` (`bono_id`),
                KEY `idx_bm_session` (`session_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4"
        );

        foreach ([
            'bono_coverage'    => "VARCHAR(10) NULL DEFAULT NULL",
            'bono_resolution'  => "VARCHAR(10) NULL DEFAULT NULL",
            'bono_resolved_at' => "DATETIME NULL DEFAULT NULL",
            'bono_resolved_by' => "INT NULL DEFAULT NULL",
        ] as $col => $def) {
            if (!$this->db->fieldExists($col, 'class_session_players')) {
                $this->db->query("ALTER TABLE `class_session_players` ADD COLUMN `{$col}` {$def}");
            }
        }

        $exists = $this->db->table('academy_settings')->where('setting_key', 'bono_control_since')->countAllResults();
        if (!$exists) {
            $this->db->table('academy_settings')->insert([
                'setting_key'   => 'bono_control_since',
                'setting_value' => date('Y-m-d'),
                'setting_type'  => 'string',
                'updated_at'    => date('Y-m-d H:i:s'),
            ]);
        }
    }

    public function down(): void
    {
        $this->db->query("DROP TABLE IF EXISTS `bono_movements`");
        foreach (['bono_resolved_by', 'bono_resolved_at', 'bono_resolution', 'bono_coverage'] as $col) {
            if ($this->db->fieldExists($col, 'class_session_players')) {
                $this->db->query("ALTER TABLE `class_session_players` DROP COLUMN `{$col}`");
            }
        }
        $this->db->table('academy_settings')->where('setting_key', 'bono_control_since')->delete();
    }
}
