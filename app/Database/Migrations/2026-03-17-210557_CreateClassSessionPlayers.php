<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateClassSessionPlayers extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'session_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'user_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'coach_id' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
            'attendance' => [
                'type'       => 'ENUM',
                'constraint' => ['pending', 'confirmed', 'declined', 'present', 'absent'],
                'default'    => 'pending',
            ],
            'responded_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'pre_obs' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'post_obs' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'created_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'default' => null,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['session_id', 'user_id'], 'uq_csp');
        $this->forge->addKey('user_id', false, false, 'idx_csp_user');
        $this->forge->addKey('coach_id', false, false, 'idx_csp_coach');
        $this->forge->createTable('class_session_players', true);
    }

    public function down()
    {
        $this->forge->dropTable('class_session_players');
    }
}
