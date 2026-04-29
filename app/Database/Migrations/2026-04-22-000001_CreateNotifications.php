<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateNotifications extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'sender_id'  => ['type' => 'INT', 'unsigned' => true],
            'type'       => ['type' => 'ENUM', 'constraint' => ['individual', 'group'], 'default' => 'individual'],
            'title'      => ['type' => 'VARCHAR', 'constraint' => 255],
            'body'       => ['type' => 'TEXT'],
            'file_path'  => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'file_name'  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'file_size'  => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('sender_id');
        $this->forge->addKey('created_at');

        $this->forge->addForeignKey('sender_id', 'users', 'id', 'CASCADE', 'CASCADE');

        $this->forge->createTable('notifications', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('notifications', true);
    }
}
