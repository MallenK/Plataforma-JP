<?php

namespace App\Controllers;

use CodeIgniter\Controller;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;
use App\Models\UserModel;
use Psr\Log\LoggerInterface;

/**
 * BaseController — clase base para todos los controllers de la plataforma.
 *
 * Centraliza aquí la lógica compartida: acceso al usuario en sesión,
 * helpers de respuesta JSON y comprobaciones de rol. Así evitamos
 * código duplicado en cada controller.
 */
abstract class BaseController extends Controller
{
    protected $playerService;

    public function initController(RequestInterface $request, ResponseInterface $response, LoggerInterface $logger)
    {
        parent::initController($request, $response, $logger);

        $this->playerService = new \App\Services\PlayerService();
    }

    // ----------------------------------------------------------------
    // Sesión — clave canónica: 'id'
    // AuthService guarda session('id'). Todos los controllers deben
    // usar estos helpers en lugar de llamar a session() directamente.
    // ----------------------------------------------------------------

    /**
     * Devuelve el ID del usuario autenticado desde la sesión.
     */
    protected function currentUserId(): ?int
    {
        return session('id');
    }

    /**
     * Devuelve el rol del usuario autenticado desde la sesión.
     */
    protected function currentRole(): ?string
    {
        return session('role');
    }

    /**
     * Devuelve los datos básicos del usuario autenticado.
     * Si se necesita más info, consultar UserModel directamente.
     */
    protected function currentUser(): array
    {
        return [
            'id'   => session('id'),
            'name' => session('name'),
            'role' => session('role'),
        ];
    }

    /**
     * Carga el usuario completo desde la BD.
     * Útil cuando se necesitan campos que no están en sesión (email, status…).
     */
    protected function currentUserFromDB(): ?array
    {
        $id = $this->currentUserId();
        if (!$id) {
            return null;
        }

        return (new UserModel())->find($id);
    }

    // ----------------------------------------------------------------
    // Comprobaciones de rol
    // SuperAdmin hereda todos los permisos de Admin.
    // ----------------------------------------------------------------

    /**
     * Devuelve true si el usuario es admin o superadmin.
     */
    protected function isAdmin(): bool
    {
        return in_array($this->currentRole(), ['admin', 'superadmin']);
    }

    /**
     * Devuelve true si el usuario es superadmin.
     */
    protected function isSuperAdmin(): bool
    {
        return $this->currentRole() === 'superadmin';
    }

    /**
     * Devuelve true si el usuario es coach.
     */
    protected function isCoach(): bool
    {
        return $this->currentRole() === 'coach';
    }

    /**
     * Devuelve true si el usuario es alumno.
     */
    protected function isAlumno(): bool
    {
        return $this->currentRole() === 'alumno';
    }

    // ----------------------------------------------------------------
    // Helpers de respuesta
    // ----------------------------------------------------------------

    /**
     * Devuelve una respuesta JSON con el código HTTP indicado.
     */
    protected function jsonResponse(array $data, int $status = 200)
    {
        return $this->response->setJSON($data)->setStatusCode($status);
    }
}
