<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateLocations extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'auto_increment' => true,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
            ],
            'description' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'address' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'type' => [
                'type'       => 'ENUM',
                'constraint' => ['pitch', 'gym', 'room', 'office', 'other'],
                'default'    => 'pitch',
            ],
            'capacity' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
            'phone' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'null'       => true,
            ],
            'active' => [
                'type'    => 'TINYINT',
                'default' => 1,
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
        $this->forge->createTable('locations', true);
    }

    public function down()
    {
        $this->forge->dropTable('locations');
    }
}
