<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class FixAutoIncrementOnChatTables extends Migration
{
    public function up(): void
    {
        foreach (['conversations', 'messages'] as $table) {
            $row  = $this->db->query("SELECT COALESCE(MAX(id), 0) AS m FROM `{$table}`")->getRow();
            // Jump past TiDB's default batch (30000) to avoid in-memory cache collisions
            $next = ((int) ceil(((int) $row->m + 1) / 30000) + 1) * 30000 + 1;
            $this->db->query("ALTER TABLE `{$table}` AUTO_INCREMENT = {$next}");
        }
    }

    public function down(): void
    {
        // AUTO_INCREMENT resets cannot be meaningfully reversed
    }
}
