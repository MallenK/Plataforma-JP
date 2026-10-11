<?php

use App\Services\ClasesService;
use App\Services\RevisionService;
use App\Services\TestDataService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Control de clases (2026-10-10): toda clase necesita al menos un alumno y un
 * entrenador; las incompletas se señalan en Finanzas › Revisión y los alumnos
 * de prueba de antes del lanzamiento se identifican por el nombre.
 *
 * @internal
 */
final class ClasesIncompletasTest extends CIUnitTestCase
{
    public function testParticipantsErrorRequiresCoachAndPlayer(): void
    {
        $this->assertNull(ClasesService::participantsError([3], [7]));
        $this->assertNull(ClasesService::participantsError(['3'], ['7', '']));
        $this->assertStringContainsString('entrenador y un alumno', ClasesService::participantsError([], []));
        $this->assertStringContainsString('entrenador y un alumno', ClasesService::participantsError([''], null));
        $this->assertStringContainsString('responsable', ClasesService::participantsError([''], [7]));
        $this->assertStringContainsString('alumno', ClasesService::participantsError([3], ['0']));
    }

    public function testCreateSessionRejectsClassWithoutParticipants(): void
    {
        $base = ['type' => 'single', 'title' => 'X', 'session_date' => '2026-01-10', 'start_time' => '10:00'];
        $res  = (new ClasesService())->createSession($base + ['coach_ids' => [3]], 1);
        $this->assertFalse($res['success']);
        $this->assertStringContainsString('alumno', $res['error']);

        $res = (new ClasesService())->quickCreate($base + ['player_ids' => [7]], 1);
        $this->assertFalse($res['success']);
        $this->assertStringContainsString('responsable', $res['error']);
    }

    public function testEmptySessionDeletionBlocker(): void
    {
        $this->assertNull(ClasesService::emptySessionDeletionBlocker(0, 0));
        $this->assertNotNull(ClasesService::emptySessionDeletionBlocker(1, 0));
        $this->assertNotNull(ClasesService::emptySessionDeletionBlocker(0, 2));
    }

    public function testIncompleteKind(): void
    {
        $this->assertSame(RevisionService::INCOMPLETE_EMPTY, RevisionService::incompleteKind(0, 0, 'scheduled'));
        $this->assertSame(RevisionService::INCOMPLETE_NO_PLAYER, RevisionService::incompleteKind(0, 1, 'completed'));
        $this->assertSame(RevisionService::INCOMPLETE_NO_COACH, RevisionService::incompleteKind(2, 0, 'completed'));
        $this->assertNull(RevisionService::incompleteKind(1, 1, 'scheduled'));
        // Cancelada sin entrenador pero con alumnos: no se impartió, da igual.
        $this->assertNull(RevisionService::incompleteKind(1, 0, 'cancelled'));
        // Cancelada sin alumnos: resto sin valor, se señala.
        $this->assertSame(RevisionService::INCOMPLETE_EMPTY, RevisionService::incompleteKind(0, 0, 'cancelled'));
    }

    public function testClassBucketsGroupByWhatToDo(): void
    {
        $unclosed = [
            ['id' => 1, 'marked' => 0, 'lista_pasada_at' => null],
            ['id' => 2, 'marked' => 3, 'lista_pasada_at' => null],
            ['id' => 3, 'marked' => 0, 'lista_pasada_at' => '2026-10-01 10:00:00'],
        ];
        $incomplete = [
            ['id' => 4, 'kind' => RevisionService::INCOMPLETE_NO_COACH],
            ['id' => 5, 'kind' => RevisionService::INCOMPLETE_NO_PLAYER],
            ['id' => 6, 'kind' => RevisionService::INCOMPLETE_EMPTY],
        ];
        $b = RevisionService::classBuckets($unclosed, $incomplete);

        $ids = static fn(array $rows) => array_column($rows, 'id');
        $this->assertSame([1], $ids($b[RevisionService::BUCKET_NO_LIST]));
        $this->assertSame([2, 3], $ids($b[RevisionService::BUCKET_TO_CLOSE]));
        $this->assertSame([4], $ids($b[RevisionService::BUCKET_NO_COACH]));
        $this->assertSame([5, 6], $ids($b[RevisionService::BUCKET_EMPTY]));
        $this->assertSame(['sin_lista', 'sin_cerrar', 'sin_entrenador', 'vacias'], array_keys($b));
    }

    public function testIsTestName(): void
    {
        foreach (['Alumno Prueba', 'Prueba', 'Rtrt', 'prueba prueba', '  RTRT  ', 'Alumna Pruebas', 'Rtrt Rtrt'] as $n) {
            $this->assertTrue(TestDataService::isTestName($n), $n);
        }
        foreach (['Pruebas García', 'Alumno', 'Marc Puig', '', null, 'Rtrt Ruiz', 'Pruebaa'] as $n) {
            $this->assertFalse(TestDataService::isTestName($n), (string) $n);
        }
    }
}
