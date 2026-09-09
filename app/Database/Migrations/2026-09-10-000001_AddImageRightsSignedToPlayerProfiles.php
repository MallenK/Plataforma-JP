<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Añade `image_rights_signed` a player_profiles: booleano que indica si el
 * alumno (o su tutor legal) ha firmado la cesión de derechos de imagen.
 *
 * Lo configuran solo admin / superadmin (ficha del alumno + pantalla de
 * edición). Por defecto 0 (sin firmar).
 */
class AddImageRightsSignedToPlayerProfiles extends Migration
{
    public function up(): void
    {
        $this->db->query(
            "ALTER TABLE `player_profiles`
             ADD COLUMN `image_rights_signed` TINYINT(1) NOT NULL DEFAULT 0
             AFTER `medical_notes`"
        );
    }

    public function down(): void
    {
        $this->db->query("ALTER TABLE `player_profiles` DROP COLUMN `image_rights_signed`");
    }
}
