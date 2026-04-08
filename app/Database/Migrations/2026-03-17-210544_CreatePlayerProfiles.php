<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Crea la tabla player_profiles.
 *
 * Almacena los datos deportivos/médicos de cada alumno.
 * Relación: un alumno → un perfil (1:1 con users.id).
 *
 * Nota: el archivo original CreatePlayers.php estaba vacío y era ambiguo.
 * Esta migration lo reemplaza con la estructura real que usa PlayerProfileModel.
 */
class CreatePlayerProfiles extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            // FK al usuario propietario del perfil
            'player_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'birth_date' => [
                'type' => 'DATE',
                'null' => true,
            ],
            // Altura en centímetros
            'height' => [
                'type'       => 'DECIMAL',
                'constraint' => '5,2',
                'null'       => true,
            ],
            // Peso en kilogramos
            'weight' => [
                'type'       => 'DECIMAL',
                'constraint' => '5,2',
                'null'       => true,
            ],
            'position' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
                'null'       => true,
            ],
            'level' => [
                'type'       => 'ENUM',
                'constraint' => ['beginner', 'intermediate', 'advanced'],
                'default'    => 'beginner',
            ],
            'medical_notes' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addPrimaryKey('id');

        // Un alumno solo puede tener un perfil
        $this->forge->addUniqueKey('player_id');

        // FK: si se borra el usuario, se borra su perfil
        $this->forge->addForeignKey('player_id', 'users', 'id', 'CASCADE', 'CASCADE');

        $this->forge->createTable('player_profiles');
    }

    public function down()
    {
        $this->forge->dropTable('player_profiles');
    }
}
