-- =============================================================
-- MIGRACIÓN — varias posiciones por alumno · JP Preparation
-- Ejecutar en phpMyAdmin sobre la BD de producción (u912370917_jpapp)
--
-- Corresponde a la migración CodeIgniter:
--   2026-09-01-000001_WidenPositionOnPlayerProfiles
--
-- La columna `position` pasa de VARCHAR(50) a TEXT para poder guardar
-- la lista JSON de posiciones. Los valores antiguos (texto libre) se
-- siguen leyendo sin tocar nada.
--
-- (En local: docker compose exec app php spark migrate)
-- =============================================================

ALTER TABLE `player_profiles` MODIFY COLUMN `position` TEXT NULL DEFAULT NULL;
