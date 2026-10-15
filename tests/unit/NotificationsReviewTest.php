<?php

use App\Services\BonoControlService;
use App\Services\ClassNoticeService;
use App\Services\PushService;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Revisión de notificaciones (v2.0.0): anti-saturación del push, avisos de clase,
 * recordatorio 25 h antes y resumen de caducidades. Solo piezas puras (sin BD).
 */
final class NotificationsReviewTest extends CIUnitTestCase
{
    private static function job(int $notif, array $recipients): array
    {
        return ['notif' => $notif, 'recipients' => $recipients, 'payload' => ['id' => $notif, 'title' => 't' . $notif]];
    }

    // ── Anti-saturación: «Tienes N notificaciones nuevas» ───────────────

    public function testBelowThresholdNothingIsGrouped(): void
    {
        $queue = [self::job(1, [10]), self::job(2, [10])];
        $this->assertSame($queue, PushService::summarizeQueue($queue));
    }

    public function testThreeOrMoreForTheSamePersonBecomeOneSummary(): void
    {
        $out = PushService::summarizeQueue([self::job(1, [10]), self::job(2, [10]), self::job(3, [10])]);

        $this->assertCount(1, $out);
        $this->assertSame([10], $out[0]['recipients']);
        $this->assertSame('Tienes 3 notificaciones nuevas', $out[0]['payload']['title']);
        $this->assertSame('jp-group', $out[0]['payload']['tag']);
        $this->assertSame('notificaciones', $out[0]['payload']['url']);
    }

    public function testOnlyThePersonOverTheThresholdIsSummarized(): void
    {
        // Usuario 10 recibe 3; usuario 20 recibe 1 (de la tercera): conserva su aviso normal.
        $out = PushService::summarizeQueue([self::job(1, [10]), self::job(2, [10]), self::job(3, [10, 20])]);

        $normal = array_values(array_filter($out, static fn ($j) => ($j['payload']['tag'] ?? null) !== 'jp-group'));
        $groups = array_values(array_filter($out, static fn ($j) => ($j['payload']['tag'] ?? null) === 'jp-group'));

        $this->assertCount(1, $normal);
        $this->assertSame([20], $normal[0]['recipients']);
        $this->assertCount(1, $groups);
        $this->assertSame([10], $groups[0]['recipients']);
    }

    public function testPeopleWithTheSameCountShareOneSummaryAndDifferentCountsDoNot(): void
    {
        $queue = [
            self::job(1, [10, 20]), self::job(2, [10, 20]), self::job(3, [10, 20]), // 10 y 20: 3
            self::job(4, [10]),                                                      // 10: 4
        ];
        $groups = PushService::summarizeQueue($queue);
        $titles = array_map(static fn ($j) => $j['payload']['title'] . '|' . implode(',', $j['recipients']), $groups);
        sort($titles);

        $this->assertSame(['Tienes 3 notificaciones nuevas|20', 'Tienes 4 notificaciones nuevas|10'], $titles);
    }

    public function testChatMessagesOfOneConversationShareATag(): void
    {
        $p = PushService::buildPayload(7, [
            'title' => 'Nuevo mensaje de Ana', 'body' => 'hola',
            'source_type' => 'conversation', 'source_id' => 42,
        ]);
        $this->assertSame('jp-c-42', $p['tag']);

        $other = PushService::buildPayload(8, ['title' => 'x', 'body' => 'y', 'source_type' => 'ticket', 'source_id' => 3]);
        $this->assertArrayNotHasKey('tag', $other);
    }

    // ── Clases ──────────────────────────────────────────────────────────

    public function testWhenLabelSaysTodayTomorrowOrTheDate(): void
    {
        $s = ['session_date' => '2026-10-17', 'start_time' => '18:00:00'];
        $this->assertSame('hoy a las 18:00', ClassNoticeService::whenLabel($s, '2026-10-17'));
        $this->assertSame('mañana (17/10) a las 18:00', ClassNoticeService::whenLabel($s, '2026-10-16'));
        $this->assertSame('el 17/10 a las 18:00', ClassNoticeService::whenLabel($s, '2026-10-10'));
    }

    public function testDescribeChangesOnlyReportsDateTimeAndVenue(): void
    {
        $before = ['session_date' => '2026-10-17', 'start_time' => '18:00', 'end_time' => '19:00', 'title' => 'A', 'focus' => 'x'];

        $this->assertSame([], ClassNoticeService::describeChanges($before, $before + [], 'Campo 1', 'Campo 1'));
        $this->assertSame([], ClassNoticeService::describeChanges($before, ['focus' => 'otro'] + $before));

        $moved = ['session_date' => '2026-10-18', 'start_time' => '17:00', 'end_time' => '18:00'] + $before;
        $changes = ClassNoticeService::describeChanges($before, $moved, 'Campo 1', 'Campo 2');
        $this->assertCount(3, $changes);
        $this->assertStringContainsString('17/10/2026 → 18/10/2026', $changes[0]);
        $this->assertStringContainsString('18:00–19:00 → 17:00–18:00', $changes[1]);
        $this->assertStringContainsString('Campo 1 → Campo 2', $changes[2]);
    }

