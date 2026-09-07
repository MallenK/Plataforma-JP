# JP Preparation — Esquema de Base de Datos (TiDB/MySQL)

**Última actualización:** 2026-09-01 (release v1.1.2)  
**Motor:** Render/validación = TiDB (compatible MySQL 5.7+); Hostinger/producción = MariaDB  
**Base de datos:** `jp_preparation` (Render) · `u912370917_jpapp` (Hostinger)

---

## Notas sobre TiDB

- `AUTO_INCREMENT` en TiDB no genera IDs secuenciales; los valores saltan en bloques de ~30 000 (ej: `class_sessions` tiene AUTO_INCREMENT=60006 con solo 22 filas).
- Evitar varios `ADD COLUMN ... AFTER <col>` en un mismo `ALTER TABLE` cuando se referencian columnas recién añadidas — ejecutar un `ALTER TABLE` por columna.
- Usar `(session_id, user_id)` como PK compuesta en tablas pivot para evitar el problema de AUTO_INCREMENT duplicado (ver `class_session_coaches`).
- Tablas con AUTO_INCREMENT pero sin valor en `INFORMATION_SCHEMA.EXTRA` pueden funcionar igualmente; verificar con `SHOW CREATE TABLE`.

---

## Mapa de relaciones principales

```
users ──────────────────────────────────────────────────────────────────┐
  │                                                                      │
  ├── player_profiles (1:1 via player_id)                                │
  ├── player_bonos (1:N via player_id)                                   │
  ├── player_annotations (N via player_id + author_id)                   │
  ├── player_metrics (N via player_id + coach_id)                        │
  │                                                                      │
  ├── class_sessions (N via created_by)                                  │
  │     ├── class_session_coaches (pivot: session_id + user_id)          │
  │     └── class_session_players (N via session_id + user_id)           │
  │                                                                      │
  ├── document_folders (personal: owner_id)                              │
  │     ├── documents (N via folder_id)                                  │
  │     │     └── player_annotations.document_id (FK opcional)           │
  │     └── folder_permissions (pivot: folder_id + user_id)              │
  │                                                                      │
  ├── conversations (pivot: user1_id + user2_id)                         │
  │     └── messages (N via conversation_id)                             │
  │                                                                      │
  ├── notifications (N via sender_id)                                    │
  │     └── notification_recipients (N via notification_id + recipient_id│
  │                                                                      │
  └── purchase_requests (N via requested_by + reviewed_by)              │
                                                                         │
bono_types ──────── player_bonos.bono_type_id ───────────────────────────┘
locations ────────── class_sessions.location_id
classes ──────────── class_sessions.class_id (recurrentes)
```

---

## Tablas

### `users`
Tabla central. Todos los usuarios del sistema independientemente del rol.

| Columna | Tipo | Nulo | Default | Notas |
|---------|------|------|---------|-------|
| `id` | INT AUTO_INCREMENT | NO | — | PK |
| `name` | VARCHAR(150) | NO | — | Nombre completo |
| `email` | VARCHAR(150) | NO | — | UNIQUE |
| `password` | VARCHAR(255) | NO | — | bcrypt hash |
| `password_changed_at` | DATETIME | SÍ | NULL | v1.1.0 · última vez que se cambió la contraseña; `AuthFilter` lo compara con `session('pw_stamp')` para cerrar sesiones tras un cambio |
| `must_change_password` | TINYINT(1) | NO | 0 | v1.1.0 · si `1`, el usuario debe pasar por `/perfil/password` antes de navegar (altas por admin y reset por admin) |
| `avatar` | VARCHAR(255) | SÍ | NULL | Ruta relativa al avatar |
| `role` | ENUM | NO | player | `superadmin`, `admin`, `staff`, `coach`, `player` |
| `staff_title` | VARCHAR(100) | SÍ | NULL | Cargo libre (ej: "Director técnico") |
| `status` | ENUM | SÍ | active | `active`, `inactive`, `blocked` |
| `welcomed_at` | DATETIME | SÍ | NULL | Fecha de primer login / tutorial visto |
| `created_at` | DATETIME | SÍ | CURRENT_TIMESTAMP | |
| `updated_at` | DATETIME | SÍ | NULL | ON UPDATE CURRENT_TIMESTAMP |

