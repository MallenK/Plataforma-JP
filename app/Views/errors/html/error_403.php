<?php
/**
 * 403 lanzado como EXCEPCIÓN (no por RoleFilter): el caso real es un token CSRF caducado
 * (formulario abierto mucho rato, sesión renovada o enviado desde otra pestaña).
 * Diseño único: errors/_shell.php.
 */
$csrf = isset($exception) && $exception instanceof \CodeIgniter\Security\Exceptions\SecurityException;
try {
    echo view('errors/_shell', $csrf ? [
        'variant' => 'session',
        'code'    => '',
        'kicker'  => 'Tiempo cumplido',
        'title'   => 'Esta página ha caducado',
        'message' => 'Llevaba demasiado tiempo abierta y por seguridad no se ha guardado nada. Recárgala e inténtalo de nuevo.',
        'reload'  => true,
    ] : [
        'variant' => 'forbidden',
        'code'    => '403',
        'kicker'  => 'Tarjeta roja',
        'title'   => 'No tienes acceso a esta sección',
        'message' => 'Tu cuenta no tiene permiso para esta acción.',
        'detail'  => ENVIRONMENT !== 'production' ? ($message ?? null) : null,
        'report'  => ['origin' => 'permiso', 'msg' => 'Acceso denegado (403)'],
    ]);
} catch (\Throwable $e) {
    echo '<!doctype html><meta charset="utf-8"><title>JP Preparation</title>'
       . '<body style="font-family:sans-serif;background:#060a10;color:#e6edf8;text-align:center;padding:15vh 20px">'
       . '<h1>Esta página ha caducado</h1><p>Recárgala e inténtalo de nuevo.</p><p><a style="color:#60a5fa" href="/dashboard">Volver al inicio</a></p></body>';
}
