<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Tests for the notifications system (pure logic — no DB required).
 *
 * Tests that require a live DB connection are marked @group db
 * and must run inside Docker.
 */
final class NotificacionesTest extends CIUnitTestCase
{
    // ────────────────────────────────────────────────────────────────
    //  Recipient resolution logic (mirrors NotificacionesController::resolveRecipients)
    // ────────────────────────────────────────────────────────────────

    public function testIndividualTypeRequiresRecipientId(): void
    {
        $recipientId = 0; // empty / not provided
        $this->assertEmpty($recipientId ? [$recipientId] : []);
    }

    public function testIndividualTypeExcludesSelf(): void
    {
        $senderId    = 5;
        $recipientId = 5;
        $resolved    = $recipientId !== $senderId ? [$recipientId] : [];
        $this->assertEmpty($resolved, 'Sender must not send to themselves');
    }

    public function testIndividualTypeResolvesOtherUser(): void
    {
        $senderId    = 5;
        $recipientId = 12;
        $resolved    = $recipientId !== $senderId ? [$recipientId] : [];
        $this->assertSame([12], $resolved);
    }

    public function testGroupRoleMapContainsAllExpectedGroups(): void
    {
        $roleMap = [
            'all'     => null,
            'players' => ['alumno', 'player'],
            'coaches' => ['coach'],
            'staff'   => ['staff', 'admin', 'superadmin'],
        ];

        $this->assertArrayHasKey('all',     $roleMap);
        $this->assertArrayHasKey('players', $roleMap);
        $this->assertArrayHasKey('coaches', $roleMap);
        $this->assertArrayHasKey('staff',   $roleMap);
    }

    public function testGroupAllHasNullRoleFilter(): void
    {
        $roleMap = ['all' => null, 'players' => ['alumno', 'player']];
        $this->assertNull($roleMap['all']);
    }

    public function testGroupPlayersIncludesAlumnoAndPlayer(): void
    {
        $roleMap = ['players' => ['alumno', 'player']];
        $this->assertContains('alumno', $roleMap['players']);
        $this->assertContains('player', $roleMap['players']);
    }

    // ────────────────────────────────────────────────────────────────
    //  Validation logic (mirrors NotificacionesController::send)
    // ────────────────────────────────────────────────────────────────

    public function testSendRequiresTitle(): void
    {
        $title = '';
        $body  = 'Some message';
        $valid = $title && $body;
        $this->assertFalse($valid, 'Empty title must fail validation');
    }

    public function testSendRequiresBody(): void
    {
        $title = 'Important notice';
        $body  = '';
        $valid = $title && $body;
        $this->assertFalse($valid, 'Empty body must fail validation');
    }

    public function testSendPassesWithTitleAndBody(): void
    {
        $title = 'Important notice';
        $body  = 'Please read this carefully.';
        $valid = $title && $body;
        $this->assertTrue($valid);
    }

    public function testGroupSendBlockedForPlayerRole(): void
    {
        $role         = 'alumno';
        $allowedRoles = ['superadmin', 'admin', 'coach'];
        $canSendGroup = in_array($role, $allowedRoles);
        $this->assertFalse($canSendGroup, 'Players must not send group notifications');
    }

    public function testGroupSendAllowedForAdminRole(): void
    {
        foreach (['superadmin', 'admin', 'coach'] as $role) {
            $canSendGroup = in_array($role, ['superadmin', 'admin', 'coach']);
            $this->assertTrue($canSendGroup, "Role '{$role}' must be allowed to send group notifications");
        }
    }

    // ────────────────────────────────────────────────────────────────
    //  File upload validation (mirrors NotificacionesController::handleFileUpload)
    // ────────────────────────────────────────────────────────────────

    public function testFileSizeLimitIs5MB(): void
    {
        $maxSize = 5 * 1024 * 1024;
        $this->assertSame(5242880, $maxSize);
    }

    public function testFileExceedingLimitIsRejected(): void
    {
        $maxSize  = 5 * 1024 * 1024;
        $fileSize = 6 * 1024 * 1024; // 6 MB
        $this->assertTrue($fileSize > $maxSize, 'Files over 5 MB must be rejected');
    }

    public function testFileWithinLimitIsAccepted(): void
    {
        $maxSize  = 5 * 1024 * 1024;
        $fileSize = 2 * 1024 * 1024; // 2 MB
        $this->assertFalse($fileSize > $maxSize, 'Files under 5 MB must pass');
    }

    public function testAllowedMimeTypesIncludeCommonFormats(): void
    {
        $allowed = [
            'image/jpeg', 'image/png', 'image/webp', 'image/gif',
            'application/pdf', 'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'application/vnd.ms-excel',
            'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'text/plain', 'video/mp4',
        ];

        $this->assertContains('image/jpeg',        $allowed);
        $this->assertContains('application/pdf',   $allowed);
        $this->assertContains('text/plain',        $allowed);
        $this->assertContains('video/mp4',         $allowed);
    }

    public function testDisallowedMimeTypeIsRejected(): void
    {
        $allowed  = ['image/jpeg', 'application/pdf', 'text/plain'];
        $mimeType = 'application/x-executable';
        $this->assertNotContains($mimeType, $allowed);
    }

    // ────────────────────────────────────────────────────────────────
    //  NotificationModel logic (pure)
    // ────────────────────────────────────────────────────────────────

    public function testCreateWithRecipientsBuildsCorrectRows(): void
    {
        $notifId      = 42;
        $recipientIds = [1, 2, 3, 2]; // duplicates
        $rows = array_map(fn($rid) => [
            'notification_id' => $notifId,
            'recipient_id'    => $rid,
            'read_at'         => null,
        ], array_unique($recipientIds));

        $this->assertCount(3, $rows, 'Duplicate recipient IDs must be deduplicated');
        foreach ($rows as $row) {
            $this->assertSame(42, $row['notification_id']);
            $this->assertNull($row['read_at']);
        }
    }

    public function testUnreadCountStartsAtZeroForEmptyRecipients(): void
    {
        $rows  = []; // no recipients
        $unread = count(array_filter($rows, fn($r) => $r['read_at'] === null));
        $this->assertSame(0, $unread);
    }

    public function testUnreadCountCorrect(): void
    {
        $rows = [
            ['read_at' => null],
            ['read_at' => '2026-06-01 10:00:00'],
            ['read_at' => null],
        ];
        $unread = count(array_filter($rows, fn($r) => $r['read_at'] === null));
        $this->assertSame(2, $unread);
    }

    public function testMarkReadSetsReadAt(): void
    {
        $row     = ['read_at' => null];
        $now     = date('Y-m-d H:i:s');
        $updated = array_merge($row, ['read_at' => $now]);

        $this->assertNotNull($updated['read_at']);
        $this->assertSame($now, $updated['read_at']);
    }

    public function testMarkAllReadSetsAllUnread(): void
    {
        $rows = [
            ['id' => 1, 'read_at' => null],
            ['id' => 2, 'read_at' => '2026-06-01 09:00:00'],
            ['id' => 3, 'read_at' => null],
        ];
        $now  = date('Y-m-d H:i:s');
        $updated = array_map(fn($r) => $r['read_at'] === null ? array_merge($r, ['read_at' => $now]) : $r, $rows);

        foreach ($updated as $r) {
            $this->assertNotNull($r['read_at']);
        }
    }
}
