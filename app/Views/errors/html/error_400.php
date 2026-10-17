<?php
/** 400 — petición no válida. Diseño único: errors/_shell.php. */
try {
    echo view('errors/_shell', [
        'variant' => 'bad',
        'code'    => '400',
        'kicker'  => 'Pase mal dado',
        'title'   => 'No hemos podido entender la petición',
        'message' => 'Algo de lo enviado no es válido. Vuelve atrás, revisa los datos e inténtalo de nuevo.',
        'detail'  => ENVIRONMENT !== 'production' ? ($message ?? null) : null,
        'report'  => ['origin' => 'error', 'msg' => 'Petición no válida (400)'],
    ]);
} catch (\Throwable $e) {
    echo '<!doctype html><meta charset="utf-8"><title>400 — JP Preparation</title>'
       . '<body style="font-family:sans-serif;background:#060a10;color:#e6edf8;text-align:center;padding:15vh 20px">'
       . '<h1>400 · Petición no válida</h1><p><a style="color:#60a5fa" href="/dashboard">Volver al inicio</a></p></body>';
}
