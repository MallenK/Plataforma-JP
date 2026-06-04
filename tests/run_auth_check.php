<?php
/**
 * Standalone test for isAssignedOrAdmin logic.
 * No PHPUnit, no CI4 bootstrap. Runs with PHP 8.0+.
 */

// ── inline copy of the logic under test ──────────────────────────────────────

function isAssignedOrAdmin(string $role, int $userId, array $session): bool
{
    if (in_array($role, ['superadmin', 'admin'])) {
        return true;
    }

    if ($role === 'coach' || $role === 'staff') {
        foreach ($session['coaches'] as $c) {
            if ((int)$c['user_id'] === $userId) {
                return true;
            }
        }
        return false;
    }

    return false;
}

// ── helpers ───────────────────────────────────────────────────────────────────

function makeSession(array $coachUserIds): array
{
    return ['coaches' => array_map(fn($id) => ['user_id' => $id], $coachUserIds)];
}

$pass = 0;
$fail = 0;

function check(string $label, bool $result, bool $expected): void
{
    global $pass, $fail;
    $ok = $result === $expected;
    $ok ? $pass++ : $fail++;
    $symbol = $ok ? 'PASS' : 'FAIL';
    $exp    = $expected ? 'true' : 'false';
    $got    = $result   ? 'true' : 'false';
    echo sprintf("[%s] %s\n", $symbol, $label);
    if (!$ok) {
        echo "       expected=$exp  got=$got\n";
    }
}

// ── tests ─────────────────────────────────────────────────────────────────────

// superadmin/admin — always allowed
check('superadmin can access any session',
    isAssignedOrAdmin('superadmin', 1, makeSession([5, 6])), true);

check('admin can access any session',
    isAssignedOrAdmin('admin', 2, makeSession([5, 6])), true);

check('superadmin with empty coaches list',
    isAssignedOrAdmin('superadmin', 1, makeSession([])), true);

// coach — only if assigned
check('coach allowed when assigned',
    isAssignedOrAdmin('coach', 10, makeSession([10, 11])), true);

check('coach denied when not assigned',
    isAssignedOrAdmin('coach', 99, makeSession([10, 11])), false);

check('coach denied when session has no coaches',
    isAssignedOrAdmin('coach', 10, makeSession([])), false);

// staff — only if assigned
check('staff allowed when assigned',
    isAssignedOrAdmin('staff', 20, makeSession([20, 21])), true);

check('staff denied when not assigned',
    isAssignedOrAdmin('staff', 99, makeSession([20, 21])), false);

check('staff denied when session has no coaches',
    isAssignedOrAdmin('staff', 20, makeSession([])), false);

// player/alumno — never allowed
check('player always denied (even if in coaches list)',
    isAssignedOrAdmin('player', 30, makeSession([30])), false);

check('alumno always denied',
    isAssignedOrAdmin('alumno', 31, makeSession([31])), false);

// type coercion — DB returns user_id as string
check('coach matches when user_id is string in DB row',
    isAssignedOrAdmin('coach', 10, ['coaches' => [['user_id' => '10']]]), true);

check('staff matches when user_id is string in DB row',
    isAssignedOrAdmin('staff', 20, ['coaches' => [['user_id' => '20']]]), true);

check('coach not matched when user_id string does not match',
    isAssignedOrAdmin('coach', 10, ['coaches' => [['user_id' => '11']]]), false);

// ── summary ───────────────────────────────────────────────────────────────────

echo "\n";
echo "Results: {$pass} passed, {$fail} failed\n";
exit($fail > 0 ? 1 : 0);
