<?php

namespace App\Controllers;

use App\Services\AuthService;
use App\Services\DemoResetService;
use App\Services\MailService;
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

    /**
     * POST /demo/contacto — formulario de contacto del login de la demo.
     * Guarda el lead en `demo_leads` (sobrevive al reset) y, si hay email
     * configurado, avisa a DEMO_CONTACT_TO. Responde JSON.
     */
    public function contact()
    {
        $this->assertDemo();

        $name    = trim((string) $this->request->getPost('name'));
        $email   = trim((string) $this->request->getPost('email'));
        $company = trim((string) $this->request->getPost('company'));
        $message = trim((string) $this->request->getPost('message'));

        // Honeypot anti-bot: campo oculto que un humano no rellena.
        if (trim((string) $this->request->getPost('website')) !== '') {
            return $this->response->setJSON(['status' => 'ok', 'csrf' => csrf_hash()]);
        }

        if ($name === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL) || mb_strlen($message) < 5) {
            return $this->response->setJSON([
                'status' => 'error',
                'error'  => 'Revisa el nombre, el email y el mensaje (mínimo 5 caracteres).',
                'csrf'   => csrf_hash(),
            ])->setStatusCode(422);
        }

        $db      = \Config\Database::connect();
        $hasTable = $db->tableExists('demo_leads');
        $leadId   = null;

        if ($hasTable) {
            $db->table('demo_leads')->insert([
                'name'       => mb_substr($name, 0, 150),
                'email'      => mb_substr($email, 0, 191),
                'company'    => $company !== '' ? mb_substr($company, 0, 150) : null,
                'message'    => mb_substr($message, 0, 4000),
                'meta'       => json_encode([
                    'ip' => $this->request->getIPAddress(),
                    'ua' => mb_substr((string) $this->request->getUserAgent(), 0, 255),
                ]),
                'created_at' => date('Y-m-d H:i:s'),
            ]);
            $leadId = (int) $db->insertID();
        }

        // Aviso por email (best-effort; si la demo no tiene RESEND_API_KEY
        // no pasa nada, el lead ya está guardado en la tabla).
        $to = (string) (env('DEMO_CONTACT_TO') ?: 'sergimallenweb@gmail.com');
        $sent = (new MailService())->send(
            $to,
            'Nuevo contacto desde la demo — ' . $name,
            '<div style="font-family:sans-serif">'
                . '<p><strong>Nombre:</strong> ' . esc($name) . '</p>'
                . '<p><strong>Email:</strong> ' . esc($email) . '</p>'
                . ($company !== '' ? '<p><strong>Academia/empresa:</strong> ' . esc($company) . '</p>' : '')
                . '<p><strong>Mensaje:</strong></p><p>' . nl2br(esc($message)) . '</p>'
                . '</div>',
            ['sender_id' => 0, 'recipient_type' => 'individual']
        );

        if ($sent && $leadId && $hasTable) {
            $db->table('demo_leads')->where('id', $leadId)->update(['emailed' => 1]);
        }
        if (! $hasTable) {
            log_message('warning', 'DemoController::contact — falta la tabla demo_leads. Lead solo en log: '
                . $name . ' <' . $email . '> ' . $message);
        }

        return $this->response->setJSON(['status' => 'ok', 'csrf' => csrf_hash()]);
    }

    /**
     * GET /demo/leads?token=XXX — lista los contactos recibidos.
     * Protegida con el mismo token que /demo/reset.
     */
    public function leads()
    {
        $this->assertDemo();

        $expected = (string) env('DEMO_RESET_TOKEN', '');
        if ($expected === '' || ! hash_equals($expected, (string) $this->request->getGet('token'))) {
            throw PageNotFoundException::forPageNotFound();
        }

        $db = \Config\Database::connect();
        $rows = $db->tableExists('demo_leads')
            ? $db->table('demo_leads')->orderBy('created_at', 'DESC')->limit(500)->get()->getResultArray()
            : [];

        $html = '<!doctype html><meta charset="utf-8"><title>Leads demo</title>'
            . '<style>body{font:14px system-ui;margin:24px;color:#111}table{border-collapse:collapse;width:100%}'
            . 'th,td{border:1px solid #ccc;padding:6px 10px;text-align:left;vertical-align:top}th{background:#f3f4f6}</style>'
            . '<h1>Contactos de la demo (' . count($rows) . ')</h1><table>'
            . '<tr><th>Fecha</th><th>Nombre</th><th>Email</th><th>Academia</th><th>Mensaje</th><th>Email enviado</th></tr>';
        foreach ($rows as $r) {
            $html .= '<tr><td>' . esc($r['created_at']) . '</td><td>' . esc($r['name']) . '</td>'
                . '<td>' . esc($r['email']) . '</td><td>' . esc($r['company'] ?? '') . '</td>'
                . '<td>' . nl2br(esc($r['message'] ?? '')) . '</td><td>' . ((int) $r['emailed'] ? 'sí' : 'no') . '</td></tr>';
        }
        $html .= '</table>';

        return $this->response->setBody($html);
    }

    /**
     * POST /demo/vertical — cambia el vocabulario visible de la demo
     * (fútbol / refuerzo escolar / idiomas / clases particulares). Solo
     * cambia etiquetas de texto (ver app/Helpers/vertical_helper.php); no
     * toca datos ni rutas. Válido tanto antes como después de iniciar
     * sesión, así que no requiere el filtro 'auth'.
     */
    public function setVertical()
    {
        $this->assertDemo();

        $vertical  = (string) $this->request->getPost('vertical');
        $verticals = demo_verticals();

        if (isset($verticals[$vertical])) {
            session()->set('demo_vertical', $vertical);
        }

        $back = (string) $this->request->getPost('redirect');
        if ($back !== '' && str_starts_with($back, '/')) {
            return redirect()->to($back);
        }

        return redirect()->back();
    }

    private function assertDemo(): void
    {
        if (! demo_mode()) {
            throw PageNotFoundException::forPageNotFound();
        }
    }
}
