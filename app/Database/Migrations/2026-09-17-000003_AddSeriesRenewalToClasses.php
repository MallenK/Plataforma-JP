<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Continuación de clases recurrentes: cuando una serie termina, el admin
 * puede generar una nueva serie enlazada (mismo patrón de días, editable)
 * en vez de crear una clase desde cero. `renewed_from_class_id` y
 * `renewed_to_class_id` enlazan la nueva plantilla con la original en
 * ambos sentidos (la original solo se puede renovar una vez).
 *
 * Sin FK dura a propósito, mismo criterio que bono_deducted_from_id: el
 * código tolera un id que apunte a una plantilla borrada.
 */
class AddSeriesRenewalToClasses extends Migration
{
    public function up(): void
    {
        $this->db->query(
            "ALTER TABLE `classes`
             ADD COLUMN `renewed_from_class_id` INT UNSIGNED NULL DEFAULT NULL AFTER `created_by`,
             ADD COLUMN `renewed_to_class_id` INT UNSIGNED NULL DEFAULT NULL AFTER `renewed_from_class_id`,
             ADD INDEX `idx_classes_renewed_from` (`renewed_from_class_id`)"
        );
    }

    public function down(): void
    {
        $this->db->query(
            "ALTER TABLE `classes`
             DROP INDEX `idx_classes_renewed_from`,
             DROP COLUMN `renewed_from_class_id`,
             DROP COLUMN `renewed_to_class_id`"
        );
    }
}
