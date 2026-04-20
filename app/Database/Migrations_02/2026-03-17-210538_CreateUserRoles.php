<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migración reservada — actualmente sin uso.
 *
 * Un usuario tiene un único rol (campo plano en users.role).
 * Esta tabla pivote se implementará en Fase 3 si se requiere
 * que un usuario pueda tener múltiples roles simultáneamente.
 *
 * Por ahora se deja vacía intencionalmente.
 */
class CreateUserRoles extends Migration
{
    public function up()
    {
        // Pendiente — ver roadmap Fase 3
    }

    public function down()
    {
        // Pendiente — ver roadmap Fase 3
    }
}
