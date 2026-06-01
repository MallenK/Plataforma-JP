<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class ShowMigrations extends BaseCommand
{
    protected $group       = 'Database';
    protected $name        = 'db:show-migrations';
    protected $description = 'Show recorded migrations and pending ones.';

    public function run(array $params): void
    {
        $db   = \Config\Database::connect();
        $rows = $db->query("SELECT id, version, class, batch FROM migrations ORDER BY id")->getResultArray();

        CLI::write("=== Recorded migrations ===", 'yellow');
        foreach ($rows as $r) {
            CLI::write("  [{$r['id']}] batch={$r['batch']} {$r['version']} {$r['class']}");
        }
        CLI::write("Max id: " . (empty($rows) ? 0 : end($rows)['id']), 'cyan');
        CLI::write("Total: " . count($rows), 'cyan');
    }
}
