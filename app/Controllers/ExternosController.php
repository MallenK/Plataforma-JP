<?php

namespace App\Controllers;

use App\Services\ClasesService;
use App\Services\ExternosService;

/**
 * Externos en una clase (clase de prueba / suelta). Controller fino: la lógica está en
 * ExternosService. Los \DomainException los convierte _remap en aviso (sin ref de ticket).
 */
class ExternosController extends BaseController
{
    private function sessionOrFail(int $id): array
    {
        $session = (new ClasesService())->getSession($id);
        if (!$session) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        // Coach/staff solo en sus clases; el resto de roles no llega aquí (filtro de ruta).
        if (!ExternosService::canManage($session, $this->currentUserId(), $this->currentRole())) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        return $session;
    }

    public function add(int $id)
    {
        $session = $this->sessionOrFail($id);
        $post    = (array) $this->request->getPost();
        $svc     = new ExternosService();

        // Precio y «cobrado» solo con acceso total a Finanzas (igual que al crear la clase).
        $money = null;
        if (\App\Services\FinanceAccessService::canFull() && $svc->moneyAvailable()) {
            $m = ExternosService::parseMoney($post);
            if ($m['errors']) {
                throw new \DomainException(implode(' ', $m['errors']));
            }
            $money = $m['price'] !== null ? $m : null;
        }

        $r = $svc->addToSession($session, $post, $this->currentUserId(), $money);

        session()->setFlashdata('success', $r['reused']
            ? 'Esta persona ya estaba registrada: se ha añadido a la clase con sus datos.'
            : 'Persona externa añadida a la clase.');

        // «Guardar y añadir otra»: vuelve con el formulario abierto para la siguiente persona.
        $another = (string) $this->request->getPost('another') === '1';
        return redirect()->to('/clases/' . $id . ($another ? '?ext_add=1' : '') . '#externos');
    }

    public function edit(int $id, int $linkId)
    {
        $this->sessionOrFail($id);
        (new ExternosService())->updateGuest(
            $id, $linkId, (array) $this->request->getPost(),
            ExternosService::canSeePersonal($this->currentRole()), $this->currentUserId()
        );

        session()->setFlashdata('success', 'Datos guardados.');
        return redirect()->to('/clases/' . $id . '#externos');
    }

    public function attendance(int $id, int $linkId)
    {
        $this->sessionOrFail($id);
        (new ExternosService())->setAttendance($id, $linkId, (string) $this->request->getPost('attendance'));

        session()->setFlashdata('success', 'Asistencia guardada.');
        return redirect()->to('/clases/' . $id);
    }

    public function price(int $id, int $linkId)
    {
        $this->sessionOrFail($id);
        $raw = trim((string) $this->request->getPost('price'));
        $cents = null;
        if ($raw !== '') {
            $cents = \App\Services\RevisionService::parseEuroToCents($raw);
            if ($cents === null) {
                throw new \DomainException('El precio no es válido (ejemplo: 25 o 25,50).');
            }
        }
        $svc = new ExternosService();
        $svc->setPrice($id, $linkId, $cents, $this->currentUserId());

        $msg = $cents === null ? 'Precio quitado.' : 'Precio guardado.';
        // «Cobrado»: fija el precio y registra el cobro completo en un solo paso.
        if ($cents !== null && $cents > 0 && $this->request->getPost('paid_now')) {
            $method = (int) $this->request->getPost('method_id');
            $paid   = $svc->collectPending($id, $linkId, $method > 0 ? $method : null, $this->currentUserId());
            if ($paid > 0) {
                $msg = 'Precio guardado y cobro de ' . eur($paid) . ' registrado.';
            }
        }

        session()->setFlashdata('success', $msg);
        return redirect()->to('/clases/' . $id . '#externos');
    }

    public function pay(int $id, int $linkId)
    {
        $this->sessionOrFail($id);
        $cents = \App\Services\RevisionService::parseEuroToCents((string) $this->request->getPost('amount'));
        if ($cents === null) {
            throw new \DomainException('El importe no es válido (ejemplo: 25 o 25,50).');
        }
        $method = (int) $this->request->getPost('method_id');
        (new ExternosService())->addPayment(
            $id, $linkId, $cents, $method > 0 ? $method : null,
            (string) $this->request->getPost('paid_at') ?: null,
            (string) $this->request->getPost('note') ?: null,
            $this->currentUserId()
        );

        session()->setFlashdata('success', 'Cobro registrado.');
        return redirect()->to('/clases/' . $id . '#externos');
    }

    public function voidPay(int $id, int $paymentId)
    {
        $this->sessionOrFail($id);
        (new ExternosService())->voidPayment($id, $paymentId, (string) $this->request->getPost('reason'), $this->currentUserId());

        session()->setFlashdata('success', 'Cobro anulado.');
        return redirect()->to('/clases/' . $id . '#externos');
    }

    public function remove(int $id, int $linkId)
    {
        $this->sessionOrFail($id);
        (new ExternosService())->removeFromSession($id, $linkId);

        session()->setFlashdata('success', 'Externo quitado de la clase.');
        return redirect()->to('/clases/' . $id);
    }
}
