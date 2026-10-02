-- TICKET-013 · Clases recurrentes ↔ bonos (cobertura, libro de movimientos, deudas)
-- Equivale a la migración 2026-10-02-000001_CreateBonoMovementsAndCoverage.
-- IDEMPOTENTE salvo los ADD COLUMN (MariaDB 10.3+ admite IF NOT EXISTS).
--
-- ⚠️ NO EJECUTAR EN PRODUCCIÓN hasta que se decida el despliegue.
--    El valor de `bono_control_since` es el PUNTO DE CONTROL: lo anterior a esa
--    fecha NO se trata como deuda (solo se muestra como "no reflejado").
--    Ejecutar este script EL DÍA del despliegue a PROD (se usa CURDATE()).
--
-- Hacer copia de seguridad de la BD antes.

CREATE TABLE IF NOT EXISTS `bono_movements` (
  `id`         INT UNSIGNED NOT NULL AUTO_INCREMENT,
  `player_id`  INT NOT NULL,
  `bono_id`    INT UNSIGNED NULL DEFAULT NULL,
  `session_id` INT UNSIGNED NULL DEFAULT NULL,
  `type`       VARCHAR(24) NOT NULL,
  `delta`      INT NOT NULL DEFAULT 0,
  `note`       VARCHAR(255) NULL DEFAULT NULL,
  `actor_id`   INT NULL DEFAULT NULL,
  `created_at` DATETIME NOT NULL,
  PRIMARY KEY (`id`),
  KEY `idx_bm_player` (`player_id`, `created_at`),
  KEY `idx_bm_bono` (`bono_id`),
  KEY `idx_bm_session` (`session_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

ALTER TABLE `class_session_players`
  ADD COLUMN IF NOT EXISTS `bono_coverage`    VARCHAR(10) NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `bono_resolution`  VARCHAR(10) NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `bono_resolved_at` DATETIME    NULL DEFAULT NULL,
  ADD COLUMN IF NOT EXISTS `bono_resolved_by` INT         NULL DEFAULT NULL;

-- Punto de control = hoy (solo si no existe ya).
INSERT INTO `academy_settings` (`setting_key`, `setting_value`, `setting_type`, `updated_at`)
SELECT 'bono_control_since', CURDATE(), 'string', NOW()
WHERE NOT EXISTS (SELECT 1 FROM `academy_settings` WHERE `setting_key` = 'bono_control_since');

-- Los avisos nuevos usan los orígenes 'class' y 'bono' en notifications.source_type:
-- si esa columna sigue siendo ENUM (entorno sin la migración 2026-09-17-000001) no
-- los admite. Este MODIFY es inofensivo si ya es VARCHAR(20).
-- (Si la columna NO existe, ejecuta antes docs/deploy/migraciones_notificaciones_origen.sql.)
ALTER TABLE `notifications`
  MODIFY COLUMN `source_type` VARCHAR(20) NULL DEFAULT NULL;

-- (OPCIONAL) Registrar la migración como ya aplicada, para que un futuro
-- `php spark migrate` no la repita (aunque es idempotente y no daría error):
-- INSERT INTO `migrations` (`version`, `class`, `group`, `namespace`, `time`, `batch`)
--   SELECT '2026-10-02-000001', 'App\\Database\\Migrations\\CreateBonoMovementsAndCoverage', 'default', 'App',
--          UNIX_TIMESTAMP(), COALESCE(MAX(`batch`), 0) + 1 FROM `migrations`;
