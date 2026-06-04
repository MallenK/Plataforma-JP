<?php

namespace App\Controllers;

use App\Models\PlayerAnnotationModel;
use App\Models\UserModel;
use App\Services\DocumentService;

class AnnotationController extends BaseController
{
    private PlayerAnnotationModel $model;

    public function __construct()
    {
        $this->model = new PlayerAnnotationModel();
    }

    /**
     * Crea una anotación para el jugador $playerId.
     * Permite adjuntar un archivo que se guarda en la carpeta personal del alumno.
     */
    public function store(int $playerId): \CodeIgniter\HTTP\RedirectResponse
    {
        $userModel = new UserModel();
        $player    = $userModel->find($playerId);

        if (!$player || $player['role'] !== 'player') {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $type    = $this->request->getPost('type') === 'internal' ? 'internal' : 'public';
        $content = trim($this->request->getPost('content') ?? '');
        $role    = $this->currentRole();

        if ($content === '') {
            session()->setFlashdata('annotation_error', 'La anotación no puede estar vacía.');
            return $this->redirectBack($playerId);
        }

        // Players solo pueden anotar su propio perfil y solo anotaciones públicas
        if ($role === 'player') {
            if ((int)$this->currentUserId() !== $playerId) {
                session()->setFlashdata('annotation_error', 'No tienes permiso para añadir anotaciones en otros perfiles.');
                return $this->redirectBack($playerId);
            }
            $type = 'public';
        }

        if ($type === 'internal' && $role === 'player') {
            session()->setFlashdata('annotation_error', 'No tienes permiso para crear anotaciones internas.');
            return $this->redirectBack($playerId);
        }

        // Manejar archivo adjunto opcional
        $documentId = null;
        $uploadedFile = $this->request->getFile('annotation_file');

        if ($uploadedFile && $uploadedFile->isValid() && !$uploadedFile->hasMoved()) {
            $docService = new DocumentService();
            $userId     = $this->currentUserId();
            // Internal annotations: store file in author's folder so players can't see it.
            // Public annotations: store in player's folder so they can access it.
            $folderId_owner = $type === 'internal' ? $userId : $playerId;
            $personalFolder = $docService->getOrCreatePersonalFolder($folderId_owner);

            if ($personalFolder) {
                $result = $docService->uploadFile(
                    $uploadedFile,
                    (int)$personalFolder['id'],
                    $userId,
                    $role,
                    'Adjunto de observación'
                );

                if ($result['success']) {
                    // Obtener el ID del último documento insertado
                    $db  = \Config\Database::connect();
                    $doc = $db->table('documents')
                        ->where('folder_id', $personalFolder['id'])
                        ->where('uploader_id', $userId)
                        ->where('deleted_at IS NULL', null, false)
                        ->orderBy('id', 'DESC')
                        ->limit(1)
                        ->get()->getRowArray();

                    $documentId = $doc['id'] ?? null;
                } else {
                    session()->setFlashdata('annotation_error', 'Error al subir el archivo: ' . ($result['error'] ?? 'Error desconocido'));
                    return $this->redirectBack($playerId);
                }
            }
        }

        $this->model->insert([
            'player_id'   => $playerId,
            'author_id'   => $this->currentUserId(),
            'type'        => $type,
            'content'     => $content,
            'document_id' => $documentId,
        ]);

        session()->setFlashdata('annotation_success', 'Anotación añadida correctamente.');

        return $this->redirectBack($playerId);
    }

    /**
     * Elimina una anotación.
     * Puede eliminar: el propio autor, admin, superadmin.
     */
    public function destroy(int $id): \CodeIgniter\HTTP\RedirectResponse
    {
        $annotation = $this->model->find($id);

        if (!$annotation) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $role      = $this->currentRole();
        $currentId = $this->currentUserId();
        $isAdmin   = in_array($role, ['admin', 'superadmin']);
        $isAuthor  = (int) $annotation['author_id'] === (int) $currentId;

        if (!$isAuthor && !$isAdmin) {
            session()->setFlashdata('annotation_error', 'No tienes permiso para eliminar esta anotación.');
            return $this->redirectBack((int) $annotation['player_id']);
        }

        $this->model->delete($id);

        session()->setFlashdata('annotation_success', 'Anotación eliminada.');

        return $this->redirectBack((int) $annotation['player_id']);
    }

    private function redirectBack(int $playerId): \CodeIgniter\HTTP\RedirectResponse
    {
        $role = $this->currentRole();

        if ($role === 'player') {
            return redirect()->to('/perfil#anotaciones');
        }

        return redirect()->to('/alumnos/' . $playerId . '#anotaciones');
    }
}
