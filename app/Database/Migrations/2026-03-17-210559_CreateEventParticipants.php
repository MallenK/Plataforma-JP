<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateEventParticipants extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'auto_increment' => true,
            ],
            'event_id' => [
                'type' => 'INT',
                'null' => true,
            ],
            'user_id' => [
                'type' => 'INT',
                'null' => true,
            ],
            'role' => [
                'type'       => 'ENUM',
                'constraint' => ['coach', 'player'],
                'null'       => true,
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['event_id', 'user_id']);
        $this->forge->addKey('user_id');
        $this->forge->addForeignKey('event_id', 'events', 'id', 'CASCADE', '');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', '');
        $this->forge->createTable('event_participants');
    }

    public function down()
    {
        $this->forge->dropTable('event_participants');
    }
}
