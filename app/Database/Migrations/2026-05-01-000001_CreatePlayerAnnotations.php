<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePlayerAnnotations extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'        => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'player_id' => ['type' => 'INT'],
            'author_id' => ['type' => 'INT'],
            'type'      => [
                'type'       => 'ENUM',
                'constraint' => ['public', 'internal'],
                'default'    => 'public',
            ],
            'content'    => ['type' => 'TEXT'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('player_id');
        $this->forge->addKey('type');

        $this->forge->createTable('player_annotations', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('player_annotations', true);
    }
}
