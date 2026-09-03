<?php

/**
 * upload_helper.php
 *
 * Utilidades de seguridad para la subida de archivos de usuario.
 *
 * Objetivo: que ningún handler de subida (mensajes, notificaciones, tickets,
 * avatares…) pueda escribir un archivo ejecutable dentro de public/ ni servir
 * contenido con un tipo que el navegador pueda "esnifar".
 *
 * Regla de oro: validar SIEMPRE la extensión final contra una lista blanca —
 * getMimeType() (finfo) por sí solo no basta, porque `text/plain` e `image/gif`
 * están permitidos y un polyglot (GIF89a + <?php …) los supera.
 *
 * Los adjuntos privados (mensajes, notificaciones, tickets) se guardan FUERA de
 * public/ — en WRITEPATH.'uploads/' — y solo se sirven por su controlador con
 * comprobación de permisos. Ver upload_private_dir() / upload_resolve_stored().
 */

if (!function_exists('upload_private_dir')) {
    /**
     * Directorio de almacenamiento de adjuntos privados, FUERA del webroot.
     * Devuelve una ruta absoluta terminada en "/".
     *
     * @param string $subfolder  'mensajes' | 'notificaciones' | 'tickets'
     */
    function upload_private_dir(string $subfolder): string
    {
        $subfolder = strtolower(preg_replace('/[^a-z0-9]/i', '', $subfolder));
        return rtrim(WRITEPATH, "/\\") . '/uploads/' . $subfolder . '/';
    }
}

if (!function_exists('upload_stored_path')) {
    /**
     * Valor relativo que se guarda en BD para un adjunto recién subido.
     * Formato: "uploads/<subfolder>/<nombre>" (compatible con las filas antiguas).
     */
    function upload_stored_path(string $subfolder, string $storedName): string
    {
        $subfolder = strtolower(preg_replace('/[^a-z0-9]/i', '', $subfolder));
        return 'uploads/' . $subfolder . '/' . basename($storedName);
    }
}

if (!function_exists('upload_resolve_stored')) {
    /**
     * Resuelve el path guardado en BD ("uploads/mensajes/xxx") a una ruta
     * absoluta en disco, comprobando primero la ubicación nueva (WRITEPATH,
     * fuera del webroot) y luego la antigua (FCPATH, dentro de public/) para
     * que los adjuntos subidos antes de la migración sigan descargándose.
     *
     * Incluye defensa anti path-traversal: solo se aceptan rutas del tipo
     * uploads/<carpeta>/<fichero> sin "..".
     *
     * @return string|null  ruta absoluta si el fichero existe, null si no
     */
    function upload_resolve_stored(?string $storedPath): ?string
    {
        $rel = ltrim(str_replace('\\', '/', (string) $storedPath), '/');

        if (!preg_match('#^uploads/[a-z0-9]+/[A-Za-z0-9._-]+$#', $rel) || str_contains($rel, '..')) {
            return null;
        }

        foreach ([rtrim(WRITEPATH, "/\\"), rtrim(FCPATH, "/\\")] as $base) {
            $full = $base . '/' . $rel;
            if (is_file($full)) {
                return $full;
            }
        }

        return null;
    }
}

if (!function_exists('upload_allowed_extension')) {
    /**
     * Normaliza y valida la extensión declarada por el cliente contra una
     * lista blanca. Devuelve la extensión en minúsculas si es válida, o null.
     *
     * @param string   $clientExtension  Normalmente $file->getClientExtension()
     * @param string[] $allowed          Lista blanca de extensiones (en minúsculas)
     */
    function upload_allowed_extension(string $clientExtension, array $allowed): ?string
    {
        $ext = strtolower(trim($clientExtension));

        // Defensa extra: nombres tipo "foo.php.jpg" o extensiones dobles.
        if ($ext === '' || !preg_match('/^[a-z0-9]{1,10}$/', $ext)) {
            return null;
        }

        return in_array($ext, $allowed, true) ? $ext : null;
    }
}

if (!function_exists('upload_harden_dir')) {
    /**
     * Crea el directorio de subida (si no existe) y deja dentro un .htaccess
     * que:
     *   - desactiva el listado de índices,
     *   - impide ejecutar cualquier script (php, phtml, cgi, pl, py, sh…),
     *   - añade X-Content-Type-Options: nosniff a lo que se sirva de ahí.
     *
     * Todas las directivas van envueltas en <IfModule> para no provocar un
     * 500 si el módulo no está cargado (Apache 2.2/2.4, LiteSpeed, PHP-FPM…).
     *
     * Nota: esto es defensa en profundidad. La protección real es guardar los
     * archivos FUERA de public/ (como hace DocumentService con WRITEPATH).
     */
    function upload_harden_dir(string $dir): void
    {
        try {
            if (!is_dir($dir)) {
                // 0775: mismo criterio que writable/ (el proceso web debe poder
                // escribir aunque el directorio lo cree la CLI de migración).
                @mkdir($dir, 0775, true);
            }

            $htaccess = rtrim($dir, "/\\") . DIRECTORY_SEPARATOR . '.htaccess';
            if (is_file($htaccess)) {
                return;
            }

            $rules = <<<'HTACCESS'
# Generado automáticamente por upload_harden_dir() — no editar a mano.
# Protege los archivos subidos por usuarios: sin listados, sin ejecución.

Options -Indexes

# --- Impedir la ejecución de código en este directorio ---
<IfModule mod_php.c>
    php_flag engine off
</IfModule>
<IfModule mod_php7.c>
    php_flag engine off
</IfModule>
<IfModule mod_php8.c>
    php_flag engine off
</IfModule>

<FilesMatch "(?i)\.(php[0-9]?|phtml|phar|pht|cgi|pl|py|sh|asp|aspx|jsp)$">
    <IfModule mod_authz_core.c>
        Require all denied
    </IfModule>
    <IfModule !mod_authz_core.c>
        Order allow,deny
        Deny from all
    </IfModule>
</FilesMatch>

<IfModule mod_headers.c>
    Header set X-Content-Type-Options "nosniff"
</IfModule>
HTACCESS;

            @file_put_contents($htaccess, $rules . "\n");
        } catch (\Throwable $e) {
            log_message('warning', 'upload_harden_dir falló para ' . $dir . ': ' . $e->getMessage());
        }
    }
}
