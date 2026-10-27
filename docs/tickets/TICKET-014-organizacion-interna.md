# TICKET-014 — Pantalla «Organización» (agenda y tareas por estados)

| Campo        | Valor                                                        |
|--------------|--------------------------------------------------------------|
| Categoría    | `feature` — Nuevo módulo                                     |
| Prioridad    | `media`                                                      |
| Estado       | `en desarrollo` (F1–F2 y F4 hechas; planificador descartado) |
| Rama         | `feat/organizacion` (de `main`, 66f5916)                     |
| Módulo       | Organización (nuevo)                                         |
| Roles        | `superadmin`, `admin`, `staff`, `coach` — **NO** `player`    |

## Objetivo

Centralizar la organización interna de la academia con recursos propios (sin
herramientas externas). Una pantalla nueva, **Organización**, en el menú lateral
para todos los roles salvo el alumno (`player`), con dos vistas:

1. **Tareas** — tablero tipo Kanban: `Pendiente → En curso → En revisión → Hecho`.
2. **Agenda** — calendario mensual de eventos propios y del equipo, con las **clases
   relacionadas con cada usuario** (solo lectura) y las tareas con fecha límite.

> El **planificador semanal** se diseñó e implementó y se retiró (decisión 2026-10-27);
> sus columnas `planned_*` se eliminaron de la migración antes de desplegar.

Cada ítem es **de equipo** o **«Solo yo»** (privado: ni admin ni superadmin lo ven).

## Datos (migración `2026-10-27-000001_Organizacion`, idempotente)

- `org_tasks`: id, title, description, status, priority, due_date, assigned_to,
  created_by, visibility (`personal`|`team`), position, completed_at, due_notified_at,
  archived_at, created_at, updated_at.
- `org_events`: id, title, description, start_at, end_at, all_day, location_text,
  created_by, visibility, reminder_sent_at, archived_at, timestamps.
- `org_event_users`: event_id, user_id (invitados/participantes).
- ⚠️ `users.id` es INT CON SIGNO → FKs sin `UNSIGNED`.
- **Nada se borra**: «eliminar» = `archived_at` (+ `AuditService::record`), como el resto de la plataforma.
- Prod: `docs/deploy/migraciones_organizacion.sql` (añadir con `git add -f`).

## Backend

- `OrganizacionController` fino → `App\Services\OrganizacionService` (lógica) +
  `OrgTaskModel`, `OrgEventModel`.
- Rutas con `filter => ['auth', 'role:superadmin,admin,staff,coach']` (actualizar la
  matriz de permisos de `Routes.php`; ya menciona «organizador» para coach sin
  que exista código).
- Visibilidad aplicada SIEMPRE en el servicio: `personal` = solo su creador (ni
  admin ni superadmin lo ven); `team` = todos los roles con acceso.
- Errores de negocio con `\DomainException` (`_remap` ya los convierte en 422/flash).
- Endpoints AJAX devuelven `csrf_hash()` fresco y llaman a `window.handleApiError`.

## Frontend

- Vistas `app/Views/organizacion/index.php`;
  JS `public/assets/js/organizacion.js` (todo en IIFE) + `organizacion.css`.
  Sin bundler ni framework SPA (regla del proyecto).
- Móvil (los coaches lo usan a pie de campo): tablero = una columna con pestañas
  de estado; agenda = mes con puntos + detalle del día.
  Mover tarea de estado con menú además de arrastrar (el arrastre táctil es frágil).
- Arrastrar y soltar: SortableJS desde cdnjs con versión fija (mismo patrón que html2canvas).
- Menú: entrada «Organización» en `components/sidebar.php`, oculta a `player`/`alumno`.

## Fases (commits separados en la misma rama, un solo PR)

- **F1** ✅ Migración + tablero de tareas.
- **F2** ✅ Agenda (eventos, participantes, clases relacionadas).
- ~~F3 Planificador semanal~~ — descartado.
- **F4** ✅ Avisos y tiempo real (categoría de preferencias «Organización», oculta al alumno):
  - Tarea asignada → asignado. Tarea que otra persona pasa a «En revisión»/«Hecho» → creador.
  - Evento: invitación, cambio de fecha/hora/lugar y cancelación → participantes.
  - Recordatorios (sin cron, `ScheduledNoticeService`): evento ≤25 h y tarea que vence hoy/mañana,
    una sola vez (`org_events.reminder_sent_at`, `org_tasks.due_notified_at`).
  - Los avisos enlazan a `/organizacion?tarea=ID` / `?evento=ID` (abre el elemento y marca leído).
  - Tiempo real: sello `org` en `/live/ping`; el tablero/agenda se recarga si otra persona cambia algo.
  - Una tarea o evento «Solo yo» no puede asignarse ni tener participantes (no lo verían).

## Decisiones tomadas (2026-10-27)

1. Parte común (equipo) y parte privada («Solo yo»).
2. Cualquiera asigna a cualquiera; solo admin/superadmin editan lo ajeno del equipo; el asignado cambia el estado.
3. Las clases relacionadas con cada usuario se ven en la agenda (solo lectura).
4. Nivel de versión: pendiente de confirmar al abrir el PR (propuesta 2.2.0).

## Pruebas previstas

- Unit puros: visibilidad, permisos, normalizadores, textos de avisos, categoría de preferencias.
- Base de datos real (MySQL Docker, esquema manual): permisos por rol y visibilidad personal vs equipo.
- JS con jsdom (`tests/js/`): tablero y agenda (pendiente).
- Test de rutas: toda ruta de Organización lleva `auth` + `role:`; `player` recibe 403.
