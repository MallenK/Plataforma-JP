<?php

use CodeIgniter\Test\CIUnitTestCase;
use App\Services\MailService;
use App\Services\AuthService;

/**
 * Cubre:
 *  - El email de bienvenida / confirmación de alta (punto 3).
 *  - El email de recuperación de contraseña.
 *  - El cableado de sendWelcomeEmail() en los servicios de alta de usuarios.
 */
final class MailServiceTest extends CIUnitTestCase
{
    // ── Email de bienvenida ─────────────────────────────────────────

    public function testWelcomeConCredencialesIncluyeEmailYPassword(): void
    {
        $html = (new MailService())->buildWelcomeEmailHtml(
            'Ana Pérez',
            'ana@example.com',
            'player',
            'Jp1a2b!'
        );

        $this->assertStringContainsString('Ana Pérez', $html);
        $this->assertStringContainsString('ana@example.com', $html);
        $this->assertStringContainsString('Jp1a2b!', $html);
        $this->assertStringContainsString('alumno', $html);
        $this->assertStringContainsString('/login', $html);
    }

    public function testWelcomeSinPasswordApuntaAlForgotPassword(): void
    {
        $html = (new MailService())->buildWelcomeEmailHtml(
            'Carlos',
            'carlos@example.com',
            'coach',
            null
        );

        $this->assertStringNotContainsString('Contraseña', $html);
        $this->assertStringContainsString('/forgot-password', $html);
        $this->assertStringContainsString('entrenador', $html);
    }

    public function testWelcomeEscapaContenido(): void
    {
        $html = (new MailService())->buildWelcomeEmailHtml(
            '<script>x</script>',
            'x@e.com',
            'staff',
            null
        );
        $this->assertStringNotContainsString('<script>x</script>', $html);
    }

    // ── send() sin API key ──────────────────────────────────────────

    public function testSendFallaSinApiKey(): void
    {
        $prev = getenv('RESEND_API_KEY');
        putenv('RESEND_API_KEY=');
        unset($_ENV['RESEND_API_KEY'], $_SERVER['RESEND_API_KEY']);

        try {
            $ok = (new MailService())->send('a@b.com', 'Asunto', '<p>x</p>');
            $this->assertFalse($ok, 'send() debe devolver false si no hay RESEND_API_KEY');
        } finally {
            if ($prev !== false) {
                putenv('RESEND_API_KEY=' . $prev);
                $_ENV['RESEND_API_KEY'] = $prev;
            }
        }
    }

    // ── Email de recuperación de contraseña ─────────────────────────

    public function testResetEmailContieneEnlaceNombreYCaducidad(): void
    {
        $method = new \ReflectionMethod(AuthService::class, 'buildResetEmailHtml');
        $method->setAccessible(true);

        // Sin constructor: buildResetEmailHtml no usa estado de instancia
        // y así evitamos depender de session() en el entorno de test.
        $svc  = (new \ReflectionClass(AuthService::class))->newInstanceWithoutConstructor();
        $html = $method->invoke($svc, 'María', 'https://app.test/reset-password?token=abc123');

        $this->assertStringContainsString('María', $html);
        $this->assertStringContainsString('https://app.test/reset-password?token=abc123', $html);
        $this->assertStringContainsString('1 hora', $html);
        $this->assertStringContainsString('Restablecer', $html);
    }

    // ── Cableado en los servicios de alta ──────────────────────────

    public function testLosServiciosDeAltaEnvianEmailDeBienvenida(): void
    {
        $player = file_get_contents(APPPATH . 'Services/PlayerService.php');
        $coach  = file_get_contents(APPPATH . 'Services/CoachService.php');
        $staff  = file_get_contents(APPPATH . 'Services/ConfiguracionService.php');

        $this->assertStringContainsString('sendWelcomeEmail', $player, 'PlayerService::createAlumno debe enviar bienvenida');
        $this->assertStringContainsString('sendWelcomeEmail', $coach,  'CoachService::createCoach debe enviar bienvenida');
        $this->assertStringContainsString('sendWelcomeEmail', $staff,  'ConfiguracionService::createStaffUser debe enviar bienvenida');
    }
}
