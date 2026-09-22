<?php

/**
 * redirect_helper.php
 *
 * Validación compartida para rutas de redirección que llegan por parámetro
 * (query string, POST) en vez de estar fijadas en el código. Sin esto, un
 * atacante podría usar el parámetro para saltar a un dominio externo
 * (open-redirect). Solo se acepta una ruta interna relativa.
 */

if (!function_exists('is_safe_redirect_path')) {
    function is_safe_redirect_path(?string $path): bool
    {
        if (!$path) {
            return false;
        }

        return (bool) preg_match('#^/[a-zA-Z0-9][a-zA-Z0-9/_?=&.\#-]*$#', $path);
    }
}
