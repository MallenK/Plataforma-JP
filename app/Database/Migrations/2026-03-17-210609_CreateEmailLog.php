<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateEmailLog extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'sender_id' => [
                'type'     => 'INT',
                'unsigned' => true,
            ],
            'recipient_type' => [
                'type'       => 'ENUM',
                'constraint' => ['individual', 'group'],
                'default'    => 'individual',
            ],
            'recipient_id' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
            'recipient_group' => [
                'type'       => 'VARCHAR',
                'constraint' => 50,
                'null'       => true,
            ],
            'subject' => [
                'type'       => 'VARCHAR',
                'constraint' => 255,
            ],
            'message' => [
                'type' => 'TEXT',
            ],
            'status' => [
                'type'       => 'ENUM',
                'constraint' => ['sent', 'failed'],
                'default'    => 'sent',
            ],
            'error_msg' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'created_at' => [
                'type'    => 'DATETIME',
                'null'    => true,
                'default' => null,
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addKey('sender_id', false, false, 'idx_sender');
        $this->forge->addKey('created_at', false, false, 'idx_created_at');
        $this->forge->createTable('email_log');
    }

    public function down()
    {
        $this->forge->dropTable('email_log');
    }
}
