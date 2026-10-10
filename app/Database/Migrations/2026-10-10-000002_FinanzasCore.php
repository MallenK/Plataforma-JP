<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Finanzas 2.0 — núcleo económico.
 *
 *  - fin_payment_methods: medios de pago (configurables).
 *  - fin_categories:      categorías de ingreso y de gasto (configurables).
 *  - fin_charges:         cargos al alumno (lo que debe: un bono vendido, una
 *                         sesión suelta, un cargo manual), con descuento.
 *  - fin_payments:        cobros (dinero que entra), con su medio de pago.
 *  - fin_payment_allocations: qué cobro paga qué cargo (pagos parciales y a plazos).
 *  - fin_expenses:        gastos, asignables a un entrenador, una sede o un concepto.
 *
 * Nada se borra: todas llevan anulación (`voided_*`). Importes en céntimos.
 * `users.id` es INT CON SIGNO → columnas de usuario sin UNSIGNED.
 *
 * Arranque: cada bono asignado y no anulado recibe su cargo (precio congelado)
 * y su cobro por el mismo importe con medio «Sin especificar» (decisión del
 * responsable: lo anterior se da por pagado). Idempotente.
 * SQL de prod: docs/deploy/migraciones_finanzas_2.0.sql
 */
class FinanzasCore extends Migration
{
    public function up(): void
    {
        $opts = "ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci";

        $this->db->query("CREATE TABLE IF NOT EXISTS `fin_payment_methods` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `code` VARCHAR(30) NOT NULL,
            `name` VARCHAR(60) NOT NULL,
            `sort` SMALLINT NOT NULL DEFAULT 0,
            `active` TINYINT(1) NOT NULL DEFAULT 1,
            `archived_at` DATETIME NULL DEFAULT NULL,
            `created_at` DATETIME NULL DEFAULT NULL,
            PRIMARY KEY (`id`), UNIQUE KEY `uq_fpm_code` (`code`)
        ) {$opts}");

        $this->db->query("CREATE TABLE IF NOT EXISTS `fin_categories` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `kind` VARCHAR(10) NOT NULL,
            `name` VARCHAR(80) NOT NULL,
            `sort` SMALLINT NOT NULL DEFAULT 0,
            `active` TINYINT(1) NOT NULL DEFAULT 1,
            `archived_at` DATETIME NULL DEFAULT NULL,
            `created_at` DATETIME NULL DEFAULT NULL,
            PRIMARY KEY (`id`), KEY `idx_fc_kind` (`kind`)
        ) {$opts}");

        $this->db->query("CREATE TABLE IF NOT EXISTS `fin_charges` (
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
        ) {$opts}");

        $this->db->query("CREATE TABLE IF NOT EXISTS `fin_payments` (
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
        ) {$opts}");

        $this->db->query("CREATE TABLE IF NOT EXISTS `fin_payment_allocations` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `payment_id` INT UNSIGNED NOT NULL,
            `charge_id` INT UNSIGNED NOT NULL,
            `amount_cents` INT NOT NULL,
            `created_at` DATETIME NOT NULL,
            PRIMARY KEY (`id`),
            KEY `idx_fal_payment` (`payment_id`),
            KEY `idx_fal_charge` (`charge_id`)
        ) {$opts}");

        $this->db->query("CREATE TABLE IF NOT EXISTS `fin_expenses` (
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
        ) {$opts}");

        $now = date('Y-m-d H:i:s');

        // Medios de pago
        foreach ([['efectivo', 'Efectivo', 10], ['bizum', 'Bizum', 20], ['transferencia', 'Transferencia', 30],
                  ['tarjeta', 'Tarjeta', 40], ['otro', 'Otro', 50], ['sin_especificar', 'Sin especificar', 90]] as [$code, $name, $sort]) {
            if (!$this->db->table('fin_payment_methods')->where('code', $code)->countAllResults()) {
                $this->db->table('fin_payment_methods')->insert(['code' => $code, 'name' => $name, 'sort' => $sort, 'created_at' => $now]);
            }
        }

        // Categorías
        $cats = [
            ['income',  'Bonos', 10], ['income', 'Sesión suelta', 20], ['income', 'Otros ingresos', 90],
            ['expense', 'Entrenadores', 10], ['expense', 'Material', 20], ['expense', 'Reparaciones', 30],
            ['expense', 'Alquiler de campos', 40], ['expense', 'Suministros', 50], ['expense', 'Marketing', 60],
            ['expense', 'Otros gastos', 90],
        ];
        foreach ($cats as [$kind, $name, $sort]) {
            if (!$this->db->table('fin_categories')->where('kind', $kind)->where('name', $name)->countAllResults()) {
                $this->db->table('fin_categories')->insert(['kind' => $kind, 'name' => $name, 'sort' => $sort, 'created_at' => $now]);
            }
        }

        // Ajustes
        foreach (['fin_notice_hours' => '24', 'fin_auto_deduct' => '1', 'fin_tax_enabled' => '0'] as $k => $v) {
            if (!$this->db->table('academy_settings')->where('setting_key', $k)->countAllResults()) {
                $this->db->table('academy_settings')->insert(['setting_key' => $k, 'setting_value' => $v, 'setting_type' => 'string', 'updated_at' => $now]);
            }
        }

        $this->backfill($now);
    }

