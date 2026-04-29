<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDocumentFolders extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'name' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
            ],
            'slug' => [
                'type'       => 'VARCHAR',
                'constraint' => 150,
            ],
            'type' => [
                'type'       => 'ENUM',
                'constraint' => ['public', 'personal', 'internal'],
                'default'    => 'public',
            ],
            'icon' => [
                'type'       => 'VARCHAR',
                'constraint' => 60,
                'default'    => 'bi-folder-fill',
            ],
            'color' => [
                'type'       => 'VARCHAR',
                'constraint' => 30,
                'default'    => 'blue',
            ],
            'owner_id' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
            'created_by' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['active', 'inactive'],
                'default'    => 'active',
            ],
            'created_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'default' => null,
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('slug', 'uq_slug');
        $this->forge->addKey('type', false, false, 'idx_type');
        $this->forge->addKey('owner_id', false, false, 'idx_owner');
        $this->forge->createTable('document_folders', true);
    }

    public function down()
    {
        $this->forge->dropTable('document_folders');
    }
}
