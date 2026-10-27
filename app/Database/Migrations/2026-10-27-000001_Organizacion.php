<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Organización interna (TICKET-014): tareas por estados y agenda.
 * due_notified_at / reminder_sent_at: marca de «ya avisado» (recordatorios una sola vez).
 * Nada se borra: archived_at. users.id es INT CON SIGNO → sin FKs UNSIGNED. Idempotente.
 */
class Organizacion extends Migration
{
    public function up(): void
    {
        $this->db->query("CREATE TABLE IF NOT EXISTS `org_tasks` (
            `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `title`         VARCHAR(200) NOT NULL,
            `description`   TEXT NULL,
            `status`        VARCHAR(20) NOT NULL DEFAULT 'pendiente',
            `priority`      VARCHAR(10) NOT NULL DEFAULT 'normal',
            `due_date`      DATE NULL,
            `assigned_to`   INT NULL,
            `created_by`    INT NOT NULL,
            `visibility`    VARCHAR(10) NOT NULL DEFAULT 'team',
            `position`      INT NOT NULL DEFAULT 0,
            `completed_at`  DATETIME NULL,
            `due_notified_at` DATETIME NULL,
            `archived_at`   DATETIME NULL,
            `created_at`    DATETIME NULL,
            `updated_at`    DATETIME NULL,
            PRIMARY KEY (`id`),
            KEY `idx_org_tasks_status` (`status`, `archived_at`),
            KEY `idx_org_tasks_due` (`due_date`),
            KEY `idx_org_tasks_assigned` (`assigned_to`),
            KEY `idx_org_tasks_creator` (`created_by`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $this->db->query("CREATE TABLE IF NOT EXISTS `org_events` (
            `id`            INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `title`         VARCHAR(200) NOT NULL,
            `description`   TEXT NULL,
            `start_at`      DATETIME NOT NULL,
            `end_at`        DATETIME NULL,
            `all_day`       TINYINT(1) NOT NULL DEFAULT 0,
            `location_text` VARCHAR(160) NULL,
            `created_by`    INT NOT NULL,
            `visibility`    VARCHAR(10) NOT NULL DEFAULT 'team',
            `reminder_sent_at` DATETIME NULL,
            `archived_at`   DATETIME NULL,
            `created_at`    DATETIME NULL,
            `updated_at`    DATETIME NULL,
            PRIMARY KEY (`id`),
            KEY `idx_org_events_start` (`start_at`),
            KEY `idx_org_events_creator` (`created_by`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

        $this->db->query("CREATE TABLE IF NOT EXISTS `org_event_users` (
            `event_id` INT UNSIGNED NOT NULL,
            `user_id`  INT NOT NULL,
            PRIMARY KEY (`event_id`, `user_id`),
            KEY `idx_org_event_users_user` (`user_id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    }

    public function down(): void
    {
        $this->db->query('DROP TABLE IF EXISTS `org_event_users`');
        $this->db->query('DROP TABLE IF EXISTS `org_events`');
        $this->db->query('DROP TABLE IF EXISTS `org_tasks`');
    }
}
