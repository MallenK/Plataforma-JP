<?php

namespace App\Models;

use CodeIgniter\Model;

class EventConfirmationModel extends Model
{
    protected $table            = 'event_confirmations';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $useTimestamps    = false;

    protected $allowedFields = [
        'event_id', 'member_id', 'status', 'notes', 'responded_at', 'created_at',
    ];

    /**
     * Upsert de confirmación para un miembro.
     */
    public function upsert(int $eventId, int $memberId, string $status, ?string $notes = null): void
    {
        $now      = date('Y-m-d H:i:s');
        $existing = $this->where('event_id', $eventId)->where('member_id', $memberId)->first();

        if ($existing) {
            $this->update($existing['id'], [
                'status'       => $status,
                'notes'        => $notes,
                'responded_at' => $now,
            ]);
        } else {
            $this->insert([
                'event_id'     => $eventId,
                'member_id'    => $memberId,
                'status'       => $status,
                'notes'        => $notes,
                'responded_at' => $now,
                'created_at'   => $now,
            ]);
        }
    }
}
