# Propuesta — Reorganización del sistema de Tickets

> Estado: **borrador para revisión**. Fecha: 2026-09-07.
> Objetivo: un sistema de soporte profesional con **dos niveles de gestión**
> (proveedor y cliente) y **reporte abierto a todos los roles**.

---

## 0. Diagnóstico rápido (por qué preguntaste)

### `/tickets/admin/dashboard` daba **500** ("Whoops!")

`TicketModel::getStats()` usaba:
```php
->selectAvg('TIMESTAMPDIFF(HOUR, created_at, resolved_at)', 'avg_hours')  // ← comas → CI4 lo rechaza
->whereNotNull('resolved_at')                                              // ← no existe en el query builder
```
`selectAvg()` de CodeIgniter rechaza cualquier argumento con comas
(`"column name not separated by comma"`), y `whereNotNull()` no es un
método del query builder. → excepción no capturada → página genérica de
error en producción.

**Corregido** en la rama `fix/tickets-dashboard-500` (select/where crudos).
Es un bug latente: fallaba en todos los entornos, nadie había abierto ese
dashboard.

### Diferencia entre `/tickets` y `/tickets/admin`

| Ruta | Qué es | Quién entra hoy |
|---|---|---|
| `/tickets` | **"Mis tickets"** — solo los que ha creado el usuario que ha entrado. Cuadrícula + (nuevo) buscador/filtros/export. | superadmin, admin, coach, staff |
| `/tickets/admin` | **Panel de gestión** — TODOS los tickets de todos los usuarios, con filtros y paginación. Tabla. | **solo superadmin** |
| `/tickets/admin/dashboard` | Estadísticas (por estado/categoría/prioridad, tiempo medio de resolución, últimos 30 días). | **solo superadmin** |

El controlador `show()` además comprueba propiedad: cada uno solo ve el
**detalle** de sus propios tickets; el superadmin, cualquiera.

---

## 1. Auditoría del sistema actual

### 1.1 Flujo actual

```
Usuario (superadmin/admin/coach/staff)
  └─ crea ticket (título, categoría, prioridad, descripción, adjunto opcional)
       ├─ status = 'abierto'
       └─ notifica a TODOS los superadmins

Superadmin
  ├─ /tickets/admin: lista, filtra, busca
  ├─ responde  → status pasa a 'en_progreso' automáticamente
  │              → notifica al creador
  ├─ cambia estado (abierto/en_progreso/resuelto/cerrado)
  │              → notifica al creador
  └─ (el creador puede cambiar la prioridad de SU ticket si sigue abierto)
```

### 1.2 Problemas / carencias

| # | Problema | Impacto |
|---|---|---|
| A | **Los alumnos NO pueden reportar nada** (`/tickets` es `role:superadmin,admin,coach,staff`; el enlace del menú se oculta si es alumno). | El rol más numeroso no tiene canal de incidencias. |
| B | **El admin NO gestiona** — solo ve sus propios tickets, igual que un coach. No puede responder ni cambiar estados. | Todo depende del superadmin; el cliente no tiene autonomía. |
| C | **Punto único de gestión** — si el superadmin no está, los tickets no avanzan. Sin cola, sin reparto. | Cuello de botella. |
| D | **Sin asignación** (`assigned_to`). No hay "este lo llevo yo". | Imposible repartir trabajo entre varios gestores. |
| E | **Sin notas internas** — no hay forma de que admin y superadmin comenten un ticket sin que lo vea el solicitante. | Coordinación por fuera (WhatsApp, etc.). |
| F | **Sin trazabilidad** — no se registra quién cambió el estado/prioridad ni cuándo. Solo quedan las respuestas. | No se puede auditar "¿por qué se cerró esto?". |
| G | **La prioridad la pone el solicitante** al crear. Un alumno marcando todo "urgente" no aporta. | Cola distorsionada. |
| H | **La categoría no enruta nada** — `bug`/`tecnico`/`consulta`/`mejora`/`otro` son solo etiquetas. | Un bug de la plataforma y una duda de la academia acaban en la misma bandeja. |
| I | **El solicitante no puede cerrar ni reabrir** su propio ticket. | "Ya me funciona" → tiene que esperar a que el superadmin lo cierre. |
| J | **Sin SLA / sin "primera respuesta"** — no se mide cuánto tarda en atenderse. | Sin visibilidad de calidad de servicio. |
| K | **`ticket_number` = `MAX(id) + 1`** con formato `TKT-AAAA-NNNNN`. Depende del `id` → salta números y no reinicia por año. | Cosmético, pero poco serio. |
| L | Notificación de ticket nuevo → **solo superadmins**. | El admin no se entera de lo que reportan sus coaches. |

