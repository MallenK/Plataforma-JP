<?php

namespace App\Libraries\Push;

/**
 * Un solo formato de log para todo el push, fácil de buscar con grep:
 *
 *   2026-10-15T10:42:07+02:00 INFO [push] evt=batch batch=a1b2c3 notif=42 subs=3 ok=2 gone=1 ms=412
 *
 * Niveles: info = resumen de cada tanda · warning = fallo de una suscripción o de un
 * envío · error = algo roto que requiere acción (claves VAPID rechazadas, excepciones)
 * · debug = detalle de diagnóstico.
 *
 * Va a DOS sitios:
 *  1. El log general de CodeIgniter (log_message), que en producción solo guarda errores
 *     (logger.threshold = 4): los avisos y resúmenes no aparecerían nunca.
 *  2. writable/logs/push-AAAA-MM-DD.log, SIEMPRE (info o superior), independiente del umbral:
 *     es lo que se mira cuando alguien dice "no me llegan los avisos". Se conservan RETENTION_DAYS días.
 */
final class PushLogger
{
    public const RETENTION_DAYS = 30;

    private static ?string $dir = null;

    /** Solo para tests: carpeta alternativa. null = writable/logs. */
    public static function useDirectory(?string $dir): void
    {
        self::$dir = $dir;
    }

    public static function debug(string $evt, array $ctx = []): void
    {
        log_message('debug', self::format($evt, $ctx));
    }

    public static function info(string $evt, array $ctx = []): void
    {
        self::emit('info', $evt, $ctx);
    }

    public static function warning(string $evt, array $ctx = []): void
    {
        self::emit('warning', $evt, $ctx);
    }

    public static function error(string $evt, array $ctx = [], ?\Throwable $e = null): void
    {
        if ($e !== null) {
            $ctx['exception'] = get_class($e) . ': ' . $e->getMessage();
            $ctx['at']        = basename($e->getFile()) . ':' . $e->getLine();
        }
        self::emit('error', $evt, $ctx);
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

    /** Nombre del fichero de log de un día (público para tests y para el comando de diagnóstico). */
    public static function fileFor(string $date): string
    {
        return rtrim(self::directory(), '/\\') . DIRECTORY_SEPARATOR . 'push-' . $date . '.log';
    }

    private static function emit(string $level, string $evt, array $ctx): void
    {
        $line = self::format($evt, $ctx);
        log_message($level, $line);
        self::write(strtoupper($level), $line);
    }

    /** Nunca lanza: un log que falla no puede romper el envío ni la petición. */
    private static function write(string $level, string $line): void
    {
        if (self::$dir === null && ENVIRONMENT === 'testing') {
            return; // los tests no ensucian writable/logs (los del propio logger fijan su carpeta)
        }

        try {
            $file = self::fileFor(date('Y-m-d'));
            $new  = !is_file($file);
            @file_put_contents($file, date('c') . ' ' . $level . ' ' . $line . PHP_EOL, FILE_APPEND | LOCK_EX);
            if ($new) {
                self::prune();
            }
        } catch (\Throwable $e) {
            // sin permisos de escritura, disco lleno...: se pierde la línea, no el envío
        }
    }

    /** Borra los push-*.log más antiguos que RETENTION_DAYS (solo al crear el de un día nuevo). */
    private static function prune(): void
    {
        $limit = date('Y-m-d', time() - self::RETENTION_DAYS * 86400);
        foreach (glob(self::fileFor('*')) ?: [] as $f) {
            if (preg_match('/push-(\d{4}-\d{2}-\d{2})\.log$/', $f, $m) && $m[1] < $limit) {
                @unlink($f);
            }
        }
    }

    private static function directory(): string
    {
        return self::$dir ?? WRITEPATH . 'logs';
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
