<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * F4 — pulido.
 *
 *  - ticket_counters            : contador de nº de ticket por año, independiente
 *                                 del `id` (antes ticket_number = MAX(id)+1 →
 *                                 saltaba números y no reiniciaba en enero).
 *  - tickets.reported_urgency   : urgencia que indica el solicitante (informativa);
 *                                 la `priority` real la fija el gestor.
 */
class AddTicketCountersAndReportedUrgency extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'year' => ['type' => 'INT', 'unsigned' => true],
            'last' => ['type' => 'INT', 'unsigned' => true, 'default' => 0],
        ]);
        $this->forge->addKey('year', true);
        $this->forge->createTable('ticket_counters', true);

        $this->db->query(
            "ALTER TABLE `tickets`
                ADD COLUMN `reported_urgency` ENUM('baja','media','alta','urgente') NULL AFTER `priority`"
        );

        // Siembra el contador del año en curso con el mayor sufijo ya emitido
        // (TKT-AAAA-NNNNN) para no reutilizar números.
        $year = (int) date('Y');
        $rows = $this->db->table('tickets')
            ->select('ticket_number')
            ->like('ticket_number', 'TKT-' . $year . '-', 'after')
            ->get()->getResultArray();

        $max = 0;
        foreach ($rows as $r) {
            $n = (int) substr((string) $r['ticket_number'], -5);
            if ($n > $max) {
                $max = $n;
            }
        }
        $this->db->table('ticket_counters')->ignore(true)->insert(['year' => $year, 'last' => $max]);
    }

    public function down(): void
    {
        $this->db->query("ALTER TABLE `tickets` DROP COLUMN `reported_urgency`");
        $this->forge->dropTable('ticket_counters', true);
    }
}