**Índices:** `PRIMARY (id)`, `UNIQUE (email)`, `INDEX idx_users_role (role)`

---

### `player_profiles`
Extensión 1:1 de `users` para alumnos. Datos deportivos y físicos.

| Columna | Tipo | Nulo | Default | Notas |
|---------|------|------|---------|-------|
| `id` | INT | NO | — | PK |
| `player_id` | INT | NO | — | UNIQUE → `users.id` |
| `birth_date` | DATE | SÍ | NULL | Fecha de nacimiento |
| `height` | INT | SÍ | NULL | Altura en cm |
| `weight` | INT | SÍ | NULL | Peso en kg |
| `position` | TEXT | SÍ | NULL | v1.1.0 · lista JSON de posiciones (`["extremo_derecho","mediapunta"]`), claves del catálogo `PlayerProfileModel::POSITIONS` (11 posiciones de fútbol). Valores antiguos en texto libre se siguen leyendo (`decodePositions()` los parte por `/`, `,`, `;`). Antes era `VARCHAR(50)` |
| `level` | ENUM | SÍ | NULL | `beginner`, `intermediate`, `advanced` (legacy) |
| `category` | ENUM | SÍ | NULL | `prebenjamin`, `benjamin`, `alevin`, `infantil`, `cadete`, `juvenil`, `junior`, `senior`, `veterano` |
| `team` | VARCHAR(120) | SÍ | NULL | Equipo en el que juega |
| `league` | VARCHAR(120) | SÍ | NULL | Liga en la que compite |
| `medical_notes` | TEXT | SÍ | NULL | Alergias, lesiones, contraindicaciones |
| `created_at` | DATETIME | SÍ | CURRENT_TIMESTAMP | |
| `updated_at` | DATETIME | SÍ | NULL | ON UPDATE |

**Índices:** `PRIMARY (id)`, `UNIQUE (player_id)` ⚠️ Existe índice duplicado `player_id_2` — ejecutar `ALTER TABLE player_profiles DROP INDEX player_id_2`

---

### `player_bonos`
Bonos (paquetes de sesiones) asignados a cada alumno. Sistema FIFO.

| Columna | Tipo | Nulo | Default | Notas |
|---------|------|------|---------|-------|
| `id` | INT UNSIGNED | NO | — | PK |
| `player_id` | INT UNSIGNED | SÍ | NULL | FK → `users.id` |
| `bono_type_id` | INT UNSIGNED | NO | — | FK → `bono_types.id` |
| `sessions_total` | INT UNSIGNED | NO | — | Sesiones al crear el bono |
| `sessions_remaining` | INT UNSIGNED | NO | — | Sesiones restantes (se decrementa al completar sesión) |
| `start_date` | DATE | NO | — | Fecha de activación |
| `expires_at` | DATE | SÍ | NULL | Caducidad |
| `notes` | TEXT | SÍ | NULL | Notas libres |
| `created_by` | INT UNSIGNED | SÍ | NULL | FK → `users.id` (admin que lo creó) |
| `created_at` | DATETIME | SÍ | NULL | |
| `updated_at` | DATETIME | SÍ | NULL | |

**Índices:** `PRIMARY (id)`, `INDEX (player_id)`, `INDEX (bono_type_id)`

---

### `bono_types`
Catálogo de tipos de bono disponibles.

| Columna | Tipo | Nulo | Default | Notas |
|---------|------|------|---------|-------|
| `id` | INT UNSIGNED | NO | — | PK |
| `name` | VARCHAR(100) | NO | — | Ej: "Bono 10 clases" |
| `sessions` | INT UNSIGNED | NO | 10 | Número de sesiones incluidas |
| `price` | DECIMAL(8,2) | NO | 0.00 | Precio |
| `validity_days` | INT UNSIGNED | NO | 90 | Días de validez desde activación |
| `active` | TINYINT(1) | NO | 1 | Si está disponible para asignar |
| `created_at` | DATETIME | NO | CURRENT_TIMESTAMP | |
| `updated_at` | DATETIME | SÍ | NULL | ON UPDATE |

