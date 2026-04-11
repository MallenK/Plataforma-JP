-- ============================================================
-- JP Preparation — Módulo: Clases y Calendario
-- Ejecutar en la base de datos: jp_preparation
-- ============================================================

-- ── Plantillas para clases recurrentes ──────────────────────
CREATE TABLE IF NOT EXISTS `classes` (
  `id`                      INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
  `title`                   VARCHAR(150)  NOT NULL,
  `description`             TEXT          NULL,
  `type`                    ENUM('single','recurring') NOT NULL DEFAULT 'single',
  -- Solo para recurrentes:
  `recurrence_days`         VARCHAR(20)   NULL COMMENT 'JSON, ej: [1,3] = Lun/Mié (ISO: 1=Lun … 7=Dom)',
  `recurrence_start`        DATE          NULL,
  `recurrence_end`          DATE          NULL,
  `recurrence_time_start`   TIME          NULL,
  `recurrence_time_end`     TIME          NULL,
  -- Defaults copiados a cada sesión generada:
  `default_location_id`     INT UNSIGNED  NULL,
  `default_location_custom` VARCHAR(255)  NULL,
  `default_focus`           TEXT          NULL,
  `created_by`              INT UNSIGNED  NOT NULL,
  `created_at`              DATETIME      DEFAULT CURRENT_TIMESTAMP,
  `updated_at`              DATETIME      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_classes_by` (`created_by`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Sesiones individuales ────────────────────────────────────
CREATE TABLE IF NOT EXISTS `class_sessions` (
  `id`              INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
  `class_id`        INT UNSIGNED  NULL COMMENT 'NULL = sesión independiente',
  `title`           VARCHAR(150)  NOT NULL,
  `session_date`    DATE          NOT NULL,
  `start_time`      TIME          NOT NULL,
  `end_time`        TIME          NOT NULL,
  `location_id`     INT UNSIGNED  NULL,
  `location_custom` VARCHAR(255)  NULL,
  `focus`           TEXT          NULL COMMENT 'Objetivo del entrenamiento',
  `pre_notes`       TEXT          NULL COMMENT 'Planificación / observaciones previas',
  `post_notes`      TEXT          NULL COMMENT 'Feedback / observaciones posteriores',
  `status`          ENUM('scheduled','completed','cancelled') NOT NULL DEFAULT 'scheduled',
  `created_by`      INT UNSIGNED  NOT NULL,
  `created_at`      DATETIME      DEFAULT CURRENT_TIMESTAMP,
  `updated_at`      DATETIME      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  INDEX `idx_cs_date`     (`session_date`),
  INDEX `idx_cs_class`    (`class_id`),
  INDEX `idx_cs_status`   (`status`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Entrenadores por sesión ──────────────────────────────────
CREATE TABLE IF NOT EXISTS `class_session_coaches` (
  `id`         INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
  `session_id` INT UNSIGNED  NOT NULL,
  `user_id`    INT UNSIGNED  NOT NULL,
  `created_at` DATETIME      DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_csc` (`session_id`, `user_id`),
  INDEX `idx_csc_user` (`user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ── Jugadores por sesión ─────────────────────────────────────
CREATE TABLE IF NOT EXISTS `class_session_players` (
  `id`           INT UNSIGNED  AUTO_INCREMENT PRIMARY KEY,
  `session_id`   INT UNSIGNED  NOT NULL,
  `user_id`      INT UNSIGNED  NOT NULL,
  `coach_id`     INT UNSIGNED  NULL COMMENT 'Entrenador asignado a este jugador en la sesión',
  `attendance`   ENUM('pending','confirmed','declined','present','absent') NOT NULL DEFAULT 'pending',
  `responded_at` DATETIME      NULL,
  `pre_obs`      TEXT          NULL COMMENT 'Observación previa del entrenador para este jugador',
  `post_obs`     TEXT          NULL COMMENT 'Feedback posterior para este jugador',
  `created_at`   DATETIME      DEFAULT CURRENT_TIMESTAMP,
  `updated_at`   DATETIME      DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY `uq_csp` (`session_id`, `user_id`),
  INDEX `idx_csp_user`  (`user_id`),
  INDEX `idx_csp_coach` (`coach_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
