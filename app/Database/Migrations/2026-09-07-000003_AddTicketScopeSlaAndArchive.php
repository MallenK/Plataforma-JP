<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * F3 — niveles + SLA + archivado.
 *
 *  - tickets.scope             : 'academia' | 'plataforma'. El alumno solo abre
 *                                de academia; admin/coach/staff de ambos.
 *                                El superadmin gestiona los dos siempre.
 *  - tickets.first_response_at : primera respuesta pública de un gestor (SLA).
 *  - tickets.archived_at       : archivado (no se borra nunca) — desaparece de
 *                                las bandejas pero conserva historial.
 */
class AddTicketScopeSlaAndArchive extends Migration
{
    public function up(): void
    {
        $this->db->query(
            "ALTER TABLE `tickets`
                ADD COLUMN `scope` ENUM('academia','plataforma') NOT NULL DEFAULT 'academia' AFTER `category`,
                ADD COLUMN `first_response_at` DATETIME NULL AFTER `resolved_at`,
                ADD COLUMN `archived_at` DATETIME NULL AFTER `closed_at`,
                ADD KEY `tickets_scope` (`scope`),
                ADD KEY `tickets_archived_at` (`archived_at`)"
        );
    }

    public function down(): void
    {
        $this->db->query(
            "ALTER TABLE `tickets`
                DROP KEY `tickets_scope`,
                DROP KEY `tickets_archived_at`,
                DROP COLUMN `scope`,
                DROP COLUMN `first_response_at`,
                DROP COLUMN `archived_at`"
        );
    }
}