    public function testCreatedMessageIsDetailedForOneClassAndSummarizedForASeries(): void
    {
        $one = ClassNoticeService::createdMessage([['title' => 'Técnica', 'session_date' => '2026-10-20', 'start_time' => '18:00']], false);
        $this->assertSame('📅 Nueva clase: Técnica', $one['title']);
        $this->assertStringContainsString('Tienes una clase de "Técnica"', $one['body']);

        $series = ClassNoticeService::createdMessage([
            ['title' => 'Técnica', 'session_date' => '2026-10-20', 'start_time' => '18:00'],
            ['title' => 'Técnica', 'session_date' => '2026-10-27', 'start_time' => '18:00'],
            ['title' => 'Técnica', 'session_date' => '2026-11-03', 'start_time' => '18:00'],
        ], true);
        $this->assertSame('📅 3 clases nuevas: Técnica', $series['title']);
        $this->assertStringContainsString('Se te ha asignado 3 clases', $series['body']);
        $this->assertStringContainsString('desde el 20/10 hasta el 03/11', $series['body']);
    }

    // ── Recordatorio 25 h antes ─────────────────────────────────────────

    public function testReminderWindowIsBetweenOneAndTwentyFiveHours(): void
    {
        $now = strtotime('2026-10-16 12:00:00');
        $base = ['status' => 'scheduled', 'reminder_sent_at' => null, 'session_date' => '2026-10-17', 'start_time' => '12:00:00'];

        $this->assertTrue(ClassNoticeService::shouldRemind($base, $now), '24 h antes: toca');
        $this->assertTrue(ClassNoticeService::shouldRemind(['start_time' => '13:00:00'] + $base, $now), '25 h justas: toca');
        $this->assertFalse(ClassNoticeService::shouldRemind(['start_time' => '13:01:00'] + $base, $now), 'más de 25 h: todavía no');
        $this->assertFalse(ClassNoticeService::shouldRemind(['session_date' => '2026-10-16', 'start_time' => '12:30:00'] + $base, $now), 'queda media hora: demasiado tarde');
        $this->assertFalse(ClassNoticeService::shouldRemind(['session_date' => '2026-10-15'] + $base, $now), 'ya pasó');
    }

    public function testReminderIsSentOnlyOnceAndOnlyForScheduledClasses(): void
    {
        $now  = strtotime('2026-10-16 12:00:00');
        $base = ['status' => 'scheduled', 'reminder_sent_at' => null, 'session_date' => '2026-10-17', 'start_time' => '10:00:00'];

        $this->assertTrue(ClassNoticeService::shouldRemind($base, $now));
        $this->assertFalse(ClassNoticeService::shouldRemind(['reminder_sent_at' => '2026-10-16 11:00:00'] + $base, $now));
        $this->assertFalse(ClassNoticeService::shouldRemind(['status' => 'cancelled'] + $base, $now));
        $this->assertFalse(ClassNoticeService::shouldRemind(['status' => 'completed'] + $base, $now));
    }

    // ── Bonos ───────────────────────────────────────────────────────────

    public function testExpirySlotSplitsTheDayInTwo(): void
    {
        $this->assertSame('2026-10-16-am', BonoControlService::expirySlot(strtotime('2026-10-16 07:30:00')));
        $this->assertSame('2026-10-16-am', BonoControlService::expirySlot(strtotime('2026-10-16 13:59:00')));
        $this->assertSame('2026-10-16-pm', BonoControlService::expirySlot(strtotime('2026-10-16 14:00:00')));
        $this->assertSame('2026-10-17-am', BonoControlService::expirySlot(strtotime('2026-10-17 00:05:00')));
    }

    public function testExpiryDigestIsDetailedForOneBonoAndListedForSeveral(): void
    {
        $one = BonoControlService::expiryDigestMessage([
            ['player' => 'Ana', 'bono' => 'Bono 10', 'when' => '20/10/2026', 'left' => 3, 'after' => 2],
        ]);
        $this->assertSame('⏳ Bono a punto de caducar: Ana', $one['title']);
        $this->assertStringContainsString('2 clase(s) programadas', $one['body']);

        $items = [];
        foreach (['Ana', 'Beto', 'Carla', 'Dani', 'Eva', 'Fran', 'Gus', 'Hugo'] as $i => $name) {
            $items[] = ['player' => $name, 'bono' => 'B', 'when' => '20/10/2026', 'left' => $i + 1, 'after' => 0];
        }
        $many = BonoControlService::expiryDigestMessage($items);
        $this->assertSame('⏳ 8 bonos a punto de caducar', $many['title']);
        $this->assertStringContainsString('Ana (1 ses., caduca el 20/10/2026)', $many['body']);
        $this->assertStringContainsString('y 2 más', $many['body']);
        $this->assertStringNotContainsString('Hugo', $many['body']);
    }

    public function testGrantedMessageMentionsTheBonoAndItsExpiry(): void
    {
        $m = BonoControlService::grantedMessage('Bono 10 sesiones', 10, '2027-10-16');
        $this->assertSame('🎟️ Tienes un bono nuevo', $m['title']);
        $this->assertSame('Se te ha asignado el bono "Bono 10 sesiones" con 10 sesión(es), válido hasta el 16/10/2027.', $m['body']);

        $noExpiry = BonoControlService::grantedMessage('Suelto', 1, null);
        $this->assertStringNotContainsString('válido hasta', $noExpiry['body']);
    }
}
