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
     * Ejecuta $fn y captura cualquier excepción inesperada (respuesta AJAX/JSON).
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

    /**
     * Igual que guard() pero para acciones que responden con redirect
     * (formularios POST). En caso de fallo: flashdata de error con la
     * referencia + URL de reporte precargada, y redirige a $fallbackUrl.
     */
    protected function guardRedirect(string $where, callable $fn, string $fallbackUrl)
    {
        try {
            return $fn();
        } catch (\DomainException $e) {
            session()->setFlashdata('error', $e->getMessage());
            return redirect()->to($fallbackUrl)->withInput();
        } catch (\Throwable $e) {
            return $this->reportRedirect($e, $where, $fallbackUrl);
        }
    }

    /** Flash de error + URL de reporte precargada, y redirect. */
    private function reportRedirect(\Throwable $e, string $where, string $fallbackUrl)
    {
        $ref = $this->errorRef($e, $where);
        // Clave propia (no 'error') para que la pinte solo el banner global
        // de layouts/app.php, sin duplicarse con el flash de cada vista.
        session()->setFlashdata('error_report_msg', 'No se ha podido completar la acción. Algo ha fallado por nuestra parte.');
        session()->setFlashdata('error_report_url', site_url('tickets/create') . '?' . http_build_query([
            'origin' => 'error',
            'ref'    => $ref,
            'url'    => (string) service('request')->getUri(),
            'msg'    => 'Error al ' . str_replace('.', ' · ', $where),
        ]));

        return redirect()->to($fallbackUrl)->withInput();
    }

    /**
     * Dispatcher con red de seguridad: envuelve TODA acción de controller.
     *
     *  - GET (renderizado de página) → re-lanza: lo maneja el handler global
     *    (ReportableExceptionHandler) con su página de error + referencia.
     *  - POST/PUT/PATCH/DELETE AJAX → JSON { error, error_ref, reportable }.
     *  - POST/... con redirect → flash de error + banner "Reportar" + volver.
     *
     * Las 404 / 403 / redirects normales pasan sin tocar.
     */
    public function _remap(string $method, ...$params)
    {
        if (! method_exists($this, $method)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        $rm = new \ReflectionMethod($this, $method);
        if (! $rm->isPublic() || $rm->isStatic() || $method === 'initController' || $method === '_remap') {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        try {
            return $this->{$method}(...$params);
        } catch (\CodeIgniter\Exceptions\PageNotFoundException $e) {
            throw $e;
        } catch (\CodeIgniter\HTTP\Exceptions\HTTPException $e) {
            throw $e;
        } catch (\DomainException $e) {
            $req = service('request');
            if ($req->isAJAX() || $req->getMethod() !== 'GET') {
                if ($req->isAJAX()) {
                    return $this->jsonFail($e->getMessage(), 422);
                }
                session()->setFlashdata('error', $e->getMessage());
                return redirect()->back()->withInput();
            }
            throw $e;
        } catch (\Throwable $e) {
            $where = strtolower((new \ReflectionClass($this))->getShortName()) . '.' . $method;
            $req   = service('request');

            if ($req->getMethod() === 'GET' && ! $req->isAJAX()) {
                throw $e; // página → handler global (página de error con ref)
            }
            if ($req->isAJAX() || str_contains($req->getHeaderLine('accept'), 'application/json')) {
                $ref = $this->errorRef($e, $where);
                return $this->jsonFail('Ha ocurrido un error inesperado. Puedes reportarlo para que lo revisemos.', 500, $ref);
            }
            // POST con redirect → volver al origen (o al dashboard)
            $back = (string) ($req->getServer('HTTP_REFERER') ?? '');
            return $this->reportRedirect($e, $where, $back !== '' ? $back : site_url('dashboard'));
        }
    }
}
