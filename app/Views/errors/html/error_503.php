<?php
/** 503 — mantenimiento / servicio no disponible. Diseño único: errors/_shell.php. */
try {
    echo view('errors/_shell', [
        'variant' => 'maintenance',
        'code'    => '503',
        'kicker'  => 'Descanso',
        'title'   => 'Estamos haciendo mejoras',
        'message' => 'La plataforma vuelve en unos minutos. No has perdido nada: prueba de nuevo en un rato.',
        'reload'  => true,
    ]);
} catch (\Throwable $e) {
    echo '<!doctype html><meta charset="utf-8"><title>503 — JP Preparation</title>'
       . '<body style="font-family:sans-serif;background:#060a10;color:#e6edf8;text-align:center;padding:15vh 20px">'
       . '<h1>Estamos haciendo mejoras</h1><p>Vuelve en unos minutos.</p></body>';
}
