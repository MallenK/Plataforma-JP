<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSessionAttendance extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'auto_increment' => true,
            ],
            'session_id' => [
                'type' => 'INT',
                'null' => true,
            ],
            'player_id' => [
                'type' => 'INT',
                'null' => true,
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['present', 'absent', 'late'],
                'default'    => 'present',
                'null'       => true,
            ],
            'check_in_time' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'notes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['session_id', 'player_id']);
        $this->forge->addKey('player_id');
        $this->forge->addForeignKey('session_id', 'sessions', 'id', 'CASCADE', '');
        $this->forge->addForeignKey('player_id', 'users', 'id', 'CASCADE', '');
        $this->forge->createTable('session_attendance');
    }

    public function down()
    {
        $this->forge->dropTable('session_attendance');
    }
}
