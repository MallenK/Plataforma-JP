<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddStaffTitleToUsers extends Migration
{
    public function up(): void
    {
        if (!$this->db->fieldExists('staff_title', 'users')) {
            $this->forge->addColumn('users', [
                'staff_title' => [
                    'type'       => 'VARCHAR',
                    'constraint' => 100,
                    'null'       => true,
                    'default'    => null,
                    'after'      => 'role',
                    'comment'    => 'Cargo / puesto específico del staff (ej: Director técnico, Recepción)',
                ],
            ]);
        }
    }

    public function down(): void
    {
        if ($this->db->fieldExists('staff_title', 'users')) {
            $this->forge->dropColumn('users', 'staff_title');
        }
    }
}
