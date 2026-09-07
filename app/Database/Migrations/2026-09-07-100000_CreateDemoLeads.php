<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Contactos dejados desde el formulario del login de la DEMO.
 *
 * Solo se usa en el entorno demo (App\Controllers\DemoController::contact).
 * La tabla se conserva en el reset nocturno (ver DemoResetService::KEEP)
 * para no perder los leads.
 */
class CreateDemoLeads extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'BIGINT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
            ],
            'email' => [
                'type'       => 'VARCHAR',
                'constraint' => 191,
            ],
            'company' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
                'null'       => true,
            ],
            'message' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'meta' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'emailed' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
            ],
            'created_at' => [
                'type' => 'DATETIME',
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('created_at', false, false, 'idx_created');
        $this->forge->createTable('demo_leads', true);
    }

    public function down()
    {
        $this->forge->dropTable('demo_leads');
    }
}
