-- ══════════════════════════════════════════════════════════════════════
--  JP PREPARATION — Configuración de la plataforma
--  Ejecutar en la base de datos: jp_preparation
--  Fecha: 2026-04-10
--
--  NOTA: Las sentencias ALTER TABLE pueden fallar si las columnas ya
--  existen. Es seguro ignorar el error "Duplicate column name".
-- ══════════════════════════════════════════════════════════════════════


-- ─────────────────────────────────────────────────────────────────────
--  1. Ajustes globales de la academia (key-value store)
-- ─────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `academy_settings` (
  `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `setting_key`   VARCHAR(100) NOT NULL,
  `setting_value` TEXT         NULL,
  `setting_type`  ENUM('string','int','bool','json') NOT NULL DEFAULT 'string',
  `updated_at`    DATETIME     NULL,
  `updated_by`    INT UNSIGNED NULL,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_setting_key` (`setting_key`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─────────────────────────────────────────────────────────────────────
--  2. Extensión de la tabla locations
--     (ya existe con: id, name, description, created_at, updated_at)
-- ─────────────────────────────────────────────────────────────────────
ALTER TABLE `locations`
  ADD COLUMN `address`  VARCHAR(255)                                        NULL AFTER `description`,
  ADD COLUMN `type`     ENUM('pitch','gym','room','office','other') NOT NULL DEFAULT 'pitch' AFTER `address`,
  ADD COLUMN `capacity` INT UNSIGNED                                        NULL AFTER `type`,
  ADD COLUMN `phone`    VARCHAR(30)                                         NULL AFTER `capacity`,
  ADD COLUMN `active`   TINYINT(1)                               NOT NULL DEFAULT 1 AFTER `phone`;


-- ─────────────────────────────────────────────────────────────────────
--  3. Tipos de bono / membresía
-- ─────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `bono_types` (
  `id`            INT UNSIGNED   NOT NULL AUTO_INCREMENT,
  `name`          VARCHAR(100)   NOT NULL COMMENT 'Ej: Bono 10 clases',
  `sessions`      INT UNSIGNED   NOT NULL DEFAULT 10,
  `price`         DECIMAL(8,2)   NOT NULL DEFAULT 0.00,
  `validity_days` INT UNSIGNED   NOT NULL DEFAULT 90 COMMENT 'Días de validez desde activación',
  `active`        TINYINT(1)     NOT NULL DEFAULT 1,
  `created_at`    DATETIME       NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at`    DATETIME       NULL ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─────────────────────────────────────────────────────────────────────
--  4. Log de emails enviados desde la plataforma
-- ─────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `email_log` (
  `id`             INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `sender_id`      INT UNSIGNED  NOT NULL,
  `recipient_type` ENUM('individual','group') NOT NULL DEFAULT 'individual',
  `recipient_id`   INT UNSIGNED  NULL  COMMENT 'user_id si es individual',
  `recipient_group`VARCHAR(50)   NULL  COMMENT 'role o "all" si es grupal',
  `subject`        VARCHAR(255)  NOT NULL,
  `message`        TEXT          NOT NULL,
  `status`         ENUM('sent','failed') NOT NULL DEFAULT 'sent',
  `error_msg`      TEXT          NULL,
  `created_at`     DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  KEY `idx_sender`     (`sender_id`),
  KEY `idx_created_at` (`created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─────────────────────────────────────────────────────────────────────
--  5. Valores por defecto de configuración
-- ─────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO `academy_settings` (`setting_key`, `setting_value`, `setting_type`) VALUES
-- General
('academy_name',       'JP Preparation',   'string'),
('academy_email',      '',                 'string'),
('academy_phone',      '',                 'string'),
('academy_language',   'es',               'string'),
('academy_timezone',   'Europe/Madrid',    'string'),
('academy_currency',   'EUR',              'string'),
('academy_location',   '',                 'string'),
('academy_website',    '',                 'string'),
-- Notificaciones: toggles
('notif_new_student',    '1',  'bool'),
('notif_bono_expiry',    '1',  'bool'),
('notif_class_reminder', '1',  'bool'),
('notif_payment_due',    '1',  'bool'),
-- Notificaciones: SMTP
('smtp_host',        '',             'string'),
('smtp_port',        '587',          'int'),
('smtp_encryption',  'tls',          'string'),
('smtp_user',        '',             'string'),
('smtp_pass',        '',             'string'),
('smtp_from_name',   'JP Preparation','string'),
('smtp_from_email',  '',             'string'),
-- Seguridad
('sec_min_password',  '8',  'int'),
('sec_require_upper', '0',  'bool'),
('sec_require_numbers','0', 'bool'),
('sec_require_special','0', 'bool'),
('sec_session_timeout','10','int'),
-- Web pública
('web_active',     '0', 'bool'),
('web_instagram',  '',  'string'),
('web_twitter',    '',  'string'),
('web_facebook',   '',  'string'),
('web_youtube',    '',  'string'),
('web_tiktok',     '',  'string');
