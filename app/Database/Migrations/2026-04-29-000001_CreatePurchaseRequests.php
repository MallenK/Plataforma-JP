<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreatePurchaseRequests extends Migration
{
    public function up(): void
    {
        $this->forge->addField([
            'id'            => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'name'          => ['type' => 'VARCHAR', 'constraint' => 200],
            'description'   => ['type' => 'TEXT', 'null' => true],
            'url'           => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'price'         => ['type' => 'DECIMAL', 'constraint' => '10,2', 'unsigned' => true, 'null' => true],
            'category'      => [
                'type'       => 'ENUM',
                'constraint' => ['equipamiento', 'tecnologia', 'material_deportivo', 'instalaciones', 'oficina', 'otros'],
                'default'    => 'otros',
            ],
            'priority'      => [
                'type'       => 'ENUM',
                'constraint' => ['alta', 'media', 'baja'],
                'default'    => 'media',
            ],
            'status'        => [
                'type'       => 'ENUM',
                'constraint' => ['pendiente', 'en_revision', 'aprobado', 'denegado', 'comprado', 'cancelado'],
                'default'    => 'pendiente',
            ],
            'admin_comment' => ['type' => 'TEXT', 'null' => true],
            'requested_by'  => ['type' => 'INT'],
            'reviewed_by'   => ['type' => 'INT', 'null' => true],
            'reviewed_at'   => ['type' => 'DATETIME', 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);

        $this->forge->addKey('id', true);
        $this->forge->addKey('status');
        $this->forge->addKey('priority');
        $this->forge->addKey('category');
        $this->forge->addKey('requested_by');

        $this->forge->addForeignKey('requested_by', 'users', 'id', 'CASCADE', 'CASCADE');
        $this->forge->addForeignKey('reviewed_by', 'users', 'id', 'SET NULL', 'CASCADE');

        $this->forge->createTable('purchase_requests', true);
    }

    public function down(): void
    {
        $this->forge->dropTable('purchase_requests', true);
    }
}
