<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateEventTeamMembers extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'team_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'member_type' => [
                'type'       => 'ENUM',
                'constraint' => ['user', 'external'],
                'default'    => 'user',
            ],
            'user_id' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
            'external_id' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
            'role' => [
                'type'       => 'ENUM',
                'constraint' => ['player', 'coach', 'staff'],
                'default'    => 'player',
            ],
            'dorsal' => [
                'type'     => 'TINYINT',
                'unsigned' => true,
                'null'     => true,
            ],
            'position' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
                'null'       => true,
            ],
            'staff_role' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'created_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'default' => null,
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('team_id', false, false, 'idx_team_id');
        $this->forge->addKey('user_id', false, false, 'idx_user_id');
        $this->forge->addKey('external_id', false, false, 'idx_external_id');
        $this->forge->createTable('event_team_members', true);
    }

    public function down()
    {
        $this->forge->dropTable('event_team_members');
    }
}
