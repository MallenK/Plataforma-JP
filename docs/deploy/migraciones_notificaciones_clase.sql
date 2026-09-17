-- =============================================================
-- MIGRACIÓN — tercer origen de notificaciones: clase · JP Preparation
-- Ejecutar en phpMyAdmin sobre la BD de producción (u912370917_jpapp)
-- y sobre la BD de pre-producción / demo si el `php spark migrate`
-- automático de Render no la aplica.
--
-- Corresponde a la migración CodeIgniter:
--   2026-09-17-000001_WidenNotificationsSourceType   (TICKET-011)
--
-- Amplía `notifications.source_type` de ENUM('ticket','conversation') a
-- VARCHAR(20) para poder guardar también 'class' (aviso de cambio de
-- responsable de una clase). Si las columnas no existen todavía (no se
-- aplicó 2026-09-16-000001), las crea directamente como VARCHAR.
--
-- (En local: docker compose exec app php spark migrate)
-- =============================================================

-- 0) Diagnóstico (opcional)
-- SHOW COLUMNS FROM `notifications` LIKE 'source%';

-- 1) Si las columnas YA existen (caso normal, tras TICKET-010):
ALTER TABLE `notifications`
  MODIFY COLUMN `source_type` VARCHAR(20) NULL DEFAULT NULL;

-- 2) Si NO existen todavía (entorno donde no se aplicó 2026-09-16-000001):
-- ALTER TABLE `notifications`
--   ADD COLUMN `source_type` VARCHAR(20) NULL DEFAULT NULL AFTER `file_size`,
--   ADD COLUMN `source_id` INT UNSIGNED NULL DEFAULT NULL AFTER `source_type`;

-- Revertir (si hiciera falta; solo tiene sentido si no se ha guardado
-- ya ninguna notificación con source_type='class'):
-- ALTER TABLE `notifications`
--   MODIFY COLUMN `source_type` ENUM('ticket','conversation') NULL DEFAULT NULL;
