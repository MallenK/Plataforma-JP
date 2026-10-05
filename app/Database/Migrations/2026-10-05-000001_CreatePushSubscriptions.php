<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Suscripciones Web Push (una fila por navegador/dispositivo instalado).
 * `endpoint_hash` (SHA-256 del endpoint) es la clave única: el endpoint es
 * demasiado largo para indexarlo. Si otro usuario inicia sesión en el mismo
 * navegador, la fila se reasigna (ver PushService::subscribe).
 */
class CreatePushSubscriptions extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            // users.id es INT con signo (ver CLAUDE.md) -> sin unsigned aquí.
            'user_id'       => ['type' => 'INT'],
            'endpoint'      => ['type' => 'TEXT'],
            'endpoint_hash' => ['type' => 'CHAR', 'constraint' => 64],
            'p256dh'        => ['type' => 'VARCHAR', 'constraint' => 255],
            'auth'          => ['type' => 'VARCHAR', 'constraint' => 255],
            'user_agent'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'failures'      => ['type' => 'TINYINT', 'unsigned' => true, 'default' => 0],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'last_used_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('endpoint_hash');
        $this->forge->addKey('user_id');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');

        $this->forge->createTable('push_subscriptions', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('push_subscriptions', true);
    }
}
