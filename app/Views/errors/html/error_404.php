<?php
/** 404 — la página no existe. Diseño único: errors/_shell.php. */
try {
    echo view('errors/_shell', [
        'variant' => 'notfound',
        'code'    => '404',
        'kicker'  => 'Fuera de juego',
        'title'   => 'No encontramos esa página',
        'message' => 'El enlace está mal escrito, la página ya no existe o se ha movido. Vuelve al inicio y sigue desde ahí.',
        'detail'  => ENVIRONMENT !== 'production' ? ($message ?? null) : null,
        'report'  => ['origin' => 'error', 'msg' => 'Página no encontrada (404)'],
    ]);
} catch (\Throwable $e) {
    echo '<!doctype html><meta charset="utf-8"><title>404 — JP Preparation</title>'
       . '<body style="font-family:sans-serif;background:#060a10;color:#e6edf8;text-align:center;padding:15vh 20px">'
       . '<h1>404 · Página no encontrada</h1><p><a style="color:#60a5fa" href="/dashboard">Volver al inicio</a></p></body>';
}
