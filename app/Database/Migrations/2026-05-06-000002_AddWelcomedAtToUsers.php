<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddWelcomedAtToUsers extends Migration
{
    public function up()
    {
        $this->forge->addColumn('users', [
            'welcomed_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'default' => null,
                'after'   => 'updated_at',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('users', 'welcomed_at');
    }
}
