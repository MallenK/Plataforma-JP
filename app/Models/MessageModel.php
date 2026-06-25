<?php

namespace App\Models;

use CodeIgniter\Model;

class MessageModel extends Model
{
    protected $table            = 'messages';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $useTimestamps    = false;

    protected $allowedFields = [
        'conversation_id', 'sender_id', 'body',
        'file_path', 'file_name', 'file_size', 'file_mime',
        'read_at', 'created_at',
    ];

    /**
     * Devuelve los mensajes de una conversación ordenados cronológicamente.
     * Permite paginar hacia atrás con $beforeId.
     */
    public function getForConversation(int $convId, int $limit = 50, ?int $beforeId = null): array
    {
        $builder = $this->db->table('messages m')
            ->select('m.*, u.name AS sender_name, u.avatar AS sender_avatar, u.role AS sender_role', false)
            ->join('users u', 'u.id = m.sender_id')
            ->where('m.conversation_id', $convId)
            ->orderBy('m.created_at', 'DESC')
            ->limit($limit);

        if ($beforeId !== null) {
            $builder->where('m.id <', $beforeId);
        }

        $rows = $builder->get()->getResultArray();
        return array_reverse($rows);
    }

    /**
     * Marca como leídos todos los mensajes de una conversación recibidos por $userId.
     */
    public function markReadInConversation(int $convId, int $userId): void
    {
        $this->db->table('messages')
            ->where('conversation_id', $convId)
            ->where('sender_id !=', $userId)
            ->where('read_at', null)
            ->update(['read_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * Cuenta los mensajes no leídos del usuario en todas sus conversaciones.
     */
    public function countUnreadForUser(int $userId): int
    {
        return (int) $this->db->query("
            SELECT COUNT(*) AS cnt
            FROM messages m
            JOIN conversations c ON c.id = m.conversation_id
            WHERE (c.user1_id = ? OR c.user2_id = ?)
              AND m.sender_id != ?
              AND m.read_at IS NULL
        ", [$userId, $userId, $userId])->getRow()->cnt;
    }
}
