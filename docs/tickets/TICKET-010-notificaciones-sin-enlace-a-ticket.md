# TICKET-010 — Las notificaciones no llevan al ticket ni a la conversación

| Campo        | Valor                                                        |
|--------------|--------------------------------------------------------------|
| Categoría    | `bug` — Error / Bug                                          |
| Prioridad    | `alta`                                                       |
| Estado       | `resuelto` — pendiente de PR y despliegue                    |
| Módulo       | Notificaciones · Tickets · Mensajes                          |
| Rama         | `fix/mensajes-historial-largo` (junto con TICKET-009, un PR) |
| Detectado en | Local, al verificar TICKET-009                               |
| Entregado en | `v1.7.2` (pendiente de despliegue)                           |

## Descripción

Al pulsar una notificación de la campanita («Nuevo ticket», «Respuesta a tu
ticket», «Ticket … actualizado», «Nuevo mensaje de …») solo se marcaba como
leída: **no abría el ticket ni la conversación**. En `/notificaciones`
tampoco había forma de ir al origen.

Es especialmente grave para coach / staff / alumno: tienen oculta la bandeja
`/tickets` y **la única forma de seguir sus tickets es pulsar la
notificación**, que debía llevar a `/tickets/:id`.

## Causa raíz

1. `NotificationModel::$allowedFields` no incluía `source_type` ni
   `source_id`. CodeIgniter descarta en silencio los campos no permitidos, así
   que, aunque `MensajesController` los enviaba, **se guardaban siempre a
   NULL**. La campanita (`components/navbar.php`) ya sabía navegar a
   `tickets/:id` y `mensajes?conv=:id`, pero nunca recibía el dato.
2. Los 4 avisos de `TicketsController` (ticket nuevo, asignación, nota
   interna, respuesta / cambio de estado) ni siquiera enviaban el origen.
3. **Ninguna migración creaba esas columnas**: en algunas BD se añadieron a
   mano (local, demo) y en otras puede que no existan. No se puede dar por
   hecho su estado en producción ni en PPR.

## Solución aplicada

- **`NotificationModel`**
  - `source_type` / `source_id` en `allowedFields` + constantes
    `SOURCE_TICKET` / `SOURCE_CONVERSATION`.
  - `prepareSource()`: valida el origen y, **si la BD aún no tiene las
    columnas**, lo quita y deja un `warning` en el log → la notificación se
    envía igual (sin enlace) en vez de romper el INSERT. Solo se comprueba
    cuando la notificación trae origen (las de bonos/grupales no hacen
    consulta extra; CodeIgniter cachea los campos por conexión).
  - `sourceLink()`: ruta, texto e icono del enlace al origen.
- **`TicketsController`**: los 4 avisos llevan `source_type = ticket` y el
  id del ticket. `MensajesController` usa las constantes.
- **`/notificaciones`**: enlace «Ver ticket» / «Ir a la conversación» en cada
  notificación con origen; al pulsarlo se marca como leída.
- **Campanita**: `keepalive: true` al marcar como leída, para que la
  navegación inmediata no cancele la petición.
- **Migración `2026-09-16-000001_AddSourceToNotifications`**
  - Añade las columnas **solo si faltan**.
  - Rellena el origen de las notificaciones ya enviadas (solo filas con
    `source_type` NULL → re-ejecutable):
    - tickets: coincidencia **exacta** del título con el nº de ticket,
      comparando en **binario** (`notifications` y `tickets` tienen collations
      distintas — `utf8mb4_0900_ai_ci` vs `utf8mb4_general_ci` en local — y un
      `IN` normal fallaba con *Illegal mix of collations*);
    - mensajes: «Nuevo mensaje de …» → la conversación entre remitente y
      destinatario;
    - **nunca enlaza a algo creado después de la notificación**: en local un
      nº de ticket borrado se había reutilizado (`TKT-2026-00013`) y sin esta
      condición avisos antiguos abrían el ticket nuevo.
  - SQL equivalente para phpMyAdmin:
    `docs/deploy/migraciones_notificaciones_origen.sql`
    (`ADD COLUMN IF NOT EXISTS`, válido en MariaDB y TiDB).

Las notificaciones antiguas que no se pueden relacionar con seguridad (nº de
ticket que ya no existe, conversación borrada) se quedan sin enlace, como
estaban.

## Cómo probarlo (local)

Cuentas (contraseña `Test1234!`):
`test.admin@test.jppreparation.local` (admin) ·
`test.alumno.chat@test.jppreparation.local` (alumno) ·
`test.coach.chat@test.jppreparation.local` (entrenador).
Usar dos navegadores / una ventana privada para tener dos sesiones.

1. `docker compose exec app php spark migrate` (si no está aplicada).
2. **Alumno** → botón de reportar / `/tickets/create` → crear un ticket.
3. **Admin** → campanita → «Nuevo ticket: …» → abre **ese** ticket.
4. **Admin** → responder en el ticket.
5. **Alumno** → campanita → «Respuesta a tu ticket …» → abre su ticket
   (sin tener acceso a la bandeja `/tickets`).
6. **Admin** → cambiar el estado (p. ej. «Resuelto»).
7. **Alumno** → `/notificaciones` → «Ticket … actualizado» tiene «Ver ticket»;
   al pulsarlo abre el ticket y la notificación queda leída.
8. **Entrenador** → Mensajes → escribir al alumno.
   **Alumno** → campanita → «Nuevo mensaje de …» → se abre ese chat.

## Verificación

- `tests/unit/NotificacionesOrigenTest.php` (9 tests): `allowedFields`,
  `prepareSource()` (válido, sin columnas, inválido), `sourceLink()`, que
  **todos** los avisos de `TicketsController` y los de Mensajes llevan
  origen, y que migración y SQL de producción comparten el relleno (binario +
  «no posterior a la notificación»).
- Suite completa: **304 tests OK** (1 omitido) en Docker.
- Navegador real (Playwright + Chromium), los 8 pasos de arriba: 10/10.
- Migración en MySQL 8 local:
  - con las columnas ya existentes → solo rellena;
  - `migrate:rollback` (las borra) + `migrate` → las crea y rellena;
  - las notificaciones antiguas del nº reutilizado quedan sin enlace.
- Sin columnas + código nuevo: enviar un mensaje → HTTP 200, la notificación
  se crea sin enlace y queda el aviso en el log.

## Notas de despliegue

- **SQL:** `docs/deploy/migraciones_notificaciones_origen.sql` en phpMyAdmin
  (producción y, si Render no la aplica, PPR/demo). Se puede ejecutar antes o
  después de subir el código y más de una vez.
- Ficheros: `app/Models/NotificationModel.php`,
  `app/Controllers/TicketsController.php`,
  `app/Controllers/MensajesController.php`,
  `app/Database/Migrations/2026-09-16-000001_AddSourceToNotifications.php`,
  `app/Views/notificaciones/index.php`, `app/Views/components/navbar.php`.
- Sin cambios de `.env`.
