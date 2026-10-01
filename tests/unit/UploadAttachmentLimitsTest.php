<?php

namespace Tests\Unit;

use App\Controllers\ClasesController;
use CodeIgniter\Test\CIUnitTestCase;

class UploadAttachmentLimitsTest extends CIUnitTestCase
{
    private ClasesController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new ClasesController();
    }

    public function testAttachmentLimitsPublicConstants()
    {
        // Las constantes deben ser públicas para que attach-upload.js las vea.
        $this->assertTrue(defined('App\Controllers\ClasesController::ATTACHMENT_MAX_DEFAULT_BYTES'));
        $this->assertTrue(defined('App\Controllers\ClasesController::ATTACHMENT_MAX_VIDEO_BYTES'));
    }

    public function testAttachmentLimitsDefaultValues()
    {
        // 5 MB por defecto para imágenes/documentos, 500 MB para vídeo.
        $this->assertSame(5 * 1024 * 1024, ClasesController::ATTACHMENT_MAX_DEFAULT_BYTES);
        $this->assertSame(500 * 1024 * 1024, ClasesController::ATTACHMENT_MAX_VIDEO_BYTES);
    }

    public function testAttachmentLimitsFunctionExists()
    {
        // Método estático que devuelve un array con 'video' y 'other'.
        $limits = ClasesController::attachmentLimits();
        $this->assertIsArray($limits);
        $this->assertArrayHasKey('video', $limits);
        $this->assertArrayHasKey('other', $limits);
    }

    public function testAttachmentLimitsRespectPHPini()
    {
        // Los límites devueltos deben ser ≤ a post_max_size y upload_max_filesize.
        $limits = ClasesController::attachmentLimits();

        $postMax = ini_parse_quantity((string) ini_get('post_max_size'));
        $uploadMax = ini_parse_quantity((string) ini_get('upload_max_filesize'));
        $phpCap = $postMax > 0 && $uploadMax > 0 ? min($postMax, $uploadMax) : null;

        // Si no hay límites de PHP, debemos devolver los de la app (sin capeo).
        if ($phpCap !== null) {
            $this->assertLessThanOrEqual($phpCap, $limits['video']);
            $this->assertLessThanOrEqual($phpCap, $limits['other']);
        }

        // En local el .htaccess normalmente pone 520 MB (post_max_size).
        // En prod puede ser distinto, pero siempre ≥ a los límites de la app.
    }

    public function testAttachmentLimitsAlwaysVideo()
    {
        $limits = ClasesController::attachmentLimits();
        // El límite de vídeo nunca debe ser menor que el de otros archivos.
        $this->assertGreaterThanOrEqual($limits['other'], $limits['video']);
    }
}
