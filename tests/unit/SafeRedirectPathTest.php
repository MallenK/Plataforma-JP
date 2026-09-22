<?php

use CodeIgniter\Test\CIUnitTestCase;

/**
 * TICKET (Yolanda, admin, 2026-09-22): al caducar la sesión a mitad de editar
 * la ficha de un alumno, "Guardar cambios" tiraba siempre al dashboard y
 * obligaba a rebuscar al alumno desde cero. AuthFilter ahora recuerda la
 * ruta y el login vuelve a ella — pero solo si es una ruta interna segura,
 * para no abrir la puerta a un open-redirect vía ?next=.
 */
final class SafeRedirectPathTest extends CIUnitTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        helper('redirect');
    }

    public function testAceptaRutasInternasTipicas(): void
    {
        $this->assertTrue(is_safe_redirect_path('/alumnos/42/editar'));
        $this->assertTrue(is_safe_redirect_path('/alumnos/42'));
        $this->assertTrue(is_safe_redirect_path('/clases?scope=all'));
        $this->assertTrue(is_safe_redirect_path('/documentacion?folder=3#archivo'));
    }

    public function testRechazaRutasVaciasONulas(): void
    {
        $this->assertFalse(is_safe_redirect_path(null));
        $this->assertFalse(is_safe_redirect_path(''));
    }

    public function testRechazaEsquemasYUrlsAbsolutas(): void
    {
        $this->assertFalse(is_safe_redirect_path('https://evil.com'));
        $this->assertFalse(is_safe_redirect_path('http://evil.com/alumnos'));
        $this->assertFalse(is_safe_redirect_path('javascript:alert(1)'));
    }

    public function testRechazaProtocolRelativeYBackslash(): void
    {
        // "//evil.com" lo interpreta el navegador como mismo esquema + host distinto.
        $this->assertFalse(is_safe_redirect_path('//evil.com'));
        // "/\evil.com" algunos navegadores lo normalizan a "//evil.com".
        $this->assertFalse(is_safe_redirect_path('/\\evil.com'));
    }
}
