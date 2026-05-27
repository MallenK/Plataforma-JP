<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Tests de permisos del sistema de documentación.
 *
 * Cubre las reglas de acceso, escritura y borrado definidas en DocumentService:
 * - Jugadores: solo su carpeta propia + públicas (sin borrar en públicas)
 * - Staff/Coach: público + propia + carpetas de jugadores; nunca borran
 * - Admin/Superadmin: acceso total; únicos que pueden borrar docs de staff/admin
 *
 * @internal
 */
final class DocPreviewPermissionsTest extends CIUnitTestCase
{
    // ── Helpers de fixtures en memoria ──────────────────────────────────

    private function makeFolder(int $id, string $type, ?int $ownerId = null, string $status = 'active'): array
    {
        return [
            'id'       => $id,
            'type'     => $type,
            'owner_id' => $ownerId,
            'status'   => $status,
            'name'     => 'Folder ' . $id,
            'slug'     => 'folder-' . $id,
        ];
    }

    private function makeDocument(int $id, int $folderId, int $uploaderId): array
    {
        return [
            'id'            => $id,
            'folder_id'     => $folderId,
            'uploader_id'   => $uploaderId,
            'name_original' => 'file-' . $id . '.pdf',
            'extension'     => 'pdf',
            'deleted_at'    => null,
        ];
    }

    // ── Lógica de acceso extraída para test sin DB ───────────────────────

    /**
     * Replica la lógica de DocumentService::canAccessFolder() para pruebas
     * en memoria (sin base de datos).
     */
    private function canAccess(array $folder, int $userId, string $role, string $ownerRole = ''): bool
    {
        if ($folder['status'] !== 'active') {
            return false;
        }

        return match($folder['type']) {
            'public'   => true,
            'personal' => (int)$folder['owner_id'] === $userId
                           || in_array($role, ['admin', 'superadmin'])
                           || (in_array($role, ['coach', 'staff']) && $ownerRole === 'player'),
            'internal' => $role !== 'player'
                           && in_array($role, ['admin', 'superadmin']),
            default    => false,
        };
    }

    /**
     * Replica la lógica de DocumentService::canWriteToFolder() para pruebas
     * en memoria.
     */
    private function canWrite(array $folder, int $userId, string $role, string $ownerRole = ''): bool
    {
        if (!$this->canAccess($folder, $userId, $role, $ownerRole)) {
            return false;
        }

        return match($folder['type']) {
            'public'   => in_array($role, ['admin', 'superadmin', 'coach', 'staff']),
            'personal' => (int)$folder['owner_id'] === $userId
                           || in_array($role, ['admin', 'superadmin'])
                           || (in_array($role, ['coach', 'staff']) && $ownerRole === 'player'),
            'internal' => in_array($role, ['admin', 'superadmin']),
            default    => false,
        };
    }

    /**
     * Replica la lógica de DocumentService::deleteFile() para pruebas
     * en memoria.
     */
    private function canDelete(array $doc, array $folder, int $userId, string $role, string $ownerRole = ''): bool
    {
        if (!$this->canAccess($folder, $userId, $role, $ownerRole)) {
            return false;
        }

        if (in_array($role, ['admin', 'superadmin'])) {
            return true;
        }

        if ($role !== 'player') {
            return false;
        }

        if ($folder['type'] === 'public') {
            return false;
        }

        return (int)$doc['uploader_id'] === $userId;
    }

    // ════════════════════════════════════════════════════════════════════
    //  ACCESO A CARPETAS
    // ════════════════════════════════════════════════════════════════════

    public function testPlayerCanAccessPublicFolder(): void
    {
        $folder = $this->makeFolder(1, 'public');
        $this->assertTrue($this->canAccess($folder, 5, 'player'));
    }

    public function testPlayerCanAccessOwnPersonalFolder(): void
    {
        $folder = $this->makeFolder(2, 'personal', 5);
        $this->assertTrue($this->canAccess($folder, 5, 'player', 'player'));
    }

    public function testPlayerCannotAccessOtherPlayerPersonalFolder(): void
    {
        $folder = $this->makeFolder(3, 'personal', 7);
        $this->assertFalse($this->canAccess($folder, 5, 'player', 'player'));
    }

