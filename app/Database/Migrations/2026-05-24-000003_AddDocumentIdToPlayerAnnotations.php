<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class AddDocumentIdToPlayerAnnotations extends Migration
{
    public function up(): void
    {
        $this->forge->addColumn('player_annotations', [
            'document_id' => [
                'type'       => 'INT',
                'constraint' => 11,
                'unsigned'   => true,
                'null'       => true,
                'default'    => null,
                'after'      => 'content',
            ],
        ]);
    }

    public function down(): void
    {
        $this->forge->dropColumn('player_annotations', 'document_id');
    }
}
