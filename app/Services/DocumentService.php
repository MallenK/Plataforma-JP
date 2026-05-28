<?php

namespace App\Services;

use App\Models\FolderModel;
use App\Models\DocumentModel;
use App\Models\FolderPermissionModel;
use App\Models\UserModel;
use CodeIgniter\HTTP\Files\UploadedFile;

class DocumentService
{
    // ── Límites de tamaño ────────────────────────────────────────────
    const MAX_SIZE_VIDEO   = 524288000; // 500 MB
    const MAX_SIZE_IMAGE   =  10485760; // 10 MB
    const MAX_SIZE_DEFAULT =  26214400; // 25 MB

    // ── Listas blancas ────────────────────────────────────────────────
    const ALLOWED_EXTENSIONS = [
        'pdf',
        'doc', 'docx',
        'xls', 'xlsx',
        'ppt', 'pptx',
        'jpg', 'jpeg', 'png', 'gif', 'webp',
        'mp4', 'mov', 'avi', 'webm',
    ];

    const MIME_WHITELIST = [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'application/vnd.ms-powerpoint',
        'application/vnd.openxmlformats-officedocument.presentationml.presentation',
        'image/jpeg', 'image/png', 'image/gif', 'image/webp',
        'video/mp4', 'video/quicktime', 'video/x-msvideo', 'video/webm',
        'application/octet-stream', // fallback genérico de algunos navegadores
    ];