---

### `player_annotations`
Observaciones/anotaciones sobre un alumno. Pueden ser públicas (visible al alumno) o internas (solo staff).

| Columna | Tipo | Nulo | Default | Notas |
|---------|------|------|---------|-------|
| `id` | INT UNSIGNED AUTO_INCREMENT | NO | — | PK |
| `player_id` | INT | NO | — | FK → `users.id` |
| `author_id` | INT | NO | — | FK → `users.id` (quien escribe) |
| `type` | ENUM | NO | public | `public`, `internal` |
| `content` | TEXT | NO | — | Texto de la anotación |
| `document_id` | INT UNSIGNED | SÍ | NULL | FK opcional → `documents.id` |
| `created_at` | DATETIME | SÍ | NULL | |
| `updated_at` | DATETIME | SÍ | NULL | |

**Índices:** `PRIMARY (id)`, `INDEX (player_id)`, `INDEX (author_id)`, `INDEX (type)`

---

### `player_metrics`
Métricas de rendimiento de un alumno en una sesión específica.

| Columna | Tipo | Nulo | Default | Notas |
|---------|------|------|---------|-------|
| `id` | INT | NO | — | PK |
| `player_id` | INT | SÍ | NULL | FK → `users.id` |
| `coach_id` | INT | SÍ | NULL | FK → `users.id` |
| `session_id` | INT | SÍ | NULL | FK → `class_sessions.id` |
| `date` | DATE | SÍ | NULL | |
| `metrics` | JSON | SÍ | NULL | Datos variables de métricas |
| `evaluation` | TEXT | SÍ | NULL | Evaluación narrativa |
| `notes` | TEXT | SÍ | NULL | Notas adicionales |
| `created_at` | DATETIME | SÍ | CURRENT_TIMESTAMP | |

---

### `player_plans`
Planes de entrenamiento asignados a alumnos (legacy, sistema antiguo).

| Columna | Tipo | Nulo | Default | Notas |
|---------|------|------|---------|-------|
| `id` | INT | NO | — | PK |
| `player_id` | INT | SÍ | NULL | FK → `users.id` |
| `plan_id` | INT | SÍ | NULL | FK → `plans.id` |
| `sessions_remaining` | INT | SÍ | NULL | |
| `start_date` | DATE | SÍ | NULL | |
| `end_date` | DATE | SÍ | NULL | |
| `status` | ENUM | SÍ | NULL | `active`, `expired`, `used` |
| `created_at` | DATETIME | SÍ | CURRENT_TIMESTAMP | |
| `updated_at` | DATETIME | SÍ | NULL | |

---

### `classes`
Plantilla para sesiones recurrentes. Una clase puede generar N sesiones.

| Columna | Tipo | Nulo | Default | Notas |
|---------|------|------|---------|-------|
| `id` | INT UNSIGNED | NO | — | PK |
| `title` | VARCHAR(150) | NO | — | |
| `description` | TEXT | SÍ | NULL | |
| `type` | ENUM | NO | single | `single`, `recurring` |
| `recurrence_days` | VARCHAR(20) | SÍ | NULL | JSON: `[1,3]` = Lun/Mié (ISO: 1=Lun…7=Dom) |
| `recurrence_start` | DATE | SÍ | NULL | |
| `recurrence_end` | DATE | SÍ | NULL | |
| `recurrence_time_start` | TIME | SÍ | NULL | |
| `recurrence_time_end` | TIME | SÍ | NULL | |
| `default_location_id` | INT UNSIGNED | SÍ | NULL | FK → `locations.id` |
| `default_location_custom` | VARCHAR(255) | SÍ | NULL | |
| `default_focus` | TEXT | SÍ | NULL | |
| `created_by` | INT UNSIGNED | NO | — | FK → `users.id` |
| `created_at` | DATETIME | SÍ | CURRENT_TIMESTAMP | |
| `updated_at` | DATETIME | SÍ | NULL | |

---

### `class_sessions`
Sesiones de entrenamiento concretas (puntuales o instancias de una recurrente).

