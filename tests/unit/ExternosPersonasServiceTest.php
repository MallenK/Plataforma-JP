<?php

use App\Services\ExternosPersonasService as S;
use CodeIgniter\Test\CIUnitTestCase;

/**
 * Personas externas: ficha e historial y conversión a alumno. Parte pura (sin BD) + comprobaciones de
 * seguridad de rutas y vistas. Los flujos con BD se prueban con `php spark` contra jp_prodlike.
 *
 * Regla del proyecto: NADA SE BORRA NI SE ANONIMIZA de estas personas.
 *
 * @internal
 */
final class ExternosPersonasServiceTest extends CIUnitTestCase
{
    // ── Conversión ─────────────────────────────────────────────────

    public function testConversionExigeEmailValidoYNoSePuedeRepetir(): void
    {
        $g = ['first_name' => 'Leo', 'last_name' => 'Martí', 'birth_date' => '2012-05-01', 'converted_user_id' => null];

        [, $e] = S::validateConversion([], $g);
        $this->assertNotEmpty(array_filter($e, fn($m) => str_contains($m, 'email')), 'el email es obligatorio: no se inventa');

        [, $e2] = S::validateConversion(['email' => 'no-es-email'], $g);
        $this->assertNotEmpty(array_filter($e2, fn($m) => str_contains($m, 'no es válido')));

        [$ok, $e3] = S::validateConversion(['email' => ' Leo@Correo.COM '], $g);
        $this->assertSame([], $e3);
        $this->assertSame('leo@correo.com', $ok['email']);
        $this->assertSame('Leo Martí', $ok['name'], 'sin nombre escrito se usa el de la ficha');
        $this->assertSame('2012-05-01', $ok['birth_date'], 'y la fecha de nacimiento de la ficha');

        [, $e4] = S::validateConversion(['email' => 'a@b.com'], ['converted_user_id' => 9] + $g);
        $this->assertNotEmpty(array_filter($e4, fn($m) => str_contains($m, 'ya es alumno')));

        [, $e5] = S::validateConversion(['email' => 'a@b.com', 'birth_date' => '01/05/2012'], $g);
        $this->assertNotEmpty(array_filter($e5, fn($m) => str_contains($m, 'nacimiento')));
    }

    // ── Ficha · totales ────────────────────────────────────────────

    public function testTotalesIgnoranLasClasesCanceladas(): void
    {
        $h = [
            ['attendance' => 'present', 'status' => 'completed', 'price_cents' => 2500, 'paid_cents' => 2500],
            ['attendance' => 'absent', 'status' => 'completed', 'price_cents' => 2500, 'paid_cents' => 0],
            ['attendance' => 'pending', 'status' => 'scheduled', 'price_cents' => null, 'paid_cents' => 0],
            ['attendance' => 'present', 'status' => 'cancelled', 'price_cents' => 9900, 'paid_cents' => 9900],
        ];
        $t = S::totals($h);
        $this->assertSame(3, $t['classes']);
        $this->assertSame(1, $t['present']);
        $this->assertSame(1, $t['absent']);
        $this->assertSame(1, $t['pending']);
        $this->assertSame(5000, $t['billed']);
        $this->assertSame(2500, $t['paid']);
    }

    public function testNombreCompleto(): void
    {
        $this->assertSame('Leo Martí', S::displayName(['first_name' => 'Leo', 'last_name' => 'Martí']));
        $this->assertSame('Leo', S::displayName(['first_name' => 'Leo', 'last_name' => null]));
    }

    // ── Seguridad y guía de uso ────────────────────────────────────

    public function testTodasLasRutasDeExternosSonSoloAdmin(): void
    {
        $routes = file_get_contents(APPPATH . 'Config/Routes.php');
        foreach (['externos\'', 'externos/(:num)\'', 'externos/(:num)/editar', 'externos/(:num)/convertir'] as $path) {
            $this->assertMatchesRegularExpression('#\$routes->(get|post)\(\'' . preg_quote($path, '#') . '.{0,140}\'filter\' => \[\'auth\', \'role:superadmin,admin\'\]#s', $routes, $path);
        }
    }

    public function testConvertirPideConfirmacionYNoRellenaPorSuposicion(): void
    {
        $view = file_get_contents(APPPATH . 'Views/externos/show.php');
        $this->assertSame(1, substr_count($view, 'data-ru-confirm="'), 'convertir pide confirmación');
        // El email de la conversión solo se precarga si la ficha ya lo tiene; si no, se deja vacío para escribirlo a mano.
        $this->assertStringContainsString("esc(\$guest['email'] ?? '')", $view);
    }

    public function testNadaSeBorraNiSeAnonimizaDeEstasPersonas(): void
    {
        // No debe existir ninguna ruta, método, comando ni migración que borre o anonimice personas externas.
        $routes = file_get_contents(APPPATH . 'Config/Routes.php');
        $this->assertStringNotContainsString('anonimizar', $routes);
        $this->assertStringNotContainsString('ExternosPersonasController::delete', $routes);

        $this->assertFileDoesNotExist(APPPATH . 'Commands/ExternosAnonimizar.php');
        $this->assertSame([], glob(APPPATH . 'Database/Migrations/*Anonymiz*') ?: []);

        foreach (['Services/ExternosPersonasService.php', 'Controllers/ExternosPersonasController.php'] as $f) {
            $src = file_get_contents(APPPATH . $f);
            $this->assertDoesNotMatchRegularExpression('/function\s+(anonymize|delete|destroy|purge)\w*\s*\(/i', $src, $f);
            $this->assertStringNotContainsString("->delete(", $src, $f);
            $this->assertStringNotContainsString('anonymized_at', $src, $f);
        }
        $this->assertStringNotContainsString('anonymized_at', file_get_contents(APPPATH . 'Models/ClassGuestModel.php'));
    }
}
