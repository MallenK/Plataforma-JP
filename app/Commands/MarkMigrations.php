<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Marks all pending migrations as "run" by inserting records with explicit IDs.
 * Bypasses auto_increment issues on TiDB.
 */
class MarkMigrations extends BaseCommand
{
    protected $group       = 'Database';
    protected $name        = 'db:mark-migrations';
    protected $description = 'Record pending CI4 migrations without running DDL (for TiDB auto_increment workaround).';

    public function run(array $params): void
    {
        $db      = \Config\Database::connect();
        $runner  = \Config\Services::migrations();

        // Get all migration files from the App namespace
        $migrations = $runner->findMigrations();
        $history    = $runner->getHistory('default');
        $recorded   = array_column($history, 'class');

        // Determine the next explicit id to use
        $maxId = (int) $db->query("SELECT COALESCE(MAX(id), 0) AS m FROM migrations")->getRow()->m;
        $nextId = $maxId + 1;

        // Determine next batch number
        $maxBatch = (int) $db->query("SELECT COALESCE(MAX(batch), 0) AS m FROM migrations")->getRow()->m;
        $batch    = $maxBatch + 1;

        $now = time();
        $count = 0;

        foreach ($migrations as $migration) {
            $class = ltrim($migration->class, '\\');

            // Already recorded?
            if (in_array($class, $recorded) || in_array('\\' . $class, $recorded)) {
                CLI::write("  Already recorded: {$class}", 'dark_gray');
                continue;
            }

            // Insert with explicit ID to bypass auto_increment
            $sql = "INSERT INTO `migrations` (`id`, `version`, `class`, `group`, `namespace`, `time`, `batch`)
                    VALUES ({$nextId}, '{$migration->version}', " . $db->escape($class) . ", 'default', 'App', {$now}, {$batch})";

            try {
                $db->query($sql);
                CLI::write("  Marked [{$nextId}] {$migration->version} {$class}", 'green');
                $nextId++;
                $count++;
            } catch (\Throwable $e) {
                CLI::write("  Error on {$class}: " . $e->getMessage(), 'red');
            }
        }

        CLI::write("Done. Marked {$count} migrations.", 'green');
    }
}
