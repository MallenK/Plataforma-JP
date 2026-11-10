<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Externos (Fase 2): el dinero de una clase de prueba / suelta.
 *
 *  - class_session_guests.price_cents : lo que cuesta ESA clase (NULL = sin fijar, 0 = gratis).
 *  - class_guest_payments             : cobros de esa clase (puede haber varios, parciales o con
 *                                       medios distintos). Nunca se borran: se ANULAN (voided_*).
 *
 * Los externos no tienen cuenta, así que NO usan fin_charges / fin_payments (cuentan por alumno);
 * los informes de Finanzas los suman aparte. Idempotente. Sin FKs ni cascadas.
 */
class ClassGuestMoney extends Migration
{
    public function up(): void
    {
        if ($this->db->tableExists('class_session_guests') && !$this->db->fieldExists('price_cents', 'class_session_guests')) {
            $this->db->query("ALTER TABLE `class_session_guests` ADD COLUMN `price_cents` INT NULL DEFAULT NULL AFTER `attendance`");
        }

        if (!$this->db->tableExists('class_guest_payments')) {
            $this->db->query("CREATE TABLE `class_guest_payments` (
                `id`               INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `session_guest_id` INT UNSIGNED NOT NULL,
                `method_id`        INT UNSIGNED NULL DEFAULT NULL,
                `amount_cents`     INT          NOT NULL,
                `paid_at`          DATE         NOT NULL,
                `note`             VARCHAR(255) NULL DEFAULT NULL,
                `voided_at`        DATETIME     NULL DEFAULT NULL,
                `voided_by`        INT          NULL DEFAULT NULL,
                `void_reason`      VARCHAR(255) NULL DEFAULT NULL,
                `created_by`       INT          NULL DEFAULT NULL,
                `created_at`       DATETIME     NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_cgp_link` (`session_guest_id`),
                KEY `idx_cgp_paid` (`paid_at`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
    }

    public function down(): void
    {
        // Sin DROP: «nada se borra».
    }
}
