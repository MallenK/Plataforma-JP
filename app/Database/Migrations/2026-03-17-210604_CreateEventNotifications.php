<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateEventNotifications extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'event_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'member_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'sent_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'default' => null,
            ],
            'read_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['event_id', 'member_id'], 'uq_event_member');
        $this->forge->addKey('member_id', false, false, 'idx_member_id');
        $this->forge->createTable('event_notifications', true);
    }

    public function down()
    {
        $this->forge->dropTable('event_notifications');
    }
}
