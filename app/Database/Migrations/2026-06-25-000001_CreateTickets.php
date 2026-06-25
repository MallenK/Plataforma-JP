<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateTickets extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'ticket_number' => ['type' => 'VARCHAR', 'constraint' => 20],
            'user_id'       => ['type' => 'INT', 'unsigned' => true],
            'category'      => ['type' => 'ENUM', 'constraint' => ['bug', 'mejora', 'consulta', 'tecnico', 'otro'], 'default' => 'otro'],
            'priority'      => ['type' => 'ENUM', 'constraint' => ['baja', 'media', 'alta', 'urgente'], 'default' => 'media'],
            'title'         => ['type' => 'VARCHAR', 'constraint' => 255],
            'description'   => ['type' => 'TEXT'],
            'status'        => ['type' => 'ENUM', 'constraint' => ['abierto', 'en_progreso', 'resuelto', 'cerrado'], 'default' => 'abierto'],
            'resolved_at'   => ['type' => 'DATETIME', 'null' => true],
            'closed_at'     => ['type' => 'DATETIME', 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('ticket_number');
        $this->forge->addKey('user_id');
        $this->forge->addKey('status');
        $this->forge->addKey('priority');
        $this->forge->addKey('created_at');

        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');

        $this->forge->createTable('tickets', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('tickets', true);
    }
}
