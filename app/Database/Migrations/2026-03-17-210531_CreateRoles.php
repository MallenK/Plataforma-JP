<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Migración reservada — actualmente sin uso.
 *
 * El sistema de roles es un campo ENUM en users.role (superadmin, admin, coach, alumno, staff).
 * Esta tabla se implementará en Fase 3 si se necesita lógica de permisos granular por rol.
 *
 * Por ahora se deja vacía intencionalmente para no crear tablas huérfanas.
 */
class CreateRoles extends Migration
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
