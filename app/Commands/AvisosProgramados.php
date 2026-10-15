<?php

namespace App\Commands;

use App\Services\ScheduledNoticeService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Avisos programados: recordatorio de clase (25 h antes) y resumen de bonos a punto
 * de caducar. La plataforma ya los dispara sola con el uso normal; este comando es
 * para quien prefiera un cron (p. ej. cada 10 minutos). Es idempotente.
 *
 *   php spark avisos:programados
 *   php spark avisos:programados --forzar-caducidad   (ignora la franja mañana/tarde)
 */
class AvisosProgramados extends BaseCommand
{
    protected $group       = 'Avisos';
    protected $name        = 'avisos:programados';
    protected $description = 'Envía los recordatorios de clase pendientes y el resumen de bonos a punto de caducar.';
    protected $usage       = 'avisos:programados [--forzar-caducidad]';

    public function run(array $params)
    {
        $res = ScheduledNoticeService::run(CLI::getOption('forzar-caducidad') !== null);
        CLI::write(sprintf('Recordatorios de clase enviados: %d', $res['reminders']));
        CLI::write(sprintf('Bonos avisados por caducidad: %d', $res['expiry']));
    }
}
