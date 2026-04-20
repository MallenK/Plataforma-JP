<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateBonoTypes extends Migration
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
                'constraint' => 100,
            ],
            'sessions' => [
                'type'     => 'INT',
                'unsigned' => true,
                'default'  => 10,
            ],
            'price' => [
                'type'       => 'DECIMAL',
                'constraint' => '8,2',
                'default'    => '0.00',
            ],
            'validity_days' => [
                'type'     => 'INT',
                'unsigned' => true,
                'default'  => 90,
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
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->createTable('bono_types');
    }

    public function down()
    {
        $this->forge->dropTable('bono_types');
    }
}
