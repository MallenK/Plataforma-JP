# Propuesta — Calendario dual para admin/superadmin que también entrena

> Estado: **borrador para revisión**. Fecha: 2026-09-15.
> Rama: `feat/calendario-dual-admin-coach` (desde `main`, `main` está en v1.6.1).
> Origen: petición de la academia — un `admin`/`superadmin` que también tiene
> clases asignadas como entrenador quiere poder ver **solo sus clases** sin
> tener que mirar el calendario completo de la academia, y viceversa.

---

## 1. Diagnóstico

Hoy el calendario (mes/semana/día) de **Clases** y del **Dashboard** son dos
vistas front-end distintas pero comparten la misma fuente de datos:

```
clases/index.php:531,779   fetch(`/clases/api/calendario?year=&month=`)
dashboard/index.php:791,904 fetch(`/clases/api/calendario?year=&month=`)
        │
        ▼
ClasesController::calendario()  →  ClasesService::getSessionsForCalendar($year, $month, $userId, $role)
```

`getSessionsForCalendar()` (`app/Services/ClasesService.php:319`) decide el
dataset **solo por rol**:

- `player`/`alumno` → solo sus sesiones (join `class_session_players`).
- `coach`/`staff` → solo las sesiones donde son responsables (join
  `class_session_coaches`).
- cualquier otro rol (`admin`, `superadmin`) → **todas** las sesiones
  (`ClassSessionModel::getForMonth()`), sin excepción.

Es decir: un `admin`/`superadmin` que también aparece como responsable
técnico de sesiones (`ClasesService::RESPONSABLE_TECNICO_ROLES` ya incluye
`admin`/`superadmin` — están pensados para poder impartir clase) **no tiene
forma de aislar "las mías"**. Ve siempre el calendario completo, tanto en
`/clases` como en el widget de Dashboard.

El renderizado del calendario (`CalOverlap`/`clusterPack`/`dayLayerHtml`,
documentado en `CLAUDE.md`) está además **duplicado** entre
`dashboard/index.php` y `clases/index.php`. Cualquier solución que implique
duplicar de nuevo ese bloque (p. ej. dos calendarios completos por página)
multiplicaría esa deuda técnica por 2.

## 2. Decisión de alcance

Se descartan **rutas/vistas separadas** (`/clases/mis-clases` como página
aparte) a favor de un **toggle de ámbito** dentro de la misma vista, en las
dos pantallas afectadas:

```
┌─────────────────────────────────────────┐
│  Clases           [ Todas ▾ Mis clases ] │  ← nuevo selector, junto al buscador
├─────────────────────────────────────────┤
│         (mismo calendario CalOverlap)    │
└─────────────────────────────────────────┘
```

Motivo: reutiliza el 100% del motor de calendario ya existente (una sola
fuente de verdad para el layout), solo cambia el **filtro de datos**. Menos
código nuevo, cero riesgo de que las dos vistas se desincronicen con el
tiempo.

El selector solo debe aparecer si el usuario **es** `admin`/`superadmin` **y
además** tiene alguna sesión asignada como responsable (si no, no hay nada
que distinguir — se le oculta para no añadir ruido).

## 3. Diseño técnico

### 3.1 Backend

- `ClasesService::getSessionsForCalendar()` gana un parámetro
  `bool $onlyMine = false`. Si es `true`, el `admin`/`superadmin` cae en la
  misma rama de query que hoy usan `coach`/`staff` (join
  `class_session_coaches` por `$userId`), en vez de `getForMonth()`.
- Nuevo helper `ClasesService::hasOwnAssignedSessions(int $userId): bool`
  (`EXISTS` sobre `class_session_coaches` para ese usuario) — decide si el
  toggle se muestra.
- `ClasesController::calendario()` lee `?scope=mine|all` (default `all`) y
  lo traduce a `$onlyMine`.
- `ClasesController::index()` (y el punto donde `DashboardController`
  prepara las flags de la vista) pasan `showScopeToggle = isAdminRole &&
  hasOwnAssignedSessions($userId)` a la vista.
- No toca `getWeekSessions()` (usada solo por `/clases/pasar-lista`, página
  de gestión ya restringida a admin/superadmin) ni el buscador `search()`
  (ya respeta rol).

### 3.2 Frontend

- Mismo componente de toggle en `clases/index.php` (cabecera del
  calendario, junto a `#cal-search-input`) y en el widget de calendario de
  `dashboard/index.php`.
- Los dos `fetch('/clases/api/calendario?...')` añaden `&scope=` según el
  estado del toggle.
- Estado del toggle: preferencia **de navegador** (localStorage), no de
  servidor — mismo patrón que "Ocultar los tickets que he reportado yo"
  (v1.6.0): es cosa de cada gestor, no cambia lo que ven los demás. Se
  recuerda por separado para Clases y para Dashboard, o se comparte una
  sola clave — a decidir en implementación (probablemente compartida: es la
  misma persona mirando el mismo dato).

### 3.3 Fuera de alcance de esta propuesta

- Torneos/Compras: no aplica, siguen desactivados.
- Cambios en `pasar_lista_semanal` (ya es una vista de gestión, no de
  "mis clases").
- Cualquier cambio de permisos: un admin/superadmin sigue viendo y pudiendo
  gestionar todo; el toggle es solo un filtro de visualización, nunca de
  autorización.

## 4. Plan de trabajo

1. `ClasesService`: `onlyMine` en `getSessionsForCalendar()` +
   `hasOwnAssignedSessions()`. Tests unitarios (estilo
   `tests/unit/ClasesSessionTimesTest.php`).
2. `ClasesController::calendario()`: leer `scope`.
3. `ClasesController::index()` / `DashboardController::index()`: calcular y
   pasar `showScopeToggle`.
4. Vista + JS: toggle en `clases/index.php` y `dashboard/index.php`,
   persistencia en localStorage, query param en los `fetch`.
5. Probar con un usuario admin/superadmin con clases asignadas y sin ellas
   (el toggle debe desaparecer en el segundo caso).
6. Version bump (MINOR, es una feature nueva) + entrada en
   `app/version.json`.
