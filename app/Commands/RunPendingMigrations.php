<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Runs pending migrations DDL as raw SQL and records them with explicit IDs,
 * bypassing TiDB's broken AUTO_INCREMENT on the migrations table.
 */
class RunPendingMigrations extends BaseCommand
{
    protected $group       = 'Database';
    protected $name        = 'db:run-pending';
    protected $description = 'Run pending migration DDL and record with explicit IDs (TiDB workaround).';

    public function run(array $params): void
    {
        $db      = \Config\Database::connect();
        $runner  = \Config\Services::migrations();
        $history = $runner->getHistory('default');
        $recorded = array_column($history, 'version');

        $maxId    = (int) $db->query("SELECT COALESCE(MAX(id), 0) AS m FROM migrations")->getRow()->m;
        $maxBatch = (int) $db->query("SELECT COALESCE(MAX(batch), 0) AS m FROM migrations")->getRow()->m;
        $nextId   = $maxId + 1;
        $batch    = $maxBatch + 1;
        $now      = time();

        $pending = $this->getPending();

        foreach ($pending as $m) {
            if (in_array($m['version'], $recorded)) {
                CLI::write("  Skip (recorded): {$m['version']}", 'dark_gray');
                continue;
            }

            // Run DDL statements
            foreach ($m['sql'] as $sql) {
                try {
                    $db->query($sql);
                } catch (\Throwable $e) {
                    CLI::write("  DDL warn [{$m['version']}]: " . $e->getMessage(), 'yellow');
                }
            }

            // Record with explicit ID
            $escapedClass = $db->escape($m['class']);
            $insert = "INSERT INTO `migrations` (`id`, `version`, `class`, `group`, `namespace`, `time`, `batch`)
                       VALUES ({$nextId}, '{$m['version']}', {$escapedClass}, 'default', 'App', {$now}, {$batch})";
            try {
                $db->query($insert);
                CLI::write("  Done [{$nextId}] {$m['version']}", 'green');
                $nextId++;
            } catch (\Throwable $e) {
                CLI::write("  Record error [{$m['version']}]: " . $e->getMessage(), 'red');
            }
        }

        CLI::write('Done.', 'green');
    }

    private function getPending(): array
    {
        return [
            [
                'version' => '2026-05-01-000001',
                'class'   => 'App\Database\Migrations\CreatePlayerAnnotations',
                'sql'     => [
                    "CREATE TABLE IF NOT EXISTS `player_annotations` (
                        `id`         INT NOT NULL AUTO_INCREMENT,
                        `player_id`  INT NOT NULL,
                        `author_id`  INT NOT NULL,
                        `type`       ENUM('public','internal') NOT NULL DEFAULT 'public',
                        `content`    TEXT NOT NULL,
                        `created_at` DATETIME NULL,
                        `updated_at` DATETIME NULL,
                        PRIMARY KEY (`id`),
                        KEY `idx_player` (`player_id`),
                        KEY `idx_type` (`type`)
                    )"
                ],
            ],
            [
                'version' => '2026-05-04-000001',
                'class'   => 'App\Database\Migrations\FixUsersRoleEnumAndCleanIds',
                'sql'     => [
                    "DELETE FROM users WHERE id = 0",
                    "ALTER TABLE users MODIFY COLUMN role ENUM('superadmin','admin','staff','coach','player') NOT NULL DEFAULT 'player'",
                    "UPDATE users SET role = 'staff' WHERE role = ''",
                ],
            ],
            [
                'version' => '2026-05-06-000001',
                'class'   => 'App\Database\Migrations\AddStaffTitleToUsers',
                'sql'     => [
                    "ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `staff_title` VARCHAR(100) NULL DEFAULT NULL AFTER `role`",
                ],
            ],
            [
                'version' => '2026-05-06-000002',
                'class'   => 'App\Database\Migrations\AddWelcomedAtToUsers',
                'sql'     => [
                    "ALTER TABLE `users` ADD COLUMN IF NOT EXISTS `welcomed_at` DATETIME NULL DEFAULT NULL AFTER `updated_at`",
                ],
            ],
            [
                'version' => '2026-05-24-000001',
                'class'   => 'App\Database\Migrations\AddCategoryTeamLeagueToPlayerProfiles',
                'sql'     => [
                    "ALTER TABLE `player_profiles` ADD COLUMN IF NOT EXISTS `category` ENUM('benjamin','prebenjamin','alevin','infantil','cadete','juvenil','junior','senior','veterano') NULL DEFAULT NULL AFTER `level`",
                    "ALTER TABLE `player_profiles` ADD COLUMN IF NOT EXISTS `team` VARCHAR(120) NULL DEFAULT NULL AFTER `category`",
                    "ALTER TABLE `player_profiles` ADD COLUMN IF NOT EXISTS `league` VARCHAR(120) NULL DEFAULT NULL AFTER `team`",
                ],
            ],
            [
                'version' => '2026-05-24-000002',
                'class'   => 'App\Database\Migrations\AddAbsenceFieldsToClassSessionPlayers',
                'sql'     => [
                    "ALTER TABLE `class_session_players` ADD COLUMN IF NOT EXISTS `absence_reason` TEXT NULL DEFAULT NULL AFTER `attendance`",
                    "ALTER TABLE `class_session_players` ADD COLUMN IF NOT EXISTS `student_note` TEXT NULL DEFAULT NULL AFTER `absence_reason`",
                    "ALTER TABLE `class_session_players` ADD COLUMN IF NOT EXISTS `student_noted_at` DATETIME NULL DEFAULT NULL AFTER `student_note`",
                ],
            ],
            [
                'version' => '2026-05-24-000003',
                'class'   => 'App\Database\Migrations\AddDocumentIdToPlayerAnnotations',
                'sql'     => [
                    "ALTER TABLE `player_annotations` ADD COLUMN IF NOT EXISTS `document_id` INT UNSIGNED NULL DEFAULT NULL AFTER `content`",
                ],
            ],
            [
                'version' => '2026-06-01-000001',
                'class'   => 'App\Database\Migrations\FixAutoIncrementOnChatTables',
                'sql'     => [], // Handled separately by db:fix-autoincrement
            ],
            [
                'version' => '2026-06-01-000002',
                'class'   => 'App\Database\Migrations\AddIdToClassSessionCoaches',
                'sql'     => [], // No-op — TiDB cannot add AUTO_INCREMENT column
            ],
        ];
    }
}
