<?php

namespace App\Models;

use CodeIgniter\Model;

class PlayerAnnotationModel extends Model
{
    protected $table      = 'player_annotations';
    protected $primaryKey = 'id';
    protected $returnType = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'player_id',
        'author_id',
        'type',
        'content',
        'document_id',
    ];

    /**
     * Devuelve las anotaciones de un jugador con el nombre del autor.
     * $types: array de tipos a incluir, p.ej. ['public'] o ['public','internal']
     */
    public function getForPlayer(int $playerId, array $types = ['public', 'internal']): array
    {
        $placeholders = implode(',', array_fill(0, count($types), '?'));

        return $this->db->query(
            "SELECT pa.*, u.name AS author_name, u.role AS author_role,
                    d.name_original AS doc_name, d.extension AS doc_ext,
                    d.size_bytes AS doc_size, d.mime_type AS doc_mime
             FROM player_annotations pa
             JOIN users u ON u.id = pa.author_id
             LEFT JOIN documents d ON d.id = pa.document_id AND d.deleted_at IS NULL
             WHERE pa.player_id = ?
               AND pa.type IN ({$placeholders})
             ORDER BY pa.created_at DESC",
            array_merge([$playerId], $types)
        )->getResultArray();
    }
}
