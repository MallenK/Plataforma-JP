<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Añade el estado 'unjustified' (No justificado) al ENUM de asistencia de
 * class_session_players. Se da por supuesto que el alumno no ha asistido y
 * permite descontar un bono con confirmación, dejando registro de la falta.
 */
class AddUnjustifiedToAttendanceEnum extends Migration
{
    private string $withNew = "ENUM('pending','confirmed','declined','present','absent','unjustified') NOT NULL DEFAULT 'pending'";
    private string $withoutNew = "ENUM('pending','confirmed','declined','present','absent') NOT NULL DEFAULT 'pending'";

    public function up(): void
    {
        $this->db->query("ALTER TABLE `class_session_players` MODIFY COLUMN `attendance` {$this->withNew}");
    }

    public function down(): void
    {
        // Revierte cualquier fila 'unjustified' a 'absent' antes de estrechar el ENUM.
        $this->db->query("UPDATE `class_session_players` SET `attendance` = 'absent' WHERE `attendance` = 'unjustified'");
        $this->db->query("ALTER TABLE `class_session_players` MODIFY COLUMN `attendance` {$this->withoutNew}");
    }
}
