<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddCategoryTeamLeagueToPlayerProfiles extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('player_profiles', [
            'category' => [
                'type'       => 'ENUM',
                'constraint' => ['benjamin', 'prebenjamin', 'alevin', 'infantil', 'cadete', 'juvenil', 'junior', 'senior', 'veterano'],
                'null'       => true,
                'default'    => null,
                'after'      => 'level',
            ],
            'team' => [
                'type'       => 'VARCHAR',
                'constraint' => 120,
                'null'       => true,
                'default'    => null,
                'after'      => 'category',
            ],
            'league' => [
                'type'       => 'VARCHAR',
                'constraint' => 120,
                'null'       => true,
                'default'    => null,
                'after'      => 'team',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('player_profiles', ['category', 'team', 'league']);
    }
}
