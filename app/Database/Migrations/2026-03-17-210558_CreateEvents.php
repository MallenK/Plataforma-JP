<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateEvents extends Migration
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
            'accommodation_info' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'schedule_info' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'cancelled' => [
                'type'    => 'TINYINT',
                'default' => 0,
            ],
            'equipment_notes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'concentration_place' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'concentration_time' => [
                'type' => 'TIME',
                'null' => true,
            ],
            'location' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'category' => [
                'type'       => 'VARCHAR',
                'constraint' => 80,
                'null'       => true,
            ],
            'type' => [
                'type'       => 'ENUM',
                'constraint' => ['torneo', 'campus'],
                'default'    => 'torneo',
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 200,
            ],
            'location_id' => [
                'type' => 'INT',
                'null' => true,
            ],
            'start_date' => [
                'type' => 'DATE',
            ],
            'end_date' => [
                'type' => 'DATE',
            ],
            'created_by' => [
                'type' => 'INT',
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
        $this->forge->addKey('location_id');
        $this->forge->addKey('created_by', false, false, 'events_ibfk_2');
        $this->forge->addKey('type', false, false, 'idx_type');
        $this->forge->addKey('start_date', false, false, 'idx_start_date');
        $this->forge->addKey('cancelled', false, false, 'idx_cancelled');
        $this->forge->addForeignKey('location_id', 'locations', 'id', '', '');
        $this->forge->addForeignKey('created_by', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('events', true);
    }

    public function down()
    {
        $this->forge->dropTable('events');
    }
}