    /** Cargo + cobro «sin especificar» para cada bono asignado que aún no tiene cargo. */
    private function backfill(string $now): void
    {
        if (!$this->db->fieldExists('price_cents', 'player_bonos')) {
            return; // falta la migración «Nada se borra»
        }
        $catBonos = $this->db->table('fin_categories')->select('id')->where('kind', 'income')->where('name', 'Bonos')->get()->getRow();
        $method   = $this->db->table('fin_payment_methods')->select('id')->where('code', 'sin_especificar')->get()->getRow();

        $rows = $this->db->query(
            "SELECT pb.id, pb.player_id, pb.price_list_cents, pb.price_cents, pb.created_at, pb.created_by, bt.name
             FROM player_bonos pb
             JOIN bono_types bt ON bt.id = pb.bono_type_id
             LEFT JOIN fin_charges fc ON fc.bono_id = pb.id
             WHERE pb.player_id IS NOT NULL AND pb.voided_at IS NULL AND fc.id IS NULL"
        )->getResultArray();

        foreach ($rows as $r) {
            $amount = (int) ($r['price_cents'] ?? 0);
            $date   = substr((string) $r['created_at'], 0, 10) ?: date('Y-m-d');
            $this->db->table('fin_charges')->insert([
                'player_id'    => (int) $r['player_id'],
                'bono_id'      => (int) $r['id'],
                'category_id'  => $catBonos->id ?? null,
                'concept'      => $r['name'],
                'list_cents'   => (int) ($r['price_list_cents'] ?? $amount),
                'amount_cents' => $amount,
                'charged_at'   => $date,
                'note'         => 'Arranque de Finanzas',
                'created_by'   => $r['created_by'] ?: null,
                'created_at'   => $now,
            ]);
            $chargeId = (int) $this->db->insertID();
            if ($amount <= 0) {
                continue;
            }
            $this->db->table('fin_payments')->insert([
                'player_id'    => (int) $r['player_id'],
                'method_id'    => $method->id ?? null,
                'amount_cents' => $amount,
                'paid_at'      => $date,
                'note'         => 'Cobro anterior a Finanzas (medio sin especificar)',
                'created_by'   => $r['created_by'] ?: null,
                'created_at'   => $now,
            ]);
            $this->db->table('fin_payment_allocations')->insert([
                'payment_id'   => (int) $this->db->insertID(),
                'charge_id'    => $chargeId,
                'amount_cents' => $amount,
                'created_at'   => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Sin bajada destructiva: es histórico económico.
    }
}
