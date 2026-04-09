<?php

namespace App\Controllers;

use App\Services\DocumentService;

class DocumentacionController extends BaseController
{
    protected DocumentService $docService;

    public function initController(
        \CodeIgniter\HTTP\RequestInterface  $request,
        \CodeIgniter\HTTP\ResponseInterface $response,
        \Psr\Log\LoggerInterface            $logger
    ) {
        parent::initController($request, $response, $logger);
        $this->docService = new DocumentService();
    }

    // ────────────────────────────────────────────────────────────────
    //  Listado principal
    // ────────────────────────────────────────────────────────────────

    public function index(?int $legacyId = null)
    {
        $userId = $this->currentUserId();
        $role   = $this->currentRole();

        // Garantizar carpeta personal del usuario autenticado
        $this->docService->getOrCreatePersonalFolder($userId);

        $folders         = $this->docService->getAccessibleFolders($userId, $role);
        $writableFolders = $this->docService->getWritableFolders($userId, $role);

        // Carpeta activa via query param
        $activeFolderId = (int)($this->request->getGet('folder') ?? 0);
        $activeFolder   = null;
        $files          = [];

        if ($activeFolderId) {
            if ($this->docService->canAccessFolder($activeFolderId, $userId, $role)) {
                $activeFolder = $this->docService->getFolder($activeFolderId);
                $files        = $this->docService->getFolderFiles($activeFolderId);
            } else {
                session()->setFlashdata('error', 'No tienes acceso a esa carpeta.');
                return redirect()->to('/documentacion');
            }
        }

        // Datos para modal de permisos (solo admin/superadmin en carpeta interna)
        $assignableUsers   = [];
        $folderPermissions = [];
        if ($this->isAdmin() && $activeFolder && $activeFolder['type'] === 'internal') {
            $assignableUsers   = $this->docService->getAssignableUsers();
            $folderPermissions = $this->docService->getFolderPermissions($activeFolderId);
        }

        return view('documentacion/index', [
            'title'             => 'Documentación — JP Preparation',
            'folders'           => $folders,
            'activeFolder'      => $activeFolder,
            'files'             => $files,
            'writableFolders'   => $writableFolders,
            'assignableUsers'   => $assignableUsers,
            'folderPermissions' => $folderPermissions,
        ]);
    }

    // ────────────────────────────────────────────────────────────────
    //  Subida de archivos
    // ────────────────────────────────────────────────────────────────

    public function upload()
    {
        $userId   = $this->currentUserId();
        $role     = $this->currentRole();
        $folderId = (int)$this->request->getPost('folder_id');
        $desc     = $this->request->getPost('description');
        $file     = $this->request->getFile('archivo');

        $result = $this->docService->uploadFile($file, $folderId, $userId, $role, $desc);

        if (!$result['success']) {
            session()->setFlashdata('upload_error', $result['error']);
        } else {
            session()->setFlashdata('success', 'Archivo subido correctamente.');
        }

        return redirect()->to('/documentacion?folder=' . $folderId);
    }

    // ────────────────────────────────────────────────────────────────
    //  Descarga (Content-Disposition: attachment)
    // ────────────────────────────────────────────────────────────────

    public function download(int $id)
    {
        $result = $this->docService->getFileForServing($id, $this->currentUserId(), $this->currentRole());

        if (!$result) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $doc  = $result['document'];
        $path = $result['path'];

        $this->logAccess('download', $doc);

        return $this->serveFile($path, $doc, 'attachment');
    }

    // ────────────────────────────────────────────────────────────────
    //  Previsualización (Content-Disposition: inline, target="_blank")
    // ────────────────────────────────────────────────────────────────

    public function preview(int $id)
    {
        $result = $this->docService->getFileForServing($id, $this->currentUserId(), $this->currentRole());

        if (!$result) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $doc  = $result['document'];
        $path = $result['path'];

        // Si no es previsualizable en navegador → descarga
        if (!in_array($doc['extension'], DocumentService::PREVIEW_EXTS)) {
            $this->logAccess('download', $doc);
            return $this->serveFile($path, $doc, 'attachment');
        }

        $this->logAccess('preview', $doc);
        return $this->serveFile($path, $doc, 'inline');
    }

    // ────────────────────────────────────────────────────────────────
    //  Eliminación de archivo (soft delete)
    // ────────────────────────────────────────────────────────────────