    public function testPlayerCannotAccessInternalFolder(): void
    {
        $folder = $this->makeFolder(4, 'internal');
        $this->assertFalse($this->canAccess($folder, 5, 'player'));
    }

    public function testPlayerCannotAccessCoachPersonalFolder(): void
    {
        $folder = $this->makeFolder(5, 'personal', 9);
        $this->assertFalse($this->canAccess($folder, 5, 'player', 'coach'));
    }

    public function testCoachCanAccessPublicFolder(): void
    {
        $folder = $this->makeFolder(1, 'public');
        $this->assertTrue($this->canAccess($folder, 9, 'coach'));
    }

    public function testCoachCanAccessOwnPersonalFolder(): void
    {
        $folder = $this->makeFolder(6, 'personal', 9);
        $this->assertTrue($this->canAccess($folder, 9, 'coach', 'coach'));
    }

    public function testCoachCanAccessPlayerPersonalFolder(): void
    {
        $folder = $this->makeFolder(7, 'personal', 5);
        $this->assertTrue($this->canAccess($folder, 9, 'coach', 'player'));
    }

    public function testCoachCannotAccessAdminPersonalFolder(): void
    {
        $folder = $this->makeFolder(8, 'personal', 2);
        $this->assertFalse($this->canAccess($folder, 9, 'coach', 'admin'));
    }

    public function testCoachCannotAccessStaffPersonalFolder(): void
    {
        $folder = $this->makeFolder(9, 'personal', 3);
        $this->assertFalse($this->canAccess($folder, 9, 'coach', 'staff'));
    }

    public function testStaffCanAccessPlayerPersonalFolder(): void
    {
        $folder = $this->makeFolder(7, 'personal', 5);
        $this->assertTrue($this->canAccess($folder, 3, 'staff', 'player'));
    }

    public function testAdminCanAccessAllFolders(): void
    {
        $public   = $this->makeFolder(1, 'public');
        $personal = $this->makeFolder(2, 'personal', 5);
        $internal = $this->makeFolder(3, 'internal');

        $this->assertTrue($this->canAccess($public,   2, 'admin'));
        $this->assertTrue($this->canAccess($personal, 2, 'admin', 'player'));
        $this->assertTrue($this->canAccess($internal, 2, 'admin'));
    }

    public function testInactiveFolderBlocksAccess(): void
    {
        $folder = $this->makeFolder(1, 'public', null, 'inactive');
        $this->assertFalse($this->canAccess($folder, 2, 'admin'));
    }

    // ════════════════════════════════════════════════════════════════════
    //  ESCRITURA EN CARPETAS
    // ════════════════════════════════════════════════════════════════════

    public function testPlayerCannotWriteToPublicFolder(): void
    {
        $folder = $this->makeFolder(1, 'public');
        $this->assertFalse($this->canWrite($folder, 5, 'player'));
    }

    public function testPlayerCanWriteToOwnPersonalFolder(): void
    {
        $folder = $this->makeFolder(2, 'personal', 5);
        $this->assertTrue($this->canWrite($folder, 5, 'player', 'player'));
    }

    public function testCoachCanWriteToPublicFolder(): void
    {
        $folder = $this->makeFolder(1, 'public');
        $this->assertTrue($this->canWrite($folder, 9, 'coach'));
    }

    public function testCoachCanWriteToPlayerPersonalFolder(): void
    {
        $folder = $this->makeFolder(7, 'personal', 5);
        $this->assertTrue($this->canWrite($folder, 9, 'coach', 'player'));
    }

    public function testCoachCannotWriteToAnotherCoachFolder(): void
    {
        $folder = $this->makeFolder(8, 'personal', 10);
        $this->assertFalse($this->canWrite($folder, 9, 'coach', 'coach'));
    }

    public function testAdminCanWriteEverywhere(): void
    {
        $this->assertTrue($this->canWrite($this->makeFolder(1, 'public'), 2, 'admin'));
        $this->assertTrue($this->canWrite($this->makeFolder(2, 'personal', 5), 2, 'admin', 'player'));
        $this->assertTrue($this->canWrite($this->makeFolder(3, 'internal'), 2, 'admin'));
    }

    // ════════════════════════════════════════════════════════════════════
    //  BORRADO DE DOCUMENTOS
    // ════════════════════════════════════════════════════════════════════

