<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Preferencias de notificación por usuario y categoría (mensajes, clases, bonos,
 * tickets, avisos). Dos canales independientes: `in_app` (centro de notificaciones /
 * campanita) y `push` (aviso en el dispositivo). Sin fila = todo activado.
 */
class CreateNotificationPreferences extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            // users.id es INT con signo (ver CLAUDE.md) -> sin unsigned aquí.
            'user_id'    => ['type' => 'INT'],
            'category'   => ['type' => 'VARCHAR', 'constraint' => 20],
            'in_app'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'push'       => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['user_id', 'category']);
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE');

        $this->forge->createTable('notification_preferences', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('notification_preferences', true);
    }
}
