<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePlayerProfiles extends Migration
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
            ],
            'birth_date' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'height' => [
                'type' => 'INT',
                'null' => true,
            ],
            'weight' => [
                'type' => 'INT',
                'null' => true,
            ],
            'position' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
            ],
            'level' => [
                'type'       => 'ENUM',
                'constraint' => ['beginner', 'intermediate', 'advanced'],
                'null'       => true,
            ],
            'medical_notes' => [
                'type' => 'TEXT',
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
        $this->forge->addUniqueKey('player_id');
        $this->forge->addForeignKey('player_id', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->createTable('player_profiles');
    }

    public function down()
    {
        $this->forge->dropTable('player_profiles');
    }
}