| Columna | Tipo | Nulo | Default | Notas |
|---------|------|------|---------|-------|
| `id` | INT UNSIGNED AUTO_INCREMENT | NO | — | PK |
| `class_id` | INT UNSIGNED | SÍ | NULL | FK → `classes.id` (si es recurrente) |
| `title` | VARCHAR(150) | NO | — | |
| `session_date` | DATE | NO | — | |
| `start_time` | TIME | NO | — | |
| `end_time` | TIME | NO | — | |
| `location_id` | INT UNSIGNED | SÍ | NULL | FK → `locations.id` |
| `location_custom` | VARCHAR(255) | SÍ | NULL | Ubicación libre |
| `focus` | TEXT | SÍ | NULL | Objetivo de la sesión |
| `pre_notes` | TEXT | SÍ | NULL | Notas previas del entrenador |
| `post_notes` | TEXT | SÍ | NULL | Notas posteriores |
| `status` | ENUM | NO | scheduled | `scheduled`, `completed`, `cancelled` |
| `created_by` | INT UNSIGNED | NO | — | FK → `users.id` |
| `created_at` | DATETIME | SÍ | CURRENT_TIMESTAMP | |
| `updated_at` | DATETIME | SÍ | NULL | ON UPDATE |

**Índices:** `PRIMARY (id)`, `INDEX (class_id)`, `INDEX (session_date)`, `INDEX (status)`

⚠️ AUTO_INCREMENT = 60006 (TiDB batch allocation normal)

---

### `class_session_coaches`
Tabla pivot: entrenadores asignados a una sesión.

| Columna | Tipo | Nulo | Default | Notas |
|---------|------|------|---------|-------|
| `session_id` | INT UNSIGNED | NO | — | PK parte 1 |
| `user_id` | INT UNSIGNED | NO | — | PK parte 2 |
| `created_at` | DATETIME | SÍ | CURRENT_TIMESTAMP | |

**PK compuesta:** `(session_id, user_id)` — sin columna `id` para evitar problema TiDB AUTO_INCREMENT  
**Índices:** `PRIMARY (session_id, user_id)`, `INDEX idx_csc_user (user_id)`

---

### `class_session_players`
Jugadores convocados a una sesión. Registra asistencia y avisos.

| Columna | Tipo | Nulo | Default | Notas |
|---------|------|------|---------|-------|
| `id` | INT UNSIGNED | NO | — | PK |
| `session_id` | INT UNSIGNED | NO | — | FK → `class_sessions.id` |
| `user_id` | INT UNSIGNED | NO | — | FK → `users.id` |
| `coach_id` | INT UNSIGNED | SÍ | NULL | Entrenador asignado a este jugador |
| `attendance` | ENUM | NO | pending | `pending`, `confirmed`, `declined`, `present`, `absent`, `unjustified` |
| `responded_at` | DATETIME | SÍ | NULL | Cuándo respondió el jugador |
| `absence_reason` | TEXT | SÍ | NULL | Motivo de ausencia (puesto por admin) |
| `student_note` | TEXT | SÍ | NULL | Aviso de ausencia enviado por el alumno |
| `student_noted_at` | DATETIME | SÍ | NULL | Cuándo envió el aviso el alumno |
| `pre_obs` | TEXT | SÍ | NULL | Observación previa del entrenador |
| `post_obs` | TEXT | SÍ | NULL | Feedback posterior |
| `created_at` | DATETIME | SÍ | CURRENT_TIMESTAMP | |
| `updated_at` | DATETIME | SÍ | NULL | ON UPDATE |

**Índices:** `PRIMARY (id)`, `UNIQUE uq_csp (session_id, user_id)`, `INDEX (coach_id)`, `INDEX (user_id)`

---

### `locations`
Sedes y espacios donde se realizan las sesiones.

