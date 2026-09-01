<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Un alumno puede jugar en más de una posición (p. ej. "Extremo" y
 * "Mediapunta"). VARCHAR(50) solo daba para un valor de texto libre
 * corto — origen del desbordamiento visual reportado en la ficha
 * (ver docs/tickets/TICKET-002). Se amplía a TEXT para guardar la
 * lista de posiciones como JSON (ver PlayerProfileModel::encodePositions()).
 *
 * Los valores existentes (texto libre) se siguen leyendo bien:
 * PlayerProfileModel::decodePositions() reconoce tanto el JSON nuevo
 * como el texto libre antiguo (incluido "Extremo/mediapunta").
 */
class WidenPositionOnPlayerProfiles extends Migration
{
    public function up()
    {
        $this->forge->modifyColumn('player_profiles', [
            'position' => [
                'name' => 'position',
                'type' => 'TEXT',
                'null' => true,
            ],
        ]);
    }

    public function down()
    {
        $this->forge->modifyColumn('player_profiles', [
            'position' => [
                'name'       => 'position',
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
            ],
        ]);
    }
}
