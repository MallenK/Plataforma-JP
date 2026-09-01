-- =============================================================
-- MIGRACIONES DE SEGURIDAD - JP Preparation
-- Ejecutar en phpMyAdmin sobre la BD de producción (u912370917_jpapp)
-- IMPORTANTE: activa "Continuar en caso de error" antes de ejecutar
-- (si una columna/tabla ya existe dará error pero seguirá con el resto)
--
-- Corresponde a las migraciones CodeIgniter:
--   2026-09-02-000001_CreateAuthEvents
--   2026-09-02-000002_AddPasswordSecurityColumnsToUsers
--
-- (En local se aplican con: docker compose exec app php spark migrate)
-- =============================================================

-- -------------------------------------------------------------
-- auth_events: registro de eventos de auth (rate-limit + auditoría)
-- -------------------------------------------------------------
CREATE TABLE IF NOT EXISTS `auth_events` (
    `id`         BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `event_type` VARCHAR(40)  NOT NULL,
    `identifier` VARCHAR(191) NULL DEFAULT NULL,
    `user_id`    INT UNSIGNED NULL DEFAULT NULL,
    `ip_address` VARCHAR(45)  NOT NULL,
    `user_agent` VARCHAR(255) NULL DEFAULT NULL,
    `meta`       JSON NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_ident_time` (`identifier`, `created_at`),
    KEY `idx_ip_time`    (`ip_address`, `created_at`),
    KEY `idx_type_time`  (`event_type`, `created_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- -------------------------------------------------------------
-- users: password_changed_at, must_change_password
-- -------------------------------------------------------------
ALTER TABLE `users` ADD COLUMN `password_changed_at` DATETIME NULL DEFAULT NULL AFTER `password`;
ALTER TABLE `users` ADD COLUMN `must_change_password` TINYINT(1) NOT NULL DEFAULT 0 AFTER `password_changed_at`;
