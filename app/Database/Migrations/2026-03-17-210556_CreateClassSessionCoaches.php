<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateClassSessionCoaches extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'session_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'user_id' => [
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
        $this->forge->addUniqueKey(['session_id', 'user_id'], 'uq_csc');
        $this->forge->addKey('user_id', false, false, 'idx_csc_user');
        $this->forge->createTable('class_session_coaches', true);
    }

    public function down()
    {
        $this->forge->dropTable('class_session_coaches');
    }
}
