<?php

namespace App\Controllers;

use App\Services\LiveService;
use App\Services\ScheduledNoticeService; // existe desde la rama de notificaciones; sin ella el sondeo funciona igual

/**
 * GET /live/ping?since=ID — sondeo único y ligero de la barra superior y de la
 * actualización en vivo (public/assets/js/live.js). Todos los roles autenticados.
 */
class LiveController extends BaseController
{
    public function ping()
    {
        // Sin cron: los avisos programados (recordatorio de clase, caducidad de bonos) se
        // disparan desde aquí, como mucho cada 5 min (ver ScheduledNoticeService).
        if (class_exists(ScheduledNoticeService::class)) {
            ScheduledNoticeService::tick();
        }

        $data = (new LiveService())->snapshot(
            (int) $this->currentUserId(),
            (string) $this->currentRole(),
            max(0, (int) $this->request->getGet('since'))
        );

        return $this->response
            ->setHeader('Cache-Control', 'no-store')
            ->setJSON($data);
    }
}
