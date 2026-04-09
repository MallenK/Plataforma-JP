<?php

namespace App\Models;

use CodeIgniter\Model;

class DocumentModel extends Model
{
    protected $table      = 'documents';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    protected $allowedFields = [
        'folder_id', 'uploader_id', 'name_original', 'name_stored',
        'mime_type', 'extension', 'size_bytes', 'description',
        'sensitive', 'deleted_at',
    ];

    protected $useTimestamps = false; // created_at manual, deleted_at para soft-delete

    /**
     * Devuelve documentos activos (no eliminados) de una carpeta,
     * con nombre del uploader mediante JOIN.
     */
    public function getByFolder(int $folderId): array
    {
        return $this->db->table('documents d')
            ->select('d.*, u.name AS uploader_name')
            ->join('users u', 'u.id = d.uploader_id')
            ->where('d.folder_id', $folderId)
            ->where('d.deleted_at IS NULL', null, false)
            ->orderBy('d.created_at', 'DESC')
            ->get()->getResultArray();
    }
}
