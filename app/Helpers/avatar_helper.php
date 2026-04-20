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
