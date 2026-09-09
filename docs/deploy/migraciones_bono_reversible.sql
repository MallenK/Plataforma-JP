-- =============================================================
-- MIGRACIÓN — descuento de bono reversible · JP Preparation
-- Ejecutar en phpMyAdmin sobre la BD de producción (u912370917_jpapp)
-- y sobre la BD de pre-producción / demo si el `php spark migrate`
-- automático de Render no la aplica.
--
-- Corresponde a la migración CodeIgniter:
--   2026-09-09-000001_AddBonoDeductedFromIdToClassSessionPlayers
--
-- Añade `bono_deducted_from_id` a class_session_players: guarda de QUÉ
-- bono (player_bonos.id) se descontó cada sesión, para poder devolver
-- la sesión al bono correcto aunque la cola FIFO de bonos del alumno
-- haya cambiado después. Sin este dato el descuento era irreversible.
--
-- Las filas antiguas quedan con NULL: al devolver, el código cae al
-- bono activo actual del alumno (comportamiento tolerante).
--
-- (En local: docker compose exec app php spark migrate)
-- =============================================================

ALTER TABLE `class_session_players`
  ADD COLUMN `bono_deducted_from_id` INT UNSIGNED NULL DEFAULT NULL
  AFTER `bono_deducted_at`;

-- Revertir (si hiciera falta):
-- ALTER TABLE `class_session_players` DROP COLUMN `bono_deducted_from_id`;
