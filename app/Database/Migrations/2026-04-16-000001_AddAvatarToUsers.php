<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddAvatarToUsers extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('users', [
            'avatar' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
                'null'       => true,
                'default'    => null,
                'after'      => 'password',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('users', 'avatar');
    }
}
