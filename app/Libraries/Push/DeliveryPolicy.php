<?php

namespace App\Libraries\Push;

/**
 * Qué hacer con cada suscripción según lo que respondió su servicio push.
 * Pura (sin BD ni red): toda la regla de borrado vive aquí y se prueba entera.
 *
 *  - 2xx            → ok: se reinicia el contador de fallos.
 *  - 404 / 410      → la suscripción ya no existe: se borra.
 *  - 0, 408, 429, 5xx → fallo TEMPORAL (red, servicio caído, límite): no cuenta. Se
 *                       reintentará con el siguiente aviso; borrar por una caída de FCM
 *                       dejaría a todos sin notificaciones.
 *  - resto de 4xx   → rechazo: cuenta como fallo y a los MAX_FAILURES se borra…
 *  - …salvo que TODO el lote sea 401/403 sin un solo éxito: eso es una clave VAPID mal
 *    puesta en el servidor, no suscripciones muertas, y no se borra nada.
 */
final class DeliveryPolicy
{
    public const OK        = 'ok';
    public const GONE      = 'gone';
    public const TRANSIENT = 'transient';
    public const REJECTED  = 'rejected';

    public const ACTION_RESET     = 'reset';
    public const ACTION_DELETE    = 'delete';
    public const ACTION_INCREMENT = 'increment';
    public const ACTION_KEEP      = 'keep';

    public const MAX_FAILURES = 5;

    /** Mínimo de suscripciones en un lote para sospechar de la configuración (con 1 no se puede distinguir). */
    private const CONFIG_SUSPECT_MIN_BATCH = 2;

    public static function classify(int $code): string
    {
        return match (true) {
            $code >= 200 && $code < 300                          => self::OK,
            $code === 404 || $code === 410                       => self::GONE,
            $code === 0 || $code === 408 || $code === 429 || $code >= 500 => self::TRANSIENT,
            default                                              => self::REJECTED,
        };
    }

    /**
     * @param list<int> $codes    respuesta de cada suscripción del lote
     * @param list<int> $failures fallos acumulados de cada una (mismo orden)
     * @return array{actions: list<string>, config_suspected: bool}
     */
    public static function plan(array $codes, array $failures): array
    {
        $configSuspected = self::looksLikeBadConfig($codes);

        $actions = [];
        foreach ($codes as $i => $code) {
            $actions[$i] = match (self::classify($code)) {
                self::OK        => self::ACTION_RESET,
                self::GONE      => self::ACTION_DELETE,
                self::TRANSIENT => self::ACTION_KEEP,
                default         => $configSuspected
                    ? self::ACTION_KEEP
                    : (($failures[$i] ?? 0) + 1 >= self::MAX_FAILURES ? self::ACTION_DELETE : self::ACTION_INCREMENT),
            };
        }

        return ['actions' => $actions, 'config_suspected' => $configSuspected];
    }

    /** @param list<int> $codes */
    public static function looksLikeBadConfig(array $codes): bool
    {
        if (count($codes) < self::CONFIG_SUSPECT_MIN_BATCH) {
            return false;
        }
        foreach ($codes as $code) {
            if ($code !== 401 && $code !== 403) {
                return false;
            }
        }
        return true;
    }
}
