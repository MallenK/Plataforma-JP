<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateSessions extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'auto_increment' => true,
            ],
            'title' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => true,
            ],
            'description' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'location_id' => [
                'type' => 'INT',
                'null' => true,
            ],
            'coach_id' => [
                'type' => 'INT',
                'null' => true,
            ],
            'start_datetime' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'end_datetime' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['scheduled', 'completed', 'cancelled'],
                'default'    => 'scheduled',
                'null'       => true,
            ],
            'created_by' => [
                'type' => 'INT',
                'null' => true,
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
        $this->forge->addKey('location_id');
        $this->forge->addKey('coach_id');
        $this->forge->addKey('created_by');
        $this->forge->addKey('start_datetime', false, false, 'idx_sessions_date');
        $this->forge->addForeignKey('location_id', 'locations', 'id', '', '');
        $this->forge->addForeignKey('coach_id', 'users', 'id', '', '');
        $this->forge->addForeignKey('created_by', 'users', 'id', '', '');
        $this->forge->createTable('sessions', true);
    }

    public function down()
    {
        $this->forge->dropTable('sessions');
    }
}
