<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * TICKET: adjuntos (fotos/vídeos/documentos) en las observaciones de
 * una sesión de clase. `player_id` NULL = adjunto general de la sesión;
 * con valor = adjunto ligado a la observación individual de ese jugador
 * (FK a class_session_players.id, no a users.id).
 */
class CreateClassSessionAttachments extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'          => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'session_id'  => ['type' => 'INT', 'unsigned' => true],
            'player_id'   => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'file_path'   => ['type' => 'VARCHAR', 'constraint' => 500],
            'file_name'   => ['type' => 'VARCHAR', 'constraint' => 255],
            'file_size'   => ['type' => 'INT', 'unsigned' => true],
            'file_mime'   => ['type' => 'VARCHAR', 'constraint' => 100],
            // users.id es INT con signo (ver CLAUDE.md) -> sin unsigned aquí.
            'uploaded_by' => ['type' => 'INT', 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('session_id');
        $this->forge->addKey('player_id');

        $this->forge->addForeignKey('session_id', 'class_sessions', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('player_id', 'class_session_players', 'id', 'CASCADE', 'SET NULL');

        $this->forge->createTable('class_session_attachments', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('class_session_attachments', true);
    }
}
