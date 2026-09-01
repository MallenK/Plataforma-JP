<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Cabeceras de seguridad en toda respuesta. Sustituye al filtro
 * `secureheaders` de CodeIgniter para poder controlar el conjunto exacto.
 *
 * NO incluye Content-Security-Policy: la app tiene JS y estilos inline por
 * todas partes y una CSP estricta la rompería. Queda anotado para una fase
 * posterior (como Report-Only primero).
 */
class SecurityHeadersFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        //
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        $response->setHeader('X-Frame-Options', 'SAMEORIGIN');
        $response->setHeader('X-Content-Type-Options', 'nosniff');
        $response->setHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->setHeader('Permissions-Policy', 'geolocation=(), microphone=(), camera=(), payment=()');
        $response->setHeader('Cross-Origin-Opener-Policy', 'same-origin');

        // X-Powered-By lo añade PHP a nivel SAPI, no CodeIgniter.
        $response->removeHeader('X-Powered-By');
        if (!headers_sent()) {
            header_remove('X-Powered-By');
        }

        if (ENVIRONMENT === 'production') {
            $response->setHeader('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
    }
}
