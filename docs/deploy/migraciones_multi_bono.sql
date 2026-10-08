-- ─────────────────────────────────────────────────────────────────────────────
-- Varios bonos activos por alumno (v1.31.0)  ·  migración 2026-10-08-000001
--
-- NO ejecutar todavía en producción: por ahora solo local. Se ejecuta por
-- phpMyAdmin (Hostinger) al desplegar. Es idempotente en la práctica: si el
-- ALTER ya se aplicó, MySQL devuelve "Duplicate column / key name" y no cambia nada.
--
-- No cambia el modelo (player_bonos.player_id ya admitía N bonos por alumno):
--   1. bono_movements.related_bono_id → en un "Cambiar bono", el otro bono implicado.
--   2. Índice en player_bonos para "bonos con saldo y vigentes de un alumno".
-- ─────────────────────────────────────────────────────────────────────────────

ALTER TABLE `bono_movements`
    ADD COLUMN `related_bono_id` INT UNSIGNED NULL DEFAULT NULL AFTER `bono_id`,
    ADD KEY `idx_bm_related` (`related_bono_id`);

ALTER TABLE `player_bonos`
    ADD KEY `idx_pb_player_saldo` (`player_id`, `sessions_remaining`, `expires_at`);

-- Registro de la migración en CodeIgniter (solo si se usa `spark migrate` en ese entorno;
-- si se aplica este SQL a mano, insertar la fila para que `migrate` no la repita):
-- INSERT INTO `migrations` (`version`, `class`, `group`, `namespace`, `time`, `batch`)
-- VALUES ('2026-10-08-000001', 'App\\Database\\Migrations\\MultiBonoSupport', 'default', 'App',
--         UNIX_TIMESTAMP(), (SELECT COALESCE(MAX(b.batch), 0) + 1 FROM (SELECT batch FROM `migrations`) b));

-- ─────────────────────────────────────────────────────────────────────────────
-- PPR / demo: ejemplos de alumnos con varios bonos (migración 2026-10-08-000002)
-- Render ejecuta `php spark migrate --all` al arrancar y la migración solo siembra
-- si APP_ENV_LABEL no está vacío (PPR/demo). En PRODUCCIÓN no hace nada.
-- Si el arranque de Render avisa "Migrations failed", aplicar arriba el ALTER a mano
-- y lanzar `php spark migrate` desde la shell de Render. Para retirar los ejemplos:
--   php spark migrate:rollback   (o: borrar los bonos con notes LIKE '%[ejemplo:multibono]%'
--   y sus filas en bono_movements).
-- ─────────────────────────────────────────────────────────────────────────────
