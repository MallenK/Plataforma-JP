# TICKET-007 — Calendario en móvil: la vista Mes se descuadra con nombres largos + clases solapadas ilegibles

| Campo        | Valor                                              |
|--------------|----------------------------------------------------|
| Categoría    | `bug` — Error / Bug                                |
| Prioridad    | `media`                                            |
| Estado       | `en curso`                                         |
| Módulo       | Dashboard (mini-calendario) · Clases (calendario)  |
| Rama         | `fix/calendario-mes-desborde-movil`                |
| Detectado en | Producción (móvil, `app.jppreparation.com`)        |
| Entregado en | `v1.1.6` (pendiente)                               |

## Descripción

En el mini-calendario del **Dashboard**, vista **Mes**, en móvil: cuando un
día tiene una clase cuyo texto es largo (p. ej. `20:00 ORIOL RODRIGUEZ`), la
"chip" del evento **no se recorta** y ensancha la columna de ese día. El resto
de columnas se comprimen, los números de los días siguientes (4, 5, 6…)
quedan aplastados a la derecha y **desalineados** respecto a la cabecera
`L M X J V S D`. La cuadrícula entera se ve rota.

## Causa raíz

En `.cal-month-grid` (y `.cal-month-headers`) las columnas se declaran como
`grid-template-columns: repeat(7, 1fr)`. Un track `1fr` tiene
`min-width: auto`, es decir **no puede encogerse por debajo del tamaño de su
contenido**. La chip es `white-space: nowrap`, así que su tamaño mínimo es
toda la cadena `20:00 ORIOL RODRIGUEZ`. En un viewport estrecho la columna
del día crece para acomodar ese texto en lugar de recortarlo, y el
`text-overflow: ellipsis` de `.cal-chip` nunca llega a activarse.

La vista **Semana** no sufre esto porque tiene `min-width` fijo + wrapper con
scroll horizontal; la vista **Mes** no.

Mismo código duplicado en dos vistas:
- `app/Views/dashboard/index.php` (bloque `<style>` inline).
- `app/Views/clases/index.php` (bloque `<style>` inline).

## Solución aplicada

En ambas vistas:

- `.cal-month-headers` y `.cal-month-grid`:
  `grid-template-columns: repeat(7, 1fr)` → `repeat(7, minmax(0, 1fr))`.
  Permite que cada columna baje del tamaño de su contenido, con lo que el
  `text-overflow: ellipsis` de `.cal-chip` recorta el texto y las 7 columnas
  quedan siempre iguales.
- `.cal-cell`: se añade `min-width: 0` (refuerzo del mismo principio para el
  contenido de la celda).

### Vistas Semana / Día — clases solapadas (`CalOverlap.slotHtml`)

Cuando en una misma franja horaria hay varias clases se pintan en columnas y,
por encima de un límite, aparece el botón que abre el pop-up "Ver todas".

- **Escritorio:** Semana admite **2** columnas (más no caben legibles en una
  columna de día); Día admite hasta **6** (ocupa todo el ancho). 3/7+ → botón.
- **Móvil** (`window.matchMedia('(max-width: 768px)')`): `maxCols` forzado a
  **1**, así que con 2+ clases en la franja se muestra **directamente el
  botón "Ver todas · N"**, que abre el pop-up selector.
- Texto del botón: de `N clases · HH:00` a `Ver todas · N` (el HH:00 ya está
  en la etiqueta de la fila; el `title` conserva el detalle completo).

### Vistas Semana / Día — rango horario (solo Dashboard)

El mini-calendario del Dashboard pintaba de **07:00 a 20:00 fijo**
(`const HS=7, HE=20`), así que las clases de las 20:00 **no se veían** en
Semana/Día. Ahora `DBCAL.hourRange()` calcula el rango a partir de las clases
visibles (07–20 por defecto, ampliado hacia arriba/abajo si hay clases fuera).
`clases/index.php` ya iba de 07 a 22, se deja igual.

Todo esto es cambio de JS (markup inline), sin BD ni migraciones.

## Datos de prueba

`app/Database/Seeds/DevTestDataSeeder.php` — nuevo método
`seedRecurringOverloadedClasses()`: crea 8 plantillas recurrentes semanales
(todas los miércoles 20:00–21:00) con rangos de vigencia escalonados, de modo
que un miércoles concreto (~3 semanas vista) coinciden las **8 clases** a la
misma hora, y el resto de miércoles coinciden 3–5. Sirve para probar el chip
"8 clases · 20:00" + el pop-up y, en la vista Mes de móvil, que la celda no se
descuadra. Solo desarrollo local:

```bash
docker compose exec app php spark db:seed DevTestDataSeeder
```

## Notas de despliegue

- Ninguna. No hay migraciones ni variables de entorno.
- Los ficheros que van a producción son las dos vistas PHP (`app/Views/...`).
  El cambio del seeder es **solo para desarrollo** (no se ejecuta en Hostinger).

## Verificación

- `tests/unit/CalendarViewsTest.php` — comprueba: (1) el mes usa
  `minmax(0, 1fr)` y no vuelve a `repeat(7, 1fr)`; (2) `slotHtml` colapsa a 1
  columna en móvil (`matchMedia('(max-width: 768px)')`), Semana pide 2 y Día
  6, y el botón dice "Ver todas · N"; (3) el Dashboard usa `hourRange()` en
  Semana y Día y ya no tiene el rango fijo `HS=7, HE=20`.
- Suite completa: 170/170 en verde (Docker).
- Manual (móvil, ancho ~360 px):
  - Dashboard → Calendario → **Mes**, mes con una clase de nombre largo → la
    chip se recorta con "…", las 7 columnas iguales, días alineados con
    `L M X J V S D`. Clases → **Mes** → igual.
  - Dashboard → **Semana**/**Día** de un día con clases a las 20:00 → ahora
    se ven (antes el grid acababa a las 20:00).
  - **Semana**/**Día** con 3+ clases a la misma hora → en móvil sale el botón
    "Ver todas · N"; en escritorio, columnas (Semana 2, Día 6) o botón.
