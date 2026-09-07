<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Siembra COMPLETA del entorno DEMO (2º servicio de Render, BBDD propia).
 *
 * Encadena la siembra base + el volumen de datos falsos + tickets +
 * las cuentas de invitado + conversaciones/notificaciones de ejemplo.
 *
 * Lo llaman:
 *   - docker/start.sh en el primer arranque (BBDD vacía) si APP_ENV_LABEL=demo
 *   - App\Services\DemoResetService (reset nocturno vía GitHub Actions)
 *
 * Ejecutar a mano:  php spark db:seed DemoSeeder
 */
class DemoSeeder extends Seeder
{
    public function run()
    {
        $this->call('DatabaseSeeder');          // academy_settings, sedes base, usuarios base
        $this->call('BulkDemoDataSeeder');       // sedes, coaches, staff, alumnos, bonos, clases
        $this->call('PreprodTicketsSeeder');     // backlog de incidencias
        $this->call('DemoGuestsSeeder');         // cuentas de invitado + branding neutro
        $this->call('DemoConversationsSeeder');  // mensajes + notificaciones de ejemplo

        echo "DemoSeeder: entorno de demostración listo.\n";
    }
}
