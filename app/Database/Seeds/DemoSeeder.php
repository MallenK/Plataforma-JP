<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Siembra COMPLETA del entorno DEMO (2º servicio de Render, BBDD propia).
 *
 * Encadena la siembra base + el volumen de datos falsos + tickets +
 * las cuentas de invitado + conversaciones/notificaciones de ejemplo +
 * el enriquecimiento de las cuentas de invitado (DemoShowcaseSeeder).
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
        $this->call('BulkDemoDataSeeder');       // 2 admins, 10 coaches, 4 staff, 50 alumnos, bonos, clases
        $this->call('BulkDemoExtrasSeeder');     // métricas, anotaciones, notificaciones, docs, tickets de academia
        $this->call('PreprodTicketsSeeder');     // backlog de incidencias (técnicas del proyecto)
        $this->call('DemoGuestsSeeder');         // cuentas de invitado + branding neutro
        $this->call('DemoConversationsSeeder');  // mensajes + notificaciones de ejemplo
        $this->call('DemoShowcaseSeeder');        // calendario/bonos/anotaciones/docs de los invitados

        echo "DemoSeeder: entorno de demostración listo.\n";
    }
}
