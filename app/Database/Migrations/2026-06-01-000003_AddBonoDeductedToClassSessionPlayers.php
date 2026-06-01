<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddBonoDeductedToClassSessionPlayers extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('class_session_players', [
            'bono_deducted_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'default' => null,
                'after'   => 'student_noted_at',
            ],
            'absence_notes' => [
                'type'       => 'VARCHAR',
                'constraint' => 500,
                'null'       => true,
                'default'    => null,
                'after'      => 'absence_reason',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('class_session_players', ['bono_deducted_at', 'absence_notes']);
    }
}
