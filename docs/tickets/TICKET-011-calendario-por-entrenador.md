# TICKET-011 — Calendario por entrenador/staff y cambio de responsable de clase

| Campo        | Valor                                                  |
|--------------|--------------------------------------------------------|
| Categoría    | `feature` — Petición del cliente                        |
| Prioridad    | `alta`                                                 |
| Estado       | `resuelto` — pendiente de PR y despliegue              |
| Módulo       | Clases · Calendario · Dashboard · Notificaciones        |
| Rama         | `feat/calendario-por-entrenador`                       |
| Detectado en | Petición del cliente (2026-09-16)                      |
| Entregado en | `v1.8.0` (pendiente de despliegue)                     |

## Petición

1. Un **admin** puede **ver y controlar el calendario de cada entrenador y de
   cada miembro del staff**: filtrar el calendario por una persona y ver solo
   sus clases.
2. Cada clase del calendario **muestra el entrenador asignado**.
3. Se puede **cambiar el entrenador** de una clase, y el cambio se guarda en
   todos los sitios.
4. El cambio **se refleja también para el entrenador** (su calendario, sus
   clases).

## 1. Decisiones (confirmadas con el usuario antes de implementar)

- **Clases recurrentes:** al cambiar el responsable se pregunta el alcance
  — «Solo esta sesión» o «Esta y las siguientes» —, como en Google Calendar.
  Nunca se aplica a la serie entera sin elegirlo a propósito, y solo se
  tocan sesiones futuras `scheduled` (una cerrada/cancelada no cambia).
- **Avisos:** notificación tanto al responsable anterior como al nuevo, con
  un solo aviso resumido si afecta a varias sesiones de una serie.
- **Dónde va el filtro:** en Clases y en el Dashboard (mismo sitio que el
  toggle «Todas / Mis clases» de v1.7.0, que este filtro sustituye y amplía).
- **Cómo se muestra el responsable:** su nombre en la propia tarjeta de la
  clase (abreviado en el calendario, completo en el detalle/tooltip); el
  color de la tarjeta sigue indicando el estado (programada/completada).

## 2. Solución aplicada

### 2.1 Filtro «Ver calendario de…» (admin/superadmin)

- El toggle «Todas / Mis clases» de `/clases` y del Dashboard pasa a ser un
  `<select>` con: *Todas las clases*, *Mis clases* (si el propio admin tiene
  sesiones asignadas), un grupo **Entrenadores**, un grupo **Staff** (solo
  personas con alguna sesión asignada — `getResponsableFilterOptions()`) y
  *Sin responsable asignado*.
- Un único parámetro seguía sirviendo, ahora con más vocabulario:
  `GET /clases/api/calendario?...&scope=all|mine|none|coach:<id>|staff:<id>`.
  `ClasesService::parseScopeParam()` (pura, testeada) lo traduce a los dos
  argumentos de `getSessionsForCalendar()`. Se guarda en `localStorage`
  (`jp_cal_scope`), igual que el toggle anterior.
- El filtro **solo se aplica si el rol es admin/superadmin**
  (`getSessionsForCalendar()` lo ignora para cualquier otro rol): un
  coach/staff no puede ver el calendario de otra persona cambiando el
  parámetro a mano — verificado.
- Con un filtro de persona activo, «Añadir sesión» / clic en un hueco del
  calendario **preasigna directamente a esa persona** en el modal de
  creación (`ClaseModal.open({ coachId })`).
- De paso se resolvió un bug latente: `switchView()` (Mes/Semana/Día)
  quitaba `active` a *todos* los `.calendar-view-tab`, incluido el toggle de
  ámbito (compartía la clase). Al pasar el ámbito a un `<select>` separado,
  deja de ocurrir.

### 2.2 Responsable visible en cada clase

- `ClasesService::attachResponsable()` añade `responsable_id`,
  `responsable_name` y `session_type` a cada evento del calendario (una
  consulta extra sobre `class_session_coaches`, máx. 1 responsable por
  sesión). Se aplica a **todos** los roles, no solo admin: la petición #2 no
  distinguía por rol.
- Mes: nombre abreviado tras el título (`CalOverlap.shortName()`, "Marc P.").
  Semana/Día: igual, en la misma línea de la tarjeta. Pop-up «Ver todas»:
  nombre completo bajo el título. Tooltip: nombre completo en los tres casos.

### 2.3 Cambiar el responsable

- Ficha de la clase: la tarjeta «Entrenadores / Staff responsable» tiene un
  botón **Cambiar** → modal con un `<select>` (el pool de coach u otro según
  `session_type`, con el actual preseleccionado, más «Sin responsable
  asignado») y, si la sesión pertenece a una serie con sesiones futuras
  programadas (`countFutureSeriesSessions()` > 1), el alcance por radio.
  El icono de papelera por fila (quitar sin reemplazar) se mantiene.
- `POST /clases/:id/responsable` → `ClasesController::changeResponsible()`
  → `ClasesService::changeResponsible()` (filtro `auth`,
  `role:superadmin,admin,staff` — un coach no puede usar esta vía, verificado
  con 403):
  - solo sesiones `scheduled`;
  - el nuevo responsable (si se indica) debe existir, estar activo y tener
    un rol válido para el `session_type` de la sesión;
  - alcance `single` (esta sesión) o `series` (esta y las siguientes
    `scheduled` de la misma clase recurrente, por fecha ≥ la de esta sesión);
  - dentro de una transacción: reemplaza el responsable
    (`syncCoaches()`) y **arrastra a los alumnos** cuyo `coach_id` era el
    anterior (o no tenían ninguno) al nuevo, sesión por sesión;
  - devuelve cuántas sesiones cambió; el controller lo traduce a un flash
    (`"Responsable actualizado en N sesiones."`).

