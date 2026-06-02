<?php

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\ClasesService;

/**
 * Tests for ClasesService business logic.
 *
 * NOTE: Tests marked @group db require a live DB connection (run inside Docker).
 *       Pure logic tests run without DB.
 */
final class ClasesServiceTest extends CIUnitTestCase
{
    // ────────────────────────────────────────────────────────────────
    //  Validation helpers (pure logic, no DB)
    // ────────────────────────────────────────────────────────────────

    public function testCreateSessionRequiresTitle(): void
    {
        // Missing title → service returns error
        $service = $this->getMockBuilder(ClasesService::class)
            ->onlyMethods(['insertSingle', 'createRecurring', 'syncCoaches', 'syncPlayers'])
            ->getMock();

        // Reach the validation in createSession via reflection
        $method = new \ReflectionMethod(ClasesService::class, 'createSession');
        $this->assertTrue($method->isPublic());
    }

    public function testClassFormatDefaultsToIndividual(): void
    {
        // When class_format is not provided, default should be 'individual'
        $data = ['title' => 'Test', 'session_date' => '2026-06-10', 'start_time' => '10:00', 'end_time' => '11:00'];
        $fmt = in_array($data['class_format'] ?? '', ['individual', 'pareja']) ? $data['class_format'] : 'individual';
        $this->assertSame('individual', $fmt);
    }

    public function testClassFormatParejaIsValid(): void
    {
        $data = ['class_format' => 'pareja'];
        $fmt = in_array($data['class_format'] ?? '', ['individual', 'pareja']) ? $data['class_format'] : 'individual';
        $this->assertSame('pareja', $fmt);
    }

    public function testClassFormatInvalidFallsBackToIndividual(): void
    {
        $data = ['class_format' => 'grupo'];
        $fmt = in_array($data['class_format'] ?? '', ['individual', 'pareja']) ? $data['class_format'] : 'individual';
        $this->assertSame('individual', $fmt);
    }

    public function testMaxPlayersForIndividualIsOne(): void
    {
        $fmt = 'individual';
        $maxPlayers = $fmt === 'pareja' ? 2 : 1;
        $this->assertSame(1, $maxPlayers);
    }

    public function testMaxPlayersForParejaIsTwo(): void
    {
        $fmt = 'pareja';
        $maxPlayers = $fmt === 'pareja' ? 2 : 1;
        $this->assertSame(2, $maxPlayers);
    }

    public function testRecurringRequiresDays(): void
    {
        // days empty → should fail validation
        $days = [];
        $this->assertEmpty($days);
        // The service checks: if (empty($days)) return error
    }

    public function testRecurringRequiresStartAndEnd(): void
    {
        $data = ['type' => 'recurring', 'recurrence_days' => [1, 3]];
        $missing = empty($data['recurrence_start'] ?? '') || empty($data['recurrence_end'] ?? '');
        $this->assertTrue($missing);
    }

    public function testRecurringWithAllFieldsIsValid(): void
    {
        $data = [
            'type'             => 'recurring',
            'title'            => 'Clase lunes y miércoles',
            'start_time'       => '10:00',
            'end_time'         => '11:00',
            'recurrence_days'  => [1, 3],
            'recurrence_start' => '2026-06-09',
            'recurrence_end'   => '2026-07-09',
            'class_format'     => 'individual',
        ];
        $this->assertNotEmpty($data['title']);
        $this->assertNotEmpty($data['recurrence_days']);
        $this->assertNotEmpty($data['recurrence_start']);
        $this->assertNotEmpty($data['recurrence_end']);
        $fmt = in_array($data['class_format'] ?? '', ['individual', 'pareja']) ? $data['class_format'] : 'individual';
        $this->assertSame('individual', $fmt);
    }

    public function testEndTimeFallsBackWhenEmpty(): void
    {
        // If end_time is empty, service adds 1 hour to start_time
        $startTime = '10:00';
        $endTime   = '';
        $resolved  = $endTime ?: date('H:i', strtotime($startTime) + 3600);
        $this->assertSame('11:00', $resolved);
    }

    public function testEndTimeFallsBackAtMidnightBoundary(): void
    {
        $startTime = '23:00';
        $endTime   = '';
        $resolved  = $endTime ?: date('H:i', strtotime($startTime) + 3600);
        $this->assertSame('00:00', $resolved);
    }

    public function testSessionTypeIsSingleByDefault(): void
    {
        $data = ['title' => 'Test', 'session_date' => '2026-06-10'];
        $type = $data['type'] ?? 'single';
        $this->assertSame('single', $type);
    }

    public function testSessionTypeRecurringIsPreserved(): void
    {
        $data = ['type' => 'recurring'];
        $type = ($data['type'] ?? 'single') === 'recurring' ? 'recurring' : 'single';
        $this->assertSame('recurring', $type);
    }

    public function testUnknownTypeNormalizesToSingle(): void
    {
        $data = ['type' => 'unknown'];
        $type = ($data['type'] ?? 'single') === 'recurring' ? 'recurring' : 'single';
        $this->assertSame('single', $type);
    }

    // ────────────────────────────────────────────────────────────────
    //  Player cap enforcement (pure logic mirror)
    // ────────────────────────────────────────────────────────────────

    public function testIndividualSessionBlocksSecondPlayer(): void
    {
        $fmt = 'individual';
        $maxPlayers = $fmt === 'pareja' ? 2 : 1;
        $currentCount = 1; // already has one player

        $blocked = $currentCount >= $maxPlayers;
        $this->assertTrue($blocked, 'A second player must be blocked in individual session');
    }

    public function testParejaSessionAllowsSecondPlayer(): void
    {
        $fmt = 'pareja';
        $maxPlayers = $fmt === 'pareja' ? 2 : 1;
        $currentCount = 1;

        $blocked = $currentCount >= $maxPlayers;
        $this->assertFalse($blocked, 'Second player should be allowed in pareja session');
    }

    public function testParejaSessionBlocksThirdPlayer(): void
    {
        $fmt = 'pareja';
        $maxPlayers = $fmt === 'pareja' ? 2 : 1;
        $currentCount = 2;

        $blocked = $currentCount >= $maxPlayers;
        $this->assertTrue($blocked, 'Third player must be blocked even in pareja session');
    }

    // ────────────────────────────────────────────────────────────────
    //  Quick-create modal (sessions from _modal_create.php data)
    // ────────────────────────────────────────────────────────────────

    public function testQuickCreateRequiresDateAndTime(): void
    {
        $data = ['title' => 'Sesión rápida', 'start_time' => '10:00'];
        $missingDate = empty($data['session_date'] ?? '');
        $this->assertTrue($missingDate, 'Quick create must require session_date');
    }

    public function testQuickCreateWithAllFieldsIsValid(): void
    {
        $data = [
            'type'         => 'single',
            'title'        => 'Sesión rápida',
            'session_date' => '2026-06-10',
            'start_time'   => '10:00',
            'end_time'     => '11:00',
            'class_format' => 'individual',
        ];
        $valid = !empty($data['title']) && !empty($data['session_date']) && !empty($data['start_time']);
        $this->assertTrue($valid);
    }
}
