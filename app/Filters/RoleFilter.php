<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * RoleFilter — control de acceso basado en roles.
 *
 * Se aplica DESPUÉS de AuthFilter (que ya garantiza que hay sesión activa).
 * Lee los roles permitidos desde los argumentos de la ruta y deniega
 * el acceso devolviendo una página 403 si el rol del usuario no está
 * en la lista.
 *
 * Uso en Routes.php:
 *   ['filter' => 'role:admin,superadmin']
 *   ['filter' => ['auth', 'role:admin,superadmin']]
 *
 * Roles disponibles en la plataforma:
 *   superadmin → acceso total, gestión de la plataforma
 *   admin      → gestión de alumnos, entrenadores y contenido
 *   coach      → acceso a sus grupos y alumnos asignados
 *   alumno     → acceso a su propio perfil y documentación
 *   staff      → acceso de apoyo (torneos, documentación)
 */
class RoleFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        // Si no se especifican roles, el filtro no restringe nada
        if (empty($arguments)) {
            return;
        }

        $userRole = session('role');

        // Roles permitidos vienen como array desde la definición de la ruta
        // Ej: 'role:admin,superadmin' → $arguments = ['admin', 'superadmin']
        if (!in_array($userRole, $arguments, strict: true)) {
            return service('response')
                ->setStatusCode(403)
                ->setBody(view('errors/error_403', [
                    'role'           => $userRole,
                    'allowedRoles'   => $arguments,
                ]));
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // No se necesita lógica post-respuesta
    }
}