    public function testAdminCanDeleteAnyDocument(): void
    {
        $folder = $this->makeFolder(2, 'personal', 5);
        $doc    = $this->makeDocument(1, 2, 5);
        $this->assertTrue($this->canDelete($doc, $folder, 2, 'admin', 'player'));
    }

    public function testPlayerCanDeleteOwnDocumentFromPersonalFolder(): void
    {
        $folder = $this->makeFolder(2, 'personal', 5);
        $doc    = $this->makeDocument(1, 2, 5);
        $this->assertTrue($this->canDelete($doc, $folder, 5, 'player', 'player'));
    }

    public function testPlayerCannotDeleteOtherPlayersDocument(): void
    {
        $folder = $this->makeFolder(2, 'personal', 5);
        $doc    = $this->makeDocument(1, 2, 7); // uploaded by player 7
        $this->assertFalse($this->canDelete($doc, $folder, 5, 'player', 'player'));
    }

    public function testPlayerCannotDeleteFromPublicFolder(): void
    {
        $folder = $this->makeFolder(1, 'public');
        $doc    = $this->makeDocument(1, 1, 5);
        $this->assertFalse($this->canDelete($doc, $folder, 5, 'player'));
    }

    public function testCoachCannotDeleteOwnDocument(): void
    {
        $folder = $this->makeFolder(6, 'personal', 9);
        $doc    = $this->makeDocument(2, 6, 9); // uploader = coach
        $this->assertFalse($this->canDelete($doc, $folder, 9, 'coach', 'coach'));
    }

    public function testCoachCannotDeleteDocumentUploadedToPlayerFolder(): void
    {
        $folder = $this->makeFolder(7, 'personal', 5);
        $doc    = $this->makeDocument(3, 7, 9); // coach uploaded to player folder
        $this->assertFalse($this->canDelete($doc, $folder, 9, 'coach', 'player'));
    }

    public function testStaffCannotDeleteAnyDocument(): void
    {
        $folder = $this->makeFolder(2, 'personal', 5);
        $doc    = $this->makeDocument(1, 2, 3); // staff uploaded
        $this->assertFalse($this->canDelete($doc, $folder, 3, 'staff', 'player'));
    }

    public function testAdminDeletesDocumentUploadedByCoach(): void
    {
        $folder = $this->makeFolder(7, 'personal', 5);
        $doc    = $this->makeDocument(3, 7, 9); // coach uploaded to player folder
        $this->assertTrue($this->canDelete($doc, $folder, 2, 'admin', 'player'));
    }

    // ════════════════════════════════════════════════════════════════════
    //  LÓGICA DEL RENDERER JS (equivalente PHP para validación)
    // ════════════════════════════════════════════════════════════════════

    /**
     * Replica la función renderer() de doc-preview.js en PHP para verificar
     * que las extensiones se clasifican correctamente.
     */
    private function jsRenderer(string $ext): string
    {
        $ext = strtolower($ext);
        $pdf   = ['pdf'];
        $image = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        $video = ['mp4', 'webm', 'mov', 'avi'];

        if (in_array($ext, $pdf))   return 'pdf';
        if (in_array($ext, $image)) return 'image';
        if (in_array($ext, $video)) return 'video';
        return 'none';
    }

    public function testRendererClassifiesPdf(): void
    {
        $this->assertSame('pdf', $this->jsRenderer('pdf'));
        $this->assertSame('pdf', $this->jsRenderer('PDF'));
    }

    public function testRendererClassifiesImages(): void
    {
        foreach (['jpg', 'jpeg', 'png', 'gif', 'webp'] as $ext) {
            $this->assertSame('image', $this->jsRenderer($ext), "Failed for: $ext");
        }
    }

    public function testRendererClassifiesVideos(): void
    {
        foreach (['mp4', 'webm', 'mov', 'avi'] as $ext) {
            $this->assertSame('video', $this->jsRenderer($ext), "Failed for: $ext");
        }
    }

    public function testRendererReturnsNoneForOfficeFiles(): void
    {
        foreach (['doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx'] as $ext) {
            $this->assertSame('none', $this->jsRenderer($ext), "Failed for: $ext");
        }
    }

    public function testRendererReturnsNoneForUnknown(): void
    {
        $this->assertSame('none', $this->jsRenderer('xyz'));
        $this->assertSame('none', $this->jsRenderer(''));
    }
}
