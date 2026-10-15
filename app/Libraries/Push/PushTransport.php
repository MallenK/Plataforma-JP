<?php

namespace App\Libraries\Push;

/**
 * Lo único que PushService necesita para enviar. WebPush lo implementa en
 * producción; los tests inyectan uno falso (sin red ni claves VAPID).
 */
interface PushTransport
{
    /**
     * Envía en paralelo y devuelve, en el mismo orden que $targets, el código HTTP
     * de cada servicio push (0 = fallo de red o de cifrado).
     *
     * @param list<array{endpoint: string, p256dh: string, auth: string}> $targets
     * @param string|list<string> $payloads un texto para todos o uno por destino
     * @return list<int>
     */
    public function sendMany(array $targets, string|array $payloads, int $ttl = 86400, string $urgency = 'normal', int $timeout = 6): array;
}
