<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * «Nada se borra» (v1.33.0, Fase 0 de Finanzas).
 *
 * 1. `audit_log`: registro de solo-añadir de cada alta, cambio, anulación,
 *    archivado o borrado bloqueado (quién, cuándo, antes, después y motivo).
 * 2. `player_bonos`: anulación en vez de borrado (`voided_*`) y precio
 *    congelado al venderlo (`price_list_cents`, `price_cents`). Los bonos que
 *    ya existían se rellenan con el precio ACTUAL de su tipo y quedan
 *    marcados `price_estimated = 1` (el precio real pagado no se guardaba).
 * 3. `bono_types.archived_at` y `locations.archived_at`: se archivan, no se borran.
 *
 * `users.id` es INT CON SIGNO → `actor_id` / `voided_by` sin UNSIGNED.
 * Solo añade; no borra ni modifica datos existentes salvo el relleno del
 * precio estimado. Idempotente. SQL de prod: docs/deploy/migraciones_nada_se_borra.sql
 */
class NadaSeBorra extends Migration
{
    public function up(): void
    {
        $this->db->query(
            "CREATE TABLE IF NOT EXISTS `audit_log` (
                `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
                `entity_type` VARCHAR(40)  NOT NULL,
                `entity_id`   INT UNSIGNED NULL DEFAULT NULL,
                `action`      VARCHAR(20)  NOT NULL,
                `before_json` LONGTEXT     NULL DEFAULT NULL,
                `after_json`  LONGTEXT     NULL DEFAULT NULL,
                `reason`      VARCHAR(500) NULL DEFAULT NULL,
                `actor_id`    INT          NULL DEFAULT NULL,
                `actor_role`  VARCHAR(20)  NULL DEFAULT NULL,
                `ip_address`  VARCHAR(45)  NULL DEFAULT NULL,
                `created_at`  DATETIME     NOT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_audit_entity` (`entity_type`, `entity_id`),
                KEY `idx_audit_created` (`created_at`),
                KEY `idx_audit_actor` (`actor_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci"
        );

        $this->addColumns('player_bonos', [
            'voided_at'        => "DATETIME NULL DEFAULT NULL",
            'voided_by'        => "INT NULL DEFAULT NULL",
            'void_reason'      => "VARCHAR(255) NULL DEFAULT NULL",
            'price_list_cents' => "INT NULL DEFAULT NULL",
            'price_cents'      => "INT NULL DEFAULT NULL",
            'price_estimated'  => "TINYINT(1) NOT NULL DEFAULT 0",
        ]);
        $this->addColumns('bono_types', ['archived_at' => "DATETIME NULL DEFAULT NULL"]);
        $this->addColumns('locations',  ['archived_at' => "DATETIME NULL DEFAULT NULL"]);

        // Precio estimado para los bonos anteriores a esta versión.
        $this->db->query(
            "UPDATE `player_bonos` pb
             JOIN `bono_types` bt ON bt.id = pb.bono_type_id
             SET pb.price_list_cents = ROUND(bt.price * 100),
                 pb.price_cents      = ROUND(bt.price * 100),
                 pb.price_estimated  = 1
             WHERE pb.price_cents IS NULL"
        );
    }

    public function down(): void
    {
        // Sin bajada destructiva: el registro de auditoría y los precios
        // congelados son histórico y no se eliminan desde una migración.
    }

    /** @param array<string,string> $columns nombre => definición SQL */
    private function addColumns(string $table, array $columns): void
    {
        if (!$this->db->tableExists($table)) {
            return;
        }
        foreach ($columns as $col => $def) {
            if (!$this->db->fieldExists($col, $table)) {
                $this->db->query("ALTER TABLE `{$table}` ADD COLUMN `{$col}` {$def}");
            }
        }
    }
}
