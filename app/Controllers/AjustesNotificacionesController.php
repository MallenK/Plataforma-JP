<?php

namespace App\Controllers;

use App\Models\NotificationPreferenceModel;

/**
 * Preferencias de notificación de CADA usuario (todos los roles).
 *
 *  GET  /configuracion/notificaciones        → página (los admin la ven también como pestaña de /configuracion)
 *  POST /configuracion/notificaciones/save   → guarda qué categorías quiere recibir y por qué canal
 */
class AjustesNotificacionesController extends BaseController
{
    public function index(): string
    {
        $prefs = new NotificationPreferenceModel();

        return view('configuracion/notificaciones', [
            'title'      => 'Notificaciones — JP Preparation',
            'categories' => NotificationPreferenceModel::categories(),
            'prefs'      => $prefs->forUser((int) $this->currentUserId()),
        ]);
    }

    public function save()
    {
        $input = $this->request->getPost('pref');
        (new NotificationPreferenceModel())->saveForUser((int) $this->currentUserId(), is_array($input) ? $input : []);

        $back = $this->request->getPost('return_to') === 'section' && $this->isAdmin()
            ? base_url('configuracion?section=notificaciones')
            : base_url('configuracion/notificaciones');

        return redirect()->to($back)->with('success', 'Preferencias de notificación guardadas.');
    }
}
