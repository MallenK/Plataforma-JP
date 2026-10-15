<?php

namespace App\Services;

use App\Libraries\Push\DeliveryPolicy;
use App\Libraries\Push\PushLogger;
use App\Libraries\Push\PushTransport;
use App\Libraries\Push\SubscriptionStore;
use App\Libraries\WebPush;
use App\Models\NotificationModel;
use Config\Database;

/**
 * Notificaciones push reales (Web Push) ligadas al centro de notificaciones.
 *
 * Cada fila de `notifications` creada con NotificationModel::createWithRecipients()
 * se empuja automáticamente a los dispositivos suscritos de sus destinatarios;
 * al pulsarla se abre el mismo destino que la campanita (GET /notificaciones/:id/ir).
 * El envío se aplaza a post_system (la respuesta HTTP ya salió) y nunca lanza.
 *
 * Piezas: SubscriptionStore (BD) · DeliveryPolicy (qué hacer con cada respuesta) ·
 * PushTransport (WebPush en producción, un falso en los tests) · PushLogger (logs).
 */
class PushService
{
    /** Hosts de los servicios push de los navegadores. Evita SSRF: el servidor hace POST a estas URLs. */
    private const ALLOWED_HOST_SUFFIXES = [
        'fcm.googleapis.com',          // Chrome, Edge, Opera, Brave, Samsung Internet, Android
        'push.services.mozilla.com',   // Firefox (updates. / autopush)
        'push.apple.com',              // Safari macOS 13+ / iOS 16.4+ (PWA instalada)
        'notify.windows.com',          // Edge/Windows (WNS)
    ];

    /**
     * Prioridad del push (cabecera Urgency, RFC 8030). 'high' hace que Android entregue el aviso
     * aunque el móvil esté bloqueado y quieto (Doze); con 'normal' Google lo retrasa. Cada push
     * lleva una notificación visible, que es lo que exige Chrome para aceptar prioridad alta.
     */
    public const URGENCY = 'high';

    /** @var list<array{notif: int, recipients: list<int>, payload: array}> */
    private static array $queue = [];
    private static bool $hooked = false;
    private static ?PushTransport $transport = null;

    // ── Configuración / pruebas ──────────────────────────────────────────

    /** Solo para tests: sustituye el transporte real. null = volver al de producción. */
    public static function setTransport(?PushTransport $transport): void
    {
        self::$transport = $transport;
    }

    /** Solo para tests: descarta lo encolado sin enviarlo. */
    public static function resetQueue(): void
    {
        self::$queue = [];
    }

    private static function transport(): ?PushTransport
    {
        return self::$transport ?? WebPush::fromEnv();
    }

    public static function enabled(): bool
    {
        return self::transport() !== null;
    }

    public static function publicKey(): string
    {
        return WebPush::fromEnv()?->publicKey() ?? '';
    }

    // ── Suscripciones ────────────────────────────────────────────────────

    public static function endpointAllowed(string $endpoint): bool
    {
        $p = parse_url($endpoint);
        if (!$p || ($p['scheme'] ?? '') !== 'https' || !isset($p['host']) || isset($p['user']) || (isset($p['port']) && (int) $p['port'] !== 443)) {
            return false;
        }
        $host = strtolower($p['host']);
        foreach (self::ALLOWED_HOST_SUFFIXES as $suffix) {
            if ($host === $suffix || str_ends_with($host, '.' . $suffix)) {
                return true;
            }
        }
        return false;
    }

    /** Alta o reasignación (mismo navegador, otro usuario). Devuelve el hash del endpoint. */
    public function subscribe(int $userId, string $endpoint, string $p256dh, string $auth, ?string $userAgent = null): string
    {
        if (!self::endpointAllowed($endpoint) || strlen($endpoint) > 2048) {
            throw new \InvalidArgumentException('Endpoint de push no permitido.');
        }
        if (strlen($p256dh) > 255 || strlen($auth) > 255
            || strlen(WebPush::b64uDecode($p256dh)) !== 65 || strlen(WebPush::b64uDecode($auth)) < 16) {
            throw new \InvalidArgumentException('Claves de suscripción no válidas.');
        }

        $hash = hash('sha256', $endpoint);
        (new SubscriptionStore())->upsert($hash, [
            'user_id'    => $userId,
            'endpoint'   => $endpoint,
            'p256dh'     => $p256dh,
            'auth'       => $auth,
            'user_agent' => $userAgent !== null ? mb_substr($userAgent, 0, 255) : null,
        ]);

        PushLogger::info('subscribe', ['user' => $userId, 'sub' => substr($hash, 0, 10), 'host' => parse_url($endpoint, PHP_URL_HOST)]);

        return $hash;
    }

