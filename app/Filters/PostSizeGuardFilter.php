<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * PostSizeGuardFilter
 *
 * Cuando un POST supera post_max_size, PHP vacía $_POST y $_FILES ANTES de
 * que la app llegue a ejecutarse (solo deja un warning suelto en el log:
 * "POST Content-Length of X bytes exceeds the limit of Y bytes"). Sin este
 * filtro, esa petición sigue su curso con el POST vacío y falla más abajo
 * de forma confusa — normalmente el filtro CSRF la rechaza porque el token
 * ya no está, así que el usuario ve un error de sesión/CSRF que no tiene
 * nada que ver con el archivo que intentó subir.
 *
 * Debe ir ANTES que 'csrf' en Filters::$globals['before'] — el Content-Length
 * original sigue disponible en $_SERVER aunque PHP haya descartado el body.
 */
class PostSizeGuardFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (strtoupper($request->getMethod()) !== 'POST') {
            return;
        }

        $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
        $limitBytes    = $this->toBytes((string) ini_get('post_max_size'));

        if ($contentLength <= 0 || $limitBytes <= 0 || $contentLength <= $limitBytes) {
            return;
        }

        $limitMb = (int) round($limitBytes / (1024 * 1024));
        $message = "El archivo es demasiado grande para subirlo (máximo {$limitMb} MB por envío).";

        log_message('warning', sprintf(
            'PostSizeGuardFilter: POST %s rechazado — Content-Length=%d > post_max_size=%d',
            (string) $request->getUri(),
            $contentLength,
            $limitBytes
        ));

        $isAjax = $request->hasHeader('X-Requested-With')
            && strtolower($request->getHeaderLine('X-Requested-With')) === 'xmlhttprequest';

        if ($isAjax) {
            return service('response')->setStatusCode(413)->setJSON(['error' => $message]);
        }

        session()->setFlashdata('error', $message);

        return redirect()->back();
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // No se necesita lógica post-respuesta
    }

    /**
     * Convierte un valor de php.ini tipo "520M"/"1G" a bytes.
     */
    private function toBytes(string $value): int
    {
        $value = trim($value);
        if ($value === '') {
            return 0;
        }

        $last = strtolower($value[strlen($value) - 1]);
        $num  = (int) $value;

        return match ($last) {
            'g'     => $num * 1024 * 1024 * 1024,
            'm'     => $num * 1024 * 1024,
            'k'     => $num * 1024,
            default => $num,
        };
    }
}
