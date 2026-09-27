# Plan: paginación (DataTables) + vista lista/cuadrícula

Rama: `feat/tablas-paginadas-vista-lista-cuadricula` (desde `origin/main`). Versión objetivo: MINOR (feat).

## Objetivo
1. Ningún listado carga/pinta todos los registros de golpe: **25 por defecto** + paginación.
2. Todos los listados tienen un toggle **Lista ⇄ Cuadrícula**, recordado por listado.

## Inventario (estado actual)
| Vista | Hoy | Datos | Estrategia |
|---|---|---|---|
| `alumnos/index` | `<table>` + filtro JS propio por `data-*` (todo en DOM) | todos los alumnos | Cliente |
| `entrenadores/index` | `<table>` | todos | Cliente |
| `bonos/index` | `<table>` con estilos inline | todos los bonos asignados (crece rápido) | Cliente → servidor si >1000 |
| `tickets/index` y `tickets/admin/index` | lista/tabla, `TicketModel::getAll(limit 30, offset)` ya soporta offset, `getForUser` limit 500 | crece sin límite | **Servidor** |
| `notificaciones/index` | 2 listas (recibidas/enviadas), `NotificationModel` con limit/offset | crece sin límite | **Servidor** |
| `documentacion/index` | tarjetas de carpetas (`renderFolderCard`) + archivos | moderado | Cliente (solo cuadrícula↔lista de archivos) |
| `configuracion/index` (4 tablas: staff, sedes, tipos de bono, actividad de seguridad) | `<table>` | pequeñas; actividad de seguridad grande | Cliente; actividad → servidor |
| `alumnos/show`, `entrenadores/show`, `perfil/index`, `bonos/show`, `clases/show`, `clases/pasar_lista` | tablas embebidas | pequeñas | **Fuera de alcance** salvo histórico largo (revisar caso a caso). `pasar_lista` NO se pagina (hay que ver toda la clase) |
| `torneos/*`, `compras/*` | módulos desactivados | — | No tocar (regla del proyecto) |
| `clases/index` (calendario) | calendario, no tabla | — | Fuera |
| `mensajes` | ya pagina por cursor (v1.7.2) | — | Fuera |

## Decisión técnica
- **DataTables 2.x** (core, sin extensiones pesadas) sobre el jQuery ya presente. Carga **local** en `public/assets/js/vendor/` (como jQuery) para no depender de CDN; solo se incluye en vistas que lo usan (`section('scripts')`).
- Idioma español (`es-ES.json` local), `pageLength: 25`, `lengthMenu: [10,25,50,100]`, `dom` minimalista con estilo `table-jp` (tema propio en `app.css`, no el de Bootstrap).
- **Modo cliente** para listados pequeños/medianos (el HTML ya viene con todas las filas; DataTables pagina en DOM). Sirve como mejora rápida, pero no reduce la carga del servidor.
- **Modo servidor** (`serverSide: true`, AJAX JSON) para tickets, notificaciones y actividad de seguridad: nuevo endpoint por listado que reutiliza `applyFilters()`/`limit,offset` + `countAll()` y respeta rol/scope. CSRF: los endpoints AJAX devuelven `csrf_hash()` fresco (patrón actual).
- Los filtros propios actuales (buscador + selects de estado/ficha) se **integran** con DataTables (`$.fn.dataTable.ext.search` en cliente; parámetros extra `ajax.data` en servidor) en vez de convivir con el JS de filtrado manual, que se elimina.

## Vista lista/cuadrícula
- Componente compartido `public/assets/js/list-view.js` + estilos en `app.css`: botones `bi-list-ul` / `bi-grid-3x3-gap` en la cabecera de la card, `aria-pressed`, persistencia en `localStorage` (`jp:view:<listId>`, con try/catch).
- **Un solo DataTable, dos presentaciones**: en modo cuadrícula el CSS convierte `tbody` en `display:grid` y cada `tr` en tarjeta (celdas con `data-label`, generado por DataTables `columns.createdCell` o atributo en la vista). Así paginación, búsqueda y orden funcionan igual en ambas vistas y no hay que mantener dos plantillas por listado.
- Para listados con avatar/estado (alumnos, entrenadores) la tarjeta reordena: avatar+nombre arriba, badges, acciones abajo. Configurable por listado con clases `.jp-card-title/.jp-card-meta/.jp-card-actions` en las `td`.
- Móvil (<768px): por defecto cuadrícula de 1 columna (sustituye el scroll horizontal de la tabla); el toggle sigue disponible.
- `documentacion`: las carpetas ya son tarjetas → se añade toggle para verlas como lista compacta.