---

## 2. Modelo propuesto

### 2.1 Concepto: 3 planos

```
┌─────────────────────────────────────────────────────────────┐
│  REPORTE  (todos los roles, incl. alumno)                    │
│  Cualquiera abre un ticket describiendo su problema.         │
└─────────────────────────────────────────────────────────────┘
                 │
                 ▼
┌─────────────────────────────────────────────────────────────┐
│  GESTIÓN — NIVEL ACADEMIA  (rol: admin + superadmin)         │
│  Los administradores de JP Preparation llevan el día a día:  │
│  ven todo, responden, cambian estado/prioridad, asignan.     │
│  Es "su" bandeja.                                            │
└─────────────────────────────────────────────────────────────┘
                 │  escalar
                 ▼
┌─────────────────────────────────────────────────────────────┐
│  GESTIÓN — NIVEL PLATAFORMA  (rol: superadmin)               │
│  El proveedor. Ve todo + los tickets marcados como           │
│  "plataforma" (bugs, cosas técnicas). Configura, borra,      │
│  dashboard global. El admin puede escalarle un ticket.       │
└─────────────────────────────────────────────────────────────┘
```

### 2.2 Matriz de permisos

| Acción | alumno | coach / staff | **admin** | **superadmin** |
|---|:---:|:---:|:---:|:---:|
| Crear ticket | ✅ | ✅ | ✅ | ✅ |
| Ver / exportar **los suyos** | ✅ | ✅ | ✅ | ✅ |
| Cerrar / reabrir **el suyo** | ✅ | ✅ | ✅ | ✅ |
| Sugerir urgencia al crear | ✅ | ✅ | ✅ | ✅ |
| Bandeja de gestión (ver **todos**) | — | — | ✅ | ✅ |
| Responder (respuesta pública) | — | — | ✅ | ✅ |
| Nota interna (gestor ↔ gestor) | — | — | ✅ | ✅ |
| Cambiar estado / prioridad | — | — | ✅ | ✅ |
| Asignar ticket | — | — | ✅ (a un admin) | ✅ (a cualquiera) |
| Escalar a "plataforma" | — | — | ✅ | — (ya es suyo) |
| Ver tickets `scope = plataforma` | — | — | ✅ (lectura) | ✅ (gestión) |
| Dashboard / estadísticas | — | — | ✅ | ✅ |
| Exportar **todos** | — | — | ✅ | ✅ |
| Borrar un ticket | — | — | — | ✅ |
| Configurar (categorías, plantillas…) | — | — | — | ✅ |

### 2.3 Cambios en la base de datos

**`tickets`** — nuevas columnas:
| Columna | Tipo | Para qué |
|---|---|---|
| `assigned_to` | `INT UNSIGNED NULL` FK users | Gestor que lo lleva. NULL = sin asignar. |
| `scope` | `ENUM('academia','plataforma') DEFAULT 'academia'` | A qué nivel de gestión le toca. |
| `reported_urgency` | `ENUM('baja','media','alta','urgente') NULL` | Lo que marcó el solicitante (informativo). `priority` la pone el gestor. |
| `first_response_at` | `DATETIME NULL` | Para métricas de SLA. |
| `reopened_count` | `INT NOT NULL DEFAULT 0` | Nº de reaperturas. |

**`ticket_replies`** — nueva columna:
| Columna | Tipo | Para qué |
|---|---|---|
| `is_internal` | `TINYINT(1) NOT NULL DEFAULT 0` | 1 = nota interna, el solicitante no la ve. |

**Nueva tabla `ticket_events`** (trazabilidad):
```
id, ticket_id, actor_id, event_type, from_value, to_value, created_at
```
`event_type` ∈ `created | status_changed | priority_changed | assigned |
escalated | reopened | closed | internal_note`. Se pinta como línea de
tiempo en el detalle del ticket.

