<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddSourceToNotifications extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('notifications', [
            'source_type' => [
                'type'       => 'ENUM',
                'constraint' => ['ticket', 'conversation'],
                'null'       => true,
                'default'    => null,
                'after'      => 'file_size',
            ],
            'source_id' => [
                'type'       => 'INT',
                'unsigned'   => true,
                'null'       => true,
                'default'    => null,
                'after'      => 'source_type',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('notifications', ['source_type', 'source_id']);
    }
}
