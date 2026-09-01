<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * Fija por escaneo de código las decisiones de endurecimiento de auth,
 * para que una refactorización futura no las revierta sin querer.
 */
final class AuthHardeningWiringTest extends CIUnitTestCase
{
    private function src(string $rel): string
    {
        return file_get_contents(APPPATH . $rel);
    }

    public function testElRateLimitDeLoginYaNoViveEnLaSesion(): void
    {
        $auth = $this->src('Services/AuthService.php');
        $this->assertStringNotContainsString("'login_attempts_'", $auth);
        $this->assertStringContainsString('AuthGuardService', $auth);
        $this->assertStringContainsString('loginLockState', $auth);
    }

    public function testLoginUsaVerificacionEnTiempoConstante(): void
    {
        $this->assertStringContainsString('verifyConstantTime', $this->src('Services/AuthService.php'));
    }

    public function testLoginRechazaCuentasInactivas(): void
    {
        $this->assertStringContainsString("!== 'active'", $this->src('Services/AuthService.php'));
    }

    public function testElTokenDeResetSeGuardaHasheado(): void
    {
        $auth = $this->src('Services/AuthService.php');
        $this->assertStringContainsString("hash('sha256', \$rawToken)", $auth);
        $this->assertStringContainsString("hash('sha256', (string) \$token)", $auth);
    }

    public function testResetYCambioNotificanPorEmail(): void
    {
        $this->assertStringContainsString('sendPasswordChangedEmail', $this->src('Services/AuthService.php'));
        $this->assertStringContainsString('sendPasswordChangedEmail', $this->src('Controllers/PerfilController.php'));
        $this->assertStringContainsString('sendPasswordChangedEmail', $this->src('Services/MailService.php'));
    }

    public function testForgotPasswordEstaLimitado(): void
    {
        $this->assertStringContainsString('resetRequestThrottle', $this->src('Services/AuthService.php'));
    }

    public function testAuthFilterPropagaElCambioDeContrasena(): void
    {
        $filter = $this->src('Filters/AuthFilter.php');
        $this->assertStringContainsString('passwordChangedElsewhere', $filter);
        $this->assertStringContainsString('must_change_password', $filter);
        $this->assertStringContainsString('pw_stamp', $filter);
    }

    public function testLasAltasFuerzanCambioDeContrasena(): void
    {
        $this->assertStringContainsString("must_change_password", $this->src('Services/PlayerService.php'));
        $this->assertStringContainsString("must_change_password", $this->src('Services/CoachService.php'));
        $this->assertStringContainsString("must_change_password", $this->src('Services/ConfiguracionService.php'));
    }

    public function testYaNoSeUsaElGeneradorDebilDeContrasenas(): void
    {
        foreach ([
            'Services/ConfiguracionService.php',
            'Controllers/AlumnosController.php',
            'Controllers/EntrenadoresController.php',
            'Controllers/PerfilController.php',
        ] as $f) {
            $this->assertStringNotContainsString("'Jp' . bin2hex(random_bytes(", $this->src($f), "$f no debe usar el generador antiguo");
            $this->assertStringContainsString('generateTempPassword', $this->src($f));
        }
    }

    public function testRutaDeCambioDeContrasenaPropia(): void
    {
        $routes = $this->src('Config/Routes.php');
        $this->assertStringContainsString("perfil/password", $routes);
        $this->assertStringContainsString('changePassword', $routes);
    }

    public function testConfigDeSeguridadEndurecida(): void
    {
        $this->assertStringContainsString('$tokenRandomize = true', $this->src('Config/Security.php'));
        $this->assertStringContainsString("ENVIRONMENT === 'production'", $this->src('Config/Cookie.php'));
        $this->assertStringContainsString('$regenerateDestroy = true', $this->src('Config/Session.php'));
        $filters = $this->src('Config/Filters.php');
        $this->assertStringContainsString('securityheaders', $filters);
        $this->assertStringContainsString('invalidchars', $filters);
    }

    public function testFiltroDeCabecerasDeSeguridad(): void
    {
        $f = $this->src('Filters/SecurityHeadersFilter.php');
        $this->assertStringContainsString('X-Frame-Options', $f);
        $this->assertStringContainsString('X-Content-Type-Options', $f);
        $this->assertStringContainsString('Referrer-Policy', $f);
        $this->assertStringContainsString('Strict-Transport-Security', $f);
    }
}
