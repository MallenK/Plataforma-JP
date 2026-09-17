<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * TICKET-011 — las notificaciones de cambio de responsable de clase
 * necesitan un tercer origen ('class') además de 'ticket'/'conversation'
 * (TICKET-010, migración 2026-09-16-000001).
 *
 * `source_type` era ENUM('ticket','conversation'): añadir un valor a un
 * ENUM existente es más frágil que necesario (y distinto en sintaxis entre
 * MySQL/MariaDB/TiDB). Se amplía a VARCHAR(20) — mismo comportamiento,
 * admite futuros orígenes sin otra migración de esquema.
 *
 * Si las columnas no existen todavía (entorno sin la 2026-09-16-000001),
 * se crean aquí directamente como VARCHAR.
 *
 * SQL equivalente para phpMyAdmin: docs/deploy/migraciones_notificaciones_clase.sql
 */
class WidenNotificationsSourceType extends Migration
{
    public function up(): void
    {
        $hasType = $this->db->fieldExists('source_type', 'notifications');
        $hasId   = $this->db->fieldExists('source_id', 'notifications');

        if (!$hasType) {
            $this->db->query(
                "ALTER TABLE `notifications`
                 ADD COLUMN `source_type` VARCHAR(20) NULL DEFAULT NULL AFTER `file_size`"
            );
        } else {
            $this->db->query(
                "ALTER TABLE `notifications`
                 MODIFY COLUMN `source_type` VARCHAR(20) NULL DEFAULT NULL"
            );
        }

        if (!$hasId) {
            $this->db->query(
                "ALTER TABLE `notifications`
                 ADD COLUMN `source_id` INT UNSIGNED NULL DEFAULT NULL AFTER `source_type`"
            );
        }
    }

    public function down(): void
    {
        if ($this->db->fieldExists('source_type', 'notifications')) {
            $this->db->query(
                "ALTER TABLE `notifications`
                 MODIFY COLUMN `source_type` ENUM('ticket','conversation') NULL DEFAULT NULL"
            );
        }
    }
}