## Fases (un commit por fase, PR único)
1. **Base**: vendor DataTables + idioma, `list-view.js`, CSS (tema tabla, grid, toggle), helper PHP `partials/list_toolbar.php` (título, contador, toggle). Sin cambiar aún ninguna vista.
2. **Piloto: `alumnos/index`** (cliente). Migrar filtros, quitar JS manual, validar en escritorio/móvil.
3. **Resto cliente**: entrenadores, bonos, configuración (staff, sedes, tipos), documentación.
4. **Servidor**: tickets (`/tickets`, `/tickets/gestion`), notificaciones (recibidas/enviadas), actividad de seguridad. Endpoints JSON + tests de scope por rol (un coach/alumno nunca ve tickets ajenos).
5. **QA y cierre**: tests, revisión mobile-first, `app/version.json` (MINOR) + panel del sidebar, docs.

## Riesgos / puntos de atención
- **Escape/XSS**: en modo servidor las celdas se generan en PHP con `esc()` (devolver HTML ya escapado o texto + renderers seguros; nunca `innerHTML` de datos crudos).
- **Acciones con `confirm()`/forms POST dentro de filas paginadas**: los handlers deben ser delegados (`$(document).on`) porque DataTables recrea nodos; los `csrf_field()` de cada fila siguen funcionando.
- **Filas fuera de página** no existen en el DOM: cualquier JS que consulte `tbody tr` (contadores, `data-*`) debe usar la API de DataTables.
- **Alertas "empty state"**: mantener el estado vacío actual cuando no hay datos (no inicializar DataTables).
- **Permisos**: endpoints nuevos con `filter => ['auth','role:...']` según la matriz de `Routes.php`.
- **Consultas N+1 en servidor**: `reply_count` ya es subquery; vigilar al añadir columnas.
- Encoding UTF-8 en vistas: editar con Edit, no con scripts Python.

## Tests
- Unit: parámetros de paginación (page/length/orden/búsqueda) saneados y con tope (máx. 100).
- Integración (MySQL vía Docker, patrón existente): endpoints de tickets/notificaciones por rol.
- JS (jsdom, `tests/js/`): persistencia del toggle y reconstrucción de tarjetas.
- Manual: Chrome escritorio + móvil con >100 registros (seeders existentes: alumnos/chat largo).

## Decisiones tomadas (2026-09-24)
1. **Paginación 100 % en el navegador** (también tickets, notificaciones y actividad de seguridad): así el buscador y los filtros actúan sobre TODOS los registros. Sustituye a la estrategia "servidor" descrita arriba. `TicketsController::adminIndex` ya no pagina en servidor (tope de seguridad 5000; los filtros GET siguen aplicándose en servidor sobre el conjunto completo).
2. Vista por defecto: lista en escritorio, cuadrícula en móvil (<768px); la elección se recuerda por listado (`localStorage`, clave `jp:view:<listado>`).
3. Las tablas embebidas de fichas SÍ entran (excepto `clases/show` y `pasar_lista`: llevan formularios de asistencia y paginar ocultaría filas al enviar).

## Implementación
- `public/assets/js/datatables.min.js` (DataTables 2.1.8, en local) + `public/assets/js/list-view.js` (`JPList` para tablas, `JPCards` para listas de tarjetas/`<li>`, auto-init con `<table data-jp-list="clave">`), cargados desde `layouts/base.php`. Estilos al final de `app.css`. Partial `partials/list_view_toggle.php`.
- Migrados: alumnos, entrenadores, bonos, tickets (`/tickets` con JPCards, `/tickets/gestion` con JPList), notificaciones (recibidas/enviadas), documentación (carpetas con JPCards + tabla de archivos del modal), configuración (staff, sedes, seguridad, auditoría) y 13 tablas embebidas (alumnos/show, alumnos/profile, entrenadores/show, perfil/index, bonos/show).
- Verificado en Chrome (Playwright) contra Docker con 120 alumnos: 25 filas por página, "26–50 de 120", búsqueda global, toggle lista/cuadrícula, sin errores JS; `phpunit tests/unit` 331 OK.
- Versión 1.11.0 en `app/version.json`.
