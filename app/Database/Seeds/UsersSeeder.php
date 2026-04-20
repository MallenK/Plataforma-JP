<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Seed inicial de usuarios del sistema.
 * Las contraseñas ya están hasheadas con bcrypt (password_hash).
 * NO modificar los hashes directamente — usar la app para cambiarlas.
 */
class UsersSeeder extends Seeder
{
    public function run()
    {
        $data = [
            [
                'id'         => 2,
                'name'       => 'Sergi Mallén López',
                'email'      => 'sergimallenweb@gmail.com',
                'password'   => '$2y$10$7LjAY1A5rnFnTNgm9Ccj4OSP7sVCc.RmuaTtqHjk.ousUPRgGTYEG',
                'role'       => 'admin',
                'status'     => 'active',
                'created_at' => '2026-03-22 02:54:02',
                'updated_at' => '2026-03-22 02:54:02',
            ],
            [
                'id'         => 3,
                'name'       => 'Gina',
                'email'      => 'ginamoref@gmail.com',
                'password'   => '$2y$10$1yrfdxIQTrEnQ3fhcSpRhujc2QE8yrH8dN0g35VwDfznbRiYS9Mym',
                'role'       => 'coach',
                'status'     => 'active',
                'created_at' => '2026-03-22 14:16:45',
                'updated_at' => '2026-04-10 15:21:59',
            ],
        ];

        $this->db->table('users')->insertBatch($data);
    }
}
