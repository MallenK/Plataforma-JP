<?php

namespace App\Models;

use CodeIgniter\Model;

class EventNotificationModel extends Model
{
    protected $table            = 'event_notifications';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $useTimestamps    = false;

    protected $allowedFields = ['event_id', 'member_id', 'sent_at', 'read_at'];

    /**
     * Cuenta las notificaciones no leídas de un usuario.
     */
    public function countUnreadForUser(int $userId): int
    {
        return $this->db->table('event_notifications en')
            ->join('event_team_members etm', 'etm.id = en.member_id')
            ->where('etm.user_id', $userId)
            ->where('etm.member_type', 'user')
            ->whereNull('en.read_at')
            ->countAllResults();
    }

    /**
     * Marca como leídas todas las notificaciones de un usuario para un evento.
     */
    public function markReadForUserEvent(int $userId, int $eventId): void
    {
        $now = date('Y-m-d H:i:s');
        $this->db->query(
            'UPDATE event_notifications en
               JOIN event_team_members etm ON etm.id = en.member_id
              SET en.read_at = ?
             WHERE etm.user_id = ? AND en.event_id = ? AND en.read_at IS NULL',
            [$now, $userId, $eventId]
        );
    }
}
