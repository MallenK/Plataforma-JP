<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class MakePlayerBonoPlayerIdNullable extends Migration
{
    public function up(): void
    {
        $this->db->query('ALTER TABLE player_bonos MODIFY player_id INT UNSIGNED NULL DEFAULT NULL');
    }

    public function down(): void
    {
        $this->db->query('DELETE FROM player_bonos WHERE player_id IS NULL');
        $this->db->query('ALTER TABLE player_bonos MODIFY player_id INT UNSIGNED NOT NULL');
    }
}
