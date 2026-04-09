<?php

namespace App\Models;

use CodeIgniter\Model;

class FolderPermissionModel extends Model
{
    protected $table      = 'folder_permissions';
    protected $primaryKey = 'id';
    protected $returnType = 'array';

    protected $allowedFields = [
        'folder_id', 'user_id', 'can_read', 'can_write', 'granted_by',
    ];

    protected $useTimestamps = false;

    public function hasReadPermission(int $folderId, int $userId): bool
    {
        return $this->where('folder_id', $folderId)
            ->where('user_id', $userId)
            ->where('can_read', 1)
            ->first() !== null;
    }

    public function hasWritePermission(int $folderId, int $userId): bool
    {
        return $this->where('folder_id', $folderId)
            ->where('user_id', $userId)
            ->where('can_write', 1)
            ->first() !== null;
    }

    /**
     * Reemplaza todos los permisos de una carpeta interna de una vez.
     */
    public function replaceAll(int $folderId, array $permissions, int $grantedBy): void
    {
        $this->where('folder_id', $folderId)->delete();

        foreach ($permissions as $userId => $perms) {
            if (!empty($perms['can_read'])) {
                $this->insert([
                    'folder_id'  => $folderId,
                    'user_id'    => (int) $userId,
                    'can_read'   => 1,
                    'can_write'  => empty($perms['can_write']) ? 0 : 1,
                    'granted_by' => $grantedBy,
                ]);
            }
        }
    }

    /**
     * Devuelve los permisos de una carpeta con nombre del usuario.
     */
    public function getByFolder(int $folderId): array
    {
        return $this->db->table('folder_permissions fp')
            ->select('fp.*, u.name AS user_name, u.email AS user_email, u.role AS user_role')
            ->join('users u', 'u.id = fp.user_id')
            ->where('fp.folder_id', $folderId)
            ->orderBy('u.name', 'ASC')
            ->get()->getResultArray();
    }
}
