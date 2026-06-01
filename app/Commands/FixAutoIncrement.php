<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class FixAutoIncrement extends BaseCommand
{
    protected $group       = 'Database';
    protected $name        = 'db:fix-autoincrement';
    protected $description = 'Fix broken AUTO_INCREMENT counters on all tables (TiDB).';

    public function run(array $params): void
    {
        $db     = \Config\Database::connect();
        $tables = $db->query("SHOW TABLES")->getResultArray();
        $key    = 'Tables_in_' . $db->getDatabase();

        foreach ($tables as $row) {
            $table = $row[$key] ?? array_values($row)[0];

            $cols = $db->query("SHOW COLUMNS FROM `{$table}` LIKE 'id'")->getResultArray();
            if (empty($cols)) {
                CLI::write("  Skipped `{$table}` (no id column)", 'yellow');
                continue;
            }

            try {
                $maxRow = $db->query("SELECT COALESCE(MAX(id), 0) AS n FROM `{$table}`")->getRow();
                // TiDB allocates IDs in batches of 30000. Jump past the current batch
                // so the next INSERT doesn't collide with existing rows.
                $next   = ((int) ceil(((int) $maxRow->n + 1) / 30000) + 1) * 30000 + 1;

                $db->query("ALTER TABLE `{$table}` AUTO_INCREMENT = {$next}");

                CLI::write("  Fixed `{$table}` → AUTO_INCREMENT = {$next}", 'green');
            } catch (\Throwable $e) {
                CLI::write("  Error on `{$table}`: " . $e->getMessage(), 'red');
            }
        }

        CLI::write('Done.', 'green');
    }
}
