<?php

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\DemoResetService;
use App\Models\UserModel;
use CodeIgniter\Exceptions\PageNotFoundException;

/**
 * Acciones exclusivas del entorno DEMO (ver app/Helpers/demo_helper.php).
 *
 * Todas devuelven 404 si demo_mode() es false, así que en producción y
 * pre-producción este controller no existe a efectos prácticos.
 */
class DemoController extends BaseController
{
    /**
     * POST /demo/invitado — inicia sesión como cuenta de invitado del rol
     * pedido, sin contraseña. Solo en la demo.
     */
    public function guestLogin()
    {
        $this->assertDemo();

        $role     = (string) $this->request->getPost('role');
        $accounts = demo_guest_accounts();

        if (! isset($accounts[$role])) {
            return redirect()->to('/login');
        }

        $user = (new UserModel())->where('email', $accounts[$role]['email'])->first();

        if (! $user || ($user['status'] ?? 'active') !== 'active') {
            log_message('error', "DemoController: falta la cuenta de invitado '{$accounts[$role]['email']}'. ¿Sembraste DemoSeeder?");
            return redirect()->to('/login')->with('error', 'La demo aún no está lista. Inténtalo en un minuto.');
        }

        (new AuthService())->establishSession($user);

        return redirect()->to('/dashboard');
    }

    /**
     * GET /demo/reset?token=XXX — borra y resiembra los datos de la demo.
     * La dispara GitHub Actions cada noche. Protegida por token secreto
     * (env DEMO_RESET_TOKEN); es GET para que un `curl` simple del cron
     * baste (no lleva CSRF y GET no está protegido por el filtro).
     */
    public function reset()
    {
        $this->assertDemo();

        $expected = (string) env('DEMO_RESET_TOKEN', '');
        $given    = (string) $this->request->getGet('token');

        if ($expected === '' || ! hash_equals($expected, $given)) {
            throw PageNotFoundException::forPageNotFound();
        }

        $summary = (new DemoResetService())->run();

        return $this->response->setJSON([
            'status'  => 'ok',
            'reseted' => true,
            'at'      => date('c'),
            'summary' => $summary,
        ]);
    }

    private function assertDemo(): void
    {
        if (! demo_mode()) {
            throw PageNotFoundException::forPageNotFound();
        }
    }
}