    public function unsubscribeByHash(string $hash, ?int $userId = null): void
    {
        $n = (new SubscriptionStore())->deleteByHash($hash, $userId);
        PushLogger::info('unsubscribe', ['user' => $userId, 'sub' => substr($hash, 0, 10), 'removed' => $n]);
    }

    public function countForUser(int $userId): int
    {
        return (new SubscriptionStore())->countForUser($userId);
    }

    // ── Envío ligado a notificaciones ────────────────────────────────────

    /**
     * Encola el push de una notificación ya creada. Llamado por NotificationModel.
     *
     * @param array<string,mixed> $notification title/body/source_type/source_id/sender_id
     * @param list<int>           $recipientIds
     */
    public static function queueForNotification(int $notifId, array $notification, array $recipientIds): void
    {
        try {
            if (!self::enabled() || $notifId <= 0) {
                return;
            }
            $senderId = (int) ($notification['sender_id'] ?? 0);
            $ids = array_values(array_unique(array_filter(
                array_map('intval', $recipientIds),
                static fn (int $id) => $id > 0 && $id !== $senderId
            )));
            if ($ids === []) {
                return;
            }

            self::$queue[] = ['notif' => $notifId, 'recipients' => $ids, 'payload' => self::buildPayload($notifId, $notification)];

            if (is_cli()) {
                self::flush();
            } elseif (!self::$hooked) {
                self::$hooked = true;
                \CodeIgniter\Events\Events::on('post_system', static fn () => self::flush());
            }
        } catch (\Throwable $e) {
            PushLogger::error('queue_failed', ['notif' => $notifId], $e);
        }
    }

    /** @return array<string,mixed> */
    public static function buildPayload(int $notifId, array $n): array
    {
        $clip = static function (string $s, int $max): string {
            $s = trim(preg_replace('/\s+/u', ' ', strip_tags($s)) ?? '');
            return mb_strlen($s) > $max ? mb_substr($s, 0, $max - 1) . '…' : $s;
        };

        $link = NotificationModel::sourceLink($n);

        return [
            'id'    => $notifId,
            'title' => $clip((string) ($n['title'] ?? ''), 80) ?: 'JP Preparation',
            'body'  => $clip((string) ($n['body'] ?? ''), 140),
            'url'   => 'notificaciones/' . $notifId . '/ir',
            'kind'  => $link ? (string) $n['source_type'] : 'general',
        ];
    }

    /** Envía lo encolado. Tras la respuesta HTTP; no lanza nunca. */
    public static function flush(): void
    {
        $queue = self::$queue;
        self::$queue = [];
        if ($queue === []) {
            return;
        }

        try {
            self::releaseClient();
            ignore_user_abort(true);
            @set_time_limit(60);

            $transport = self::transport();
            if (!$transport) {
                PushLogger::warning('flush_skipped', ['reason' => 'sin claves VAPID', 'jobs' => count($queue)]);
                return;
            }

            $alive = self::existingNotifications(array_column($queue, 'notif'));
            foreach ($queue as $job) {
                if (!in_array($job['notif'], $alive, true)) {
                    // La notificación no existe (transacción revertida): un push llevaría a un 404.
                    PushLogger::warning('job_dropped', ['notif' => $job['notif'], 'reason' => 'notificación inexistente']);
                    continue;
                }
                try {
                    self::deliver($transport, $job);
                } catch (\Throwable $e) {
                    // Un trabajo roto no debe llevarse por delante los demás de la cola.
                    PushLogger::error('deliver_failed', ['notif' => $job['notif']], $e);
                }
            }
        } catch (\Throwable $e) {
            PushLogger::error('flush_failed', [], $e);
        }
    }

