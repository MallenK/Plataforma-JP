<?php

use CodeIgniter\Test\CIUnitTestCase;
use CodeIgniter\HTTP\IncomingRequest;
use CodeIgniter\HTTP\URI;
use CodeIgniter\HTTP\UserAgent;
use CodeIgniter\Security\Security;
use CodeIgniter\Security\Exceptions\SecurityException;
use Config\App as AppConfig;
use Config\Security as SecurityConfig;

/**
 * Reproduce y fija el 403 al "Descontar bono" desde /clases/{id}/lista.
 *
 * Causa raíz: el fetch de la vista pasar_lista.php enviaba SIEMPRE la
 * cabecera `X-CSRF-TOKEN`, con valor '' porque leía de un
 * <meta name="csrf-token"> que no existe en el layout. En CI4,
 * Security::getPostedToken() da prioridad a la cabecera: si la cabecera
 * está presente pero vacía devuelve null y NO llega a mirar el token que
 * sí iba en el cuerpo JSON -> CSRF falla -> 403 Forbidden.
 *
 * El arreglo: no mandar la cabecera vacía (o mandarla con el hash real).
 */
final class DeductBonoCsrfTest extends CIUnitTestCase
{
    private SecurityConfig $config;

    protected function setUp(): void
    {
        parent::setUp();
        $this->config = new SecurityConfig();
        // Estado por defecto del proyecto (ver app/Config/Security.php).
        $this->config->tokenRandomize = true;
        $this->config->regenerate     = false;
        $_SESSION = [];
    }

    private function makeRequest(string $body, array $headers = []): IncomingRequest
    {
        $request = new IncomingRequest(new AppConfig(), new URI('http://localhost/clases/125/jugadores/49/descontar-bono'), $body, new UserAgent());
        $request->setMethod('POST');
        $request->setHeader('Content-Type', 'application/json');
        foreach ($headers as $name => $value) {
            $request->setHeader($name, $value);
        }
        $request->setBody($body);

        return $request;
    }

    private function freshSecurity(): Security
    {
        // Nueva instancia => genera y persiste el hash en sesión.
        return new Security($this->config);
    }

    public function testEmptyCsrfHeaderShadowsValidBodyTokenAndFails(): void
    {
        $security = $this->freshSecurity();
        $hash     = $security->getHash(); // lo que devolvería csrf_hash() en la vista

        // Igual que hacía la vista: token correcto en el cuerpo JSON, pero
        // además una cabecera X-CSRF-TOKEN vacía.
        $body    = json_encode([$this->config->tokenName => $hash]);
        $request = $this->makeRequest($body, ['X-CSRF-TOKEN' => '']);

        $this->expectException(SecurityException::class);
        $security->verify($request);
    }

    public function testValidBodyTokenWithoutHeaderPasses(): void
    {
        $security = $this->freshSecurity();
        $hash     = $security->getHash();

        $body    = json_encode([$this->config->tokenName => $hash]);
        $request = $this->makeRequest($body); // sin cabecera X-CSRF-TOKEN

        $security->verify($request);
        $this->addToAssertionCount(1); // no lanzó excepción
    }

    public function testValidTokenInHeaderPasses(): void
    {
        $security = $this->freshSecurity();
        $hash     = $security->getHash();

        $request = $this->makeRequest('{}', ['X-CSRF-TOKEN' => $hash]);

        $security->verify($request);
        $this->addToAssertionCount(1);
    }
}
