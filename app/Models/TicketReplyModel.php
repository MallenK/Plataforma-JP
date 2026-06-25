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
        'ticket_id', 'user_id', 'body', 'created_at',
    ];

    public function createReply(int $ticketId, int $userId, string $body): int
    {
        $result = $this->insert([
            'ticket_id'  => $ticketId,
            'user_id'    => $userId,
            'body'       => $body,
            'created_at' => date('Y-m-d H:i:s'),
        ], true);

        return $result === false ? 0 : (int) $result;
    }

    public function getForTicket(int $ticketId): array
    {
        return $this->db->table('ticket_replies tr')
            ->select('tr.*, u.name AS user_name, u.avatar AS user_avatar, u.role AS user_role')
            ->join('users u', 'u.id = tr.user_id')
            ->where('tr.ticket_id', $ticketId)
            ->orderBy('tr.created_at', 'ASC')
            ->get()->getResultArray();
    }
}
