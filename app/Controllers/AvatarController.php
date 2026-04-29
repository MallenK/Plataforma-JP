<?php

namespace App\Controllers;

use App\Models\UserModel;

/**
 * AvatarController
 *
 * Gestiona la subida, actualización y eliminación de avatares de usuario.
 * Cualquier usuario puede gestionar su propio avatar.
 * Admin/superadmin pueden gestionar el avatar de cualquier usuario.
 */
class AvatarController extends BaseController
{
    private const UPLOAD_PATH   = FCPATH . 'uploads/avatars/';
    private const MAX_SIZE_KB   = 2048;
    private const ALLOWED_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];

    /**
     * POST /avatar/upload
     * POST /avatar/upload/{id}  (solo admin)
     *
     * Sube o reemplaza el avatar del usuario indicado (o del autenticado).
     */
    public function upload(?int $targetId = null): \CodeIgniter\HTTP\RedirectResponse
    {
        $userId = $this->resolveTargetUser($targetId);
        if ($userId === null) {
            session()->setFlashdata('error', 'No tienes permiso para editar este avatar.');
            return redirect()->back();
        }

        $file = $this->request->getFile('avatar');

        if (!$file || !$file->isValid() || $file->hasMoved()) {
            session()->setFlashdata('error', 'El archivo no es válido.');
            return redirect()->back();
        }

        if (!in_array($file->getMimeType(), self::ALLOWED_TYPES)) {
            session()->setFlashdata('error', 'Formato no permitido. Usa JPG, PNG, WebP o GIF.');
            return redirect()->back();
        }

        if ($file->getSizeByUnit('kb') > self::MAX_SIZE_KB) {
            session()->setFlashdata('error', 'La imagen no puede superar 2 MB.');
            return redirect()->back();
        }

        // Crear directorio si no existe
        if (!is_dir(self::UPLOAD_PATH)) {
            mkdir(self::UPLOAD_PATH, 0755, true);
        }

        // Eliminar avatar anterior si existe
        $model    = new UserModel();
        $user     = $model->find($userId);
        $oldAvatar = $user['avatar'] ?? null;
        if ($oldAvatar && file_exists(FCPATH . $oldAvatar)) {
            @unlink(FCPATH . $oldAvatar);
        }

        // Nombre único para evitar colisiones
        $newName = 'avatar_' . $userId . '_' . time() . '.' . $file->getExtension();
        $file->move(self::UPLOAD_PATH, $newName);

        $relativePath = 'uploads/avatars/' . $newName;
        $model->update($userId, ['avatar' => $relativePath]);

        // Actualizar sesión si el usuario modificó su propio avatar
        if ((int)$userId === (int)$this->currentUserId()) {
            session()->set('avatar', $relativePath);
        }

        session()->setFlashdata('success', 'Avatar actualizado correctamente.');
        return redirect()->back();
    }

    /**
     * POST /avatar/delete
     * POST /avatar/delete/{id}  (solo admin)
     *
     * Elimina el avatar del usuario y vuelve a mostrar las iniciales.
     */
    public function delete(?int $targetId = null): \CodeIgniter\HTTP\RedirectResponse
    {
        $userId = $this->resolveTargetUser($targetId);
        if ($userId === null) {
            session()->setFlashdata('error', 'No tienes permiso para editar este avatar.');
            return redirect()->back();
        }

        $model = new UserModel();
        $user  = $model->find($userId);

        if (!empty($user['avatar']) && file_exists(FCPATH . $user['avatar'])) {
            @unlink(FCPATH . $user['avatar']);
        }

        $model->update($userId, ['avatar' => null]);

        if ((int)$userId === (int)$this->currentUserId()) {
            session()->set('avatar', null);
        }

        session()->setFlashdata('success', 'Avatar eliminado.');
        return redirect()->back();
    }

    // ────────────────────────────────────────────────────────────────────
    //  Helpers privados
    // ────────────────────────────────────────────────────────────────────

    /**
     * Resuelve el ID de usuario destino:
     * - Si $targetId es null → usuario autenticado.
     * - Si $targetId está presente → solo permitido a admin/superadmin.
     * Retorna null si no hay permiso.
     */
    private function resolveTargetUser(?int $targetId): ?int
    {
        if ($targetId === null) {
            return $this->currentUserId();
        }

        if ($this->isAdmin()) {
            return $targetId;
        }

        // Un usuario solo puede modificar su propio avatar
        return ((int)$targetId === (int)$this->currentUserId())
            ? $targetId
            : null;
    }
}
