<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateConversations extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'user1_id'        => ['type' => 'INT', 'unsigned' => true],
            'user2_id'        => ['type' => 'INT', 'unsigned' => true],
            'created_at'      => ['type' => 'DATETIME', 'null' => true],
            'last_message_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['user1_id', 'user2_id']);
        $this->forge->addKey('last_message_at');

        $this->forge->addForeignKey('user1_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('user2_id', 'users', 'id', 'CASCADE', 'CASCADE');

        $this->forge->createTable('conversations', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('conversations', true);
    }
}
