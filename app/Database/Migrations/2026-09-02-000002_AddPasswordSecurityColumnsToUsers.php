<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Columnas para:
 *  - password_changed_at: propagar el cambio de contraseña a otras sesiones
 *    (AuthFilter compara con session('pw_stamp')).
 *  - must_change_password: forzar al usuario a definir su contraseña en el
 *    primer acceso (altas creadas por admin) o tras un reset por admin.
 */
class AddPasswordSecurityColumnsToUsers extends Migration
{
    public function up()
    {
        $this->forge->addColumn('users', [
            'password_changed_at' => [
                'type'  => 'DATETIME',
                'null'  => true,
                'after' => 'password',
            ],
            'must_change_password' => [
                'type'       => 'TINYINT',
                'constraint' => 1,
                'default'    => 0,
                'null'       => false,
                'after'      => 'password_changed_at',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('users', ['password_changed_at', 'must_change_password']);
    }
}
