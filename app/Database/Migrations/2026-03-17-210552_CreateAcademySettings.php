<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class CreateAcademySettings extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id' => [
                'type'           => 'INT',
                'unsigned'       => true,
                'auto_increment' => true,
            ],
            'setting_key' => [
                'type'       => 'VARCHAR',
                'constraint' => 100,
            ],
            'setting_value' => [
                'type' => 'TEXT',
                'null' => true,
            ],
            'setting_type' => [
                'type'       => 'ENUM',
                'constraint' => ['string', 'int', 'bool', 'json'],
                'default'    => 'string',
            ],
            'updated_at' => [
                'type' => 'DATETIME',
                'null' => true,
            ],
            'updated_by' => [
                'type'     => 'INT',
                'unsigned' => true,
                'null'     => true,
            ],
        ]);

        $this->forge->addPrimaryKey('id');
        $this->forge->addUniqueKey('setting_key', 'uq_setting_key');
        $this->forge->createTable('academy_settings', true);
    }

    public function down()
    {
        $this->forge->dropTable('academy_settings');
    }
}
