-- =============================================================
-- MIGRACIÓN — continuación de clases recurrentes · JP Preparation
-- Ejecutar en phpMyAdmin sobre la BD de producción (u912370917_jpapp)
-- y sobre la BD de pre-producción / demo si el `php spark migrate`
-- automático de Render no la aplica.
--
-- Corresponde a la migración CodeIgniter:
--   2026-09-17-000003_AddSeriesRenewalToClasses
--
-- Añade a `classes` los enlaces entre la serie original y la nueva serie
-- generada al "continuar" una clase recurrente (mismo patrón de días, un
-- mes después, editable). Sin FK dura a propósito — mismo criterio que
-- bono_deducted_from_id: el código tolera un id que apunte a una
-- plantilla borrada.
--
-- (En local: docker compose exec app php spark migrate)
-- =============================================================

ALTER TABLE `classes`
  ADD COLUMN `renewed_from_class_id` INT UNSIGNED NULL DEFAULT NULL AFTER `created_by`,
  ADD COLUMN `renewed_to_class_id` INT UNSIGNED NULL DEFAULT NULL AFTER `renewed_from_class_id`,
  ADD INDEX `idx_classes_renewed_from` (`renewed_from_class_id`);

-- Revertir (si hiciera falta, y ninguna clase se ha continuado todavía):
-- ALTER TABLE `classes`
--   DROP INDEX `idx_classes_renewed_from`,
--   DROP COLUMN `renewed_from_class_id`,
--   DROP COLUMN `renewed_to_class_id`;
