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
     * Todos los adjuntos visibles para un alumno (user_id) a través de sus
     * sesiones: los generales de cada sesión en la que participó + los
     * individuales ligados a su propia observación. Para su ficha de
     * jugador — más recientes primero, listos para agrupar por sesión.
     */
    public function getForUserAcrossSessions(int $userId, int $limit = 200): array
    {
        $builder = $this->db->table('class_session_attachments csa');
        $builder->select("csa.*, cs.id AS session_id, cs.title AS session_title, cs.session_date,
                           IF(csa.player_id IS NULL, 'general', 'individual') AS scope")
            ->join('class_sessions cs', 'cs.id = csa.session_id')
            ->join('class_session_players csp', 'csp.session_id = csa.session_id')
            ->where('csp.user_id', $userId)
            ->where('(csa.player_id IS NULL OR csa.player_id = csp.id)', null, false)
            ->orderBy('cs.session_date', 'DESC')
            ->orderBy('csa.id', 'DESC')
            ->limit($limit);

        return $builder->get()->getResultArray();
    }
}
