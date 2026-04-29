<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateEventConfirmations extends Migration
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
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['pending', 'confirmed', 'declined'],
                'default'    => 'pending',
            ],
            'notes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'responded_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'created_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'default' => null,
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['event_id', 'member_id'], 'uq_event_member');
        $this->forge->addKey('member_id', false, false, 'idx_member_id');
        $this->forge->createTable('event_confirmations', true);
    }

    public function down()
    {
        $this->forge->dropTable('event_confirmations');
    }
}
