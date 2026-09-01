<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Registro de eventos de autenticación. Sirve para dos cosas:
 *  - Decisiones de rate-limit / bloqueo (contar fallos por email o IP
 *    dentro de una ventana) — ver App\Services\AuthGuardService.
 *  - Auditoría visible en Configuración → Seguridad.
 */
class CreateAuthEvents extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'event_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 40,
            ],
            'identifier' => [
                'type'       => 'VARCHAR',
                'constraint' => 191,
                'null'       => true,
            ],
            'user_id' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
            'ip_address' => [
                'type'       => 'VARCHAR',
                'constraint' => 45,
            ],
            'user_agent' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
            ],
            'meta' => [
                'type' => 'JSON',
                'null' => true,
            ],
            'created_at' => [
                'type' => 'DATETIME',
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey(['identifier', 'created_at'], false, false, 'idx_ident_time');
        $this->forge->addKey(['ip_address', 'created_at'], false, false, 'idx_ip_time');
        $this->forge->addKey(['event_type', 'created_at'], false, false, 'idx_type_time');
        $this->forge->createTable('auth_events', true);
    }

    public function down()
    {
        $this->forge->dropTable('auth_events');
    }
}