### 2.4 Rutas reorganizadas

| Ruta | Rol | Cambio |
|---|---|---|
| `GET /tickets` | **+alumno** | "Mis tickets". Ya lleva grid + buscador + filtros + export. |
| `GET /tickets/create`, `POST /tickets` | **+alumno** | Crear. |
| `GET /tickets/{id}` | todos (dueño o gestor) | Detalido: el dueño ve respuestas públicas + botón cerrar/reabrir; el gestor ve notas internas + acciones + timeline. |
| `GET /tickets/gestion` | **admin + superadmin** | Antes `/tickets/admin`. Bandeja: tabla, filtros server-side, columna "Asignado", export global, acciones en bloque. |
| `GET /tickets/gestion/dashboard` | **admin + superadmin** | Antes `/tickets/admin/dashboard`. + métricas de SLA. |
| `POST /tickets/{id}/responder` | gestor | Respuesta pública. |
| `POST /tickets/{id}/nota` | gestor | Nota interna. |
| `POST /tickets/{id}/estado` | gestor | Cambiar estado. |
| `POST /tickets/{id}/prioridad` | gestor (o dueño si abierto) | Cambiar prioridad. |
| `POST /tickets/{id}/asignar` | gestor | Asignar. |
| `POST /tickets/{id}/escalar` | admin | scope → plataforma, notifica superadmins. |
| `POST /tickets/{id}/cerrar` · `/reabrir` | **dueño o gestor** | El solicitante puede cerrar/reabrir el suyo. |
| `POST /tickets/{id}/eliminar` | **superadmin** | Borrado (con confirmación). |

> Se mantiene `/tickets/admin` como alias 301 → `/tickets/gestion` un tiempo.

### 2.5 Notificaciones

| Evento | Destinatario |
|---|---|
| Ticket nuevo | **admins** (+ superadmins si `scope = plataforma`) |
| Respuesta pública | el solicitante |
| Nota interna | los demás gestores (nunca el solicitante) |
| Cambio de estado | el solicitante |
| Asignación | el asignado |
| Escalado | superadmins |
| Reapertura | el asignado (o admins si no hay) |

### 2.6 Detalles "profesionales"

- **Urgencia vs Prioridad**: el solicitante marca *"¿cómo de urgente es para ti?"* (`reported_urgency`); el gestor fija la **prioridad real**. En la bandeja se ven las dos.
- **Badge de SLA**: "sin responder hace 2 d" en rojo si `first_response_at IS NULL` y el ticket tiene > X horas.
- **Estado "esperando al solicitante"**: cuando el gestor responde y necesita datos, marca el ticket como pendiente del solicitante (se puede modelar con un flag `awaiting_user` en lugar de un estado nuevo, para no romper los 4 estados actuales).
- **Plantillas de respuesta** rápida (fase posterior).
- **Generador de `ticket_number`**: contador por año independiente del `id`
  (`TKT-2026-0001`, reinicia en enero). Tabla `ticket_counters(year, last)`.

---

## 3. Plan de implementación por fases

| Fase | Contenido | Migración | Riesgo |
|---|---|---|---|
| **0 — ya** | Fix dashboard 500 · buscador + filtros + export (`/tickets` y `/tickets/gestion`) | — | Ninguno. Ramas: `fix/tickets-dashboard-500`, `feat/tickets-search-export`. |
| **1 — apertura** | Alumnos pueden crear/ver sus tickets · `/tickets/admin` → `/tickets/gestion` accesible a `admin,superadmin` · admin puede responder + cambiar estado · notificar a admins · el solicitante puede cerrar/reabrir el suyo | Ninguna (solo rutas + permisos) | Bajo. |
| **2 — reparto ✅** | `assigned_to` + asignación/desasignación (dropdown en el detalle + filtro "sin asignar / asignados a mí / por gestor" en la bandeja) · notas internas (`is_internal`, no las ve el solicitante, avisan a los demás gestores) · tabla `ticket_events` + historial en el panel lateral del detalle. Migración `2026-09-07-000002`. | 1 columna en `tickets` + 1 en `ticket_replies` + tabla `ticket_events` (aditivas) | Bajo. Hecho. |
| **3 — niveles ✅** | `scope` academia/plataforma: el alumno solo abre de academia, admin/coach/staff eligen; el superadmin gestiona ambos siempre (sin "escalar y soltar" — el gestor reclasifica el ámbito). SLA en el dashboard: tiempo medio 1ª respuesta (`first_response_at`), pendientes de 1ª respuesta, sin atender +48 h. Archivado (`archived_at`) en vez de borrado: fuera de las bandejas, historial intacto, reversible. Migración `2026-09-07-000003`. | 3 columnas en `tickets` (aditivas) | Hecho. |
| **4 — pulido** | Plantillas de respuesta · `reported_urgency` separada de `priority` · generador de número por año · alias 301 de `/tickets/admin` | 2 columnas + 1 tabla | Bajo. |