| Columna | Tipo | Nulo | Default | Notas |
|---------|------|------|---------|-------|
| `id` | INT | NO | — | PK |
| `name` | VARCHAR(150) | NO | — | |
| `description` | TEXT | SÍ | NULL | |
| `address` | VARCHAR(255) | SÍ | NULL | |
| `type` | ENUM | NO | pitch | `pitch`, `gym`, `room`, `office`, `other` |
| `capacity` | INT UNSIGNED | SÍ | NULL | |
| `phone` | VARCHAR(30) | SÍ | NULL | |
| `active` | TINYINT(1) | NO | 1 | |
| `created_at` | DATETIME | SÍ | CURRENT_TIMESTAMP | |
| `updated_at` | DATETIME | SÍ | NULL | |

---

### `document_folders`
Carpetas del sistema documental. Tres tipos: pública, personal (por alumno) e interna.

| Columna | Tipo | Nulo | Default | Notas |
|---------|------|------|---------|-------|
| `id` | INT UNSIGNED | NO | — | PK |
| `name` | VARCHAR(150) | NO | — | Nombre visible |
| `slug` | VARCHAR(150) | NO | — | UNIQUE, identificador URL |
| `type` | ENUM | NO | public | `public`, `personal`, `internal` |
| `icon` | VARCHAR(60) | NO | bi-folder-fill | Clase Bootstrap Icons |
| `color` | VARCHAR(30) | NO | blue | Color del icono |
| `owner_id` | INT UNSIGNED | SÍ | NULL | Solo para `personal`: FK → `users.id` |
| `created_by` | INT UNSIGNED | NO | — | FK → `users.id` |
| `status` | ENUM | NO | active | `active`, `inactive` |
| `created_at` | DATETIME | NO | CURRENT_TIMESTAMP | |
| `updated_at` | DATETIME | SÍ | NULL | ON UPDATE |

**Índices:** `PRIMARY (id)`, `UNIQUE (slug)`, `INDEX (owner_id)`, `INDEX (type)`

---

### `documents`
Archivos subidos al sistema documental. Soft-delete mediante `deleted_at`.

| Columna | Tipo | Nulo | Default | Notas |
|---------|------|------|---------|-------|
| `id` | INT UNSIGNED AUTO_INCREMENT | NO | — | PK |
| `folder_id` | INT UNSIGNED | NO | — | FK → `document_folders.id` |
| `uploader_id` | INT UNSIGNED | NO | — | FK → `users.id` |
| `name_original` | VARCHAR(255) | NO | — | Nombre original del archivo |
| `name_stored` | VARCHAR(255) | NO | — | Nombre en disco (UUID-based) |
| `mime_type` | VARCHAR(100) | NO | — | MIME type |
| `extension` | VARCHAR(15) | NO | — | Extensión sin punto |
| `size_bytes` | BIGINT UNSIGNED | NO | 0 | Tamaño en bytes |
| `description` | TEXT | SÍ | NULL | Descripción opcional |
| `sensitive` | TINYINT(1) | NO | 0 | Si es sensible → Cache-Control: no-store |
| `created_at` | DATETIME | NO | CURRENT_TIMESTAMP | |
| `deleted_at` | DATETIME | SÍ | NULL | Soft delete |

**Índices:** `PRIMARY (id)`, `INDEX (folder_id)`, `INDEX (uploader_id)`, `INDEX (deleted_at)`

---

### `folder_permissions`
Permisos de acceso a carpetas internas para usuarios específicos.

| Columna | Tipo | Nulo | Default | Notas |
|---------|------|------|---------|-------|
| `id` | INT UNSIGNED | NO | — | PK |
| `folder_id` | INT UNSIGNED | NO | — | FK → `document_folders.id` |
| `user_id` | INT UNSIGNED | NO | — | FK → `users.id` |
| `can_read` | TINYINT(1) | NO | 1 | |
| `can_write` | TINYINT(1) | NO | 0 | |
| `granted_by` | INT UNSIGNED | NO | — | FK → `users.id` |
| `created_at` | DATETIME | NO | CURRENT_TIMESTAMP | |

**Índices:** `PRIMARY (id)`, `UNIQUE uq_folder_user (folder_id, user_id)`

---

### `conversations`
Conversaciones de chat entre dos usuarios.

