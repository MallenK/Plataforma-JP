<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class FixClassesAutoIncrement extends Migration
{
    public function up()
    {
        // Resetting AUTO_INCREMENT so it continues after the existing MAX(id).
        // MySQL ignores values <= current max and uses MAX(id)+1 automatically.
        $this->db->query('ALTER TABLE `classes` AUTO_INCREMENT = 1');
        $this->db->query('ALTER TABLE `class_sessions` AUTO_INCREMENT = 1');
    }

    public function down()
    {
        // Nothing to reverse — AUTO_INCREMENT reset is safe and idempotent.
    }
}
