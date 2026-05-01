<?php

namespace App\Controllers;

use App\Models\PlayerAnnotationModel;
use App\Models\UserModel;

class AnnotationController extends BaseController
{
    private PlayerAnnotationModel $model;

    public function __construct()
    {
        $this->model = new PlayerAnnotationModel();
    }

    /**
     * Crea una anotación para el jugador $playerId.
     * Todos los roles pueden crear anotaciones públicas.
     * Solo roles no-player pueden crear anotaciones internas.
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

        if ($content === '') {
            session()->setFlashdata('annotation_error', 'La anotación no puede estar vacía.');
            return $this->redirectBack($playerId);
        }

        $role = $this->currentRole();

        if ($type === 'internal' && $role === 'player') {
            session()->setFlashdata('annotation_error', 'No tienes permiso para crear anotaciones internas.');
            return $this->redirectBack($playerId);
        }

        $this->model->insert([
            'player_id' => $playerId,
            'author_id' => $this->currentUserId(),
            'type'      => $type,
            'content'   => $content,
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
            return redirect()->to('/alumno');
        }

        return redirect()->to('/alumnos/' . $playerId . '#anotaciones');
    }
}
