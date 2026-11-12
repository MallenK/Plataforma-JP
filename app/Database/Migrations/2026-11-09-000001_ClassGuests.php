<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Externos (Fase 1): personas que reciben una clase SIN estar registradas como alumnos
 * (clase de prueba / primera vez, clase suelta pagada).
 *
 *  - class_guests          → la ficha ligera (sin cuenta ni contraseña). Una persona = una fila,
 *                            aunque venga a varias clases.
 *  - class_session_guests  → su participación en una clase concreta (tipo + asistencia).
 *
 * A propósito NO se toca class_session_players: de ella cuelgan bonos, descuento automático,
 * historial y Finanzas, y casi todo hace JOIN con users. Los externos viven aparte.
 * Sin FKs ni cascadas: «nada se borra». users.id es INT CON SIGNO, por eso created_by/coach_id
 * van sin UNSIGNED. Idempotente.
 */
class ClassGuests extends Migration
{
    public function up(): void
    {
        if (!$this->db->tableExists('class_guests')) {
            $this->db->query("CREATE TABLE `class_guests` (
                `id`                INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `first_name`        VARCHAR(100) NOT NULL,
                `last_name`         VARCHAR(100) NULL DEFAULT NULL,
                `phone`             VARCHAR(30)  NULL DEFAULT NULL,
                `phone_key`         VARCHAR(30)  NULL DEFAULT NULL,
                `email`             VARCHAR(150) NULL DEFAULT NULL,
                `birth_date`        DATE         NULL DEFAULT NULL,
                `guardian_name`     VARCHAR(150) NULL DEFAULT NULL,
                `guardian_phone`    VARCHAR(30)  NULL DEFAULT NULL,
                `image_rights`      TINYINT(1)   NOT NULL DEFAULT 0,
                `notes`             TEXT         NULL,
                `converted_user_id` INT          NULL DEFAULT NULL,
                `created_by`        INT          NULL DEFAULT NULL,
                `archived_at`       DATETIME     NULL DEFAULT NULL,
                `created_at`        DATETIME     NULL DEFAULT NULL,
                `updated_at`        DATETIME     NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                KEY `idx_cg_phone` (`phone_key`),
                KEY `idx_cg_email` (`email`),
                KEY `idx_cg_converted` (`converted_user_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }

        if (!$this->db->tableExists('class_session_guests')) {
            $this->db->query("CREATE TABLE `class_session_guests` (
                `id`          INT UNSIGNED NOT NULL AUTO_INCREMENT,
                `session_id`  INT UNSIGNED NOT NULL,
                `guest_id`    INT UNSIGNED NOT NULL,
                `kind`        ENUM('trial','single') NOT NULL DEFAULT 'trial',
                `attendance`  ENUM('pending','present','absent') NOT NULL DEFAULT 'pending',
                `coach_id`    INT          NULL DEFAULT NULL,
                `notes`       TEXT         NULL,
                `created_by`  INT          NULL DEFAULT NULL,
                `created_at`  DATETIME     NULL DEFAULT NULL,
                `updated_at`  DATETIME     NULL DEFAULT NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uq_csg` (`session_id`, `guest_id`),
                KEY `idx_csg_guest` (`guest_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        }
    }

    public function down(): void
    {
        // Sin DROP: «nada se borra». Si hay que revertir, hacerlo a mano y con copia.
    }
}
