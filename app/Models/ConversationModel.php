<?php

namespace App\Models;

use CodeIgniter\Model;

class ConversationModel extends Model
{
    protected $table            = 'conversations';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = false;
    protected $useTimestamps    = false;

    protected $allowedFields = ['id', 'user1_id', 'user2_id', 'created_at', 'last_message_at'];

    /**
     * Devuelve o crea la conversación entre dos usuarios.
     * user1_id siempre es el menor de los dos IDs para unicidad.
     *
     * NOTE: La tabla conversations no tiene AUTO_INCREMENT en TiDB (datos importados
     * sin secuencia). Generamos el id explícitamente para evitar Duplicate PRIMARY.
     */
    public function findOrCreate(int $userA, int $userB): array
    {
        if ($userA <= 0 || $userB <= 0 || $userA === $userB) {
            return [];
        }

        [$u1, $u2] = $userA < $userB ? [$userA, $userB] : [$userB, $userA];

        $conv = $this->where('user1_id', $u1)->where('user2_id', $u2)->first();
        if ($conv) {
            return $conv;
        }

        // Generate explicit ID: TiDB ignores AUTO_INCREMENT = N on this table.
        $maxRow = $this->db->query("SELECT COALESCE(MAX(id), -1) + 1 AS next_id FROM conversations")->getRow();
        $nextId = (int) $maxRow->next_id;

        $now = date('Y-m-d H:i:s');
        try {
            $this->insert([
                'id'         => $nextId,
                'user1_id'   => $u1,
                'user2_id'   => $u2,
                'created_at' => $now,
            ]);
        } catch (\Throwable $e) {
            // Race condition (another request used the same id or created same pair) → retry read.
            $conv = $this->where('user1_id', $u1)->where('user2_id', $u2)->first();
            return $conv ?: [];
        }

        return $this->find($nextId) ?: [];
    }

    /**
     * Lista las conversaciones de un usuario con info del otro participante
     * y el último mensaje.
     */
    public function getForUser(int $userId): array
    {
        $rows = $this->db->query("
            SELECT
                c.id,
                c.last_message_at,
                IF(c.user1_id = ?, c.user2_id, c.user1_id) AS other_user_id,
                u.name     AS other_name,
                u.avatar   AS other_avatar,
                u.role     AS other_role,
                m.body         AS last_body,
                m.file_name    AS last_file,
                m.sender_id    AS last_sender_id,
                (SELECT COUNT(*) FROM messages ms
                 WHERE ms.conversation_id = c.id
                   AND ms.sender_id != ?
                   AND ms.read_at IS NULL) AS unread_count
            FROM conversations c
            JOIN users u ON u.id = IF(c.user1_id = ?, c.user2_id, c.user1_id)
            LEFT JOIN (
                SELECT conversation_id, MAX(id) AS last_id
                FROM messages
                GROUP BY conversation_id
            ) lm ON lm.conversation_id = c.id
            LEFT JOIN messages m ON m.id = lm.last_id
            WHERE c.user1_id = ? OR c.user2_id = ?
            ORDER BY COALESCE(c.last_message_at, c.created_at) DESC
        ", [$userId, $userId, $userId, $userId, $userId]);

        return $rows->getResultArray();
    }

    /**
     * Actualiza el timestamp del último mensaje.
     */
    public function touchLastMessage(int $convId): void
    {
        $this->update($convId, ['last_message_at' => date('Y-m-d H:i:s')]);
    }
}
