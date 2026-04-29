<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class LocationsSeeder extends Seeder
{
    public function run()
    {
        $data = [
            [
                'name'        => 'Campo 1',
                'description' => '',
                'address'     => 'Carrer del Doctor Barraquer 24',
                'type'        => 'pitch',
                'capacity'    => null,
                'phone'       => '',
                'active'      => 1,
                'created_at'  => '2026-04-10 16:07:07',
                'updated_at'  => '2026-04-10 16:07:07',
            ],
        ];

        $this->db->table('locations')->insertBatch($data);
    }
}
