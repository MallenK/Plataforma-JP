-- =============================================================
-- MIGRACIÓN — derechos de imagen firmados · JP Preparation
-- Ejecutar en phpMyAdmin sobre la BD de producción (u912370917_jpapp)
-- y sobre la BD de pre-producción / demo si el `php spark migrate`
-- automático de Render no la aplica.
--
-- Corresponde a la migración CodeIgniter:
--   2026-09-10-000001_AddImageRightsSignedToPlayerProfiles
--
-- Añade `image_rights_signed` a player_profiles: booleano que indica si
-- el alumno (o su tutor legal) ha firmado la cesión de derechos de
-- imagen. Lo configuran solo admin / superadmin desde la ficha del
-- alumno y desde la pantalla de edición. Por defecto 0 (sin firmar).
--
-- (En local: docker compose exec app php spark migrate)
-- =============================================================

ALTER TABLE `player_profiles`
  ADD COLUMN `image_rights_signed` TINYINT(1) NOT NULL DEFAULT 0
  AFTER `medical_notes`;

-- Revertir (si hiciera falta):
-- ALTER TABLE `player_profiles` DROP COLUMN `image_rights_signed`;
