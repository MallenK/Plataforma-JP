<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateTicketAttachments extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'ticket_id'  => ['type' => 'INT', 'unsigned' => true],
            'reply_id'   => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'file_path'  => ['type' => 'VARCHAR', 'constraint' => 500],
            'file_name'  => ['type' => 'VARCHAR', 'constraint' => 255],
            'file_size'  => ['type' => 'INT', 'unsigned' => true],
            'file_mime'  => ['type' => 'VARCHAR', 'constraint' => 100],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('ticket_id');
        $this->forge->addKey('reply_id');

        $this->forge->addForeignKey('ticket_id', 'tickets',       'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('reply_id',  'ticket_replies', 'id', 'CASCADE', 'SET NULL');

        $this->forge->createTable('ticket_attachments', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('ticket_attachments', true);
    }
}
