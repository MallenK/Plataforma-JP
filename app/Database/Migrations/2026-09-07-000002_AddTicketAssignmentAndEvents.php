<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * F2 — reparto de tickets.
 *
 *  - tickets.assigned_to        : gestor que lleva el ticket (NULL = sin asignar)
 *  - ticket_replies.is_internal : 1 = nota interna gestor↔gestor (el solicitante no la ve)
 *  - ticket_events              : trazabilidad (quién cambió qué y cuándo) → timeline
 */
class AddTicketAssignmentAndEvents extends Migration
{
    public function up(): void
    {
        // users.id es INT con signo en esta base — assigned_to debe casar.
        $this->db->query(
            "ALTER TABLE `tickets`
                ADD COLUMN `assigned_to` INT NULL AFTER `status`,
                ADD KEY `tickets_assigned_to` (`assigned_to`),
                ADD CONSTRAINT `tickets_assigned_to_fk`
                    FOREIGN KEY (`assigned_to`) REFERENCES `users`(`id`)
                    ON DELETE SET NULL ON UPDATE CASCADE"
        );

        $this->db->query(
            "ALTER TABLE `ticket_replies`
                ADD COLUMN `is_internal` TINYINT(1) NOT NULL DEFAULT 0 AFTER `body`"
        );

        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'ticket_id'  => ['type' => 'INT', 'unsigned' => true],
            'actor_id'   => ['type' => 'INT', 'null' => true],
            'event_type' => ['type' => 'VARCHAR', 'constraint' => 32],
            'from_value' => ['type' => 'VARCHAR', 'constraint' => 191, 'null' => true],
            'to_value'   => ['type' => 'VARCHAR', 'constraint' => 191, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('ticket_id');
        $this->forge->addForeignKey('ticket_id', 'tickets', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('actor_id', 'users', 'id', 'SET NULL', 'CASCADE');
        $this->forge->createTable('ticket_events', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('ticket_events', true);

        $this->db->query("ALTER TABLE `ticket_replies` DROP COLUMN `is_internal`");

        $this->db->query(
            "ALTER TABLE `tickets`
                DROP FOREIGN KEY `tickets_assigned_to_fk`,
                DROP KEY `tickets_assigned_to`,
                DROP COLUMN `assigned_to`"
        );
    }
}
