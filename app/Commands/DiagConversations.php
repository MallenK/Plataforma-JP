<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

class DiagConversations extends BaseCommand
{
    protected $group       = 'Database';
    protected $name        = 'db:diag-conversations';
    protected $description = 'Diagnose conversations table state.';

    public function run(array $params): void
    {
        $db = \Config\Database::connect();

        // 1. Current rows
        CLI::write("=== Rows in conversations ===", 'yellow');
        $rows = $db->query("SELECT id, user1_id, user2_id FROM conversations ORDER BY id")->getResultArray();
        foreach ($rows as $r) {
            CLI::write("  id={$r['id']} user1={$r['user1_id']} user2={$r['user2_id']}");
        }

        // 2. AUTO_INCREMENT from information_schema
        CLI::write("", 'white');
        CLI::write("=== AUTO_INCREMENT from information_schema ===", 'yellow');
        $ai = $db->query("SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'conversations'")->getRow();
        CLI::write("  AUTO_INCREMENT = " . ($ai->AUTO_INCREMENT ?? 'NULL'));

        // 3. SHOW CREATE TABLE
        CLI::write("", 'white');
        CLI::write("=== Table structure ===", 'yellow');
        $ct = $db->query("SHOW CREATE TABLE conversations")->getRow();
        CLI::write($ct->{'Create Table'} ?? 'N/A');

        // 4. Messages table structure
        CLI::write("", 'white');
        CLI::write("=== Messages table structure ===", 'yellow');
        $mt = $db->query("SHOW CREATE TABLE messages")->getRow();
        CLI::write($mt->{'Create Table'} ?? 'N/A');
        $mi = $db->query("SELECT AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'messages'")->getRow();
        CLI::write("  messages AUTO_INCREMENT = " . ($mi->AUTO_INCREMENT ?? 'NULL'));
    }
}
