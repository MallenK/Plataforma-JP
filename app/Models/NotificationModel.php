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

    // source_type/source_id = a dónde lleva la notificación al pulsarla
    // (campanita y /notificaciones). Sin ellos en allowedFields CodeIgniter
    // los descartaba en silencio y nunca se guardaban (TICKET-010).
    protected $allowedFields = [
        'sender_id', 'type', 'title', 'body',
        'file_path', 'file_name', 'file_size', 'created_at',
        'source_type', 'source_id',
    ];

    public const SOURCE_TICKET       = 'ticket';
    public const SOURCE_CONVERSATION = 'conversation';
    public const SOURCE_CLASS        = 'class';

    /**
     * Inserta una notificación y sus destinatarios en una sola operación.
     * Devuelve el ID de la notificación creada.
     */
    public function createWithRecipients(array $data, array $recipientIds): int
    {
        $data['created_at'] = date('Y-m-d H:i:s');

        if (isset($data['source_type'])) {
            $data = self::prepareSource($data, $this->db->fieldExists('source_type', $this->table));
        }

        $result = $this->insert($data, true);

        // `false` significa error de insert; 0 puede ocurrir en TiDB con AUTO_INCREMENT roto.
        // En ambos casos no insertamos destinatarios (no hay notificación válida).
        if ($result === false) {
            return 0;
        }

        $notifId = (int) $result;

        if (!empty($recipientIds)) {
            $rows = array_map(fn($rid) => [
                'notification_id' => $notifId,
                'recipient_id'    => $rid,
                'read_at'         => null,
            ], array_unique($recipientIds));

            $this->db->table('notification_recipients')->insertBatch($rows);
        }

        return $notifId;
    }

    /**
     * Enlace al origen de una notificación (mismo destino que la campanita
     * en components/navbar.php), o null si no tiene.
     *
     * @return array{path: string, label: string, icon: string}|null
     */
    public static function sourceLink(array $notification): ?array
    {
        $id = (int) ($notification['source_id'] ?? 0);
        if ($id <= 0) {
            return null;
        }

        return match ($notification['source_type'] ?? null) {
            self::SOURCE_TICKET       => ['path' => 'tickets/' . $id, 'label' => 'Ver ticket', 'icon' => 'bi-life-preserver'],
            self::SOURCE_CONVERSATION => ['path' => 'mensajes?conv=' . $id, 'label' => 'Ir a la conversación', 'icon' => 'bi-chat-dots'],
            self::SOURCE_CLASS        => ['path' => 'clases/' . $id, 'label' => 'Ver clase', 'icon' => 'bi-calendar3'],
            default                   => null,
        };
    }

    /**
     * Normaliza el origen de la notificación. Si la BD aún no tiene las
     * columnas (entorno sin la migración 2026-09-16-000001), se quita el
     * origen: la notificación se envía igual, solo que sin enlace, en vez de
     * fallar el INSERT y perder el aviso.
     */
    public static function prepareSource(array $data, bool $columnsExist): array
    {
        $valid = in_array($data['source_type'] ?? null, [self::SOURCE_TICKET, self::SOURCE_CONVERSATION, self::SOURCE_CLASS], true)
            && (int) ($data['source_id'] ?? 0) > 0;

        if (!$columnsExist || !$valid) {
            if ($valid) {
                log_message('warning', '[NotificationModel] notifications sin columnas source_type/source_id: se guarda sin enlace. Ejecutar la migración 2026-09-16-000001.');
            }
            unset($data['source_type'], $data['source_id']);
            return $data;
        }

        $data['source_id'] = (int) $data['source_id'];
        return $data;
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
     * Cuenta las notificaciones creadas por un remitente en los últimos
     * $minutes minutos (anti-spam / rate-limit del envío).
     */
    public function countRecentBySender(int $senderId, int $minutes): int
    {
        $since = date('Y-m-d H:i:s', time() - $minutes * 60);

        return (int) $this->db->table('notifications')
            ->where('sender_id', $senderId)
            ->where('created_at >=', $since)
            ->countAllResults();
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
