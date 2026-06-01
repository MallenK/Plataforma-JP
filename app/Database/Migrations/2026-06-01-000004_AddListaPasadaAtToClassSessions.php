<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddListaPasadaAtToClassSessions extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('class_sessions', [
            'lista_pasada_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'default' => null,
                'after'   => 'post_notes',
            ],
        ]);
        $this->forge->addColumn('class_sessions', [
            'lista_pasada_by' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'default'    => null,
                'after'      => 'lista_pasada_at',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('class_sessions', ['lista_pasada_at', 'lista_pasada_by']);
    }
}
