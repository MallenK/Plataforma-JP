<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

class AuthFilter implements FilterInterface
{
    /** Cada cuánto (segundos) se re-verifica en BD que la contraseña no ha cambiado. */
    private const PW_RECHECK_EVERY = 300;

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
            session()->destroy();

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

        // ── Propagación del cambio de contraseña a otras sesiones ──────────
        // Si la contraseña se ha cambiado desde otro dispositivo (o por un
        // admin), pw_stamp de esta sesión quedará por detrás de la BD.
        if ($invalid = $this->passwordChangedElsewhere()) {
            session()->destroy();
            if ($request->isAJAX()) {
                return service('response')
                    ->setJSON(['error' => 'Tu contraseña ha cambiado. Vuelve a iniciar sesión.', 'session_expired' => true])
                    ->setStatusCode(401);
            }
            return redirect()->to('/login?expired=1');
        }

        // ── Cambio de contraseña obligatorio (alta nueva / reset por admin) ─
        if (session()->get('must_change_password')) {
            $path = ltrim((string) $request->getUri()->getPath(), '/');
            $allowed = str_starts_with($path, 'perfil/password')
                || $path === 'logout'
                || str_starts_with($path, 'assets/');

            if (!$allowed) {
                if ($request->isAJAX()) {
                    return service('response')
                        ->setJSON(['error' => 'Debes cambiar tu contraseña antes de continuar.', 'must_change_password' => true])
                        ->setStatusCode(403);
                }
                return redirect()->to('/perfil/password');
            }
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        //
    }

    /**
     * Comprueba (como mucho cada PW_RECHECK_EVERY segundos) que
     * users.password_changed_at no sea más reciente que el pw_stamp
     * guardado en la sesión. Devuelve true si la sesión debe caer.
     */
    private function passwordChangedElsewhere(): bool
    {
        $checkedAt = (int) session()->get('pw_check_at');
        if ($checkedAt > 0 && (time() - $checkedAt) < self::PW_RECHECK_EVERY) {
            return false;
        }

        $userId = (int) session()->get('id');
        if ($userId <= 0) {
            return false;
        }

        $row = \Config\Database::connect()
            ->table('users')
            ->select('password_changed_at, created_at')
            ->where('id', $userId)
            ->get()->getRowArray();

        if (!$row) {
            return true; // el usuario ya no existe
        }

        $dbStamp   = $row['password_changed_at'] ?: ($row['created_at'] ?? null);
        $sessStamp = session()->get('pw_stamp');

        session()->set('pw_check_at', time());

        if ($dbStamp === null) {
            return false;
        }
        if ($sessStamp === null) {
            // Sesión antigua sin pw_stamp: la adoptamos, no la tiramos.
            session()->set('pw_stamp', $dbStamp);
            return false;
        }

        return strtotime($dbStamp) > strtotime($sessStamp);
    }
}
