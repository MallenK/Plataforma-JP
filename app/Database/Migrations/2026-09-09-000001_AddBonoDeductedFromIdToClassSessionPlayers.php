<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Añade `bono_deducted_from_id` a class_session_players: guarda de QUÉ bono
 * (`player_bonos.id`) se descontó la sesión, para poder devolverla al bono
 * correcto aunque la cola FIFO de bonos del alumno haya cambiado después.
 *
 * Antes solo existía `bono_deducted_at` (timestamp) y el descuento era
 * irreversible: cualquier corrección de asistencia dejaba el saldo congelado.
 *
 * Sin FK dura a propósito (player_bonos.id es INT UNSIGNED; el código ya
 * tolera un id nulo o que apunte a un bono borrado, cayendo al bono activo).
 */
class AddBonoDeductedFromIdToClassSessionPlayers extends Migration
{
    public function up(): void
    {
        $this->db->query(
            "ALTER TABLE `class_session_players`
             ADD COLUMN `bono_deducted_from_id` INT UNSIGNED NULL DEFAULT NULL AFTER `bono_deducted_at`"
        );
    }

    public function down(): void
    {
        $this->db->query(
            "ALTER TABLE `class_session_players` DROP COLUMN `bono_deducted_from_id`"
        );
    }
}
