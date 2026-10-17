<?php
/**
 * 500 en producción, con referencia (`$ref`) y botón «Reportar» que abre un ticket
 * precargado. Lo pinta ReportableExceptionHandler. Diseño único: errors/_shell.php.
 */
$ref = $ref ?? '—';
try {
    echo view('errors/_shell', [
        'variant' => 'error',
        'code'    => '500',
        'kicker'  => 'Falta técnica',
        'title'   => 'Algo ha fallado de nuestra parte',
        'message' => 'Se ha producido un error inesperado. No es culpa tuya. Repórtalo y lo revisamos cuanto antes.',
        'ref'     => $ref,
        'reload'  => true,
        'report'  => ['origin' => 'error', 'msg' => 'Error inesperado (500)'],
    ]);
} catch (\Throwable $e) {
    // Último recurso: página mínima con la referencia, sin dependencias.
    echo '<!doctype html><meta charset="utf-8"><title>Algo ha fallado — JP Preparation</title>'
       . '<body style="font-family:sans-serif;background:#060a10;color:#e6edf8;text-align:center;padding:15vh 20px">'
       . '<h1>Algo ha fallado de nuestra parte</h1><p>Ref: ' . htmlspecialchars((string) $ref, ENT_QUOTES) . '</p>'
       . '<p><a style="color:#fb923c" href="/dashboard">Volver al inicio</a></p></body>';
}
