<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateClasses extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'title' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
            ],
            'description' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'type' => [
                'type'       => 'ENUM',
                'constraint' => ['single', 'recurring'],
                'default'    => 'single',
            ],
            'recurrence_days' => [
                'type'       => 'VARCHAR',
                'constraint' => 20,
                'null'       => true,
            ],
            'recurrence_start' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'recurrence_end' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'recurrence_time_start' => [
                'type' => 'TIME',
                'null' => true,
            ],
            'recurrence_time_end' => [
                'type' => 'TIME',
                'null' => true,
            ],
            'default_location_id' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
            'default_location_custom' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'default_focus' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'created_by' => [
                'type'     => 'INT',
                'unsigned' => true,
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
        $this->forge->addKey('created_by', false, false, 'idx_classes_by');
        $this->forge->createTable('classes');
    }

    public function down()
    {
        $this->forge->dropTable('classes');
    }
}