Cada fase = su rama + PR + validación en pre-producción antes de Hostinger.

---

## 4. Decisiones que necesito de ti

> **Respondidas 2026-09-07** (aplicadas en F3): (1) el superadmin gestiona
> academia y plataforma siempre; el `scope` solo limita qué puede clasificar
> el que reporta (alumno → solo academia). (2) los alumnos adjuntan archivos
> igual que el resto. (3) se mantienen las 5 categorías. (4) solo archivado,
> nunca borrado.

1. **`scope` academia/plataforma**: ¿lo quieres desde el principio (fase 1) o
   basta con "admin gestiona todo, superadmin es el jefe" y el `scope` viene
   después (fase 3)?
2. **Alumnos**: ¿pueden adjuntar archivos como el resto, o solo texto?
3. **Categorías**: las 5 actuales (`bug`, `mejora`, `consulta`, `tecnico`,
   `otro`) — ¿valen o quieres retocarlas para el alumno (p. ej. "Problema con
   una clase", "Acceso / contraseña", "Facturación / bono")?
4. **Borrado de tickets**: ¿de verdad lo quieres, o mejor solo "archivar"
   (nunca se borra, desaparece de las bandejas)?

---

# ANEXO — Reporte contextual de errores ("Reportar problema" desde la alerta)

> Añadido tras la revisión: además de facilitar el reporte a **todos** los
> roles, cuando un usuario se topa con un **error (500)** o una
> **prohibición (403)**, o falla una petición AJAX, debe poder **abrir un
> ticket directamente desde la alerta**, con el contexto ya rellenado.

## Qué se guarda automáticamente en el ticket

| Dato | Origen | Cómo |
|---|---|---|
| **Usuario** | sesión | ya lo tiene el ticket (`user_id`) + se cita en la descripción |
| **Fecha/hora** | servidor + cliente | `created_at` + timestamp del navegador en `context` |
| **URL / pantalla** | `window.location` | en `context` y en el título |
| **Acción que falló** | el `fetch` / la ruta | endpoint + método |
| **Mensaje del servidor + Referencia** | `error_ref` | código corto (6 hex) que correlaciona con el log del servidor |
| **Navegador** | `navigator.userAgent` | en `context` |
| **Captura de pantalla** | *opcional* | el usuario adjunta la suya, **o** botón "Capturar" (html2canvas) |
| **Reporte del error (texto libre)** | el usuario | "¿qué estabas haciendo?" |

## Arquitectura

### A) Backend — `ErrorReportTrait` (en `BaseController`)
Generaliza lo que ya hace `MensajesController`:
```php
protected function errorRef(\Throwable $e, string $where): string   // genera ref + log estructurado
protected function jsonFail(string $msg, int $status = 500, ?string $ref = null): ResponseInterface
protected function guard(string $where, callable $fn)               // ejecuta y captura → jsonFail
```
`log_message('critical', "[ref] {$where} · url={$url} · user={$uid} · rol={$rol} · {$msg}\n{$trace}")`.
Respuesta AJAX: `{ "error": "...", "error_ref": "A3F91C", "reportable": true }`.

### B) Handler global de excepciones (`Config/Exceptions.php`)
Handler propio: toda excepción no capturada → `ref` + log + en producción
renderiza `errors/html/production.php` **personalizada** con:
- "Algo ha fallado. Referencia **A3F91C**."
- Botón **"Reportar este problema"** → abre el modal precargado.

### C) Página 403 (`error_403.php`)
Botón "¿Deberías tener acceso? **Reportar**" → ticket `categoria=consulta`,
título "Sin permiso para {ruta}".

### D) Frontend — `window.reportProblem(ctx)` + `showAlert` extendido
- Helper JS global (en `app.js`): abre `#modalTicketRapido` con
  `category=bug`, `priority=alta`, título y descripción **precargados** con
  el contexto (URL, endpoint, ref, navegador, hora). El usuario solo escribe
  "qué hacía" y, si quiere, adjunta captura.
- `showAlert(msg, type, { ref, context })` — si hay `ref`, el toast muestra
  un botón **"Reportar"**.
- Los `fetch` que devuelvan `error_ref` lo disparan automáticamente.

### E) Captura de pantalla
- **Manual** (por defecto): campo de adjunto en el modal.
- **Automática** (opción): botón "📷 Capturar pantalla" → `html2canvas`
  (CDN `cdnjs`, permitido por la CSP) genera un PNG del estado actual y lo
  adjunta. *Decisión pendiente: ¿añadimos html2canvas?*

### F) Datos extra en `tickets` (migración pequeña, aditiva)
| Columna | Tipo |
|---|---|
| `origin` | `ENUM('manual','error','permiso') DEFAULT 'manual'` |
| `context` | `JSON NULL` — url, endpoint, ref, userAgent, ts cliente |
| `error_ref` | `VARCHAR(12) NULL` — para que el gestor busque el log |

En el detalle del ticket, si `origin != 'manual'`, se muestra un bloque
"Contexto técnico" formateado (solo para gestores).

## Dónde poner los `try/catch` — estudio de puntos

Ordenado por **(probabilidad de fallo × uso × complejidad × importancia)**:

### P0 — Subidas de archivos (todas)
`handleFileUpload()` en **Tickets, Mensajes, Notificaciones, Documentación,
Avatares** (15 puntos de subida en el repo). Fallan por tamaño, extensión,
MIME, permisos de carpeta, disco lleno, `move()` fallido. Alto uso, y el
usuario no entiende el error crudo.

### P1 — Clases (`ClasesController`, 25 métodos — lo más complejo y usado)
- `quickCreate`, `store`, `update` — creación de sesiones (puntuales + recurrentes)
- `guardarLista` — pasar lista + **descuento de bono** (operación con varias escrituras)
- `deductBono` — el del 403 histórico
- `addPlayer` / `addCoach` / `removePlayer` / `removeCoach`
- `cancel`, `destroy`
- `calendario`, `buscar` — AJAX de alto tráfico

### P1 — Bonos (`BonosController`)
- `store` — el `#1062 Duplicate entry` histórico
- `assign`, y el consumo de sesiones

### P1 — Notificaciones (`NotificacionesController`)
- `send` — adjuntos + rate-limit + resolución de destinatarios
- `download`

### P2 — Auth (`AuthController`)
- `loginPost`, `forgotPasswordPost`, `resetPasswordPost` — dependen de BD +
  email (Resend) + rate-limit (`auth_events`)

### P2 — Alumnos (`AlumnosController`)
- `store`, `update`, `saveProfile` — posiciones múltiples, JSON, subida de avatar

### P2 — Configuración (`ConfiguracionController`, 16 métodos, 0 try/catch hoy)
- `createStaff`, `createSede`, `createBonoType` + sus `edit` / `delete`

### P3 — resto
Entrenadores, Anotaciones, `DashboardController::getStats`
(recordatorio: `TicketModel::getStats` petaba), Perfil (completar cobertura).

### Red global
El handler de excepciones (B) cubre **todo lo demás** que no se envuelva
explícitamente — así ningún error acaba en un "Whoops!" sin referencia.

## Fases

| Fase | Contenido |
|---|---|
| **E1** | `ErrorReportTrait` + handler global + `production.php`/`error_403.php` con botón · `window.reportProblem` + `showAlert` con "Reportar" · migración (`origin`, `context`, `error_ref`) · captura manual |
| **E2** | Envolver P0 (subidas) + P1 (Clases, Bonos, Notificaciones) con `guard()` |
| **E3** | Envolver P2 + P3 · bloque "Contexto técnico" en el detalle del ticket para gestores |
| **E4** | (opcional) captura automática con html2canvas |

Encaja en paralelo a las fases del sistema de tickets. La **F1** de tickets
(alumnos pueden reportar) es prerrequisito de **E1**.
