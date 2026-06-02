<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDocumentIdToPlayerAnnotations extends Migration
{
    public function up(): void
    {
        // Add document_id only if the column does not exist yet
        $exists = $this->db->query(
            "SELECT COUNT(*) AS cnt FROM information_schema.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME   = 'player_annotations'
               AND COLUMN_NAME  = 'document_id'"
        )->getRow()->cnt ?? 0;

        if (!$exists) {
            $this->db->query(
                "ALTER TABLE `player_annotations`
                 ADD COLUMN `document_id` INT UNSIGNED NULL DEFAULT NULL
                 AFTER `content`"
            );
        }
    }

    public function down(): void
    {
        $this->db->query("ALTER TABLE `player_annotations` DROP COLUMN IF EXISTS `document_id`");
    }
}
