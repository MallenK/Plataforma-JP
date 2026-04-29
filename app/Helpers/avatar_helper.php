<?php

/**
 * avatar_helper.php
 *
 * Funciones de ayuda para renderizar avatares de usuario.
 * Muestra la imagen si existe, o las iniciales como fallback.
 */

if (!function_exists('avatar_initials')) {
    /**
     * Obtiene las iniciales de un nombre (máx. 2 letras).
     */
    function avatar_initials(string $name): string
    {
        $parts = explode(' ', trim($name));
        if (count($parts) >= 2) {
            return strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
        }
        return strtoupper(substr($name, 0, 1));
    }
}

if (!function_exists('avatar_html')) {
    /**
     * Renderiza un avatar como <img> si hay imagen, o <div> con iniciales si no.
     *
     * @param string|null $avatarPath  Ruta relativa desde public/ (p.ej. 'uploads/avatars/xxx.jpg')
     * @param string      $name        Nombre del usuario (para iniciales y alt)
     * @param string      $cssClass    Clase CSS del contenedor (sidebar-avatar, topbar-avatar, etc.)
     * @param string      $size        Tamaño del img si se muestra imagen (heredado del CSS de la clase)
     */
    function avatar_html(?string $avatarPath, string $name, string $cssClass = 'avatar'): string
    {
        $initials = avatar_initials($name);

        if ($avatarPath && file_exists(FCPATH . $avatarPath)) {
            $url = base_url($avatarPath) . '?v=' . filemtime(FCPATH . $avatarPath);
            return '<img src="' . esc($url, 'attr') . '" alt="' . esc($initials, 'attr') . '" class="' . esc($cssClass, 'attr') . '" style="object-fit:cover;border-radius:50%;">';
        }

        return '<div class="' . esc($cssClass, 'attr') . '">' . esc($initials) . '</div>';
    }
}

if (!function_exists('timeAgo')) {
    /**
     * Devuelve una cadena legible "hace X tiempo" a partir de un datetime string.
     */
    function timeAgo(?string $datetime): string
    {
        if (!$datetime) return '';
        $now  = time();
        $then = strtotime($datetime);
        $diff = $now - $then;

        if ($diff < 60)     return 'ahora';
        if ($diff < 3600)   return floor($diff / 60) . ' min';
        if ($diff < 86400)  return floor($diff / 3600) . ' h';
        if ($diff < 604800) return floor($diff / 86400) . ' d';
        return date('d/m/Y', $then);
    }
}

if (!function_exists('formatBytes')) {
    /**
     * Convierte bytes a una cadena legible (KB, MB…).
     */
    function formatBytes(int $bytes): string
    {
        if ($bytes < 1024)    return $bytes . ' B';
        if ($bytes < 1048576) return round($bytes / 1024, 1) . ' KB';
        return round($bytes / 1048576, 1) . ' MB';
    }
}
