<?php

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\AuthGuardService;

/**
 * Lógica pura de AuthGuardService: generación de contraseñas temporales,
 * lista de comunes, validación de contraseña nueva y verificación en
 * tiempo constante. (El bloqueo por fuerza bruta, que necesita BD, se
 * cubre en AuthGuardDbTest.)
 */
final class AuthGuardServiceTest extends CIUnitTestCase
{
    private AuthGuardService $guard;

    protected function setUp(): void
    {
        parent::setUp();
        $this->guard = new AuthGuardService();
    }

    // ── generateTempPassword ───────────────────────────────────────

    public function testTempPasswordEsFuerte(): void
    {
        for ($i = 0; $i < 50; $i++) {
            $pw = $this->guard->generateTempPassword();
            $this->assertGreaterThanOrEqual(12, strlen($pw));
            $this->assertMatchesRegularExpression('/[a-z]/', $pw, 'debe tener minúscula');
            $this->assertMatchesRegularExpression('/[A-Z]/', $pw, 'debe tener mayúscula');
            $this->assertMatchesRegularExpression('/[0-9]/', $pw, 'debe tener dígito');
            // sin caracteres ambiguos
            $this->assertDoesNotMatchRegularExpression('/[0O1lI]/', $pw);
            // sin el prefijo/sufijo fijo del generador antiguo
            $this->assertStringStartsNotWith('Jp', $pw);
        }
    }

    public function testTempPasswordsSonDistintas(): void
    {
        $a = $this->guard->generateTempPassword();
        $b = $this->guard->generateTempPassword();
        $this->assertNotSame($a, $b);
    }

    public function testTempPasswordNoEsComun(): void
    {
        $pw = $this->guard->generateTempPassword();
        $this->assertFalse($this->guard->isCommonPassword($pw));
    }

    // ── isCommonPassword ───────────────────────────────────────────

    public function testDetectaContrasenasComunes(): void
    {
        $this->assertTrue($this->guard->isCommonPassword('123456'));
        $this->assertTrue($this->guard->isCommonPassword('password'));
        $this->assertTrue($this->guard->isCommonPassword('QWERTY'));           // normaliza a minúsculas
        $this->assertTrue($this->guard->isCommonPassword('  futbol  '));       // trim
        $this->assertTrue($this->guard->isCommonPassword('jppreparation'));
    }

    public function testNoMarcaComoComunUnaContrasenaRazonable(): void
    {
        $this->assertFalse($this->guard->isCommonPassword('Tr3bol-Cabra-92!'));
    }

    // ── validateNewPassword ────────────────────────────────────────

    public function testRechazaContrasenaCorta(): void
    {
        $this->assertNotNull($this->guard->validateNewPassword('abc12'));
    }

    public function testRechazaContrasenaComun(): void
    {
        $err = $this->guard->validateNewPassword('12345678');
        $this->assertNotNull($err);
        $this->assertStringContainsString('común', $err);
    }

    public function testRechazaSiEsIgualALaActual(): void
    {
        $hash = password_hash('MiClaveActual-2026', PASSWORD_BCRYPT);
        $err  = $this->guard->validateNewPassword('MiClaveActual-2026', $hash);
        $this->assertNotNull($err);
        $this->assertStringContainsString('distinta', $err);
    }

    public function testAceptaUnaContrasenaValida(): void
    {
        $hash = password_hash('otra-cosa', PASSWORD_BCRYPT);
        $this->assertNull($this->guard->validateNewPassword('Bosque-Verde-41xz', $hash));
    }

    // ── verifyConstantTime ─────────────────────────────────────────

    public function testVerifyConstantTimeConHashNuloDevuelveFalse(): void
    {
        $this->assertFalse($this->guard->verifyConstantTime('loquesea', null));
    }

    public function testVerifyConstantTimeConHashRealFunciona(): void
    {
        $hash = password_hash('secreta', PASSWORD_BCRYPT);
        $this->assertTrue($this->guard->verifyConstantTime('secreta', $hash));
        $this->assertFalse($this->guard->verifyConstantTime('otra', $hash));
    }

    public function testElHashDummyEsUnBcryptValido(): void
    {
        $ref  = new \ReflectionClass(AuthGuardService::class);
        $dummy = $ref->getConstant('DUMMY_HASH');
        $this->assertMatchesRegularExpression('/^\$2y\$\d\d\$/', $dummy);
        // password_verify no lanza y devuelve bool con un hash válido
        $this->assertIsBool(password_verify('x', $dummy));
    }
}
