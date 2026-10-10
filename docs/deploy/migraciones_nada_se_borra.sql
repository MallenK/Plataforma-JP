-- ─────────────────────────────────────────────────────────────────────────────
-- «Nada se borra» (v1.33.0, Fase 0 de Finanzas)  ·  migración 2026-10-10-000001
--
-- NO ejecutar todavía en producción: se integrará más adelante (decisión del
-- responsable, 2026-10-10). Se ejecuta por phpMyAdmin (Hostinger) al desplegar,
-- ANTES de subir el código (el código nuevo lee estas columnas).
--
-- Prod = MariaDB 11.8 → `ADD COLUMN IF NOT EXISTS` / `CREATE TABLE IF NOT EXISTS`:
-- es idempotente, se puede ejecutar dos veces sin error.
--
-- Solo AÑADE: una tabla y columnas nuevas. No borra ni cambia datos, salvo
-- rellenar el precio estimado de los bonos existentes (paso 3).
-- Verificado sobre la copia anonimizada de prod (jp_prodlike, 10/10/2026):
-- 217 bonos con precio estimado, 0 sin precio; la suma coincide con el estudio.
-- ─────────────────────────────────────────────────────────────────────────────

-- 1. Registro de auditoría (solo se añaden filas)
CREATE TABLE IF NOT EXISTS `audit_log` (
    `id`          BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    `entity_type` VARCHAR(40)  NOT NULL,
    `entity_id`   INT UNSIGNED NULL DEFAULT NULL,
    `action`      VARCHAR(20)  NOT NULL,
    `before_json` LONGTEXT     NULL DEFAULT NULL,
    `after_json`  LONGTEXT     NULL DEFAULT NULL,
    `reason`      VARCHAR(500) NULL DEFAULT NULL,
    `actor_id`    INT          NULL DEFAULT NULL,
    `actor_role`  VARCHAR(20)  NULL DEFAULT NULL,
    `ip_address`  VARCHAR(45)  NULL DEFAULT NULL,
    `created_at`  DATETIME     NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_audit_entity` (`entity_type`, `entity_id`),
    KEY `idx_audit_created` (`created_at`),
    KEY `idx_audit_actor` (`actor_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- 2. Bonos: anulación (no borrado) y precio congelado. users.id es INT con signo.
ALTER TABLE `player_bonos`
    ADD COLUMN IF NOT EXISTS `voided_at`        DATETIME     NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `voided_by`        INT          NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `void_reason`      VARCHAR(255) NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `price_list_cents` INT          NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `price_cents`      INT          NULL DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS `price_estimated`  TINYINT(1)   NOT NULL DEFAULT 0;

-- Tipos de bono y sedes: se archivan, no se borran.
ALTER TABLE `bono_types` ADD COLUMN IF NOT EXISTS `archived_at` DATETIME NULL DEFAULT NULL;
ALTER TABLE `locations`  ADD COLUMN IF NOT EXISTS `archived_at` DATETIME NULL DEFAULT NULL;

-- 3. Precio estimado para los bonos anteriores a esta versión (el precio real
--    pagado no se guardaba). Se confirman uno a uno en Finanzas › Revisión.
UPDATE `player_bonos` pb
JOIN `bono_types` bt ON bt.id = pb.bono_type_id
SET pb.price_list_cents = ROUND(bt.price * 100),
    pb.price_cents      = ROUND(bt.price * 100),
    pb.price_estimated  = 1
WHERE pb.price_cents IS NULL;

-- 4. Comprobación (debe dar 0 bonos sin precio)
SELECT COUNT(*) AS bonos, SUM(price_estimated) AS estimados,
       SUM(price_cents IS NULL) AS sin_precio, SUM(price_cents) / 100 AS eur
FROM `player_bonos`;

-- 5. Registro de la migración en CodeIgniter (para que `spark migrate` no la repita):
--    (el MAX va en una subconsulta: un agregado sin GROUP BY devuelve siempre
--     una fila y el NOT EXISTS no impediría insertarla dos veces)
INSERT INTO `migrations` (`version`, `class`, `group`, `namespace`, `time`, `batch`)
SELECT '2026-10-10-000001', 'App\\Database\\Migrations\\NadaSeBorra', 'default', 'App',
       UNIX_TIMESTAMP(), nb.next_batch
FROM (SELECT COALESCE(MAX(batch), 0) + 1 AS next_batch FROM `migrations`) nb
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `version` = '2026-10-10-000001');

-- Después de desplegar el código, desde SSH (solo lectura):
--   php spark finanzas:integridad
