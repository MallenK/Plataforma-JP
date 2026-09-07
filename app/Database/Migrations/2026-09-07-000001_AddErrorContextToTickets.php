<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Contexto de reporte para los tickets generados desde una alerta de error
 * o de permiso (feature "Reportar problema" desde la alerta).
 *
 *  - origin     : cómo se abrió el ticket
 *  - error_ref  : código corto que correlaciona con el log del servidor
 *  - context    : JSON con URL, endpoint, navegador, timestamp del cliente…
 */
class AddErrorContextToTickets extends Migration
{
    public function up(): void
    {
        $this->db->query(
            "ALTER TABLE `tickets`
                ADD COLUMN `origin`    ENUM('manual','error','permiso') NOT NULL DEFAULT 'manual' AFTER `status`,
                ADD COLUMN `error_ref` VARCHAR(12) NULL AFTER `origin`,
                ADD COLUMN `context`   TEXT NULL AFTER `error_ref`"
        );
    }

    public function down(): void
    {
        $this->db->query(
            "ALTER TABLE `tickets`
                DROP COLUMN `origin`,
                DROP COLUMN `error_ref`,
                DROP COLUMN `context`"
        );
    }
}
