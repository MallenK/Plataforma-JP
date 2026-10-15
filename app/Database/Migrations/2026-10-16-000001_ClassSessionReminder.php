<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Recordatorio de clase (25 h antes): marca de "ya avisada" por sesión, para que
 * cada clase se recuerde una sola vez aunque el disparador se ejecute muchas veces.
 * Idempotente.
 */
class ClassSessionReminder extends Migration
{
    public function up(): void
    {
        if (!$this->db->tableExists('class_sessions')) {
            return;
        }
        if (!$this->db->fieldExists('reminder_sent_at', 'class_sessions')) {
            $this->db->query("ALTER TABLE `class_sessions` ADD COLUMN `reminder_sent_at` DATETIME NULL DEFAULT NULL");
        }
    }

    public function down(): void
    {
        if ($this->db->fieldExists('reminder_sent_at', 'class_sessions')) {
            $this->db->query("ALTER TABLE `class_sessions` DROP COLUMN `reminder_sent_at`");
        }
    }
}
