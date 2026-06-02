<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddClassFormatToClasses extends Migration
{
    public function up(): void
    {
        $this->db->query(
            "ALTER TABLE `classes` ADD COLUMN `class_format` ENUM('individual','pareja') NOT NULL DEFAULT 'individual'"
        );
        $this->db->query(
            "ALTER TABLE `class_sessions` ADD COLUMN `class_format` ENUM('individual','pareja') NOT NULL DEFAULT 'individual'"
        );
    }

    public function down(): void
    {
        $this->db->query("ALTER TABLE `classes` DROP COLUMN `class_format`");
        $this->db->query("ALTER TABLE `class_sessions` DROP COLUMN `class_format`");
    }
}
