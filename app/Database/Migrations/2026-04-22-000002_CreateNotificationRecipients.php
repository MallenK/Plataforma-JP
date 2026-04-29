<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateNotificationRecipients extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'              => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'notification_id' => ['type' => 'INT', 'unsigned' => true],
            'recipient_id'    => ['type' => 'INT', 'unsigned' => true],
            'read_at'         => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey(['notification_id', 'recipient_id']);
        $this->forge->addKey('recipient_id');

        $this->forge->addForeignKey('notification_id', 'notifications', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('recipient_id', 'users', 'id', 'CASCADE', 'CASCADE');

        $this->forge->createTable('notification_recipients', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('notification_recipients', true);
    }
}