    public function deleteFile(int $id)
    {
        $doc = (new \App\Models\DocumentModel())->find($id);
        $folderId = $doc['folder_id'] ?? 0;

        $ok = $this->docService->deleteFile($id, $this->currentUserId(), $this->currentRole());

        if ($ok) {
            session()->setFlashdata('success', 'Archivo eliminado correctamente.');
        } else {
            session()->setFlashdata('error', 'No tienes permiso para eliminar este archivo.');
        }

        return redirect()->to('/documentacion?folder=' . $folderId);
    }

    // ────────────────────────────────────────────────────────────────
    //  Gestión de carpetas (solo admin/superadmin)
    // ────────────────────────────────────────────────────────────────

    public function createFolder()
    {
        $result = $this->docService->createFolder([
            'name'  => $this->request->getPost('name'),
            'type'  => $this->request->getPost('type'),
            'icon'  => $this->request->getPost('icon')  ?? 'bi-folder-fill',
            'color' => $this->request->getPost('color') ?? 'blue',
        ], $this->currentUserId());

        if ($result['success']) {
            session()->setFlashdata('success', 'Carpeta creada correctamente.');
        } else {
            session()->setFlashdata('error', 'No se pudo crear la carpeta.');
        }

        return redirect()->to('/documentacion');
    }

    public function deleteFolder(int $id)
    {
        $ok = $this->docService->deleteFolder($id, $this->currentRole());

        session()->setFlashdata(
            $ok ? 'success' : 'error',
            $ok ? 'Carpeta eliminada correctamente.' : 'No se pudo eliminar la carpeta.'
        );

        return redirect()->to('/documentacion');
    }

    // ────────────────────────────────────────────────────────────────
    //  Permisos de carpetas internas (solo admin/superadmin)
    // ────────────────────────────────────────────────────────────────

    public function savePermissions(int $folderId)
    {
        $raw = $this->request->getPost('perms') ?? [];
        // $raw viene como ['userId' => ['can_read' => '1', 'can_write' => '1']]

        $this->docService->savePermissions($folderId, $raw, $this->currentUserId());

        session()->setFlashdata('success', 'Permisos actualizados correctamente.');

        return redirect()->to('/documentacion?folder=' . $folderId);
    }

    // ════════════════════════════════════════════════════════════════
    //  HELPERS PRIVADOS
    // ════════════════════════════════════════════════════════════════

    /**
     * Sirve un archivo desde el filesystem de forma segura, con streaming
     * para evitar cargar ficheros grandes en memoria (vídeos, etc.).
     *
     * @param string $disposition 'attachment' | 'inline'
     */
    private function serveFile(string $path, array $doc, string $disposition): \CodeIgniter\HTTP\ResponseInterface
    {
        if (!file_exists($path)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        // Nombre seguro para el header (RFC 5987)
        $safeName = rawurlencode($doc['name_original']);

        $this->response
            ->setHeader('Content-Type',        $doc['mime_type'])
            ->setHeader('Content-Disposition', "{$disposition}; filename*=UTF-8''{$safeName}")
            ->setHeader('Content-Length',      (string) filesize($path));

        if ($doc['sensitive']) {
            $this->response->setHeader('Cache-Control', 'no-store, no-cache, must-revalidate');
            $this->response->setHeader('Pragma', 'no-cache');
        } else {
            $this->response->setHeader('Cache-Control', 'private, max-age=3600');
        }

        // Limpiar buffer de salida antes de readfile para no cargar en memoria
        if (ob_get_level()) {
            ob_end_clean();
        }

        // Enviar headers ya acumulados por CI4
        foreach ($this->response->getHeaders() as $name => $header) {
            header($name . ': ' . $header->getValueLine());
        }

        http_response_code($this->response->getStatusCode());

        flush();
        readfile($path);
        exit;
    }

    /**
     * Registra en la tabla logs quién descargó/previsualizó qué archivo.
     */
    private function logAccess(string $action, array $doc): void
    {
        try {
            \Config\Database::connect()->table('logs')->insert([
                'user_id'   => $this->currentUserId(),
                'action'    => $action,
                'entity'    => 'document',
                'entity_id' => $doc['id'],
                'data'      => json_encode([
                    'file'      => $doc['name_original'],
                    'folder_id' => $doc['folder_id'],
                ]),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
        } catch (\Throwable $e) {
            // No interrumpir la descarga si el log falla
            log_message('error', 'Log de descarga fallido: ' . $e->getMessage());
        }
    }
}
