<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Varios bonos activos por alumno (v1.31.0).
 *
 * 1. `bono_movements.related_bono_id`: en un "Cambiar bono" cada movimiento
 *    apunta al otro bono implicado, para que el libro (y las finanzas) sepan
 *    de dónde y a dónde se movió cada sesión.
 * 2. Índice `idx_pb_player_saldo` en `player_bonos` (player_id, sessions_remaining,
 *    expires_at): cubre la consulta "bonos con saldo y vigentes de un alumno",
 *    que ahora se hace en cada pantalla (Bonos, Pasar lista, ficha, avisos).
 *
 * `player_bonos.player_id` ya admitía N bonos por alumno: no hay cambio de
 * modelo, solo trazabilidad e índice. Sin prioridad de consumo: la elección
 * del bono es siempre manual. Idempotente.
 */
class MultiBonoSupport extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('bono_movements') && !$this->db->fieldExists('related_bono_id', 'bono_movements')) {
            $this->db->query("ALTER TABLE `bono_movements` ADD COLUMN `related_bono_id` INT UNSIGNED NULL DEFAULT NULL AFTER `bono_id`");
            $this->db->query("ALTER TABLE `bono_movements` ADD KEY `idx_bm_related` (`related_bono_id`)");
        }

        if (!$this->indexExists('player_bonos', 'idx_pb_player_saldo')) {
            $this->db->query("ALTER TABLE `player_bonos` ADD KEY `idx_pb_player_saldo` (`player_id`, `sessions_remaining`, `expires_at`)");
        }
    }

    public function down(): void
    {
        if ($this->indexExists('player_bonos', 'idx_pb_player_saldo')) {
            $this->db->query("ALTER TABLE `player_bonos` DROP KEY `idx_pb_player_saldo`");
        }
        if ($this->db->tableExists('bono_movements') && $this->db->fieldExists('related_bono_id', 'bono_movements')) {
            $this->db->query("ALTER TABLE `bono_movements` DROP KEY `idx_bm_related`");
            $this->db->query("ALTER TABLE `bono_movements` DROP COLUMN `related_bono_id`");
        }
    }

    private function indexExists(string $table, string $index): bool
    {
        $rows = $this->db->query("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index])->getResultArray();
        return !empty($rows);
    }
}
