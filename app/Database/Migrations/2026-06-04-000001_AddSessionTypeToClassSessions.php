<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddSessionTypeToClassSessions extends Migration
{
    public function up(): void
    {
        $this->db->query(
            "ALTER TABLE `class_sessions` ADD COLUMN `session_type` ENUM('coach','staff') NOT NULL DEFAULT 'coach'"
        );
    }

    public function down(): void
    {
        $this->db->query("ALTER TABLE `class_sessions` DROP COLUMN `session_type`");
    }
}
