<?php

/**
 * Formato de importes (céntimos → "1.234,50 €"). Finanzas 2.0.
 */
if (!function_exists('eur')) {
    function eur(?int $cents, bool $symbol = true): string
    {
        if ($cents === null) {
            return '—';
        }
        // Espacio de no separación: el «€» nunca queda solo en otra línea.
        return number_format($cents / 100, 2, ',', '.') . ($symbol ? "\u{00A0}€" : '');
    }
}

if (!function_exists('eur_input')) {
    /** Para rellenar un input: "225,50" (sin miles ni símbolo). */
    function eur_input(?int $cents): string
    {
        return $cents === null ? '' : number_format($cents / 100, 2, ',', '');
    }
}