| Columna | Tipo | Nulo | Default | Notas |
|---------|------|------|---------|-------|
| `id` | INT | NO | — | PK |
| `user1_id` | INT | NO | — | FK → `users.id` (user1 < user2 por convención) |
| `user2_id` | INT | NO | — | FK → `users.id` |
| `created_at` | DATETIME | SÍ | NULL | |
| `last_message_at` | DATETIME | SÍ | NULL | Para ordenar bandeja |

**Índices:** `PRIMARY (id)`, `UNIQUE uq_conversation (user1_id, user2_id)`, `INDEX (user2_id)`, `INDEX (last_message_at)`

---

### `messages`
Mensajes individuales de una conversación.

| Columna | Tipo | Nulo | Default | Notas |
|---------|------|------|---------|-------|
| `id` | INT AUTO_INCREMENT | NO | — | PK |
| `conversation_id` | INT | NO | — | FK → `conversations.id` |
| `sender_id` | INT | NO | — | FK → `users.id` |
| `body` | TEXT | SÍ | NULL | Texto del mensaje |
| `file_path` | VARCHAR(500) | SÍ | NULL | Adjunto |
| `file_name` | VARCHAR(255) | SÍ | NULL | |
| `file_size` | INT UNSIGNED | SÍ | NULL | |
| `file_mime` | VARCHAR(100) | SÍ | NULL | |
| `read_at` | DATETIME | SÍ | NULL | Cuándo lo leyó el destinatario |
| `created_at` | DATETIME | SÍ | NULL | |

**Índices:** `PRIMARY (id)`, `INDEX (conversation_id)`, `INDEX (sender_id)`, `INDEX (created_at)`

---

### `notifications`
Notificaciones enviadas (individuales o grupales).

| Columna | Tipo | Nulo | Default | Notas |
|---------|------|------|---------|-------|
| `id` | INT | NO | — | PK |
| `sender_id` | INT | NO | — | FK → `users.id` |
| `type` | ENUM | SÍ | individual | `individual`, `group` |
| `title` | VARCHAR(255) | NO | — | |
| `body` | TEXT | NO | — | |
| `file_path` | VARCHAR(500) | SÍ | NULL | Adjunto opcional |
| `file_name` | VARCHAR(255) | SÍ | NULL | |
| `file_size` | INT UNSIGNED | SÍ | NULL | |
| `created_at` | DATETIME | SÍ | NULL | |

---

### `notification_recipients`
Destinatarios de cada notificación.

| Columna | Tipo | Nulo | Default | Notas |
|---------|------|------|---------|-------|
| `id` | INT | NO | — | PK |
| `notification_id` | INT | NO | — | FK → `notifications.id` |
| `recipient_id` | INT | NO | — | FK → `users.id` |
| `read_at` | DATETIME | SÍ | NULL | Cuándo fue leída |

---

### `purchase_requests`
Lista de compras / solicitudes de material.

| Columna | Tipo | Nulo | Default | Notas |
|---------|------|------|---------|-------|
| `id` | INT AUTO_INCREMENT | NO | — | PK |
| `name` | VARCHAR(200) | NO | — | Nombre del artículo |
| `description` | TEXT | SÍ | NULL | |
| `url` | VARCHAR(500) | SÍ | NULL | Enlace al producto |
| `price` | DECIMAL(10,2) UNSIGNED | SÍ | NULL | |
| `category` | ENUM | SÍ | otros | `equipamiento`, `tecnologia`, `material_deportivo`, `instalaciones`, `oficina`, `otros` |
| `priority` | ENUM | SÍ | media | `alta`, `media`, `baja` |
| `status` | ENUM | SÍ | pendiente | `pendiente`, `en_revision`, `aprobado`, `denegado`, `comprado`, `cancelado` |
| `admin_comment` | TEXT | SÍ | NULL | Comentario del admin al revisar |
| `requested_by` | INT | NO | — | FK → `users.id` |
| `reviewed_by` | INT | SÍ | NULL | FK → `users.id` |
| `reviewed_at` | DATETIME | SÍ | NULL | |
| `created_at` | DATETIME | SÍ | NULL | |
| `updated_at` | DATETIME | SÍ | NULL | |

---