    const VIDEO_EXTS    = ['mp4', 'mov', 'avi', 'webm'];
    const IMAGE_EXTS    = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    const PREVIEW_EXTS  = ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'webp', 'mp4', 'webm'];

    protected FolderModel           $folderModel;
    protected DocumentModel         $docModel;
    protected FolderPermissionModel $permModel;

    public function __construct()
    {
        $this->folderModel = new FolderModel();
        $this->docModel    = new DocumentModel();
        $this->permModel   = new FolderPermissionModel();
    }

    // ════════════════════════════════════════════════════════════════
    //  ACCESO Y PERMISOS
    // ════════════════════════════════════════════════════════════════

    /**
     * Comprueba si un usuario puede VER una carpeta.
     *
     * Reglas:
     * - public:   todos los roles
     * - personal: propietario | admin/superadmin | (coach/staff si el propietario es player)
     * - internal: no-player + (admin/superadmin | permiso explícito)
     */
    public function canAccessFolder(int $folderId, int $userId, string $role): bool
    {
        $folder = $this->folderModel->find($folderId);
        if (!$folder || $folder['status'] !== 'active') {
            return false;
        }

        return match($folder['type']) {
            'public'   => true,
            'personal' => (int)$folder['owner_id'] === $userId
                           || in_array($role, ['admin', 'superadmin'])
                           || in_array($role, ['coach', 'staff']),
            'internal' => $role !== 'player'
                           && (in_array($role, ['admin', 'superadmin'])
                               || $this->permModel->hasReadPermission($folderId, $userId)),
            default    => false,
        };
    }

    /**
     * Comprueba si un usuario puede SUBIR archivos a una carpeta.
     *
     * Reglas:
     * - public:   admin/superadmin/coach/staff (jugadores no suben a públicas)
     * - personal: propietario | admin/superadmin | (coach/staff si el propietario es player)
     * - internal: admin/superadmin | permiso explícito de escritura
     */
    public function canWriteToFolder(int $folderId, int $userId, string $role): bool
    {
        if (!$this->canAccessFolder($folderId, $userId, $role)) {
            return false;
        }

        $folder = $this->folderModel->find($folderId);

        return match($folder['type']) {
            'public'   => in_array($role, ['admin', 'superadmin', 'coach', 'staff']),
            'personal' => (int)$folder['owner_id'] === $userId
                           || in_array($role, ['admin', 'superadmin'])
                           || (in_array($role, ['coach', 'staff'])
                               && $this->getFolderOwnerRole((int)($folder['owner_id'] ?? 0)) === 'player'),
            'internal' => in_array($role, ['admin', 'superadmin'])
                           || $this->permModel->hasWritePermission($folderId, $userId),
            default    => false,
        };
    }

    // ════════════════════════════════════════════════════════════════
    //  CARPETAS
    // ════════════════════════════════════════════════════════════════

    /**
     * Devuelve todas las carpetas a las que tiene acceso el usuario,
     * con el conteo de archivos de cada una.
     */
    public function getAccessibleFolders(int $userId, string $role): array
    {
        $db      = \Config\Database::connect();
        $builder = $db->table('document_folders df')
            ->select('df.*, u.name AS owner_name, u.role AS owner_role, (SELECT COUNT(*) FROM documents d WHERE d.folder_id = df.id AND d.deleted_at IS NULL) AS files_count')
            ->join('users u', 'u.id = df.owner_id', 'left')
            ->where('df.status', 'active');

        if ($role === 'player') {
            // Carpetas públicas + carpeta personal propia
            $builder->where(
                "(df.type = 'public' OR (df.type = 'personal' AND df.owner_id = {$userId}))",
                null, false
            );
        } elseif (in_array($role, ['admin', 'superadmin'])) {
            // Ve todo
        } else {
            // Coach / staff: públicas + todas las carpetas personales + internas asignadas
            $builder->where(
                "(df.type = 'public'
                  OR df.type = 'personal'
                  OR (df.type = 'internal' AND EXISTS (
                        SELECT 1 FROM folder_permissions fp
                        WHERE fp.folder_id = df.id
                          AND fp.user_id = {$userId}
                          AND fp.can_read = 1
                  )))",
                null, false
            );
        }

        return $builder->orderBy('df.type', 'ASC')
                       ->orderBy('df.name', 'ASC')
                       ->get()->getResultArray();
    }

    /**
     * Carpetas en las que el usuario puede subir archivos.
     * Para el selector del modal de subida.
     */
    public function getWritableFolders(int $userId, string $role): array
    {
        $all = $this->getAccessibleFolders($userId, $role);

        return array_values(array_filter(
            $all,
            fn($f) => $this->canWriteToFolder((int)$f['id'], $userId, $role)
        ));
    }

    public function getFolder(int $id): ?array
    {
        return $this->folderModel->find($id);
    }

    /**
     * Crea o devuelve la carpeta personal del usuario.
     * - Si existe pero está inactiva, la reactiva.
     * - Si el slug ya está ocupado por otra carpeta, genera uno alternativo.
     */
    public function getOrCreatePersonalFolder(int $userId): ?array
    {
        if ($userId <= 0) {
            return null;
        }

        // Buscar carpeta existente para este owner (activa o inactiva)
        $folder = $this->folderModel
            ->where('type', 'personal')
            ->where('owner_id', $userId)
            ->first();

        if ($folder) {
            // Reactivar si estaba inactiva
            if ($folder['status'] !== 'active') {
                $this->folderModel->update((int)$folder['id'], ['status' => 'active']);
                $folder['status'] = 'active';
            }
            return $folder;
        }

        // Generar slug único (puede haber conflicto de slugs huérfanos)
        $slug = 'personal-' . $userId;
        if ($this->folderModel->where('slug', $slug)->first()) {
            $slug = 'personal-' . $userId . '-' . substr(md5((string)$userId . microtime()), 0, 6);
        }

        try {
            $folderId = $this->folderModel->insert([
                'name'       => 'Mi carpeta',
                'slug'       => $slug,
                'type'       => 'personal',
                'icon'       => 'bi-person-fill',
                'color'      => 'blue',
                'owner_id'   => $userId,
                'created_by' => $userId,
                'status'     => 'active',
            ], true);
        } catch (\Throwable $e) {
            log_message('error', 'DocumentService::getOrCreatePersonalFolder insert failed uid=' . $userId . ': ' . $e->getMessage());
            return null;
        }

        if (!$folderId) {
            log_message('error', 'DocumentService::getOrCreatePersonalFolder insert returned falsy uid=' . $userId);
            return null;
        }

        $this->ensureStorageDir('personal', (int)$folderId, $userId);

        return $this->folderModel->find((int)$folderId);
    }

    /**
     * Garantiza que todos los usuarios activos tienen carpeta personal activa.
     * Solo debe llamarse con rol admin/superadmin.
     */
    public function ensureAllPersonalFolders(): void
    {
        $db = \Config\Database::connect();

        // Todos los usuarios activos
        $allUsers = $db->table('users')
            ->select('id')
            ->where('status', 'active')
            ->get()->getResultArray();

        // Owner IDs que YA tienen carpeta personal (activa o inactiva)
        $existing = $db->table('document_folders')
            ->select('owner_id')
            ->where('type', 'personal')
            ->where('owner_id IS NOT NULL', null, false)
            ->get()->getResultArray();

        $existingOwnerIds = array_map('intval', array_column($existing, 'owner_id'));

        foreach ($allUsers as $user) {
            $uid = (int)$user['id'];
            if ($uid > 0 && !in_array($uid, $existingOwnerIds, true)) {
                $this->getOrCreatePersonalFolder($uid);
            }
        }
    }

    /**
     * Crea una carpeta pública o interna. Solo admin/superadmin.
     */
    public function createFolder(array $data, int $createdBy): array
    {
        $slug = $this->generateSlug($data['name']);

        // Si el slug ya existe, añadir sufijo
        $existing = $this->folderModel->where('slug', $slug)->first();
        if ($existing) {
            $slug .= '-' . time();
        }

        $folderId = $this->folderModel->insert([
            'name'       => $data['name'],
            'slug'       => $slug,
            'type'       => $data['type'],
            'icon'       => $data['icon'] ?? 'bi-folder-fill',
            'color'      => $data['color'] ?? 'blue',
            'owner_id'   => null,
            'created_by' => $createdBy,
            'status'     => 'active',
        ], true);

        if ($folderId === false) {
            return ['success' => false, 'errors' => $this->folderModel->errors()];
        }

        $folder = $this->folderModel->find($folderId);
        $this->ensureStorageDir($folder['type'], $folderId, null);

        return ['success' => true, 'folderId' => $folderId];
    }

    /**
     * Baja lógica de una carpeta (marca archivos y carpeta como inactivos).
     * No borra ficheros físicos — se pueden purgar manualmente.
     */
    public function deleteFolder(int $folderId, string $role): bool
    {
        if (!in_array($role, ['admin', 'superadmin'])) {
            return false;
        }

        $folder = $this->folderModel->find($folderId);
        if (!$folder || $folder['type'] === 'personal') {
            return false;
        }

        // Soft delete de los archivos
        $this->docModel->db->table('documents')
            ->where('folder_id', $folderId)
            ->where('deleted_at IS NULL', null, false)
            ->set(['deleted_at' => date('Y-m-d H:i:s')])
            ->update();

        return (bool) $this->folderModel->update($folderId, ['status' => 'inactive']);
    }

    // ════════════════════════════════════════════════════════════════
    //  ARCHIVOS
    // ════════════════════════════════════════════════════════════════

    /**
     * Devuelve los archivos activos de una carpeta.
     */
    public function getFolderFiles(int $folderId): array
    {
        return $this->docModel->getByFolder($folderId);
    }

    /**
     * Valida y almacena un archivo subido.
     *
     * @return array ['success' => bool, 'error' => string|null]
     */
    public function uploadFile(
        UploadedFile $file,
        int $folderId,
        int $uploaderId,
        string $role,
        ?string $description = null
    ): array {
        // 1. Comprobar que el archivo llegó bien
        if (!$file->isValid()) {
            return ['success' => false, 'error' => 'El archivo no se recibió correctamente: ' . $file->getErrorString()];
        }

        // 2. Comprobar permisos de escritura
        if (!$this->canWriteToFolder($folderId, $uploaderId, $role)) {
            return ['success' => false, 'error' => 'No tienes permiso para subir archivos a esta carpeta.'];
        }

        // 3. Validar extensión
        $ext = strtolower($file->getClientExtension());
        if (!in_array($ext, self::ALLOWED_EXTENSIONS)) {
            return ['success' => false, 'error' => "Tipo de archivo no permitido (.{$ext})."];
        }

        // 4. Validar MIME real (finfo, no el que declara el navegador)
        $mime = $file->getMimeType();
        if (!in_array($mime, self::MIME_WHITELIST)) {
            return ['success' => false, 'error' => "Tipo MIME no permitido ({$mime})."];
        }

        // 5. Validar tamaño según tipo
        $size = $file->getSize();
        if (in_array($ext, self::VIDEO_EXTS) && $size > self::MAX_SIZE_VIDEO) {
            return ['success' => false, 'error' => 'Los vídeos no pueden superar 500 MB.'];
        }
        if (in_array($ext, self::IMAGE_EXTS) && $size > self::MAX_SIZE_IMAGE) {
            return ['success' => false, 'error' => 'Las imágenes no pueden superar 10 MB.'];
        }
        if (!in_array($ext, self::VIDEO_EXTS) && !in_array($ext, self::IMAGE_EXTS) && $size > self::MAX_SIZE_DEFAULT) {
            return ['success' => false, 'error' => 'El archivo no puede superar 25 MB.'];
        }

        // 6. Construir ruta de destino
        $folder  = $this->folderModel->find($folderId);
        $destDir = $this->buildStoragePath($folder);
        $this->ensureStorageDir($folder['type'], $folderId, $folder['owner_id']);

        // 7. Nombre en disco: UUID opaco (no inferible por el usuario)
        $storedName = bin2hex(random_bytes(16)) . '.' . $ext;

        // 8. Mover archivo
        $file->move($destDir, $storedName);

        // 9. Insertar en BD
        $this->docModel->insert([
            'folder_id'     => $folderId,
            'uploader_id'   => $uploaderId,
            'name_original' => $file->getClientName(),
            'name_stored'   => $storedName,
            'mime_type'     => $mime,
            'extension'     => $ext,
            'size_bytes'    => $size,
            'description'   => $description,
            'sensitive'     => in_array($folder['type'], ['personal']) ? 1 : 0,
        ]);

        return ['success' => true, 'error' => null];
    }

    /**
     * Verifica permisos y devuelve la ruta física del archivo para servir.
     *
     * @return array|null ['document' => array, 'path' => string] o null si no autorizado
     */
    public function getFileForServing(int $fileId, int $userId, string $role): ?array
    {
        $doc = $this->docModel->find($fileId);
        if (!$doc || $doc['deleted_at'] !== null) {
            return null;
        }

        if (!$this->canAccessFolder((int)$doc['folder_id'], $userId, $role)) {
            return null;
        }

        $folder = $this->folderModel->find($doc['folder_id']);
        $path   = $this->buildStoragePath($folder) . $doc['name_stored'];

        if (!file_exists($path)) {
            return null;
        }

        return ['document' => $doc, 'path' => $path];
    }

    /**
     * Elimina un archivo (soft delete).
     *
     * Reglas:
     * - Admin/superadmin: puede borrar cualquier documento.
     * - Player: puede borrar sus propios documentos, nunca en carpeta pública.
     * - Staff/Coach: no pueden borrar (sus documentos solo los borra el admin).
     */
    public function deleteFile(int $fileId, int $userId, string $role): bool
    {
        $doc = $this->docModel->find($fileId);
        if (!$doc || $doc['deleted_at'] !== null) {
            return false;
        }

        if (!$this->canAccessFolder((int)$doc['folder_id'], $userId, $role)) {
            return false;
        }

        if (in_array($role, ['admin', 'superadmin'])) {
            return (bool) $this->docModel->update($fileId, ['deleted_at' => date('Y-m-d H:i:s')]);
        }

        // Solo jugadores pueden borrar sus propios documentos, y no en carpetas públicas
        if ($role !== 'player') {
            return false;
        }

        $folder = $this->folderModel->find($doc['folder_id']);
        if ($folder['type'] === 'public') {
            return false;
        }

        if ((int)$doc['uploader_id'] !== $userId) {
            return false;
        }

        return (bool) $this->docModel->update($fileId, ['deleted_at' => date('Y-m-d H:i:s')]);
    }

    // ════════════════════════════════════════════════════════════════
    //  PERMISOS DE CARPETAS INTERNAS
    // ════════════════════════════════════════════════════════════════

    /**
     * Todos los usuarios activos (para la vista de admin/superadmin).
     */
    public function getAllUsers(): array
    {
        return (new UserModel())
            ->where('status', 'active')
            ->select('id, name, email, role')
            ->orderBy('role')
            ->orderBy('name')
            ->findAll();
    }

    /**
     * Usuarios que pueden recibir permisos en carpetas internas (nunca player).
     */
    public function getAssignableUsers(): array
    {
        return (new UserModel())
            ->whereNotIn('role', ['player'])
            ->where('status', 'active')
            ->select('id, name, email, role')
            ->orderBy('name', 'ASC')
            ->findAll();
    }

    public function getFolderPermissions(int $folderId): array
    {
        return $this->permModel->getByFolder($folderId);
    }

    /**
     * Reemplaza todos los permisos de una carpeta interna.
     * $permissions: [userId => ['can_read' => 1, 'can_write' => 0], ...]
     */
    public function savePermissions(int $folderId, array $permissions, int $grantedBy): bool
    {
        $folder = $this->folderModel->find($folderId);
        if (!$folder || $folder['type'] !== 'internal') {
            return false;
        }

        $this->permModel->replaceAll($folderId, $permissions, $grantedBy);

        return true;
    }

    // ════════════════════════════════════════════════════════════════
    //  HELPERS PRIVADOS
    // ════════════════════════════════════════════════════════════════

    private function buildStoragePath(array $folder): string
    {
        $base = WRITEPATH . 'uploads/';

        return match($folder['type']) {
            'public'   => $base . 'public/'   . $folder['id'] . '/',
            'personal' => $base . 'personal/' . ($folder['owner_id'] ?? $folder['id']) . '/',
            'internal' => $base . 'internal/' . $folder['id'] . '/',
            default    => $base . 'misc/',
        };
    }

    private function ensureStorageDir(string $type, int $folderId, ?int $ownerId): void
    {
        $base = WRITEPATH . 'uploads/';
        $path = match($type) {
            'public'   => $base . 'public/'   . $folderId . '/',
            'personal' => $base . 'personal/' . ($ownerId ?? $folderId) . '/',
            'internal' => $base . 'internal/' . $folderId . '/',
            default    => $base . 'misc/',
        };

        try {
            if (!is_dir($path)) {
                @mkdir($path, 0755, true);
            }
            // Proteger directorio con .htaccess si no existe
            $htaccess = dirname($path) . '/.htaccess';
            if (!file_exists($htaccess)) {
                @file_put_contents($htaccess, "Options -Indexes\nDeny from all\n");
            }
        } catch (\Throwable $e) {
            log_message('warning', 'DocumentService::ensureStorageDir failed: ' . $e->getMessage());
        }
    }

    private function getFolderOwnerRole(int $ownerId): string
    {
        if ($ownerId <= 0) {
            return '';
        }
        $row = \Config\Database::connect()
            ->table('users')
            ->select('role')
            ->where('id', $ownerId)
            ->get()->getRow();
        return $row ? (string)$row->role : '';
    }

    private function generateSlug(string $name): string
    {
        $slug = mb_strtolower($name, 'UTF-8');
        $slug = str_replace(['á','é','í','ó','ú','ü','ñ'], ['a','e','i','o','u','u','n'], $slug);
        $slug = preg_replace('/[^a-z0-9]+/', '-', $slug);
        return trim($slug, '-');
    }
}
