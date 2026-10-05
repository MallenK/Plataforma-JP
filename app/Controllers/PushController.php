<?php

namespace App\Controllers;

use App\Services\PushService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Suscripción de dispositivos a las notificaciones push (todos los roles).
 * Las notificaciones en sí salen de NotificationModel::createWithRecipients().
 *
 *  POST /push/subscribe    → alta/renovación de la suscripción de este navegador
 *  POST /push/unsubscribe  → baja de este navegador
 *  POST /push/test         → notificación de prueba solo al propio usuario
 */
class PushController extends BaseController
{
    private PushService $push;

    public function __construct()
    {
        $this->push = new PushService();
    }

    private function json(array $data, int $status = 200): ResponseInterface
    {
        return $this->response->setStatusCode($status)->setJSON($data + ['csrf' => csrf_hash()]);
    }

    public function subscribe(): ResponseInterface
    {
        if (!PushService::enabled()) {
            return $this->json(['ok' => false, 'error' => 'Las notificaciones push no están configuradas en el servidor.'], 503);
        }

        $sub  = $this->request->getJSON(true)['subscription'] ?? null;
        $keys = $sub['keys'] ?? [];

        try {
            $hash = $this->push->subscribe(
                (int) $this->currentUserId(),
                (string) ($sub['endpoint'] ?? ''),
                (string) ($keys['p256dh'] ?? ''),
                (string) ($keys['auth'] ?? ''),
                $this->request->getUserAgent()->getAgentString()
            );
        } catch (\InvalidArgumentException $e) {
            return $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        // Para borrar la suscripción de ESTE navegador al cerrar sesión (AuthController::logout).
        session()->set('push_endpoint_hash', $hash);

        return $this->json(['ok' => true]);
    }

    public function unsubscribe(): ResponseInterface
    {
        $endpoint = (string) ($this->request->getJSON(true)['endpoint'] ?? '');
        if ($endpoint !== '') {
            $this->push->unsubscribeByHash(hash('sha256', $endpoint), (int) $this->currentUserId());
        }
        session()->remove('push_endpoint_hash');

        return $this->json(['ok' => true]);
    }

    public function test(): ResponseInterface
    {
        $userId = (int) $this->currentUserId();
        if (!PushService::enabled() || $this->push->countForUser($userId) === 0) {
            return $this->json(['ok' => false, 'error' => 'Este dispositivo aún no tiene las notificaciones activadas.'], 409);
        }

        // Una notificación real (aparece en la campanita) con remitente = el propio usuario.
        // queueForNotification excluye al remitente, así que se envía a mano.
        $data = [
            'sender_id' => $userId,
            'type'      => 'individual',
            'title'     => 'Notificación de prueba',
            'body'      => 'Si ves esto, las notificaciones de este dispositivo funcionan.',
        ];
        $id = (new \App\Models\NotificationModel())->createWithRecipients($data, [$userId], false); // la prueba ignora las preferencias
        if ($id <= 0) {
            return $this->json(['ok' => false, 'error' => 'No se pudo crear la prueba.'], 500);
        }
        // createWithRecipients no empuja al propio remitente; aquí sí queremos.
        PushService::queueForNotification($id, ['sender_id' => 0] + $data, [$userId]);

        return $this->json(['ok' => true]);
    }
}
