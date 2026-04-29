<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateClassSessions extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'class_id' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
            'title' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
            ],
            'session_date' => [
                'type' => 'DATE',
            ],
            'start_time' => [
                'type' => 'TIME',
            ],
            'end_time' => [
                'type' => 'TIME',
            ],
            'location_id' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
            'location_custom' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'focus' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'pre_notes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'post_notes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['scheduled', 'completed', 'cancelled'],
                'default'    => 'scheduled',
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
        $this->forge->addKey('session_date', false, false, 'idx_cs_date');
        $this->forge->addKey('class_id', false, false, 'idx_cs_class');
        $this->forge->addKey('status', false, false, 'idx_cs_status');
        $this->forge->createTable('class_sessions', true);
    }

    public function down()
    {
        $this->forge->dropTable('class_sessions');
    }
}
