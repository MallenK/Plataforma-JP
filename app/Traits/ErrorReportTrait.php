<?php

namespace App\Traits;

/**
 * ErrorReportTrait — captura uniforme de errores + referencia reportable.
 *
 * Lo usa BaseController, así que está disponible en todos los controllers.
 *
 *   return $this->guard('clases.quickCreate', function () {
 *       // ... lógica que puede lanzar ...
 *       return $this->response->setJSON([...]);
 *   });
 *
 * Si la lógica lanza, se genera un código corto (`error_ref`), se registra
 * en el log con contexto (url, usuario, rol, traza) y se devuelve un JSON
 *   { "error": "...", "error_ref": "A3F91C", "reportable": true }
 * que el frontend usa para ofrecer el botón "Reportar".
 */
trait ErrorReportTrait
{
    /**
     * Genera una referencia corta y registra la excepción con contexto.
     */
    protected function errorRef(\Throwable $e, string $where): string
    {
        $ref = strtoupper(bin2hex(random_bytes(3)));

        $req  = service('request');
        $uid  = method_exists($this, 'currentUserId') ? $this->currentUserId() : (session('id') ?? '?');
        $rol  = method_exists($this, 'currentRole')   ? $this->currentRole()   : (session('role') ?? '?');
        $url  = $req->getMethod() . ' ' . (string) $req->getUri();

        log_message('critical', sprintf(
            "[TICKET-REF %s] %s · %s · user=%s rol=%s · %s: %s\n%s",
            $ref, $where, $url, $uid, $rol, $e::class, $e->getMessage(), $e->getTraceAsString()
        ));

        return $ref;
    }

    /**
     * Respuesta JSON de error con referencia reportable.
     */
    protected function jsonFail(string $msg, int $status = 500, ?string $ref = null)
    {
        $payload = ['error' => $msg];
        if ($ref !== null) {
            $payload['error_ref'] = $ref;
            $payload['reportable'] = true;
        }

        return $this->response->setJSON($payload)->setStatusCode($status);
    }

    /**
     * Ejecuta $fn y captura cualquier excepción inesperada.
     * Las excepciones "de negocio" que quieras mostrar tal cual deben
     * lanzarse como \DomainException — se devuelven con su mensaje y 422,
     * sin referencia (no son un fallo del sistema).
     */
    protected function guard(string $where, callable $fn)
    {
        try {
            return $fn();
        } catch (\DomainException $e) {
            return $this->jsonFail($e->getMessage(), 422);
        } catch (\Throwable $e) {
            $ref = $this->errorRef($e, $where);
            return $this->jsonFail(
                'Ha ocurrido un error inesperado. Puedes reportarlo para que lo revisemos.',
                500,
                $ref
            );
        }
    }
}
