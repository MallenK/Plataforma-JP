-- ============================================================================
-- Externos (Fase 1): personas que reciben una clase SIN cuenta de alumno
-- (clase de prueba / clase suelta). Equivale a la migración
-- 2026-11-09-000001_ClassGuests. Idempotente (CREATE TABLE IF NOT EXISTS).
-- Solo AÑADE tablas: no toca ninguna existente. Sin FKs ni cascadas.
-- Orden de despliegue: ejecutar este SQL ANTES (o a la vez) de subir app/.
-- Sin las tablas, la ficha de clase muestra "se activará al aplicar la migración"
-- y no falla.
-- ============================================================================

CREATE TABLE IF NOT EXISTS `class_guests` (
    `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `first_name`        VARCHAR(100) NOT NULL,
    `last_name`         VARCHAR(100) NULL DEFAULT NULL,
    `phone`             VARCHAR(30)  NULL DEFAULT NULL,
    `phone_key`         VARCHAR(30)  NULL DEFAULT NULL,
    `email`             VARCHAR(150) NULL DEFAULT NULL,
    `birth_date`        DATE         NULL DEFAULT NULL,
    `guardian_name`     VARCHAR(150) NULL DEFAULT NULL,
    `guardian_phone`    VARCHAR(30)  NULL DEFAULT NULL,
    `image_rights`      TINYINT(1)   NOT NULL DEFAULT 0,
    `notes`             TEXT         NULL,
    `converted_user_id` INT          NULL DEFAULT NULL,
    `created_by`        INT          NULL DEFAULT NULL,
    `archived_at`       DATETIME     NULL DEFAULT NULL,
    `created_at`        DATETIME     NULL DEFAULT NULL,
    `updated_at`        DATETIME     NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_cg_phone` (`phone_key`),
    KEY `idx_cg_email` (`email`),
    KEY `idx_cg_converted` (`converted_user_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `class_session_guests` (
    `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `session_id`  INT UNSIGNED NOT NULL,
    `guest_id`    INT UNSIGNED NOT NULL,
    `kind`        ENUM('trial','single') NOT NULL DEFAULT 'trial',
    `attendance`  ENUM('pending','present','absent') NOT NULL DEFAULT 'pending',
    `coach_id`    INT          NULL DEFAULT NULL,
    `notes`       TEXT         NULL,
    `created_by`  INT          NULL DEFAULT NULL,
    `created_at`  DATETIME     NULL DEFAULT NULL,
    `updated_at`  DATETIME     NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    UNIQUE KEY `uq_csg` (`session_id`, `guest_id`),
    KEY `idx_csg_guest` (`guest_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Si usas phpMyAdmin con spark migrate no disponible en prod, hay que registrar también la migración
-- para que un futuro `spark migrate` no intente crearla de nuevo (aunque es idempotente):
-- INSERT INTO `migrations` (`version`,`class`,`group`,`namespace`,`time`,`batch`)
--   SELECT '2026-11-09-000001','App\Database\Migrations\ClassGuests','default','App',UNIX_TIMESTAMP(),COALESCE(MAX(`batch`),0)+1 FROM `migrations`;

-- ============================================================================
-- Externos (Fase 2): dinero. Equivale a la migración 2026-11-10-000001_ClassGuestMoney.
-- Ejecutar DESPUÉS de las dos tablas de arriba. Idempotente (MariaDB): el ALTER usa IF NOT EXISTS; antes fallaba
-- con "Duplicate column" si se repite (es inocuo); el CREATE TABLE usa IF NOT EXISTS.
-- ============================================================================
ALTER TABLE `class_session_guests` ADD COLUMN IF NOT EXISTS `price_cents` INT NULL DEFAULT NULL AFTER `attendance`;

CREATE TABLE IF NOT EXISTS `class_guest_payments` (
    `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `session_guest_id` INT UNSIGNED NOT NULL,
    `method_id`        INT UNSIGNED NULL DEFAULT NULL,
    `amount_cents`     INT          NOT NULL,
    `paid_at`          DATE         NOT NULL,
    `note`             VARCHAR(255) NULL DEFAULT NULL,
    `voided_at`        DATETIME     NULL DEFAULT NULL,
    `voided_by`        INT          NULL DEFAULT NULL,
    `void_reason`      VARCHAR(255) NULL DEFAULT NULL,
    `created_by`       INT          NULL DEFAULT NULL,
    `created_at`       DATETIME     NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_cgp_link` (`session_guest_id`),
    KEY `idx_cgp_paid` (`paid_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
