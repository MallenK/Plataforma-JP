<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateExternalParticipants extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
            ],
            'type' => [
                'type'       => 'ENUM',
                'constraint' => ['player', 'coach', 'staff'],
                'default'    => 'player',
            ],
            'position' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
                'null'       => true,
            ],
            'birth_date' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'phone' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'null'       => true,
            ],
            'email' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => true,
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
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('type', false, false, 'idx_type');
        $this->forge->createTable('external_participants');
    }

    public function down()
    {
        $this->forge->dropTable('external_participants');
    }
}
