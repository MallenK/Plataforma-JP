<?php

namespace App\Libraries\Push;

/**
 * Un solo formato de log para todo el push, fácil de buscar con grep:
 *
 *   [push] evt=batch batch=a1b2c3 notif=42 subs=3 ok=2 gone=1 failed=0 ms=412
 *
 * Niveles: info = resumen de cada tanda · warning = fallo de una suscripción o de un
 * envío · error = algo roto que requiere acción (claves VAPID rechazadas, excepciones)
 * · debug = detalle de diagnóstico.
 */
final class PushLogger
{
    public static function debug(string $evt, array $ctx = []): void
    {
        log_message('debug', self::format($evt, $ctx));
    }

    public static function info(string $evt, array $ctx = []): void
    {
        log_message('info', self::format($evt, $ctx));
    }

    public static function warning(string $evt, array $ctx = []): void
    {
        log_message('warning', self::format($evt, $ctx));
    }

    public static function error(string $evt, array $ctx = [], ?\Throwable $e = null): void
    {
        if ($e !== null) {
            $ctx['exception'] = get_class($e) . ': ' . $e->getMessage();
            $ctx['at']        = basename($e->getFile()) . ':' . $e->getLine();
        }
        log_message('error', self::format($evt, $ctx));
    }

    /** @param array<string,scalar|null> $ctx */
    public static function format(string $evt, array $ctx = []): string
    {
        $parts = ['[push]', 'evt=' . $evt];
        foreach ($ctx as $key => $value) {
            $parts[] = $key . '=' . self::value($value);
        }
        return implode(' ', $parts);
    }

    private static function value(mixed $v): string
    {
        if ($v === null) {
            return '-';
        }
        if (is_bool($v)) {
            return $v ? 'yes' : 'no';
        }
        $s = trim(preg_replace('/\s+/u', ' ', (string) $v) ?? '');
        if (mb_strlen($s) > 200) {
            $s = mb_substr($s, 0, 199) . '…';
        }
        return preg_match('/[\s="]/', $s) ? '"' . str_replace('"', "'", $s) . '"' : $s;
    }
}
