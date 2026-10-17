<?php

namespace App\Filters;

use App\Services\FinanceAccessService;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * FinanceFilter — acceso a Finanzas por PERSONA, además de por rol.
 *
 * Se encadena DESPUÉS de 'auth' y 'role:superadmin,admin':
 *   ['filter' => ['auth', 'role:superadmin,admin', 'finance:full']]
 *
 *   finance:full    → solo quien tiene acceso total (superadmin raíz + los autorizados por él).
 *   finance:review  → además, el resto de admin/superadmin, y SOLO en /finanzas/revision
 *                     (bloque «Clases»). /finanzas y el resto de pestañas son finance:full.
 *   finance:master  → solo el superadmin raíz (gestionar quién ve las finanzas).
 *
 * El nivel sale de la fila ACTUAL del usuario en BD (FinanceAccessService), no de
 * lo guardado en sesión. Todo rechazo queda en el log con nivel 'error'.
 */
class FinanceFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        $required = $arguments[0] ?? 'full';
        $access   = FinanceAccessService::current();
        $level    = $access['level'];

        $allowed = $required === 'master'
            ? $access['master']
            : FinanceAccessService::satisfies($level, $required);

        if ($allowed) {
            return;
        }

        log_message('error', sprintf(
            'FinanceFilter: acceso denegado · %s %s · user=%s rol=%s nivel=%s · requerido=%s',
            $request->getMethod(),
            (string) $request->getUri(),
            session('id') ?? '?',
            session('role') ?? '?',
            $level,
            $required
        ));

        // Queda también en el registro de auditoría (quién intentó entrar y adónde), para que el
        // administrador principal pueda revisarlo. Nunca tumba la petición.
        if ((int) session('id') > 0) {
            \App\Services\AuditService::record(
                'finance_access', null, \App\Services\AuditService::BLOCKED, null,
                ['path' => $request->getUri()->getPath(), 'method' => $request->getMethod(), 'level' => $level, 'required' => $required],
                'Acceso denegado a Finanzas'
            );
        }

        $reason = $required === 'master'
            ? 'Solo el administrador principal puede gestionar quién ve las finanzas.'
            : 'Las finanzas completas son solo para las personas autorizadas.';

        if ($request->isAJAX()) {
            return service('response')
                ->setJSON(['error' => $reason, 'forbidden' => true])
                ->setStatusCode(403);
        }

        return service('response')
            ->setStatusCode(403)
            ->setBody(view('errors/error_403', [
                'role'      => session('role'),
                'reason'    => $reason,
                'context'   => 'finanzas',
                'altUrl'    => $level === FinanceAccessService::LEVEL_REVIEW ? base_url('finanzas/revision') : null,
                'altLabel'  => 'Ir a la revisión de clases',
            ]));
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // Sin lógica post-respuesta.
    }
}
