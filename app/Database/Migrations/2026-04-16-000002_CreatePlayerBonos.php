<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePlayerBonos extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'player_id' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => false,
            ],
            'bono_type_id' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => false,
            ],
            // Sesiones totales al crear el bono (snapshot del tipo)
            'sessions_total' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => false,
            ],
            // Sesiones restantes (se van descontando)
            'sessions_remaining' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => false,
            ],
            'start_date' => [
                'type' => 'DATE',
                'null' => false,
            ],
            // Fecha de caducidad (start_date + validity_days del tipo)
            'expires_at' => [
                'type' => 'DATE',
                'null' => true,
            ],
            'notes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'created_by' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('player_id');
        $this->forge->addKey('bono_type_id');

        $this->forge->addForeignKey('player_id',     'users',       'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('bono_type_id',  'bono_types',  'id', 'CASCADE', 'CASCADE');

        $this->forge->createTable('player_bonos', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('player_bonos', true);
    }
}
