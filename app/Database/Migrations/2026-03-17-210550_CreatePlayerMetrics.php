<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePlayerMetrics extends Migration
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
            'coach_id' => [
                'type' => 'INT',
                'null' => true,
            ],
            'session_id' => [
                'type' => 'INT',
                'null' => true,
            ],
            'date' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'metrics' => [
                'type' => 'JSON',
                'null' => true,
            ],
            'evaluation' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'notes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'created_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'default' => null,
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('player_id');
        $this->forge->addKey('coach_id');
        $this->forge->addKey('session_id');
        $this->forge->addKey('date', false, false, 'idx_player_metrics_date');
        $this->forge->addForeignKey('player_id', 'users', 'id', '', '');
        $this->forge->addForeignKey('coach_id', 'users', 'id', '', '');
        $this->forge->addForeignKey('session_id', 'sessions', 'id', '', 'SET NULL');
        $this->forge->createTable('player_metrics');
    }

    public function down()
    {
        $this->forge->dropTable('player_metrics');
    }
}
