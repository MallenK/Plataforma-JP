-- ══════════════════════════════════════════════════════════════════════
--  JP PREPARATION — Torneos y Campus
--  Ejecutar en la base de datos: jp_preparation
-- ══════════════════════════════════════════════════════════════════════


-- ─────────────────────────────────────────────────────────────────────
--  1. Eventos (torneos y campus)
--
--  type = 'torneo' → competición oficial o amistosa
--  type = 'campus' → campus formativo con programa, alojamiento, etc.
--
--  El estado se calcula dinámicamente por fecha, salvo 'cancelled'
--  que es la única bandera manual.
-- ─────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `events` (
  `id`                   INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `type`                 ENUM('torneo','campus') NOT NULL DEFAULT 'torneo',
  `name`                 VARCHAR(200)  NOT NULL,
  `description`          TEXT          NULL,
  `category`             VARCHAR(80)   NULL  COMMENT 'Sub-8, Sub-12, Absoluto…',
  `start_date`           DATE          NOT NULL,
  `end_date`             DATE          NOT NULL,
  `location`             VARCHAR(255)  NULL,
  `concentration_time`   TIME          NULL  COMMENT 'Hora de concentración',
  `concentration_place`  VARCHAR(255)  NULL  COMMENT 'Lugar de concentración',
  `equipment_notes`      TEXT          NULL  COMMENT 'Equipamiento / material necesario',
  -- Campus-specific (NULL para torneos)
  `accommodation_info`   TEXT          NULL  COMMENT 'Información de alojamiento (campus)',
  `schedule_info`        TEXT          NULL  COMMENT 'Programa de actividades (campus)',
  -- Control
  `cancelled`            TINYINT(1)    NOT NULL DEFAULT 0,
  `created_by`           INT UNSIGNED  NOT NULL,
  `created_at`           DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`           DATETIME      NULL     ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_type`       (`type`),
  KEY `idx_start_date` (`start_date`),
  KEY `idx_cancelled`  (`cancelled`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─────────────────────────────────────────────────────────────────────
--  2. Equipos dentro de un evento
--
--  Un evento puede tener uno o varios equipos.
--  Cada equipo tiene nombre propio y categoría opcional.
-- ─────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `event_teams` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_id`   INT UNSIGNED NOT NULL,
  `name`       VARCHAR(150) NOT NULL  COMMENT 'Ej: Equipo A, Sub-14…',
  `category`   VARCHAR(80)  NULL,
  `notes`      TEXT         NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_event_id` (`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─────────────────────────────────────────────────────────────────────
--  3. Participantes externos (reutilizables entre eventos)
--
--  Personas que no tienen cuenta en la plataforma pero participan
--  puntualmente en torneos o campus.
-- ─────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `external_participants` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(150) NOT NULL,
  `type`       ENUM('player','coach','staff') NOT NULL DEFAULT 'player',
  `position`   VARCHAR(60)  NULL  COMMENT 'Portero, Delantero… (solo players)',
  `birth_date` DATE         NULL,
  `phone`      VARCHAR(30)  NULL,
  `email`      VARCHAR(150) NULL,
  `notes`      TEXT         NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME     NULL     ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_type` (`type`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─────────────────────────────────────────────────────────────────────
--  4. Miembros de cada equipo
--
--  Cada miembro puede ser un usuario de la plataforma (member_type=user)
--  o un participante externo (member_type=external).
--  Solo se usa uno de: user_id / external_id según member_type.
-- ─────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `event_team_members` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `team_id`     INT UNSIGNED NOT NULL,
  `member_type` ENUM('user','external') NOT NULL DEFAULT 'user',
  `user_id`     INT UNSIGNED NULL  COMMENT 'FK users.id si member_type=user',
  `external_id` INT UNSIGNED NULL  COMMENT 'FK external_participants.id si member_type=external',
  `role`        ENUM('player','coach','staff') NOT NULL DEFAULT 'player',
  `dorsal`      TINYINT UNSIGNED NULL  COMMENT 'Dorsal (solo jugadores)',
  `position`    VARCHAR(60)  NULL  COMMENT 'Posición en el campo (solo jugadores)',
  `staff_role`  VARCHAR(100) NULL  COMMENT 'Rol de coach/staff: Primer entrenador, Fisio, Delegado…',
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_team_id`    (`team_id`),
  KEY `idx_user_id`    (`user_id`),
  KEY `idx_external_id`(`external_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─────────────────────────────────────────────────────────────────────
--  5. Notificaciones internas de convocatoria
--
--  Solo para miembros con user_id (los externos no tienen cuenta).
--  read_at = NULL → notificación no leída.
-- ─────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `event_notifications` (
  `id`        INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_id`  INT UNSIGNED NOT NULL,
  `member_id` INT UNSIGNED NOT NULL COMMENT 'FK event_team_members.id',
  `sent_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `read_at`   DATETIME     NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_event_member` (`event_id`, `member_id`),
  KEY `idx_member_id` (`member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─────────────────────────────────────────────────────────────────────
--  6. Confirmaciones de asistencia
--
--  Los miembros con cuenta pueden confirmar o rechazar su convocatoria.
--  Los externos quedan en 'pending' hasta que el admin actualice.
-- ─────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `event_confirmations` (
  `id`           INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_id`     INT UNSIGNED NOT NULL,
  `member_id`    INT UNSIGNED NOT NULL COMMENT 'FK event_team_members.id',
  `status`       ENUM('pending','confirmed','declined') NOT NULL DEFAULT 'pending',
  `notes`        TEXT         NULL  COMMENT 'Motivo opcional al rechazar',
  `responded_at` DATETIME     NULL,
  `created_at`   DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_event_member` (`event_id`, `member_id`),
  KEY `idx_member_id` (`member_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─────────────────────────────────────────────────────────────────────
--  7. Resultados del evento (opcional)
-- ─────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `event_results` (
  `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `event_id`    INT UNSIGNED NOT NULL,
  `team_id`     INT UNSIGNED NULL  COMMENT 'Resultado ligado a un equipo específico (opcional)',
  `result_text` VARCHAR(255) NULL  COMMENT 'Ej: 3-1, Campeones, 2º clasificado…',
  `notes`       TEXT         NULL,
  `created_at`  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`  DATETIME     NULL     ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_event_id` (`event_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
