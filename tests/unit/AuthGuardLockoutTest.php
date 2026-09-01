<?php

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\AuthGuardService;
use App\Models\AuthEventModel;
use App\Models\SettingsModel;

/**
 * Bloqueo por fuerza bruta y límite de recuperación de contraseña.
 * Usa un AuthEventModel en memoria (sin BD) para poder afirmar sobre la
 * lógica de conteo por ventana / por sujeto.
 */
final class AuthGuardLockoutTest extends CIUnitTestCase
{
    private const IDENT = 'bruteforce@test.local';
    private const IP    = '203.0.113.77';

    private FakeAuthEventModel $events;
    private AuthGuardService $guard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->events = new FakeAuthEventModel();
        $this->guard  = new AuthGuardService($this->events, new FakeSettingsModel());
    }

    private function seed(string $type, int $agoSec = 0, ?string $ident = null, ?string $ip = null): void
    {
        $this->events->rows[] = [
            'event_type' => $type,
            'identifier' => $ident ?? self::IDENT,
            'ip_address' => $ip ?? self::IP,
            'created_at' => date('Y-m-d H:i:s', time() - $agoSec),
        ];
    }

    public function testNoBloqueaConPocosFallos(): void
    {
        $this->seed('login_fail');
        $this->seed('login_fail');
        $this->assertNull($this->guard->loginLockState(self::IDENT, self::IP));
    }

    public function testBloqueaTrasCincoFallosDeCuenta(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->seed('login_fail');
        }
        $lock = $this->guard->loginLockState(self::IDENT, self::IP);
        $this->assertNotNull($lock);
        $this->assertSame('account', $lock['scope']);
        $this->assertGreaterThan(0, $lock['retryAfter']);
    }

    public function testUnLoginCorrectoResetsElContador(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->seed('login_fail', 120);
        }
        $this->seed('login_success', 60);
        $this->seed('login_fail', 0);
        $this->assertNull(
            $this->guard->loginLockState(self::IDENT, self::IP),
            'Los fallos anteriores al último login correcto no cuentan'
        );
    }

    public function testFallosFueraDeLaVentanaNoCuentan(): void
    {
        for ($i = 0; $i < 6; $i++) {
            $this->seed('login_fail', 3600);
        }
        $this->assertNull($this->guard->loginLockState(self::IDENT, self::IP));
    }

    public function testBloqueaPorIpConMuchosFallosDistintasCuentas(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->seed('login_fail', 0, "victima{$i}@test.local");
        }
        $lock = $this->guard->loginLockState('otra@test.local', self::IP);
        $this->assertNotNull($lock);
        $this->assertSame('ip', $lock['scope']);
    }

    public function testResetRequestThrottlePorIntervaloCorto(): void
    {
        $this->seed('pwreset_request');
        $wait = $this->guard->resetRequestThrottle(self::IDENT, self::IP);
        $this->assertNotNull($wait);
        $this->assertGreaterThan(0, $wait);
    }

    public function testResetRequestPermitidoTrasElIntervalo(): void
    {
        $this->seed('pwreset_request', 200);
        $this->assertNull($this->guard->resetRequestThrottle(self::IDENT, self::IP));
    }

    public function testResetRequestLimitadoPorHoraDeEmail(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->seed('pwreset_request', 300 + $i * 60);
        }
        $this->assertNotNull($this->guard->resetRequestThrottle(self::IDENT, self::IP));
    }

    public function testResetAttemptThrottlePorIp(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->seed('pwreset_fail');
        }
        $this->assertTrue($this->guard->resetAttemptThrottled(self::IP));
    }

    public function testPasswordChangeThrottle(): void
    {
        for ($i = 0; $i < 5; $i++) {
            $this->seed('pwchange_fail', 0, 'uid:42');
        }
        $this->assertTrue($this->guard->passwordChangeThrottled(42));
        $this->assertFalse($this->guard->passwordChangeThrottled(99));
    }
}

/**
 * AuthEventModel en memoria: solo implementa lo que usa AuthGuardService.
 */
class FakeAuthEventModel extends AuthEventModel
{
    /** @var array<int,array<string,string>> */
    public array $rows = [];

    public function __construct()
    {
        // No llamar al constructor de Model (evita conexión a BD).
    }

    public function insert($row = null, bool $returnID = true)
    {
        $this->rows[] = (array) $row;
        return 1;
    }

    public function countRecent(string $eventType, string $column, string $value, int $minutes, ?string $since = null): int
    {
        $cutoff = time() - $minutes * 60;
        $n = 0;
        foreach ($this->rows as $r) {
            if (($r['event_type'] ?? null) !== $eventType) continue;
            if (($r[$column] ?? null) !== $value) continue;
            $ts = strtotime($r['created_at']);
            if ($ts < $cutoff) continue;
            if ($since !== null && $ts <= strtotime($since)) continue;
            $n++;
        }
        return $n;
    }

    public function lastAt(string $eventType, string $column, string $value): ?string
    {
        $last = null;
        foreach ($this->rows as $r) {
            if (($r['event_type'] ?? null) !== $eventType) continue;
            if (($r[$column] ?? null) !== $value) continue;
            if ($last === null || strtotime($r['created_at']) > strtotime($last)) {
                $last = $r['created_at'];
            }
        }
        return $last;
    }
}

class FakeSettingsModel extends SettingsModel
{
    public function __construct() {}
    public function get(string $key, $default = null): mixed { return $default; }
    public function getAll(): array { return []; }
}
