<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Trazabilidad de un ticket: quién cambió el estado / la prioridad, quién lo
 * asignó, quién respondió… Se pinta como línea de tiempo en el detalle.
 */
class TicketEventModel extends Model
{
    protected $table            = 'ticket_events';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $useTimestamps    = false;

    protected $allowedFields = [
        'ticket_id', 'actor_id', 'event_type', 'from_value', 'to_value', 'created_at',
    ];

    public const TYPES = [
        'created', 'status_changed', 'priority_changed', 'assigned',
        'unassigned', 'reply', 'internal_note', 'reopened', 'closed',
        'scope_changed', 'archived', 'unarchived',
    ];

    public function log(int $ticketId, ?int $actorId, string $type, ?string $from = null, ?string $to = null): void
    {
        if (!in_array($type, self::TYPES, true)) {
            return;
        }

        $this->insert([
            'ticket_id'  => $ticketId,
            'actor_id'   => $actorId ?: null,
            'event_type' => $type,
            'from_value' => $from !== null ? mb_substr($from, 0, 191) : null,
            'to_value'   => $to   !== null ? mb_substr($to, 0, 191)   : null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }

    /** @return array<int,array<string,mixed>> */
    public function getForTicket(int $ticketId): array
    {
        return $this->db->table('ticket_events e')
            ->select('e.*, u.name AS actor_name, u.role AS actor_role')
            ->join('users u', 'u.id = e.actor_id', 'left')
            ->where('e.ticket_id', $ticketId)
            ->orderBy('e.created_at', 'ASC')
            ->orderBy('e.id', 'ASC')
            ->get()->getResultArray();
    }
}
