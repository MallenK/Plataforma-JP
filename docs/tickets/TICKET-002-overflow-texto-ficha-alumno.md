# TICKET-002 — Desbordamiento de texto en las tarjetas de la ficha de alumno

| Campo        | Valor                                   |
|--------------|-----------------------------------------|
| Categoría    | `bug` — Error / Bug                     |
| Prioridad    | `media`                                 |
| Estado       | `abierto`                               |
| Módulo       | Alumnos (ficha / perfil)               |
| Rama         | `fix/overflow-texto-ficha`              |
| Detectado en | Producción (móvil, iPhone)             |

## Descripción

En la ficha del alumno, la tarjeta **POSICIÓN** con el valor
`Extremo/mediapunta` desborda el ancho de la tarjeta y el texto se
solapa visualmente con la tarjeta **CATEGORÍA** contigua (ver captura
adjunta). Ocurre en móvil, donde cada tarjeta ocupa media pantalla.

## Causa raíz

`public/assets/css/app.css`:

- `.metric-value` no tenía reglas de partido de palabra, y
  `Extremo/mediapunta` es un token sin espacios que no rompe de forma
  natural.
- `.metric-card` no tenía `overflow` controlado, así que el texto
  sobresalía fuera de sus límites.
- `.metric-icon` podía encogerse dentro del header flex.

## Solución aplicada

En `.metric-card`, `.metric-card-header`, `.metric-label`,
`.metric-value` y `.metric-icon`:

- `.metric-card`: `overflow: hidden` + `min-width: 0`.
- `.metric-value`: `overflow-wrap: anywhere` + `word-break: break-word`
  + `hyphens: auto` y `line-height` 1 → 1.15 para textos de 2 líneas.
- `.metric-card-header`: `gap` + `min-width: 0`.
- `.metric-label`: `min-width: 0` + `overflow-wrap: anywhere`.
- `.metric-icon`: `flex-shrink: 0`.

Afecta a todas las vistas que usan `.metric-card` (ficha de alumno,
perfil del alumno, dashboard, bonos, clases, compras), por lo que
cubre también cualquier otro valor largo del mismo tipo.

## Verificación

- `tests/unit/MetricCardOverflowTest.php`
- Manual: abrir `/alumno` y `/alumnos/:id` en viewport móvil con una
  posición larga; el texto se parte dentro de la tarjeta.
