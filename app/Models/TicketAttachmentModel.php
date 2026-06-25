<?php

namespace App\Models;

use CodeIgniter\Model;

class TicketAttachmentModel extends Model
{
    protected $table            = 'ticket_attachments';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $useTimestamps    = false;

    protected $allowedFields = [
        'ticket_id', 'reply_id', 'file_path', 'file_name', 'file_size', 'file_mime', 'created_at',
    ];

    public function addAttachment(int $ticketId, ?int $replyId, array $fileData): int
    {
        $result = $this->insert([
            'ticket_id'  => $ticketId,
            'reply_id'   => $replyId,
            'file_path'  => $fileData['path'],
            'file_name'  => $fileData['name'],
            'file_size'  => $fileData['size'],
            'file_mime'  => $fileData['mime'],
            'created_at' => date('Y-m-d H:i:s'),
        ], true);

        return $result === false ? 0 : (int) $result;
    }

    public function getForTicket(int $ticketId): array
    {
        return $this->where('ticket_id', $ticketId)->where('reply_id IS NULL')->findAll();
    }

    public function getForReply(int $replyId): array
    {
        return $this->where('reply_id', $replyId)->findAll();
    }
}
