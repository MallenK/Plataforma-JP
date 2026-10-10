-- ─────────────────────────────────────────────────────────────────────────────
-- Finanzas 2.0 · migración 2026-10-10-000002 (FinanzasCore)
--
-- NO ejecutar todavía en producción: la integración se decidirá más adelante.
-- ORDEN al desplegar la 2.0.0 en prod (phpMyAdmin / SSH):
--   1. docs/deploy/migraciones_nada_se_borra.sql           (v1.33.0)
--   2. docs/migraciones/2026-10-05_push_pwa_notificaciones.sql (PWA)
--   3. ESTE fichero
--   4. Subir el código
--   5. php spark finanzas:cierre-inicial            (prueba en seco)
--      php spark finanzas:cierre-inicial --aplicar  (o botón en Finanzas › Revisión)
--   6. php spark finanzas:integridad                (solo lectura)
--
-- Prod = MariaDB 11.8. Idempotente: se puede ejecutar dos veces sin duplicar nada.
-- Solo AÑADE tablas y filas. users.id es INT con signo.
-- Arranque: cada bono asignado y no anulado recibe su cargo (precio congelado)
-- y un cobro por el mismo importe con medio «Sin especificar» (decisión del
-- responsable: lo anterior se da por pagado).
-- ─────────────────────────────────────────────────────────────────────────────

