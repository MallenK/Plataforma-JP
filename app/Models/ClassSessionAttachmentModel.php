<?php

namespace App\Models;

use CodeIgniter\Model;

class ClassSessionAttachmentModel extends Model
{
    protected $table            = 'class_session_attachments';
    protected $primaryKey       = 'id';
    protected $returnType       = 'array';
    protected $useAutoIncrement = true;
    protected $useTimestamps    = false;

    protected $allowedFields = [
        'session_id', 'player_id', 'file_path', 'file_name', 'file_size',
        'file_mime', 'uploaded_by', 'created_at',
    ];

    public function addAttachment(int $sessionId, ?int $playerId, int $uploadedBy, array $fileData): int
    {
        $result = $this->insert([
            'session_id'  => $sessionId,
            'player_id'   => $playerId,
            'file_path'   => $fileData['path'],
            'file_name'   => $fileData['name'],
            'file_size'   => $fileData['size'],
            'file_mime'   => $fileData['mime'],
            'uploaded_by' => $uploadedBy,
            'created_at'  => date('Y-m-d H:i:s'),
        ], true);

        return $result === false ? 0 : (int) $result;
    }

    /** Adjuntos generales de la sesión (no ligados a un jugador concreto). */
    public function getForSession(int $sessionId): array
    {
        return $this->where('session_id', $sessionId)->where('player_id IS NULL')->orderBy('id', 'DESC')->findAll();
    }

    /** Adjuntos de la observación individual de un jugador en una sesión. */
    public function getForPlayer(int $playerId): array
    {
        return $this->where('player_id', $playerId)->orderBy('id', 'DESC')->findAll();
    }

    /**
     * Todos los adjuntos individuales de un alumno (user_id) a través de
     * todas sus sesiones, más recientes primero — para su ficha de jugador.
     */
    public function getForUserAcrossSessions(int $userId, int $limit = 100): array
    {
        return $this->select('class_session_attachments.*, class_sessions.title AS session_title, class_sessions.session_date AS session_date')
            ->join('class_session_players', 'class_session_players.id = class_session_attachments.player_id')
            ->join('class_sessions', 'class_sessions.id = class_session_attachments.session_id')
            ->where('class_session_players.user_id', $userId)
            ->orderBy('class_sessions.session_date', 'DESC')
            ->orderBy('class_session_attachments.id', 'DESC')
            ->limit($limit)
            ->findAll();
    }
}
