-- ══════════════════════════════════════════════════════════════════════
--  JP PREPARATION — Sistema de documentación
--  Ejecutar en la base de datos: jp_preparation
--  Fecha: 2026-04-10
-- ══════════════════════════════════════════════════════════════════════

-- ─────────────────────────────────────────────────────────────────────
--  1. Carpetas del sistema de documentación
--
--  type = 'public'   → visible para todos los roles excepto 'player'
--  type = 'personal' → carpeta privada de un usuario (owner_id = users.id)
--  type = 'internal' → acceso restringido por folder_permissions; nunca 'player'
-- ─────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `document_folders` (
  `id`         INT UNSIGNED     NOT NULL AUTO_INCREMENT,
  `name`       VARCHAR(150)     NOT NULL,
  `slug`       VARCHAR(150)     NOT NULL,
  `type`       ENUM('public','personal','internal') NOT NULL DEFAULT 'public',
  `icon`       VARCHAR(60)      NOT NULL DEFAULT 'bi-folder-fill',
  `color`      VARCHAR(30)      NOT NULL DEFAULT 'blue',
  `owner_id`   INT UNSIGNED     NULL     COMMENT 'Solo para type=personal: FK a users.id',
  `created_by` INT UNSIGNED     NOT NULL,
  `status`     ENUM('active','inactive') NOT NULL DEFAULT 'active',
  `created_at` DATETIME         NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `updated_at` DATETIME         NULL     ON UPDATE CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_slug` (`slug`),
  KEY `idx_type`  (`type`),
  KEY `idx_owner` (`owner_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─────────────────────────────────────────────────────────────────────
--  2. Archivos subidos
--
--  name_original → nombre que ve el usuario (el del fichero original)
--  name_stored   → nombre UUID en disco (nunca accesible por URL directa)
--  sensitive     → 1 = contiene datos personales → headers no-cache al servir
--  deleted_at    → soft delete (los ficheros físicos se pueden purgar aparte)
-- ─────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `documents` (
  `id`            INT UNSIGNED  NOT NULL AUTO_INCREMENT,
  `folder_id`     INT UNSIGNED  NOT NULL,
  `uploader_id`   INT UNSIGNED  NOT NULL,
  `name_original` VARCHAR(255)  NOT NULL COMMENT 'Nombre visible al usuario',
  `name_stored`   VARCHAR(255)  NOT NULL COMMENT 'UUID filename en disco',
  `mime_type`     VARCHAR(100)  NOT NULL,
  `extension`     VARCHAR(15)   NOT NULL,
  `size_bytes`    BIGINT UNSIGNED NOT NULL DEFAULT 0,
  `description`   TEXT          NULL,
  `sensitive`     TINYINT(1)    NOT NULL DEFAULT 0 COMMENT 'Datos personales: no cachear',
  `created_at`    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  `deleted_at`    DATETIME      NULL,
  PRIMARY KEY (`id`),
  KEY `idx_folder`    (`folder_id`),
  KEY `idx_uploader`  (`uploader_id`),
  KEY `idx_deleted`   (`deleted_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─────────────────────────────────────────────────────────────────────
--  3. Permisos de carpetas internas
--
--  Solo aplica a type='internal'. Los roles admin/superadmin tienen acceso
--  siempre (hardcoded en DocumentService). Esta tabla gestiona el resto.
--  Nunca se insertan registros con user_id de un 'player'.
-- ─────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `folder_permissions` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `folder_id`  INT UNSIGNED NOT NULL,
  `user_id`    INT UNSIGNED NOT NULL,
  `can_read`   TINYINT(1)   NOT NULL DEFAULT 1,
  `can_write`  TINYINT(1)   NOT NULL DEFAULT 0,
  `granted_by` INT UNSIGNED NOT NULL,
  `created_at` DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (`id`),
  UNIQUE KEY `uq_folder_user` (`folder_id`, `user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ─────────────────────────────────────────────────────────────────────
--  4. Seed: carpetas públicas por defecto
--  Ajusta created_by al ID del primer admin de tu instalación (por defecto 2)
-- ─────────────────────────────────────────────────────────────────────
INSERT IGNORE INTO `document_folders` (`name`, `slug`, `type`, `icon`, `color`, `created_by`) VALUES
('Reglamentos',      'reglamentos',     'public', 'bi-file-earmark-text-fill', 'blue',   2),
('Vídeos técnicos',  'videos-tecnicos', 'public', 'bi-camera-video-fill',      'green',  2),
('Nutrición',        'nutricion',       'public', 'bi-clipboard2-pulse-fill',  'orange', 2),
('Psicología',       'psicologia',      'public', 'bi-brain',                  'purple', 2);


-- ─────────────────────────────────────────────────────────────────────
--  CONFIGURACIÓN PHP RECOMENDADA
--  Para subidas de hasta 500 MB añade en tu php.ini o .htaccess de public/:
--
--    upload_max_filesize = 512M
--    post_max_size       = 520M
--    memory_limit        = 256M
--    max_execution_time  = 300
--    max_input_time      = 300
-- ─────────────────────────────────────────────────────────────────────
