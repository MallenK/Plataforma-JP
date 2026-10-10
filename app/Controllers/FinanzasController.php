<?php

namespace App\Controllers;

use App\Services\BonoControlService;
use App\Services\RevisionService;

/**
 * Finanzas — solo superadmin y admin.
 *
 * v1.33.0 (Fase 0): solo existe la pestaña «Revisión» (lo que hay que revisar
 * a mano para que el histórico económico cuadre). El resto de pestañas
 * (Resumen, Movimientos, Cobros, Gastos…) llegan con la v2.0 — ver
 * docs/finanzas/PLAN-finanzas-v2.md.
 */
class FinanzasController extends BaseController
{
    /** /finanzas → de momento, la única pestaña disponible. */
    public function index()
    {
        return redirect()->to('/finanzas/revision');
    }

    /** Ruta antigua `/pendientes` (v1.33.0 en desarrollo) → Finanzas › Revisión. */
    public function legacyPendientes()
    {
        return redirect()->to('/finanzas/revision', 301);
    }

    public function revision()
    {
        $rev     = new RevisionService();
        $control = new BonoControlService();

        return view('finanzas/revision', [
            'title'      => 'Finanzas · Revisión — JP Preparation',
            'tab'        => 'revision',
            'counts'     => $rev->counts(),
            'unclosed'   => $rev->unclosedSessions(),
            'debts'      => $control->openDebts(),
            'preControl' => $control->unreflected(),
            'estimated'  => $rev->estimatedPriceBonos(),
            'unused'     => $rev->expiredUnused(),
            'since'      => (new \App\Services\BonoCoverageService())->controlSince(),
        ]);
    }

    /** Confirma (o corrige con motivo) el precio real pagado de un bono. */
    public function confirmPrice(int $bonoId)
    {
        $res = (new BonoControlService())->confirmPrice(
            $bonoId,
            $this->request->getPost('price'),
            $this->request->getPost('reason'),
            (int) $this->currentUserId()
        );
        session()->setFlashdata($res['success'] ? 'success' : 'error', $res['success'] ? 'Precio guardado.' : $res['error']);

        $back = (string) $this->request->getPost('back');
        return redirect()->to($back === 'bono' ? '/bonos/' . $bonoId : '/finanzas/revision#precios');
    }
}
