-- =============================================================
-- MIGRACIÓN — estado de asistencia "No justificado" · JP Preparation
-- Ejecutar en phpMyAdmin sobre la BD de producción (u912370917_jpapp)
-- y sobre la BD de pre-producción (2ª BD de Hostinger) si el
-- `php spark migrate` automático de Render no la aplica.
--
-- Corresponde a la migración CodeIgniter:
--   2026-09-07-000005_AddUnjustifiedToAttendanceEnum
--
-- Añade el valor 'unjustified' al ENUM `attendance` de
-- class_session_players. Se da por supuesto que el alumno NO asistió;
-- desde "Pasar lista" se puede descontar 1 sesión de su bono con
-- confirmación, quedando registro de la falta (bono_deducted_at).
--
-- (En local: docker compose exec app php spark migrate)
-- =============================================================

ALTER TABLE `class_session_players`
  MODIFY COLUMN `attendance`
  ENUM('pending','confirmed','declined','present','absent','unjustified')
  NOT NULL DEFAULT 'pending';

-- Revertir (si hiciera falta):
-- UPDATE `class_session_players` SET `attendance` = 'absent' WHERE `attendance` = 'unjustified';
-- ALTER TABLE `class_session_players`
--   MODIFY COLUMN `attendance`
--   ENUM('pending','confirmed','declined','present','absent')
--   NOT NULL DEFAULT 'pending';
