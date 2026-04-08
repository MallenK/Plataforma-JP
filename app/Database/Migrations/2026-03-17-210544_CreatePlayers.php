<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migración reservada — reemplazada por CreatePlayerProfiles.
 *
 * El nombre original 'CreatePlayers' era ambiguo. La tabla real
 * de datos deportivos de alumnos se llama 'player_profiles' y
 * tiene su propia migración (2026-03-17-210544_CreatePlayerProfiles.php).
 *
 * Esta migración se conserva vacía para no romper el histórico de migraciones.
 */
class CreatePlayers extends Migration
{
    public function up()
    {
        // Reemplazada por CreatePlayerProfiles
    }

    public function down()
    {
        // Reemplazada por CreatePlayerProfiles
    }
}
