<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateEventResults extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'event_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'team_id' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
            'result_text' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
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
        $this->forge->addKey('event_id', false, false, 'idx_event_id');
        $this->forge->createTable('event_results', true);
    }

    public function down()
    {
        $this->forge->dropTable('event_results');
    }
}
