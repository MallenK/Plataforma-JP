<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddAbsenceFieldsToClassSessionPlayers extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('class_session_players', [
            'absence_reason' => [
                'type'    => 'TEXT',
                'null'    => true,
                'default' => null,
                'after'   => 'attendance',
            ],
            'student_note' => [
                'type'    => 'TEXT',
                'null'    => true,
                'default' => null,
                'after'   => 'absence_reason',
            ],
            'student_noted_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'default' => null,
                'after'   => 'student_note',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('class_session_players', ['absence_reason', 'student_note', 'student_noted_at']);
    }
}
