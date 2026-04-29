<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePlayerPlans extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'auto_increment' => true,
            ],
            'player_id' => [
                'type' => 'INT',
                'null' => true,
            ],
            'plan_id' => [
                'type' => 'INT',
                'null' => true,
            ],
            'sessions_remaining' => [
                'type' => 'INT',
                'null' => true,
            ],
            'start_date' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'end_date' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['active', 'expired', 'used'],
                'null'       => true,
            ],
            'created_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'default' => null,
            ],
            'updated_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'default' => null,
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['player_id', 'plan_id', 'status']);
        $this->forge->addKey('plan_id');
        $this->forge->addForeignKey('player_id', 'users', 'id', '', '');
        $this->forge->addForeignKey('plan_id', 'plans', 'id', '', '');
        $this->forge->createTable('player_plans', true);
    }

    public function down()
    {
        $this->forge->dropTable('player_plans');
    }
}
