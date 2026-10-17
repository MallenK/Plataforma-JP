<?php
/**
 * Error genérico en producción (el que CodeIgniter muestra cuando no hay una vista
 * específica para el código). Los 5xx de producción normalmente los atiende
 * ReportableExceptionHandler con production_reportable.php; esto es la red de seguridad.
 * Diseño único: errors/_shell.php.
 */
try {
    echo view('errors/_shell', [
        'variant' => 'error',
        'code'    => '',
        'kicker'  => 'Falta técnica',
        'title'   => 'Algo ha fallado',
        'message' => 'Se ha producido un error inesperado. No es culpa tuya. Inténtalo de nuevo y, si sigue pasando, repórtalo.',
        'reload'  => true,
        'report'  => ['origin' => 'error', 'msg' => 'Error inesperado'],
    ]);
} catch (\Throwable $e) {
    echo '<!doctype html><meta charset="utf-8"><title>JP Preparation</title>'
       . '<body style="font-family:sans-serif;background:#060a10;color:#e6edf8;text-align:center;padding:15vh 20px">'
       . '<h1>Algo ha fallado</h1><p>Inténtalo de nuevo en un momento.</p><p><a style="color:#60a5fa" href="/dashboard">Volver al inicio</a></p></body>';
}
