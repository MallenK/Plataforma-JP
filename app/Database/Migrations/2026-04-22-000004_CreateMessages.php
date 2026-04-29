<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateMessages extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'conversation_id' => ['type' => 'INT', 'unsigned' => true],
            'sender_id'       => ['type' => 'INT', 'unsigned' => true],
            'body'            => ['type' => 'TEXT', 'null' => true],
            'file_path'       => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'file_name'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'file_size'       => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'file_mime'       => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'read_at'         => ['type' => 'DATETIME', 'null' => true],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('conversation_id');
        $this->forge->addKey('sender_id');
        $this->forge->addKey('created_at');

        $this->forge->addForeignKey('conversation_id', 'conversations', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('sender_id', 'users', 'id', 'CASCADE', 'CASCADE');

        $this->forge->createTable('messages', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('messages', true);
    }
}