### `academy_settings`
Configuración global de la academia (clave-valor).

| Columna | Tipo | Nulo | Default | Notas |
|---------|------|------|---------|-------|
| `id` | INT UNSIGNED | NO | — | PK |
| `setting_key` | VARCHAR(100) | NO | — | UNIQUE |
| `setting_value` | TEXT | SÍ | NULL | |
| `setting_type` | ENUM | NO | string | `string`, `int`, `bool`, `json` |
| `updated_at` | DATETIME | SÍ | NULL | |
| `updated_by` | INT UNSIGNED | SÍ | NULL | FK → `users.id` |

---

### `logs`
Registro de acciones de auditoría (descargas, previsualizaciones, etc.).

| Columna | Tipo | Nulo | Default | Notas |
|---------|------|------|---------|-------|
| `id` | INT | NO | — | PK |
| `user_id` | INT | SÍ | NULL | FK → `users.id` |
| `action` | VARCHAR(150) | SÍ | NULL | Ej: `download`, `preview` |
| `entity` | VARCHAR(150) | SÍ | NULL | Ej: `document` |
| `entity_id` | INT | SÍ | NULL | ID del objeto afectado |
| `data` | JSON | SÍ | NULL | Datos adicionales de contexto |
| `created_at` | DATETIME | SÍ | CURRENT_TIMESTAMP | |

---

### `password_resets`
Tokens de restablecimiento de contraseña.

| Columna | Tipo | Nulo | Default | Notas |
|---------|------|------|---------|-------|
| `id` | INT | NO | — | PK |
| `user_id` | INT | SÍ | NULL | FK → `users.id` |
| `email` | VARCHAR(150) | NO | — | |
| `token` | VARCHAR(255) | NO | — | Token seguro (SHA-256) |
| `expires_at` | DATETIME | NO | — | |
| `created_at` | DATETIME | SÍ | CURRENT_TIMESTAMP | |

---

### `email_log`
Historial de emails enviados desde la plataforma.

| Columna | Tipo | Nulo | Default | Notas |
|---------|------|------|---------|-------|
| `id` | INT UNSIGNED | NO | — | PK |
| `sender_id` | INT UNSIGNED | NO | — | FK → `users.id` |
| `recipient_type` | ENUM | NO | individual | `individual`, `group` |
| `recipient_id` | INT UNSIGNED | SÍ | NULL | user_id si es individual |
| `recipient_group` | VARCHAR(50) | SÍ | NULL | role o "all" si es grupal |
| `subject` | VARCHAR(255) | NO | — | |
| `message` | TEXT | NO | — | |
| `status` | ENUM | NO | sent | `sent`, `failed` |
| `error_msg` | TEXT | SÍ | NULL | |
| `created_at` | DATETIME | NO | CURRENT_TIMESTAMP | |

---

### `auth_events`
**v1.1.0** · Registro de eventos de autenticación. Doble uso: (1) decisiones de
rate-limit / bloqueo por fuerza bruta (`App\Services\AuthGuardService` cuenta
`login_fail` por `identifier` o por `ip_address` dentro de una ventana), y
(2) auditoría en Configuración → Seguridad → "Actividad de seguridad reciente".

| Columna | Tipo | Nulo | Default | Notas |
|---------|------|------|---------|-------|
| `id` | BIGINT UNSIGNED AUTO_INCREMENT | NO | — | PK |
| `event_type` | VARCHAR(40) | NO | — | `login_success`, `login_fail`, `logout`, `lockout`, `pwreset_request`, `pwreset_success`, `pwreset_fail`, `pwchange_success`, `pwchange_fail`, `admin_pwreset` |
| `identifier` | VARCHAR(191) | SÍ | NULL | email en minúsculas (o `uid:<n>` para `pwchange_fail`) |
| `user_id` | INT UNSIGNED | SÍ | NULL | |
| `ip_address` | VARCHAR(45) | NO | — | |
| `user_agent` | VARCHAR(255) | SÍ | NULL | |
| `meta` | JSON | SÍ | NULL | contexto extra (`reason`, `by`…) |
| `created_at` | DATETIME | NO | — | |

