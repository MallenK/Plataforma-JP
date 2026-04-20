<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateObservations extends Migration
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
                'null' => true,
            ],
            'session_id' => [
                'type' => 'INT',
                'null' => true,
            ],
            'author_id' => [
                'type' => 'INT',
                'null' => true,
            ],
            'type' => [
                'type'       => 'ENUM',
                'constraint' => ['coach', 'parent'],
                'null'       => true,
            ],
            'visibility' => [
                'type'       => 'ENUM',
                'constraint' => ['private', 'shared'],
                'null'       => true,
            ],
            'content' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'created_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'default' => null,
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('player_id');
        $this->forge->addKey('session_id');
        $this->forge->addKey('author_id');
        $this->forge->addForeignKey('player_id', 'users', 'id', '', '');
        $this->forge->addForeignKey('session_id', 'sessions', 'id', '', 'SET NULL');
        $this->forge->addForeignKey('author_id', 'users', 'id', '', '');
        $this->forge->createTable('observations');
    }

    public function down()
    {
        $this->forge->dropTable('observations');
    }
}
