<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

class FixUsersRoleEnumAndCleanIds extends Migration
{
    public function up()
    {
        // 1. Eliminar filas con id=0 que corrompen el AUTO_INCREMENT
        $this->db->query('DELETE FROM users WHERE id = 0');

        // 2. Ampliar el ENUM para incluir todos los roles reales de la app.
        //    La migración original solo tenía admin/coach/player; faltaban
        //    staff y superadmin, por lo que MySQL (modo no-estricto) guardaba
        //    cadena vacía al intentar asignar esos roles.
        $this->db->query(
            "ALTER TABLE users
             MODIFY COLUMN role
             ENUM('superadmin','admin','staff','coach','player')
             NOT NULL DEFAULT 'player'"
        );

        // 3. Reparar cualquier fila con role='' (consecuencia del bug anterior)
        //    Se deja como 'staff' porque ese era el rol que se intentaba asignar.
        $this->db->query("UPDATE users SET role = 'staff' WHERE role = ''");

        // 4. Resetear AUTO_INCREMENT — MySQL lo ajusta al MAX(id)+1 automáticamente.
        $this->db->query('ALTER TABLE users AUTO_INCREMENT = 1');
    }

    public function down()
    {
        $this->db->query(
            "ALTER TABLE users
             MODIFY COLUMN role
             ENUM('admin','coach','player')
             NULL DEFAULT 'player'"
        );
    }
}
