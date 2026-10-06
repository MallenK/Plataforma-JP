<?php

namespace App\Services;

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

    private const MAX_FAILURES = 5;

    /** @var list<array{recipients: list<int>, payload: array}> */
    private static array $queue = [];
    private static bool $hooked = false;

    public static function enabled(): bool
    {
        return WebPush::fromEnv() !== null;
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

        $db   = Database::connect();
        $hash = hash('sha256', $endpoint);
        $now  = date('Y-m-d H:i:s');
        $data = [
            'user_id'      => $userId,
            'endpoint'     => $endpoint,
            'p256dh'       => $p256dh,
            'auth'         => $auth,
            'user_agent'   => $userAgent !== null ? mb_substr($userAgent, 0, 255) : null,
            'failures'     => 0,
            'last_used_at' => $now,
        ];

        $exists = $db->table('push_subscriptions')->select('id')->where('endpoint_hash', $hash)->get()->getRowArray();
        if ($exists) {
            $db->table('push_subscriptions')->where('id', $exists['id'])->update($data);
        } else {
            $db->table('push_subscriptions')->insert($data + ['endpoint_hash' => $hash, 'created_at' => $now]);
        }

        return $hash;
    }

    public function unsubscribeByHash(string $hash, ?int $userId = null): void
    {
        $b = Database::connect()->table('push_subscriptions')->where('endpoint_hash', $hash);
        if ($userId !== null) {
            $b->where('user_id', $userId);
        }
        $b->delete();
    }

    public function countForUser(int $userId): int
    {
        return (int) Database::connect()->table('push_subscriptions')->where('user_id', $userId)->countAllResults();
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

            self::$queue[] = ['recipients' => $ids, 'payload' => self::buildPayload($notifId, $notification)];

            if (is_cli()) {
                self::flush();
            } elseif (!self::$hooked) {
                self::$hooked = true;
                \CodeIgniter\Events\Events::on('post_system', static fn () => self::flush());
            }
        } catch (\Throwable $e) {
            log_message('error', '[PushService] queue: ' . $e->getMessage());
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
            if (function_exists('fastcgi_finish_request')) {
                @fastcgi_finish_request();
            }
            ignore_user_abort(true);
            @set_time_limit(60);

            $push = WebPush::fromEnv();
            if (!$push) {
                return;
            }
            $db = Database::connect();

            foreach ($queue as $job) {
                $unread = self::unreadCounts($job['recipients']);
                $subs   = $db->table('push_subscriptions')->whereIn('user_id', $job['recipients'])->get()->getResultArray();
                if ($subs === []) {
                    continue;
                }

                // Un payload por suscripción: lleva el nº de no leídas de SU usuario (badge de la app instalada).
                $targets = [];
                $bodies  = [];
                foreach ($subs as $s) {
                    $targets[] = ['endpoint' => $s['endpoint'], 'p256dh' => $s['p256dh'], 'auth' => $s['auth']];
                    $bodies[]  = json_encode($job['payload'] + ['unread' => $unread[(int) $s['user_id']] ?? 1], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }

                self::applyResults($subs, $push->sendMany($targets, $bodies));
            }
        } catch (\Throwable $e) {
            log_message('error', '[PushService] flush: ' . $e->getMessage());
        }
    }

    /** @param list<array> $subs @param list<int> $codes */
    private static function applyResults(array $subs, array $codes): void
    {
        $db  = Database::connect();
        $now = date('Y-m-d H:i:s');
        foreach ($subs as $i => $s) {
            $code = $codes[$i] ?? 0;
            if ($code >= 200 && $code < 300) {
                $db->table('push_subscriptions')->where('id', $s['id'])->update(['failures' => 0, 'last_used_at' => $now]);
            } elseif ($code === 404 || $code === 410) {
                // El navegador/usuario ya no existe o revocó el permiso.
                $db->table('push_subscriptions')->where('id', $s['id'])->delete();
            } else {
                // 0 = red caída, 4xx/5xx = rechazo temporal o clave inválida: tolerar unos cuantos.
                log_message('warning', "[PushService] push HTTP {$code} (sub {$s['id']})");
                if ((int) $s['failures'] + 1 >= self::MAX_FAILURES) {
                    $db->table('push_subscriptions')->where('id', $s['id'])->delete();
                } else {
                    $db->table('push_subscriptions')->where('id', $s['id'])->update(['failures' => (int) $s['failures'] + 1]);
                }
            }
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
