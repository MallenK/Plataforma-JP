<?php

namespace App\Models;

use CodeIgniter\Model;

class TicketReplyModel extends Model
{
    protected $table            = 'ticket_replies';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $useTimestamps    = false;

    protected $allowedFields = [
        'ticket_id', 'user_id', 'body', 'is_internal', 'created_at',
    ];

    public function createReply(int $ticketId, int $userId, string $body, bool $isInternal = false): int
    {
        $result = $this->insert([
            'ticket_id'   => $ticketId,
            'user_id'     => $userId,
            'body'        => $body,
            'is_internal' => $isInternal ? 1 : 0,
            'created_at'  => date('Y-m-d H:i:s'),
        ], true);

        return $result === false ? 0 : (int) $result;
    }

    /**
     * @param bool $includeInternal false = oculta las notas internas (vista del solicitante)
     */
    public function getForTicket(int $ticketId, bool $includeInternal = true): array
    {
        $builder = $this->db->table('ticket_replies tr')
            ->select('tr.*, u.name AS user_name, u.avatar AS user_avatar, u.role AS user_role')
            ->join('users u', 'u.id = tr.user_id')
            ->where('tr.ticket_id', $ticketId);

        if (!$includeInternal) {
            $builder->where('tr.is_internal', 0);
        }

        return $builder->orderBy('tr.created_at', 'ASC')
            ->get()->getResultArray();
    }

    /** Nº de respuestas públicas (para el contador visible al solicitante). */
    public function countPublic(int $ticketId): int
    {
        return (int) $this->db->table('ticket_replies')
            ->where('ticket_id', $ticketId)
            ->where('is_internal', 0)
            ->countAllResults();
    }
}
