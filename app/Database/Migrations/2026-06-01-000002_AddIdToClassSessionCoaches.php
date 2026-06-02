<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

// TiDB does not support ADD COLUMN ... AUTO_INCREMENT via ALTER TABLE.
// The id column was never created on TiDB (it uses _tidb_rowid internally).
// All queries were updated to not reference csc.id — this migration is intentionally a no-op.
class AddIdToClassSessionCoaches extends Migration
{
    public function up(): void {}

    public function down(): void {}
}
