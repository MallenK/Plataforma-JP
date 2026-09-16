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

    /** Mensajes por bloque en la carga progresiva del chat (TICKET-009). */
    public const PAGE_SIZE     = 30;
    public const MAX_PAGE_SIZE = 100;

    /**
     * Un bloque del historial de una conversación, del más reciente hacia
     * atrás. Paginación por cursor ($beforeId = id del mensaje más antiguo
     * ya pintado): usa el índice de conversation_id (que incluye el id) y no
     * se descoloca si llegan mensajes nuevos mientras se pagina.
     *
     * @return array{messages: array<int, array>, has_more: bool}
     */
    public function getPage(int $convId, int $limit = self::PAGE_SIZE, ?int $beforeId = null): array
    {
        $limit = self::clampPageSize($limit);

        $builder = $this->db->table('messages m')
            ->select('m.*, u.name AS sender_name, u.avatar AS sender_avatar, u.role AS sender_role', false)
            ->join('users u', 'u.id = m.sender_id')
            ->where('m.conversation_id', $convId)
            ->orderBy('m.id', 'DESC')
            ->limit($limit + 1); // +1 para saber si quedan más sin un COUNT(*)

        if ($beforeId !== null) {
            $builder->where('m.id <', $beforeId);
        }

        return self::buildPage($builder->get()->getResultArray(), $limit);
    }

    public static function clampPageSize(int $limit): int
    {
        return max(1, min($limit, self::MAX_PAGE_SIZE));
    }

    /**
     * Recibe hasta $limit + 1 filas en orden descendente (más reciente
     * primero) y devuelve el bloque en orden cronológico + si quedan más.
     */
    public static function buildPage(array $rowsNewestFirst, int $limit): array
    {
        return [
            'messages' => array_reverse(array_slice($rowsNewestFirst, 0, $limit)),
            'has_more' => count($rowsNewestFirst) > $limit,
        ];
    }

    /**
     * IDs de mis mensajes que el otro ya ha leído, a partir de $fromId.
     * El chat solo pregunta por los que aún pinta como "enviado", así el
     * sondeo cada 3 s no devuelve el historial entero de IDs.
     */
    public function getReadIdsFrom(int $convId, int $senderId, int $fromId = 0): array
    {
        $rows = $this->db->table('messages')
            ->select('id')
            ->where('conversation_id', $convId)
            ->where('sender_id', $senderId)
            ->where('id >=', $fromId)
            ->where('read_at IS NOT NULL', null, false)
            ->get()->getResultArray();

        return array_map('intval', array_column($rows, 'id'));
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
