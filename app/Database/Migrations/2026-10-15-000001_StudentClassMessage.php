<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Mensaje libre del alumno sobre una clase (llegaré tarde, molestias, dudas).
 * Distinto del aviso de ausencia (`student_note`): no cambia la asistencia ni
 * el bono. Lo ven el entrenador de la clase y los admin. Idempotente.
 */
class StudentClassMessage extends Migration
{
    public function up(): void
    {
        if (!$this->db->tableExists('class_session_players')) {
            return;
        }
        if (!$this->db->fieldExists('student_message', 'class_session_players')) {
            $this->db->query("ALTER TABLE `class_session_players` ADD COLUMN `student_message` VARCHAR(600) NULL DEFAULT NULL AFTER `student_noted_at`");
        }
        if (!$this->db->fieldExists('student_message_at', 'class_session_players')) {
            $this->db->query("ALTER TABLE `class_session_players` ADD COLUMN `student_message_at` DATETIME NULL DEFAULT NULL AFTER `student_message`");
        }
    }

    public function down(): void
    {
        foreach (['student_message_at', 'student_message'] as $col) {
            if ($this->db->fieldExists($col, 'class_session_players')) {
                $this->db->query("ALTER TABLE `class_session_players` DROP COLUMN `{$col}`");
            }
        }
    }
}