    /** @param array{notif: int, recipients: list<int>, payload: array} $job */
    private static function deliver(PushTransport $transport, array $job): void
    {
        $started = microtime(true);
        $batch   = bin2hex(random_bytes(3));
        $store   = new SubscriptionStore();

        $subs = $store->forUsers($job['recipients']);
        if ($subs === []) {
            PushLogger::info('batch', ['batch' => $batch, 'notif' => $job['notif'], 'subs' => 0]);
            return;
        }

        // Un payload por suscripción: lleva el nº de no leídas de SU usuario (badge de la app instalada).
        $unread  = self::unreadCounts($job['recipients']);
        $targets = [];
        $bodies  = [];
        foreach ($subs as $s) {
            $targets[] = ['endpoint' => $s['endpoint'], 'p256dh' => $s['p256dh'], 'auth' => $s['auth']];
            $bodies[]  = json_encode($job['payload'] + ['unread' => $unread[(int) $s['user_id']] ?? 1], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        $codes   = $transport->sendMany($targets, $bodies, 86400, self::URGENCY);
        $summary = self::applyResults($store, $subs, $codes, $batch);

        PushLogger::info('batch', [
            'batch' => $batch, 'notif' => $job['notif'], 'subs' => count($subs),
            'ok' => $summary['ok'], 'gone' => $summary['gone'], 'transient' => $summary['transient'],
            'rejected' => $summary['rejected'], 'deleted' => $summary['deleted'],
            'ms' => (int) round((microtime(true) - $started) * 1000),
        ]);
    }

    /**
     * Aplica DeliveryPolicy a cada suscripción.
     *
     * @param list<array> $subs
     * @param list<int>   $codes
     * @return array{ok:int,gone:int,transient:int,rejected:int,deleted:int}
     */
    private static function applyResults(SubscriptionStore $store, array $subs, array $codes, string $batch): array
    {
        $failures = array_map(static fn (array $s) => (int) $s['failures'], $subs);
        $plan     = DeliveryPolicy::plan($codes, $failures);
        $out      = ['ok' => 0, 'gone' => 0, 'transient' => 0, 'rejected' => 0, 'deleted' => 0];

        if ($plan['config_suspected']) {
            PushLogger::error('vapid_rejected', [
                'batch' => $batch, 'subs' => count($subs), 'codes' => implode(',', array_unique($codes)),
                'hint'  => 'Todos los servicios push rechazan la firma VAPID: revisar vapid.publicKey/privateKey/subject del .env. No se borra ninguna suscripcion.',
            ]);
        }

        foreach ($subs as $i => $s) {
            $code = $codes[$i] ?? 0;
            $out[DeliveryPolicy::classify($code)]++;

            switch ($plan['actions'][$i]) {
                case DeliveryPolicy::ACTION_RESET:
                    $store->markDelivered((int) $s['id']);
                    break;
                case DeliveryPolicy::ACTION_DELETE:
                    $store->delete((int) $s['id']);
                    $out['deleted']++;
                    break;
                case DeliveryPolicy::ACTION_INCREMENT:
                    $store->setFailures((int) $s['id'], (int) $s['failures'] + 1);
                    break;
            }

            if ($plan['actions'][$i] !== DeliveryPolicy::ACTION_RESET) {
                PushLogger::warning('delivery_failed', [
                    'batch' => $batch, 'sub_id' => $s['id'], 'user' => $s['user_id'], 'http' => $code,
                    'action' => $plan['actions'][$i], 'host' => parse_url((string) $s['endpoint'], PHP_URL_HOST),
                ]);
            }
        }

        return $out;
    }

    /**
     * Libera al navegador antes de enviar para que no espere a los servicios push.
     * LiteSpeed (Hostinger) usa litespeed_finish_request; PHP-FPM, fastcgi_finish_request.
     */
    private static function releaseClient(): void
    {
        if (function_exists('litespeed_finish_request')) {
            @litespeed_finish_request();
            $via = 'litespeed';
        } elseif (function_exists('fastcgi_finish_request')) {
            @fastcgi_finish_request();
            $via = 'fastcgi';
        } else {
            $via = 'none';
        }
        PushLogger::debug('release_client', ['via' => $via, 'sapi' => PHP_SAPI]);
    }

    /**
     * Ids que siguen existiendo en `notifications`. Si la consulta falla se asume que existen todos:
     * es preferible un aviso de más que perder uno.
     *
     * @param  list<int> $ids
     * @return list<int>
     */
    private static function existingNotifications(array $ids): array
    {
        try {
            $rows = Database::connect()->table('notifications')->select('id')->whereIn('id', $ids)->get()->getResultArray();
            return array_map(static fn (array $r) => (int) $r['id'], $rows);
        } catch (\Throwable $e) {
            PushLogger::warning('exists_check_failed', ['error' => $e->getMessage()]);
            return array_map('intval', $ids);
        }
    }

    /** @return array<int,int> user_id => no leídas */
    private static function unreadCounts(array $userIds): array
    {
        $rows = Database::connect()->table('notification_recipients')
            ->select('recipient_id, COUNT(id) AS n')
            ->whereIn('recipient_id', $userIds)
            ->where('read_at IS NULL')
            ->groupBy('recipient_id')
            ->get()->getResultArray();

        $out = [];
        foreach ($rows as $r) {
            $out[(int) $r['recipient_id']] = (int) $r['n'];
        }
        return $out;
    }
}
