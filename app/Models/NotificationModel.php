<?php

namespace App\Models;

use CodeIgniter\Model;

class NotificationModel extends Model
{
    protected $table            = 'notifications';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $useTimestamps    = false;

    protected $allowedFields = [
        'sender_id', 'type', 'title', 'body',
        'file_path', 'file_name', 'file_size', 'created_at',
    ];

    /**
     * Inserta una notificación y sus destinatarios en una sola operación.
     * Devuelve el ID de la notificación creada.
     */
    public function createWithRecipients(array $data, array $recipientIds): int
    {
        $data['created_at'] = date('Y-m-d H:i:s');
        $notifId = $this->insert($data, true);

        if ($notifId && !empty($recipientIds)) {
            $rows = array_map(fn($rid) => [
                'notification_id' => $notifId,
                'recipient_id'    => $rid,
                'read_at'         => null,
            ], array_unique($recipientIds));

            $this->db->table('notification_recipients')->insertBatch($rows);
        }

        return (int) $notifId;
    }

    /**
     * Devuelve las notificaciones de un usuario paginadas, con datos del remitente.
     */
    public function getForUser(int $userId, int $limit = 20, int $offset = 0): array
    {
        return $this->db->table('notification_recipients nr')
            ->select('n.*, nr.read_at AS recipient_read_at, nr.id AS recipient_row_id,
                      u.name AS sender_name, u.avatar AS sender_avatar, u.role AS sender_role')
            ->join('notifications n', 'n.id = nr.notification_id')
            ->join('users u', 'u.id = n.sender_id')
            ->where('nr.recipient_id', $userId)
            ->orderBy('n.created_at', 'DESC')
            ->limit($limit, $offset)
            ->get()
            ->getResultArray();
    }

    /**
     * Cuenta las no leídas de un usuario.
     */
    public function countUnread(int $userId): int
    {
        return (int) $this->db->table('notification_recipients')
            ->where('recipient_id', $userId)
            ->where('read_at IS NULL')
            ->countAllResults();
    }

    /**
     * Devuelve las notificaciones enviadas por un usuario (para admins/superadmins).
     */
    public function getSentByUser(int $userId, int $limit = 20, int $offset = 0): array
    {
        return $this->db->table('notifications n')
            ->select('n.*, COUNT(nr.id) AS recipient_count,
                      SUM(nr.read_at IS NOT NULL) AS read_count')
            ->join('notification_recipients nr', 'nr.notification_id = n.id', 'left')
            ->where('n.sender_id', $userId)
            ->groupBy('n.id')
            ->orderBy('n.created_at', 'DESC')
            ->limit($limit, $offset)
            ->get()
            ->getResultArray();
    }

    /**
     * Marca como leída una notificación específica para un usuario.
     */
    public function markRead(int $userId, int $notificationId): void
    {
        $this->db->table('notification_recipients')
            ->where('recipient_id', $userId)
            ->where('notification_id', $notificationId)
            ->where('read_at IS NULL')
            ->update(['read_at' => date('Y-m-d H:i:s')]);
    }

    /**
     * Marca todas las notificaciones de un usuario como leídas.
     */
    public function markAllRead(int $userId): void
    {
        $this->db->table('notification_recipients')
            ->where('recipient_id', $userId)
            ->where('read_at IS NULL')
            ->update(['read_at' => date('Y-m-d H:i:s')]);
    }
}
