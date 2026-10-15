<?php

namespace App\Controllers;

use App\Libraries\Push\PushLogger;
use App\Services\PushService;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Suscripción de dispositivos a las notificaciones push (todos los roles).
 * Las notificaciones en sí salen de NotificationModel::createWithRecipients().
 *
 *  POST /push/subscribe    → alta/renovación de la suscripción de este navegador
 *  POST /push/unsubscribe  → baja de este navegador
 *  POST /push/test         → notificación de prueba solo al propio usuario
 *
 * Errores esperados (entrada inválida, push sin configurar) se responden aquí con su
 * código; los inesperados (BD caída...) los recoge BaseController::_remap, que los
 * registra con una referencia y devuelve {error_ref, reportable}.
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

    /** Cuerpo JSON de la petición, o null si no es un JSON válido (objeto/array). */
    private function readJson(): ?array
    {
        try {
            $data = $this->request->getJSON(true);
        } catch (\Throwable $e) {
            return null;
        }
        return is_array($data) ? $data : null;
    }

    /** Guarda en sesión el hash de este navegador para darlo de baja al cerrar sesión (AuthController::logout). */
    protected function rememberEndpoint(string $hash): void
    {
        session()->set('push_endpoint_hash', $hash);
    }

    protected function forgetEndpoint(): void
    {
        session()->remove('push_endpoint_hash');
    }

    public function subscribe(): ResponseInterface
    {
        if (!PushService::enabled()) {
            PushLogger::warning('subscribe_rejected', ['user' => $this->currentUserId(), 'reason' => 'push no configurado (faltan claves VAPID)']);
            return $this->json(['ok' => false, 'error' => 'Las notificaciones push no están configuradas en el servidor.'], 503);
        }

        $body = $this->readJson();
        if ($body === null) {
            return $this->json(['ok' => false, 'error' => 'Petición no válida.'], 400);
        }

        $sub  = $body['subscription'] ?? null;
        $keys = is_array($sub) ? ($sub['keys'] ?? []) : [];
        if (!is_array($sub) || !is_array($keys)) {
            return $this->json(['ok' => false, 'error' => 'Falta la suscripción.'], 422);
        }

        try {
            $hash = $this->push->subscribe(
                (int) $this->currentUserId(),
                (string) ($sub['endpoint'] ?? ''),
                (string) ($keys['p256dh'] ?? ''),
                (string) ($keys['auth'] ?? ''),
                $this->request->getUserAgent()->getAgentString()
            );
        } catch (\InvalidArgumentException $e) {
            PushLogger::warning('subscribe_rejected', ['user' => $this->currentUserId(), 'reason' => $e->getMessage()]);
            return $this->json(['ok' => false, 'error' => $e->getMessage()], 422);
        }

        $this->rememberEndpoint($hash);

        return $this->json(['ok' => true]);
    }

    public function unsubscribe(): ResponseInterface
    {
        $endpoint = (string) ($this->readJson()['endpoint'] ?? '');
        if ($endpoint !== '') {
            $this->push->unsubscribeByHash(hash('sha256', $endpoint), (int) $this->currentUserId());
        }
        $this->forgetEndpoint();

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
            PushLogger::error('test_failed', ['user' => $userId, 'reason' => 'no se pudo crear la notificación']);
            return $this->json(['ok' => false, 'error' => 'No se pudo crear la prueba.'], 500);
        }
        // createWithRecipients no empuja al propio remitente; aquí sí queremos.
        PushService::queueForNotification($id, ['sender_id' => 0] + $data, [$userId]);

        return $this->json(['ok' => true]);
    }
}
