<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateTicketReplies extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'ticket_id'  => ['type' => 'INT', 'unsigned' => true],
            'user_id'    => ['type' => 'INT', 'unsigned' => true],
            'body'       => ['type' => 'TEXT'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('ticket_id');

        $this->forge->addForeignKey('ticket_id', 'tickets', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('user_id',   'users',   'id', 'CASCADE', 'CASCADE');

        $this->forge->createTable('ticket_replies', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('ticket_replies', true);
    }
}
