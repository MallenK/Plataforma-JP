<?php

use CodeIgniter\Test\CIUnitTestCase;
use App\Controllers\ClasesController;

/**
 * Tests for the isAssignedOrAdmin authorization helper in ClasesController.
 *
 * These are pure-logic tests — no HTTP, no DB, no service calls.
 * We subclass the controller and stub currentRole()/currentUserId() to
 * exercise every branch of the authorization logic in isolation.
 */
final class ClasesControllerAuthTest extends CIUnitTestCase
{
    // ────────────────────────────────────────────────────────────────
    //  Helpers
    // ────────────────────────────────────────────────────────────────

    /**
     * Build a fake session array with the given coaches list.
     */
    private function makeSession(array $coachUserIds): array
    {
        $coaches = array_map(fn(int $id) => ['user_id' => $id], $coachUserIds);
        return ['id' => 99, 'title' => 'Test', 'coaches' => $coaches, 'players' => []];
    }

    /**
     * Build a stub controller with the given role and userId injected.
     */
    private function makeController(string $role, int $userId): ClasesController
    {
        $ctrl = new class($role, $userId) extends ClasesController {
            public function __construct(private string $fakeRole, private int $fakeUserId) {}
            protected function currentRole(): ?string  { return $this->fakeRole; }
            protected function currentUserId(): ?int   { return $this->fakeUserId; }
            // expose protected method for testing
            public function checkAccess(array $session): bool { return $this->isAssignedOrAdmin($session); }
        };
        return $ctrl;
    }

    // ────────────────────────────────────────────────────────────────
    //  superadmin / admin — always allowed
    // ────────────────────────────────────────────────────────────────

    public function testSuperadminCanAccessAnySession(): void
    {
        $ctrl    = $this->makeController('superadmin', 1);
        $session = $this->makeSession([5, 6]); // user 1 NOT in coaches
        $this->assertTrue($ctrl->checkAccess($session));
    }

    public function testAdminCanAccessAnySession(): void
    {
        $ctrl    = $this->makeController('admin', 2);
        $session = $this->makeSession([5, 6]);
        $this->assertTrue($ctrl->checkAccess($session));
    }

    // ────────────────────────────────────────────────────────────────
    //  coach — only if assigned
    // ────────────────────────────────────────────────────────────────

    public function testCoachAllowedWhenAssigned(): void
    {
        $ctrl    = $this->makeController('coach', 10);
        $session = $this->makeSession([10, 11]);
        $this->assertTrue($ctrl->checkAccess($session));
    }

    public function testCoachDeniedWhenNotAssigned(): void
    {
        $ctrl    = $this->makeController('coach', 99);
        $session = $this->makeSession([10, 11]);
        $this->assertFalse($ctrl->checkAccess($session));
    }

    public function testCoachDeniedWhenSessionHasNoCoaches(): void
    {
        $ctrl    = $this->makeController('coach', 10);
        $session = $this->makeSession([]);
        $this->assertFalse($ctrl->checkAccess($session));
    }

    // ────────────────────────────────────────────────────────────────
    //  staff — only if assigned (same table as coach)
    // ────────────────────────────────────────────────────────────────

    public function testStaffAllowedWhenAssigned(): void
    {
        $ctrl    = $this->makeController('staff', 20);
        $session = $this->makeSession([20, 21]);
        $this->assertTrue($ctrl->checkAccess($session));
    }

    public function testStaffDeniedWhenNotAssigned(): void
    {
        $ctrl    = $this->makeController('staff', 99);
        $session = $this->makeSession([20, 21]);
        $this->assertFalse($ctrl->checkAccess($session));
    }

    public function testStaffDeniedWhenSessionHasNoCoaches(): void
    {
        $ctrl    = $this->makeController('staff', 20);
        $session = $this->makeSession([]);
        $this->assertFalse($ctrl->checkAccess($session));
    }

    // ────────────────────────────────────────────────────────────────
    //  player / alumno — never allowed via this method
    // ────────────────────────────────────────────────────────────────

    public function testPlayerAlwaysDenied(): void
    {
        $ctrl    = $this->makeController('player', 30);
        $session = $this->makeSession([30]); // even if somehow in coaches list
        $this->assertFalse($ctrl->checkAccess($session));
    }

    public function testAlumnoAlwaysDenied(): void
    {
        $ctrl    = $this->makeController('alumno', 31);
        $session = $this->makeSession([31]);
        $this->assertFalse($ctrl->checkAccess($session));
    }

    // ────────────────────────────────────────────────────────────────
    //  Type coercion — user_id stored as string in DB row
    // ────────────────────────────────────────────────────────────────

    public function testCoachMatchesWhenUserIdIsString(): void
    {
        // DB rows often come back as strings — int cast must handle this
        $ctrl    = $this->makeController('coach', 10);
        $session = ['id' => 1, 'coaches' => [['user_id' => '10']], 'players' => []];
        $this->assertTrue($ctrl->checkAccess($session));
    }

    public function testStaffMatchesWhenUserIdIsString(): void
    {
        $ctrl    = $this->makeController('staff', 20);
        $session = ['id' => 1, 'coaches' => [['user_id' => '20']], 'players' => []];
        $this->assertTrue($ctrl->checkAccess($session));
    }
}
