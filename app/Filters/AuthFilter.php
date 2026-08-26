<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthFilter implements FilterInterface
{
    /**
     * Do whatever processing this filter needs to do.
     * By default it should not return anything during
     * normal execution. However, when an abnormal state
     * is found, it should return an instance of
     * CodeIgniter\HTTP\Response. If it does, script
     * execution will end and that Response will be
     * sent back to the client, allowing for error pages,
     * redirects, etc.
     *
     * @param RequestInterface $request
     * @param array|null       $arguments
     *
     * @return RequestInterface|ResponseInterface|string|void
     */
    public function before(RequestInterface $request, $arguments = null)
    {
        if (!session()->get('isLoggedIn')) {
            if ($request->isAJAX()) {
                return service('response')
                    ->setJSON(['error' => 'Sesión no iniciada.', 'session_expired' => true])
                    ->setStatusCode(401);
            }
            return redirect()->to('/login');
        }

        $timeout      = (int)(new \App\Models\SettingsModel())->get('sec_session_timeout', 10);
        $lastActivity = session()->get('last_activity');

        if ($lastActivity !== null && (time() - $lastActivity) > ($timeout * 60)) {
            session()->remove(['isLoggedIn', 'id', 'name', 'role', 'avatar', 'last_activity']);

            // Las peticiones AJAX (fetch de Mensajes, Notificaciones, etc.) no
            // deben recibir una redirección — el JS no la interpreta como
            // sesión caducada y muestra un error genérico. Devolvemos JSON
            // con una marca explícita para que el frontend pueda avisar al
            // usuario en vez de fallar en silencio.
            if ($request->isAJAX()) {
                return service('response')
                    ->setJSON(['error' => 'Tu sesión ha caducado. Vuelve a iniciar sesión.', 'session_expired' => true])
                    ->setStatusCode(401);
            }
            return redirect()->to('/login?expired=1');
        }

        session()->set('last_activity', time());
    }

    /**
     * Allows After filters to inspect and modify the response
     * object as needed. This method does not allow any way
     * to stop execution of other after filters, short of
     * throwing an Exception or Error.
     *
     * @param RequestInterface  $request
     * @param ResponseInterface $response
     * @param array|null        $arguments
     *
     * @return ResponseInterface|void
     */
    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        //
    }
}
