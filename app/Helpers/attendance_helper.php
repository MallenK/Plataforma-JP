<?php

/**
 * attendance_helper.php
 *
 * Catálogo único de estados de asistencia de una sesión de clase.
 * Antes cada vista (pasar_lista, pasar_lista_semanal, clases/show) definía
 * su propio array con etiquetas y colores distintos. Esta es la fuente única.
 *
 * Dos ejes:
 *   - "asistencia"  → lo que pasó de verdad (present / absent / unjustified)
 *   - "convocatoria"→ respuesta previa del alumno (pending / confirmed / declined)
 */

if (!function_exists('attendance_catalog')) {
    /**
     * @return array<string,array{label:string,short:string,color:string,bg:string,fg:string,icon:string,group:string}>
     */
    function attendance_catalog(): array
    {
        return [
            'present' => [
                'label' => 'Presente',   'short' => 'Presente',
                'color' => '#059669', 'bg' => '#d1fae5', 'fg' => '#065f46',
                'icon'  => 'person-check-fill', 'group' => 'asistencia',
            ],
            'absent' => [
                'label' => 'Ausente',    'short' => 'Ausente',
                'color' => '#dc2626', 'bg' => '#fee2e2', 'fg' => '#991b1b',
                'icon'  => 'person-x-fill', 'group' => 'asistencia',
            ],
            'unjustified' => [
                'label' => 'No justificado', 'short' => 'No justif.',
                'color' => '#b91c1c', 'bg' => '#fee2e2', 'fg' => '#7f1d1d',
                'icon'  => 'person-x-fill', 'group' => 'asistencia',
            ],
            'pending' => [
                'label' => 'Pendiente',  'short' => 'Pendiente',
                'color' => '#d97706', 'bg' => '#fef3c7', 'fg' => '#92400e',
                'icon'  => 'hourglass-split', 'group' => 'convocatoria',
            ],
            'confirmed' => [
                'label' => 'Confirmada', 'short' => 'Confirmada',
                'color' => '#2563eb', 'bg' => '#dbeafe', 'fg' => '#1e40af',
                'icon'  => 'check-circle', 'group' => 'convocatoria',
            ],
            'declined' => [
                'label' => 'Avisó ausencia', 'short' => 'Avisó',
                'color' => '#6b7280', 'bg' => '#f3f4f6', 'fg' => '#374151',
                'icon'  => 'dash-circle', 'group' => 'convocatoria',
            ],
        ];
    }
}

if (!function_exists('attendance_meta')) {
    /**
     * Metadatos de un estado concreto. Cae a 'pending' si el valor es desconocido.
     */
    function attendance_meta(?string $key): array
    {
        $catalog = attendance_catalog();
        return $catalog[$key] ?? $catalog['pending'];
    }
}

if (!function_exists('attendance_groups')) {
    /**
     * Estados agrupados por eje, para pintar <optgroup> en los selectores.
     *
     * @return array{asistencia:array<string,string>,convocatoria:array<string,string>}
     */
    function attendance_groups(): array
    {
        $out = ['asistencia' => [], 'convocatoria' => []];
        foreach (attendance_catalog() as $key => $meta) {
            $out[$meta['group']][$key] = $meta['label'];
        }
        return $out;
    }
}

if (!function_exists('attendance_badge')) {
    /**
     * Píldora de estado coherente con el resto de la plataforma (.badge-status).
     */
    function attendance_badge(?string $key, bool $short = false): string
    {
        $m    = attendance_meta($key);
        $text = $short ? $m['short'] : $m['label'];
        return '<span class="pl-badge" style="background:' . $m['bg'] . ';color:' . $m['fg'] . '">'
            . '<i class="bi bi-' . $m['icon'] . '"></i>' . esc($text) . '</span>';
    }
}
