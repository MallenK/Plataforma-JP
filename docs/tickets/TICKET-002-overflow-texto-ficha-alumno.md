# TICKET-002 — Desbordamiento de texto en las tarjetas de la ficha de alumno

| Campo        | Valor                                   |
|--------------|-----------------------------------------|
| Categoría    | `bug` — Error / Bug                     |
| Prioridad    | `media`                                 |
| Estado       | `resuelto`                              |
| Módulo       | Alumnos (ficha / perfil)               |
| Ramas        | `fix/overflow-texto-ficha`, `feat/multiples-posiciones-alumno` |
| Detectado en | Producción (móvil, iPhone)             |

## Descripción

En la ficha del alumno, la tarjeta **POSICIÓN** con el valor
`Extremo/mediapunta` desborda el ancho de la tarjeta y el texto se
solapa visualmente con la tarjeta **CATEGORÍA** contigua (ver captura
adjunta). Ocurre en móvil, donde cada tarjeta ocupa media pantalla.

## Causa raíz

Dos causas, una visual y otra de fondo:

1. `public/assets/css/app.css`: `.metric-value` no tenía reglas de
   partido de palabra y `.metric-card` no tenía `overflow` controlado.
2. **Causa de fondo:** `position` en `player_profiles` era un único
   campo de texto libre (`VARCHAR(50)`). "Extremo/mediapunta" no es un
   error de tecleo: son **dos posiciones reales** metidas a mano en un
   campo pensado para una sola cadena corta.

## Solución aplicada

### Parche visual (`fix/overflow-texto-ficha`)

En `.metric-card`, `.metric-card-header`, `.metric-label`,
`.metric-value` y `.metric-icon`: `overflow: hidden`, `min-width: 0`,
`overflow-wrap: anywhere`, `word-break: break-word`, `flex-shrink: 0`
donde corresponde. Cubre cualquier valor largo en las vistas que usan
`.metric-card` (ficha de alumno, perfil, dashboard, bonos, clases).

### Solución de fondo (`feat/multiples-posiciones-alumno`)

- `player_profiles.position` pasa de `VARCHAR(50)` a `TEXT` (migración
  `2026-09-01-000001_WidenPositionOnPlayerProfiles`), y se guarda como
  **lista JSON** de posiciones de un catálogo fijo
  (`PlayerProfileModel::POSITIONS`, `encodePositions()` / `decodePositions()`).
  Los valores antiguos en texto libre (incluido "Extremo/mediapunta")
  se siguen leyendo: `decodePositions()` los reconoce y los parte por
  `/`, `,` o `;`.
- Los 3 formularios (`alumnos/create.php`, `edit.php`,
  `create_profile.php`) pasan de un `<input type="text">` a un selector
  de checkboxes múltiple (`alumnos/_position_checkboxes.php`).
- La ficha (`alumnos/show.php`, `alumnos/profile.php`) muestra las
  posiciones como **lista** (una por línea) en vez de una única cadena
  larga — elimina el problema de raíz, no solo lo tapa con CSS.
- Vistas compactas (`alumnos/index.php`, `dashboard/index.php`,
  `perfil/index.php`) muestran las posiciones unidas por coma, con
  wrap defensivo.

## Verificación

- `tests/unit/MetricCardOverflowTest.php`
- `tests/unit/PlayerProfilePositionsTest.php`
- Manual: alumno de prueba con posiciones "Extremo derecho" +
  "Mediapunta" → se ven como lista en la ficha, sin desbordar, en
  móvil y escritorio.
