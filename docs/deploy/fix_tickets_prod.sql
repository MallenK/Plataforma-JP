-- =====================================================================
--  JP Preparation — ARREGLO del módulo de Soporte (tickets) en PRODUCCIÓN
--  BD: u912370917_jpapp        phpMyAdmin → pestaña SQL
--
--  QUÉ ARREGLA
--  El código de tickets (F1–F4) ya está en prod, pero sus columnas/tablas
--  NO. Por eso /tickets/gestion revienta con:
--      "Unknown column 't.archived_at' in 'WHERE'"
--  Este script añade SOLO lo que falta. Es aditivo: no borra ni cambia
--  ningún dato existente.
--
--  ANTES DE EJECUTAR
--   1) Exporta la BD completa (backup con fecha).
--   2) Marca la casilla  "Continuar en caso de error"  en phpMyAdmin.
--      (Lo que tu BD ya tenga dará "Duplicate column/key" y se salta:
--       es lo normal, no es un problema.)
--
--  Corresponde a las migraciones CodeIgniter:
--   2026-09-07-000001  origin / error_ref / context
--   2026-09-07-000002  assigned_to + ticket_replies.is_internal + ticket_events
--   2026-09-07-000003  scope / first_response_at / archived_at   ← el que falla
--   2026-09-07-000004  ticket_counters + reported_urgency
-- =====================================================================

USE `u912370917_jpapp`;
SET FOREIGN_KEY_CHECKS = 0;

-- ─────────────────────────────────────────────────────────────────────
-- 000001 — reporte contextual de errores
-- ─────────────────────────────────────────────────────────────────────
ALTER TABLE `tickets` ADD COLUMN `origin`    ENUM('manual','error','permiso') NOT NULL DEFAULT 'manual' AFTER `status`;
ALTER TABLE `tickets` ADD COLUMN `error_ref` VARCHAR(12) NULL AFTER `origin`;
ALTER TABLE `tickets` ADD COLUMN `context`   TEXT NULL AFTER `error_ref`;

-- ─────────────────────────────────────────────────────────────────────
-- 000002 — asignación, notas internas e historial de eventos
-- ─────────────────────────────────────────────────────────────────────
ALTER TABLE `tickets` ADD COLUMN `assigned_to` INT NULL AFTER `status`;
ALTER TABLE `tickets` ADD KEY `tickets_assigned_to` (`assigned_to`);

ALTER TABLE `ticket_replies` ADD COLUMN `is_internal` TINYINT(1) NOT NULL DEFAULT 0 AFTER `body`;

CREATE TABLE IF NOT EXISTS `ticket_events` (
    `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `ticket_id`  INT UNSIGNED NOT NULL,
    `actor_id`   INT NULL DEFAULT NULL,
    `event_type` VARCHAR(32) NOT NULL,
    `from_value` VARCHAR(191) NULL DEFAULT NULL,
    `to_value`   VARCHAR(191) NULL DEFAULT NULL,
    `created_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`),
    KEY `ticket_id` (`ticket_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ─────────────────────────────────────────────────────────────────────
-- 000003 — ámbito, SLA y archivado   (la columna que provoca el error)
-- ─────────────────────────────────────────────────────────────────────
ALTER TABLE `tickets` ADD COLUMN `scope` ENUM('academia','plataforma') NOT NULL DEFAULT 'academia' AFTER `category`;
ALTER TABLE `tickets` ADD COLUMN `first_response_at` DATETIME NULL AFTER `resolved_at`;
ALTER TABLE `tickets` ADD COLUMN `archived_at`       DATETIME NULL AFTER `closed_at`;
ALTER TABLE `tickets` ADD KEY `tickets_scope` (`scope`);
ALTER TABLE `tickets` ADD KEY `tickets_archived_at` (`archived_at`);

-- ─────────────────────────────────────────────────────────────────────
-- 000004 — contador anual de nº de ticket + urgencia reportada
-- ─────────────────────────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS `ticket_counters` (
    `year` INT UNSIGNED NOT NULL,
    `last` INT UNSIGNED NOT NULL DEFAULT 0,
    PRIMARY KEY (`year`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

ALTER TABLE `tickets` ADD COLUMN `reported_urgency` ENUM('baja','media','alta','urgente') NULL AFTER `priority`;

-- Siembra el contador del año en curso con el nº más alto ya emitido
-- (formato TKT-AAAA-NNNNN) para no reutilizar números.
INSERT IGNORE INTO `ticket_counters` (`year`, `last`)
SELECT YEAR(CURDATE()),
       COALESCE(MAX(CAST(RIGHT(`ticket_number`, 5) AS UNSIGNED)), 0)
FROM `tickets`
WHERE `ticket_number` LIKE CONCAT('TKT-', YEAR(CURDATE()), '-%');

-- ─────────────────────────────────────────────────────────────────────
-- Claves foráneas (OPCIONALES).
-- NOTA (2026-09-08): en la BD de Hostinger estas 3 fallan con errno 150
-- porque el tipo de tickets.id / users.id de prod no casa con lo que
-- asumen las migraciones. Se dejaron SIN aplicar a propósito — la
-- aplicación NO las necesita (joins y cascadas van por código).
-- Déjalas comentadas salvo que sepas que los tipos coinciden.
-- ─────────────────────────────────────────────────────────────────────
/*

ALTER TABLE `tickets`
  ADD CONSTRAINT `tickets_assigned_to_fk`
  FOREIGN KEY (`assigned_to`) REFERENCES `users`(`id`)
  ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE `ticket_events`
  ADD CONSTRAINT `ticket_events_ticket_fk`
  FOREIGN KEY (`ticket_id`) REFERENCES `tickets`(`id`)
  ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE `ticket_events`
  ADD CONSTRAINT `ticket_events_actor_fk`
  FOREIGN KEY (`actor_id`) REFERENCES `users`(`id`)
  ON DELETE SET NULL ON UPDATE CASCADE;
*/

SET FOREIGN_KEY_CHECKS = 1;

-- =====================================================================
--  COMPROBAR
--   SELECT `origin`,`assigned_to`,`scope`,`first_response_at`,
--          `archived_at`,`reported_urgency`
--     FROM `tickets` LIMIT 1;
--   -> no debe dar "Unknown column".
--
--  Luego en la web: entra en "Soporte" (/tickets/gestion) — ya no da error.
-- =====================================================================
