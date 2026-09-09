<?php

namespace App\Models;

use CodeIgniter\Model;

class PlayerProfileModel extends Model
{
    protected $table = 'player_profiles';
    protected $allowedFields = [
        'player_id',
        'birth_date',
        'height',
        'weight',
        'position',
        'level',
        'category',
        'team',
        'league',
        'medical_notes',
        'image_rights_signed',
    ];

    /**
     * Catálogo de posiciones de fútbol. Un alumno puede tener varias.
     * Se muestra como checkboxes en los formularios y en formato lista
     * en la ficha (en vez de un único texto libre, que es lo que
     * provocaba el desbordamiento visual — ver TICKET-002).
     */
    public const POSITIONS = [
        'portero'           => 'Portero',
        'lateral_derecho'   => 'Lateral derecho',
        'lateral_izquierdo' => 'Lateral izquierdo',
        'central'           => 'Central',
        'pivote'            => 'Pivote',
        'mediocentro'       => 'Mediocentro',
        'interior'          => 'Interior',
        'mediapunta'        => 'Mediapunta',
        'extremo_derecho'   => 'Extremo derecho',
        'extremo_izquierdo' => 'Extremo izquierdo',
        'delantero_centro'  => 'Delantero centro',
    ];

    /**
     * Codifica una lista de posiciones (claves de POSITIONS, o texto
     * libre si se cuela algo fuera del catálogo) para guardarla en
     * la columna `position` (TEXT) como JSON.
     *
     * @param string[] $positions
     */
    public static function encodePositions(array $positions): ?string
    {
        $clean = array_values(array_unique(array_filter(array_map('trim', $positions))));
        if (empty($clean)) {
            return null;
        }
        return json_encode($clean, JSON_UNESCAPED_UNICODE);
    }

    /**
     * Decodifica la columna `position` a una lista de claves/etiquetas.
     * Soporta:
     *  - El formato nuevo: JSON de un array de claves de POSITIONS.
     *  - El formato antiguo: texto libre (incluido "Extremo/mediapunta"),
     *    partido por '/', ',' o ';' para poder mostrarlo también en lista.
     *
     * @return string[]
     */
    public static function decodePositions(?string $raw): array
    {
        if ($raw === null || trim($raw) === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return array_values(array_filter(array_map('trim', $decoded), fn($v) => $v !== ''));
        }

        // Formato antiguo: texto libre, posiblemente varias posiciones
        // separadas por '/', ',' o ';'.
        $parts = preg_split('/[\/,;]+/', $raw) ?: [$raw];
        return array_values(array_filter(array_map('trim', $parts), fn($v) => $v !== ''));
    }

    /**
     * Etiqueta legible de una posición: usa el catálogo si la clave
     * es conocida, o el propio valor si viene de texto libre/legacy.
     */
    public static function positionLabel(string $keyOrLabel): string
    {
        return self::POSITIONS[$keyOrLabel] ?? $keyOrLabel;
    }

    /**
     * Lista de posiciones ya traducidas a etiqueta legible, lista para
     * pintar en la ficha del alumno.
     *
     * @return string[]
     */
    public static function decodePositionLabels(?string $raw): array
    {
        return array_map([self::class, 'positionLabel'], self::decodePositions($raw));
    }

    /**
     * Posiciones en una sola línea legible: "Extremo derecho / Mediapunta".
     * Devuelve $empty ('—' por defecto) si no hay ninguna.
     */
    public static function formatPositions(?string $raw, string $empty = '—'): string
    {
        $labels = self::decodePositionLabels($raw);
        return $labels === [] ? $empty : implode(' / ', $labels);
    }
}