### 2.4 Reflejo para el entrenador + notificaciones

- Automático en calendario, ficha, pasar lista y buscador (todo sale de
  `class_session_coaches`) — verificado: el entrenador que pierde la clase
  deja de verla en su calendario y su ficha; el que la recibe la ve al
  momento.
- Notificación al anterior responsable (si tenía y cambia) y al nuevo (si se
  asigna), con origen **nuevo** `NotificationModel::SOURCE_CLASS = 'class'`
  (además de `ticket`/`conversation` de TICKET-010) → enlaza a `/clases/:id`
  desde la campanita y desde `/notificaciones`.
- **Migración `2026-09-17-000001_WidenNotificationsSourceType`**:
  `notifications.source_type` era `ENUM('ticket','conversation')`; se amplía
  a `VARCHAR(20)` (crea las columnas si ni siquiera existían). Igual que
  TICKET-010, `prepareSource()` sigue enviando la notificación sin enlace si
  la BD todavía no tiene las columnas. SQL de producción:
  `docs/deploy/migraciones_notificaciones_clase.sql`.

## 3. Fuera de alcance

- Reprogramar/arrastrar clases en el calendario.
- Varios responsables por sesión (sigue siendo 1).
- Cambiar el responsable de la plantilla recurrente para sesiones que aún no
  existen (se generan todas al crear la serie).
- Deduplicar el motor `CalOverlap` entre Clases y Dashboard (se mantienen
  los dos bloques sincronizados, como hasta ahora — cambios reflejados en
  ambos ficheros).
- El formulario de edición completo (`/clases/:id/editar`) sigue permitiendo
  cambiar `coach_ids[]` directamente; ese camino no arrastra a los alumnos
  automáticamente ni notifica (ya tiene sus propios selectores por alumno,
  así que no queda huérfano, pero es un flujo más pesado y preexistente al
  margen de este ticket).

## 4. Errores reales encontrados y corregidos durante la verificación

Dos bugs de los que un simple `php -l` (solo sintaxis) no habría avisado —
solo salieron al probar contra la BD real en Docker:

1. **`getResponsableFilterOptions()`**: `->select('DISTINCT u.id, u.name, u.role')`
   hace que CodeIgniter escape `DISTINCT` como si fuera una columna más
   (`` `DISTINCT` ``) → error de sintaxis SQL. Arreglado con
   `->select('u.id, u.name, u.role')->distinct()`.
2. **`changeResponsible()`**: usaba `->orWhereNull('coach_id')`, que no
   existe en el `Builder` de esta versión de CodeIgniter/MySQLi (sí existe
   `whereNull()`, pero no su variante `or`). Sustituido por
   `->orWhere('coach_id', null)`. Sin este fix, el endpoint devolvía un 500
   silencioso — silencioso además porque el log de ese día tenía permisos
   `644` root:root (Apache/`www-data` no podía escribir), otro rasguño ya
   conocido del entorno Docker local, sin relación con el código.

## 5. Verificación

- `tests/unit/ClasesResponsableTest.php` (10 tests): `parseScopeParam()`
  (las 5 formas + valores inválidos ignorados), ruta con el filtro de rol
  correcto, `NotificationModel::SOURCE_CLASS` + su enlace, y que el
  código/las vistas llevan el responsable y el modal de cambio.
- Suite completa: **314 tests OK** (1 omitido) en Docker.
- Verificación end-to-end en Chromium (Playwright) + comprobaciones directas
  contra MySQL, con datos de `BulkDemoDataSeeder` (22 entrenadores, 354
  sesiones): filtro por entrenador (`coach:28` → solo sus sesiones),
  «Sin responsable» (22 sesiones), un coach no puede filtrar por otra
  persona; selector persistente en `localStorage` y tras recargar; cambio de
  responsable `single` en una sesión de una serie de 7 → solo esa sesión
  cambia; alcance `series` → las 7 sesiones futuras cambian, las 9 pasadas
  no; el entrenador que pierde la clase deja de verla en su calendario y el
  que la recibe la ve; ambos reciben notificación con enlace a la clase;
  admin es un rol válido como responsable (no se rechaza); un coach no puede
  llamar al endpoint (403).

## 6. Notas de despliegue

- **SQL:** `docs/deploy/migraciones_notificaciones_clase.sql` en phpMyAdmin
  (producción y PPR/demo si Render no la aplica). Idempotente y sin orden
  estricto respecto al despliegue del código (igual que TICKET-010).
- Ficheros: `app/Config/Routes.php`, `app/Controllers/ClasesController.php`,
  `app/Controllers/DashboardController.php`, `app/Models/NotificationModel.php`,
  `app/Services/ClasesService.php`, `app/Views/clases/index.php`,
  `app/Views/clases/show.php`, `app/Views/dashboard/index.php`,
  `public/assets/css/app.css`, `public/assets/js/clase-modal.js`.
- Sin cambios de `.env`.
- Nota de entorno local: para las pruebas manuales se detectó que las
  cuentas de demo `demo.coach1`/`demo.coach2` (`BulkDemoDataSeeder`) tenían
  en la BD de Docker una contraseña distinta a la documentada en el propio
  seeder (`Demo1234!`); se igualaron a la del seeder. Solo afecta al entorno
  local, no a producción.