CREATE TABLE IF NOT EXISTS `fin_payment_methods` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `code` VARCHAR(30) NOT NULL,
    `name` VARCHAR(60) NOT NULL,
    `sort` SMALLINT NOT NULL DEFAULT 0,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `archived_at` DATETIME NULL DEFAULT NULL,
    `created_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`), UNIQUE KEY `uq_fpm_code` (`code`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fin_categories` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `kind` VARCHAR(10) NOT NULL,
    `name` VARCHAR(80) NOT NULL,
    `sort` SMALLINT NOT NULL DEFAULT 0,
    `active` TINYINT(1) NOT NULL DEFAULT 1,
    `archived_at` DATETIME NULL DEFAULT NULL,
    `created_at` DATETIME NULL DEFAULT NULL,
    PRIMARY KEY (`id`), KEY `idx_fc_kind` (`kind`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fin_charges` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `player_id` INT NOT NULL,
    `bono_id` INT UNSIGNED NULL DEFAULT NULL,
    `category_id` INT UNSIGNED NULL DEFAULT NULL,
    `concept` VARCHAR(160) NOT NULL,
    `list_cents` INT NOT NULL DEFAULT 0,
    `discount_cents` INT NOT NULL DEFAULT 0,
    `discount_reason` VARCHAR(120) NULL DEFAULT NULL,
    `amount_cents` INT NOT NULL,
    `charged_at` DATE NOT NULL,
    `estimated` TINYINT(1) NOT NULL DEFAULT 0,
    `note` VARCHAR(255) NULL DEFAULT NULL,
    `voided_at` DATETIME NULL DEFAULT NULL,
    `voided_by` INT NULL DEFAULT NULL,
    `void_reason` VARCHAR(255) NULL DEFAULT NULL,
    `created_by` INT NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_fch_player` (`player_id`, `charged_at`),
    KEY `idx_fch_bono` (`bono_id`),
    KEY `idx_fch_date` (`charged_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fin_payments` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `player_id` INT NOT NULL,
    `method_id` INT UNSIGNED NULL DEFAULT NULL,
    `amount_cents` INT NOT NULL,
    `paid_at` DATE NOT NULL,
    `reference` VARCHAR(120) NULL DEFAULT NULL,
    `note` VARCHAR(255) NULL DEFAULT NULL,
    `estimated` TINYINT(1) NOT NULL DEFAULT 0,
    `voided_at` DATETIME NULL DEFAULT NULL,
    `voided_by` INT NULL DEFAULT NULL,
    `void_reason` VARCHAR(255) NULL DEFAULT NULL,
    `created_by` INT NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_fpa_player` (`player_id`, `paid_at`),
    KEY `idx_fpa_date` (`paid_at`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fin_payment_allocations` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `payment_id` INT UNSIGNED NOT NULL,
    `charge_id` INT UNSIGNED NOT NULL,
    `amount_cents` INT NOT NULL,
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_fal_payment` (`payment_id`),
    KEY `idx_fal_charge` (`charge_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS `fin_expenses` (
    `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
    `category_id` INT UNSIGNED NULL DEFAULT NULL,
    `amount_cents` INT NOT NULL,
    `spent_at` DATE NOT NULL,
    `method_id` INT UNSIGNED NULL DEFAULT NULL,
    `supplier` VARCHAR(120) NULL DEFAULT NULL,
    `description` VARCHAR(255) NULL DEFAULT NULL,
    `staff_id` INT NULL DEFAULT NULL,
    `location_id` INT NULL DEFAULT NULL,
    `attachment_path` VARCHAR(500) NULL DEFAULT NULL,
    `attachment_name` VARCHAR(255) NULL DEFAULT NULL,
    `voided_at` DATETIME NULL DEFAULT NULL,
    `voided_by` INT NULL DEFAULT NULL,
    `void_reason` VARCHAR(255) NULL DEFAULT NULL,
    `created_by` INT NULL DEFAULT NULL,
    `created_at` DATETIME NOT NULL,
    PRIMARY KEY (`id`),
    KEY `idx_fex_date` (`spent_at`),
    KEY `idx_fex_staff` (`staff_id`),
    KEY `idx_fex_cat` (`category_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Medios de pago (no duplica por `code`)
INSERT IGNORE INTO `fin_payment_methods` (`code`, `name`, `sort`, `created_at`) VALUES
    ('efectivo', 'Efectivo', 10, NOW()), ('bizum', 'Bizum', 20, NOW()), ('transferencia', 'Transferencia', 30, NOW()),
    ('tarjeta', 'Tarjeta', 40, NOW()), ('otro', 'Otro', 50, NOW()), ('sin_especificar', 'Sin especificar', 90, NOW());

-- Categorías (no duplica por tipo + nombre)
INSERT INTO `fin_categories` (`kind`, `name`, `sort`, `created_at`)
SELECT v.kind, v.name, v.sort, NOW() FROM (
    SELECT 'income' kind, 'Bonos' name, 10 sort UNION ALL SELECT 'income', 'Sesión suelta', 20
    UNION ALL SELECT 'income', 'Otros ingresos', 90 UNION ALL SELECT 'expense', 'Entrenadores', 10
    UNION ALL SELECT 'expense', 'Material', 20 UNION ALL SELECT 'expense', 'Reparaciones', 30
    UNION ALL SELECT 'expense', 'Alquiler de campos', 40 UNION ALL SELECT 'expense', 'Suministros', 50
    UNION ALL SELECT 'expense', 'Marketing', 60 UNION ALL SELECT 'expense', 'Otros gastos', 90
) v
WHERE NOT EXISTS (SELECT 1 FROM `fin_categories` c WHERE c.kind = v.kind AND c.name = v.name);

-- Ajustes (regla de descuento automático activa, 24 h de aviso, IVA apagado)
INSERT INTO `academy_settings` (`setting_key`, `setting_value`, `setting_type`, `updated_at`)
SELECT v.k, v.val, 'string', NOW() FROM (
    SELECT 'fin_notice_hours' k, '24' val UNION ALL SELECT 'fin_auto_deduct', '1' UNION ALL SELECT 'fin_tax_enabled', '0'
) v
WHERE NOT EXISTS (SELECT 1 FROM `academy_settings` s WHERE s.setting_key = v.k);

-- Arranque 1/3: cargo por cada bono asignado, no anulado y sin cargo
INSERT INTO `fin_charges` (`player_id`, `bono_id`, `category_id`, `concept`, `list_cents`, `amount_cents`, `charged_at`, `note`, `created_by`, `created_at`)
SELECT pb.player_id, pb.id,
       (SELECT id FROM fin_categories WHERE kind = 'income' AND name = 'Bonos' LIMIT 1),
       bt.name, COALESCE(pb.price_list_cents, pb.price_cents, 0), COALESCE(pb.price_cents, 0),
       DATE(pb.created_at), 'Arranque de Finanzas', pb.created_by, NOW()
FROM player_bonos pb
JOIN bono_types bt ON bt.id = pb.bono_type_id
WHERE pb.player_id IS NOT NULL AND pb.voided_at IS NULL
  AND NOT EXISTS (SELECT 1 FROM fin_charges fc WHERE fc.bono_id = pb.id);

-- Arranque 2/3: cobro «Sin especificar» por cada cargo de arranque aún sin cobro
INSERT INTO `fin_payments` (`player_id`, `method_id`, `amount_cents`, `paid_at`, `reference`, `note`, `created_by`, `created_at`)
SELECT c.player_id, (SELECT id FROM fin_payment_methods WHERE code = 'sin_especificar' LIMIT 1),
       c.amount_cents, c.charged_at, CONCAT('arranque:', c.id),
       'Cobro anterior a Finanzas (medio sin especificar)', c.created_by, NOW()
FROM fin_charges c
WHERE c.note = 'Arranque de Finanzas' AND c.voided_at IS NULL AND c.amount_cents > 0
  AND NOT EXISTS (SELECT 1 FROM fin_payment_allocations a WHERE a.charge_id = c.id)
  AND NOT EXISTS (SELECT 1 FROM fin_payments p WHERE p.reference = CONCAT('arranque:', c.id));

-- Arranque 3/3: cada cobro de arranque paga su cargo
INSERT INTO `fin_payment_allocations` (`payment_id`, `charge_id`, `amount_cents`, `created_at`)
SELECT p.id, c.id, p.amount_cents, NOW()
FROM fin_payments p
JOIN fin_charges c ON p.reference = CONCAT('arranque:', c.id)
WHERE NOT EXISTS (SELECT 1 FROM fin_payment_allocations a WHERE a.payment_id = p.id);

-- Registro de la migración (para que `spark migrate` no la repita)
INSERT INTO `migrations` (`version`, `class`, `group`, `namespace`, `time`, `batch`)
SELECT '2026-10-10-000002', 'App\\Database\\Migrations\\FinanzasCore', 'default', 'App', UNIX_TIMESTAMP(), nb.next_batch
FROM (SELECT COALESCE(MAX(batch), 0) + 1 AS next_batch FROM `migrations`) nb
WHERE NOT EXISTS (SELECT 1 FROM `migrations` WHERE `version` = '2026-10-10-000002');

-- Comprobación: cargos = cobros = bonos asignados no anulados, mismo importe
SELECT (SELECT COUNT(*) FROM fin_charges WHERE voided_at IS NULL) AS cargos,
       (SELECT SUM(amount_cents) / 100 FROM fin_charges WHERE voided_at IS NULL) AS eur_cargos,
       (SELECT COUNT(*) FROM fin_payments WHERE voided_at IS NULL) AS cobros,
       (SELECT SUM(amount_cents) / 100 FROM fin_payments WHERE voided_at IS NULL) AS eur_cobros,
       (SELECT COUNT(*) FROM player_bonos WHERE player_id IS NOT NULL AND voided_at IS NULL) AS bonos;
