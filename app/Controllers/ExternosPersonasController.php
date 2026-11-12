<?php

namespace App\Controllers;

use App\Services\AuditService;
use App\Services\ExternosPersonasService;
use App\Services\FinanceAccessService;

/**
 * Personas externas (sin cuenta de alumno): listado, ficha con todo su historial, conversión a alumno
 * (nada se borra ni se anonimiza). Solo admin/superadmin: contiene datos personales y, a veces, de menores.
 * Controller fino: la lógica está en ExternosPersonasService. Los \DomainException los convierte
 * _remap en aviso (sin ref de ticket).
 */
class ExternosPersonasController extends BaseController
{
    private function svc(): ExternosPersonasService
    {
        return new ExternosPersonasService();
    }

    private function guestOrFail(int $id): array
    {
        $g = $this->svc()->getGuest($id);
        if (!$g) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        return $g;
    }

    public function index()
    {
        $svc    = $this->svc();
        $q      = trim((string) $this->request->getGet('q'));
        $status = (string) $this->request->getGet('estado');
        if (!isset(ExternosPersonasService::STATUSES[$status])) {
            $status = 'activos';
        }
        $money = FinanceAccessService::canFull();

        return view('externos/index', [
            'title'        => 'Personas externas — JP Preparation',
            'pageTitle'    => 'Personas externas',
            'pageSubtitle' => 'Quien ha recibido clases sin ser alumno',
            'available'    => $svc->available(),
            'guests'       => $svc->listGuests($q, $status, $money),
            'q'            => $q,
            'status'       => $status,
            'canMoney'     => $money,
        ]);
    }

    public function show(int $id)
    {
        $svc   = $this->svc();
        $guest = $this->guestOrFail($id);
        $money = FinanceAccessService::canFull();
        $hist  = $svc->history($id, $money);

        // Se consultan datos personales (a veces de menores): queda anotado.
        AuditService::record('class_guest', $id, AuditService::VIEW, null, null, 'Consulta de la ficha', $this->currentUserId());

        return view('externos/show', [
            'title'        => ($guest['name'] ?: 'Persona externa') . ' — JP Preparation',
            'pageTitle'    => $guest['name'],
            'pageSubtitle' => 'Persona externa',
            'guest'        => $guest,
            'history'      => $hist,
            'totals'       => ExternosPersonasService::totals($hist),
            'canMoney'     => $money,
        ]);
    }

    public function update(int $id)
    {
        $this->guestOrFail($id);
        $this->svc()->updateGuest($id, (array) $this->request->getPost(), $this->currentUserId());

        session()->setFlashdata('success', 'Datos guardados.');
        return redirect()->to('/externos/' . $id);
    }

    public function convert(int $id)
    {
        $this->guestOrFail($id);
        $r = $this->svc()->convertToPlayer($id, (array) $this->request->getPost(), $this->currentUserId());

        session()->setFlashdata('success', 'Ya es alumno. Se le ha enviado un email de bienvenida; apunta la contraseña temporal por si no le llega.');
        session()->setFlashdata('created_password', $r['password']);
        session()->setFlashdata('created_user_id', $r['user_id']);
        return redirect()->to('/externos/' . $id);
    }
}
