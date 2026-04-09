<?php

/**
 * ══════════════════════════════════════════════════════════════════
 *  JP PREPARATION — Debug Console Helper
 * ══════════════════════════════════════════════════════════════════
 *
 *  Convierte datos PHP en console.log organizados visualmente.
 *  Solo activo en entornos que NO sean 'production'.
 *
 *  GUÍA DE USO RÁPIDO
 *  ──────────────────
 *  1. Carga el helper en el Controller (o en BaseController para siempre):
 *       helper('debug');
 *
 *  2. Llama a console_debug() desde cualquier vista PHP:
 *       <?= console_debug('PlayerController::index', [
 *           'players'   => $players,
 *           'total'     => count($players),
 *           'filters'   => ['role' => 'player', 'status' => 'active'],
 *       ]) ?>
 *
 *  CONVENCIÓN DE ETIQUETAS
 *  ───────────────────────
 *   Formato: 'ControllerName::method'   → contexto de dónde viene
 *   Arrays de filas  → console.table()  → fácil de escanear
 *   Valores simples  → console.log()
 *   Objetos/arrays   → console.dir()
 *
 *  COLORES POR TIPO DE LOG (visible en Chrome DevTools)
 *  ─────────────────────────────────────────────────────
 *   🟣 Cabecera de grupo    → morado   (#6c63ff)
 *   🔵 Arrays de filas      → azul     (console.table)
 *   🟡 Valores escalares    → amarillo (#f0b429)
 *   🔴 Errores / vacíos     → rojo     (#e53e3e)
 *   🟢 Éxito / counts       → verde    (#38a169)
 *
 * ══════════════════════════════════════════════════════════════════
 */

if (!function_exists('console_debug')) {
    /**
     * Genera un bloque <script> con console.group organizado.
     *
     * @param string $label  Etiqueta del grupo (p.ej. 'PlayerController::index')
     * @param array  $data   Datos asociativos a mostrar
     * @param bool   $collapsed  true → groupCollapsed (cerrado por defecto)
     */
    function console_debug(string $label, array $data = [], bool $collapsed = false): string
    {
        // Solo activo fuera de producción
        if (ENVIRONMENT === 'production') {
            return '';
        }

        $json     = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $groupFn  = $collapsed ? 'console.groupCollapsed' : 'console.group';
        $safeLabel = addslashes($label);

        // Detecta si hay arrays de filas para usar console.table
        $tableKeys = [];
        $scalarKeys = [];
        foreach ($data as $key => $value) {
            if (is_array($value) && !empty($value) && is_array(reset($value))) {
                $tableKeys[] = $key;
            } else {
                $scalarKeys[] = $key;
            }
        }

        $lines = [];
        $lines[] = "<script>";
        $lines[] = "(function() {";
        $lines[] = "  const _d = " . $json . ";";
        $lines[] = "  {$groupFn}('%c[JP Debug] {$safeLabel}', 'color:#6c63ff;font-weight:bold;font-size:12px');";

        // Escalares primero
        foreach ($scalarKeys as $key) {
            $safeKey = addslashes($key);
            $lines[] = "  if (_d['{$safeKey}'] === null || _d['{$safeKey}'] === undefined || _d['{$safeKey}'] === '' || (Array.isArray(_d['{$safeKey}']) && _d['{$safeKey}'].length === 0)) {";
            $lines[] = "    console.log('%c  {$safeKey}:', 'color:#e53e3e;font-weight:600', '(vacío / null)');";
            $lines[] = "  } else if (typeof _d['{$safeKey}'] === 'number') {";
            $lines[] = "    console.log('%c  {$safeKey}:', 'color:#38a169;font-weight:600', _d['{$safeKey}']);";
            $lines[] = "  } else {";
            $lines[] = "    console.log('%c  {$safeKey}:', 'color:#f0b429;font-weight:600', _d['{$safeKey}']);";
            $lines[] = "  }";
        }

        // Arrays de filas → console.table
        foreach ($tableKeys as $key) {
            $safeKey = addslashes($key);
            $lines[] = "  console.log('%c  {$safeKey} (' + _d['{$safeKey}'].length + ' filas):', 'color:#4299e1;font-weight:600');";
            $lines[] = "  console.table(_d['{$safeKey}']);";
        }

        $lines[] = "  console.groupEnd();";
        $lines[] = "})();";
        $lines[] = "</script>";

        return implode("\n", $lines);
    }
}
