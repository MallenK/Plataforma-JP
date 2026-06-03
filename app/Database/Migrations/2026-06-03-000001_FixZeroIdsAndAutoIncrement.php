<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Fix rows with id=0 in tables where TiDB AUTO_INCREMENT
 * was not properly set after SQL dump import.
 *
 * Affected tables:
 *   - player_bonos      (BUG-04/05/06)
 *   - locations         (BUG-11)
 *   - player_profiles   (BUG-01)
 *   - notifications     (BUG-08)
 *
 * For each table:
 *   1. Detect row(s) with id=0
 *   2. Reassign to MAX(id)+1 (updating FK children first)
 *   3. Reset AUTO_INCREMENT to a TiDB-safe value (next batch boundary)
 */
class FixZeroIdsAndAutoIncrement extends Migration
{
    /**
     * Tables to fix and their dependent FK tables.
     *
     * Format:
     *   'table' => [
     *       ['child_table', 'fk_column'],
     *       ...
     *   ]
     */
    private array $tables = [
        'player_bonos'    => [],
        'locations'       => [
            ['class_sessions', 'location_id'],
        ],
        'player_profiles' => [],
        'notifications'   => [
            ['notification_recipients', 'notification_id'],
        ],
    ];

    public function up(): void
    {
        foreach ($this->tables as $table => $fkChildren) {
            $this->fixTable($table, $fkChildren);
        }
    }

    public function down(): void
    {
        // AUTO_INCREMENT changes cannot be meaningfully reversed
    }

    private function fixTable(string $table, array $fkChildren): void
    {
        // Check if a row with id=0 exists
        $zeroCheck = $this->db
            ->query("SELECT COUNT(*) AS cnt FROM `{$table}` WHERE id = 0")
            ->getRow();

        if ((int)($zeroCheck->cnt ?? 0) === 0) {
            // No id=0 rows — just ensure AUTO_INCREMENT is safe
            $this->setAutoIncrement($table);
            return;
        }

        // Calculate new ID for the zero row (MAX excluding 0, then +1)
        $maxRow = $this->db
            ->query("SELECT COALESCE(MAX(id), 0) AS m FROM `{$table}` WHERE id > 0")
            ->getRow();
        $newId = (int)($maxRow->m ?? 0) + 1;

        // Update FK children first (so FK constraints don't block the PK update)
        foreach ($fkChildren as [$childTable, $fkCol]) {
            $this->db->query(
                "UPDATE `{$childTable}` SET `{$fkCol}` = {$newId} WHERE `{$fkCol}` = 0"
            );
        }

        // Reassign the zero row
        $this->db->query(
            "UPDATE `{$table}` SET id = {$newId} WHERE id = 0"
        );

        // Now reset AUTO_INCREMENT to a TiDB-safe value
        $this->setAutoIncrement($table);
    }

    private function setAutoIncrement(string $table): void
    {
        $maxRow = $this->db
            ->query("SELECT COALESCE(MAX(id), 0) AS m FROM `{$table}`")
            ->getRow();
        $max  = (int)($maxRow->m ?? 0);
        // Jump to next 30000 batch boundary + 1 to avoid TiDB pre-allocation collisions
        $next = ((int) ceil(($max + 1) / 30000) + 1) * 30000 + 1;
        $this->db->query("ALTER TABLE `{$table}` AUTO_INCREMENT = {$next}");
    }
}
