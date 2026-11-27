<?php

use App\Models\NotificationModel;
use App\Services\StudentCommentNoticeService;
use CodeIgniter\Test\CIUnitTestCase;

final class StudentCommentNoticeTest extends CIUnitTestCase
{
    public function testMaximoDosEntrenadoresDistintosEmpezandoPorLaClaseMasCercana(): void
    {
        $rows = [['coach_id' => '7'], ['coach_id' => 7], ['coach_id' => null], ['coach_id' => 9], ['coach_id' => 11]];

        $this->assertSame([7, 9], StudentCommentNoticeService::pickCoaches($rows));
        $this->assertSame([], StudentCommentNoticeService::pickCoaches([]));
    }

    public function testElMensajeRecortaTextosLargosEIndicaElAdjunto(): void
    {
        $msg = StudentCommentNoticeService::buildMessage('Ana', str_repeat('á', 500), true);

        $this->assertStringContainsString('Ana', $msg['title']);
        $this->assertStringContainsString(str_repeat('á', 300) . '…', $msg['body']);
        $this->assertStringNotContainsString(str_repeat('á', 301), $msg['body']);
        $this->assertStringContainsString('archivo adjunto', $msg['body']);
    }

    public function testElAvisoEnlazaConLasObservacionesDelAlumno(): void
    {
        $data = NotificationModel::prepareSource(['source_type' => 'player', 'source_id' => 5], true);

        $this->assertSame('player', $data['source_type']);
        $this->assertSame('alumnos/5#anotaciones', NotificationModel::sourceLink($data)['path']);
    }
}
