<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateDocuments extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'folder_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'uploader_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'name_original' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],
            'name_stored' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],
            'mime_type' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'extension' => [
                'type'       => 'VARCHAR',
                'constraint' => 15,
            ],
            'size_bytes' => [
                'type'     => 'BIGINT',
                'unsigned' => true,
                'default'  => 0,
            ],
            'description' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'sensitive' => [
                'type'    => 'TINYINT',
                'default' => 0,
            ],
            'created_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'default' => null,
            ],
            'deleted_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('folder_id', false, false, 'idx_folder');
        $this->forge->addKey('uploader_id', false, false, 'idx_uploader');
        $this->forge->addKey('deleted_at', false, false, 'idx_deleted');
        $this->forge->createTable('documents');
    }

    public function down()
    {
        $this->forge->dropTable('documents');
    }
}