**Índices:** `PRIMARY (id)`, `idx_ident_time (identifier, created_at)`, `idx_ip_time (ip_address, created_at)`, `idx_type_time (event_type, created_at)`

Sin FK (se conserva el histórico aunque se borre el usuario). Conviene purgar
filas > 90 días periódicamente.

---

### `session_attendance` *(legacy)*
Sistema de asistencia antiguo. Reemplazado por `class_session_players.attendance`.

| Columna | Tipo | Nulo |
|---------|------|------|
| `id` | INT | NO |
| `session_id` | INT | SÍ |
| `player_id` | INT | SÍ |
| `status` | ENUM('present','absent','late') | SÍ |
| `check_in_time` | DATETIME | SÍ |
| `notes` | TEXT | SÍ |

---

### `sessions` *(legacy)*
Sistema de sesiones antiguo. Reemplazado por `class_sessions`.

---

### `observations` *(legacy)*
Sistema de observaciones antiguo. Reemplazado por `player_annotations`.

---

### `plans` *(legacy)*
Planes de entrenamiento (no en uso activo).

---

## Tablas de Torneos *(desactivadas temporalmente)*

Las siguientes tablas existen en BD pero las rutas de torneos están comentadas:

- **`events`** — Torneos y campus. Campos: title, description, type (`torneo`/`campus`), start_date, end_date, location, category, cancelled, etc.
- **`event_teams`** — Equipos dentro de un evento.
- **`event_team_members`** — Miembros de cada equipo (usuarios internos o participantes externos).
- **`event_confirmations`** — Confirmación de asistencia de cada miembro.
- **`event_notifications`** — Notificaciones enviadas sobre el evento.
- **`event_results`** — Resultados registrados tras el evento.
- **`event_participants`** — Participantes directos (tabla legacy de torneos v1).
- **`external_participants`** — Personas externas sin cuenta en la plataforma.

---

## Migraciones de las releases (aplicar a mano)

> En **Render (TiDB)** el `php spark migrate --all` del arranque **no crea
> bien las migraciones nuevas** (AUTO_INCREMENT roto en la tabla `migrations`).
> En **Hostinger** no hay CLI. En ambos entornos las migraciones de las
> releases se ejecutan como SQL a mano.

**v1.1.0** — ver `docs/deploy/migraciones_seguridad.sql` + `docs/deploy/migraciones_posiciones.sql`:

```sql
CREATE TABLE IF NOT EXISTS `auth_events` ( ... );  -- ver sección auth_events
ALTER TABLE `users` ADD COLUMN `password_changed_at` DATETIME NULL DEFAULT NULL AFTER `password`;
ALTER TABLE `users` ADD COLUMN `must_change_password` TINYINT(1) NOT NULL DEFAULT 0 AFTER `password_changed_at`;
ALTER TABLE `player_profiles` MODIFY COLUMN `position` TEXT NULL DEFAULT NULL;
```

**v1.1.2** — sin cambios de BD (solo rediseño de las pantallas de acceso).

---

## Pendiente en BD (fixes)

```sql
-- 1. Columnas de asistencia avanzada (CRÍTICO si no se han ejecutado)
ALTER TABLE `class_session_players`
  ADD COLUMN `absence_reason` TEXT NULL DEFAULT NULL AFTER `attendance`;
ALTER TABLE `class_session_players`
  ADD COLUMN `student_note` TEXT NULL DEFAULT NULL AFTER `absence_reason`;
ALTER TABLE `class_session_players`
  ADD COLUMN `student_noted_at` DATETIME NULL DEFAULT NULL AFTER `student_note`;

-- 2. Índice duplicado
ALTER TABLE `player_profiles` DROP INDEX `player_id_2`;

-- 3. Auto increment seguro en class_session_players (recomendado)
-- SELECT MAX(id) FROM class_session_players; → usa ese valor + 100
ALTER TABLE `class_session_players` AUTO_INCREMENT = <max+100>;

-- 4. Purga periódica de auth_events (opcional)
DELETE FROM `auth_events` WHERE `created_at` < DATE_SUB(NOW(), INTERVAL 90 DAY);
```
