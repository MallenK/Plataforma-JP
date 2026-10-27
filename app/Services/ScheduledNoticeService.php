<?php

namespace App\Services;

/**
 * Avisos programados que no los provoca una acción de nadie (recordatorio de clase
 * 25 h antes y resumen de bonos a punto de caducar). En Hostinger no hay cron, así que
 * se disparan «desde el uso»: cada pocos minutos, la primera petición que pasa por
 * aquí hace el trabajo y el resto sale al instante. También hay un comando
 * (`php spark avisos:programados`) para usar un cron de verdad si se prefiere.
 *
 * Cada pieza es idempotente (marca «ya avisado» en BD), así que ejecutarlo de más no duplica nada.
 */
class ScheduledNoticeService
{
    /** Segundos mínimos entre dos pasadas. */
    public const MIN_INTERVAL = 300;

    private const CACHE_KEY = 'jp_scheduled_notices_tick';

    /** Pasada con freno. Nunca lanza ni retrasa la petición que la dispara más de una consulta. */
    public static function tick(): void
    {
        try {
            $cache = cache();
            if ($cache->get(self::CACHE_KEY)) {
                return;
            }
            $cache->save(self::CACHE_KEY, 1, self::MIN_INTERVAL);
        } catch (\Throwable $e) {
            // Sin caché usable: se sigue (cada pieza ya tiene su propia marca en BD).
        }
        self::run();
    }

    /**
     * Pasada completa, sin freno.
     *
     * @return array{reminders:int, expiry:int, org:int}
     */
    public static function run(bool $forceExpiry = false): array
    {
        $out = ['reminders' => 0, 'expiry' => 0, 'org' => 0];
        try {
            $out['reminders'] = (new ClassNoticeService())->sendReminders();
        } catch (\Throwable $e) {
            log_message('error', 'ScheduledNoticeService reminders: ' . $e->getMessage());
        }
        try {
            $out['expiry'] = (new BonoControlService())->runExpiryAlerts($forceExpiry);
        } catch (\Throwable $e) {
            log_message('error', 'ScheduledNoticeService expiry: ' . $e->getMessage());
        }
        try {
            $out['org'] = (new OrgNoticeService())->sendReminders();
        } catch (\Throwable $e) {
            log_message('error', 'ScheduledNoticeService org: ' . $e->getMessage());
        }
        return $out;
    }
}
