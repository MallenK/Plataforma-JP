<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateFolderPermissions extends Migration
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
            'user_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'can_read' => [
                'type'    => 'TINYINT',
                'default' => 1,
            ],
            'can_write' => [
                'type'    => 'TINYINT',
                'default' => 0,
            ],
            'granted_by' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'created_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'default' => null,
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey(['folder_id', 'user_id'], 'uq_folder_user');
        $this->forge->createTable('folder_permissions', true);
    }

    public function down()
    {
        $this->forge->dropTable('folder_permissions');
    }
}
