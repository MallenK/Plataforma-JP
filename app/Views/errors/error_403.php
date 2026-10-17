<?php
/**
 * 403 por rol o por persona (RoleFilter / FinanceFilter). Se pinta con view(), no con
 * el manejador de excepciones. Diseño único: errors/_shell.php.
 *
 * Variables opcionales: $role, $reason (por qué), $altUrl/$altLabel (a dónde ir en su lugar).
 */
try {
    echo view('errors/_shell', [
        'variant'  => 'forbidden',
        'code'     => '403',
        'kicker'   => 'Tarjeta roja',
        'title'    => 'No tienes acceso a esta sección',
        'message'  => !empty($reason)
            ? $reason . ' Si crees que es un error, avísanos con el botón de abajo.'
            : 'Tu cuenta no tiene permiso para entrar aquí. Si crees que es un error, avísanos con el botón de abajo.',
        'role'     => $role ?? null,
        'altUrl'   => $altUrl ?? null,
        'altLabel' => $altLabel ?? '',
        'report'   => ['origin' => 'permiso', 'msg' => 'Acceso denegado (403)'],
    ]);
} catch (\Throwable $e) {
    echo '<!doctype html><meta charset="utf-8"><title>403 — JP Preparation</title>'
       . '<body style="font-family:sans-serif;background:#060a10;color:#e6edf8;text-align:center;padding:15vh 20px">'
       . '<h1>403 · Sin acceso</h1><p>No tienes permiso para entrar en esta sección.</p><p><a style="color:#60a5fa" href="/dashboard">Volver al inicio</a></p></body>';
}
