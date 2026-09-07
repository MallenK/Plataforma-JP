<?php

namespace App\Libraries;

use CodeIgniter\Debug\BaseExceptionHandler;
use CodeIgniter\Debug\ExceptionHandler as DefaultExceptionHandler;
use CodeIgniter\Debug\ExceptionHandlerInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use Throwable;

/**
 * Handler de excepciones para errores 5xx en peticiones HTML de producción.
 *
 * Genera una referencia corta (`error_ref`), la registra en el log con
 * contexto y muestra una página de error que ofrece "Reportar este problema"
 * (abre un ticket precargado). Cualquier otro caso lo delega al handler
 * estándar de CodeIgniter.
 */
final class ReportableExceptionHandler extends BaseExceptionHandler implements ExceptionHandlerInterface
{
    public function handle(
        Throwable $exception,
        RequestInterface $request,
        ResponseInterface $response,
        int $statusCode,
        int $exitCode,
    ) {
        $ref = strtoupper(bin2hex(random_bytes(3)));

        log_message('critical', sprintf(
            "[TICKET-REF %s] EXCEPTION %s %s · user=%s rol=%s · %s: %s\n%s",
            $ref,
            $request->getMethod(),
            (string) $request->getUri(),
            session('id') ?? '?',
            session('role') ?? '?',
            $exception::class,
            $exception->getMessage(),
            $exception->getTraceAsString(),
        ));

        // Limpia cualquier buffer parcial antes de responder.
        while (ob_get_level() > $this->obLevel) {
            ob_end_clean();
        }

        $wantsHtml = str_contains($request->getHeaderLine('accept'), 'text/html')
            && ! $request->isAJAX();

        if (! headers_sent()) {
            header('HTTP/1.1 500 Internal Server Error', true, 500);
            header('Content-Type: ' . ($wantsHtml ? 'text/html' : 'application/json') . '; charset=UTF-8');
        }

        if ($wantsHtml) {
            try {
                echo view('errors/html/production_reportable', ['ref' => $ref]);
            } catch (Throwable) {
                (new DefaultExceptionHandler($this->config))
                    ->handle($exception, $request, $response, $statusCode, $exitCode);
                return;
            }
        } else {
            echo json_encode([
                'error'      => 'Ha ocurrido un error inesperado. Puedes reportarlo para que lo revisemos.',
                'error_ref'  => $ref,
                'reportable' => true,
            ]);
        }

        if (ENVIRONMENT !== 'testing') {
            exit($exitCode);
        }
    }
}
