<?php

namespace App\Services;

/**
 * Borra y resiembra los datos del entorno DEMO.
 *
 * NO usa `migrate:refresh`: las migraciones no corren limpias desde cero
 * en MariaDB (ver TKT-2026-00010). En su lugar vacía todas las tablas de
 * datos conservando el esquema y vuelve a lanzar el DemoSeeder.
 *
 * Lo invoca App\Controllers\DemoController::reset(), que a su vez está
 * protegido por token y por demo_mode().
 */
class DemoResetService
{
    /**
     * Tablas que NO se vacían: control del framework y los leads del
     * formulario de contacto (deben sobrevivir al reset nocturno).
     */
    private const KEEP = ['migrations', 'demo_leads'];

    /**
     * @return array<string,int>  recuento de filas por tabla tras resembrar
     */
    public function run(): array
    {
        if (! demo_mode()) {
            throw new \DomainException('DemoResetService solo puede ejecutarse en la demo.');
        }

        $db = \Config\Database::connect();

        $db->query('SET FOREIGN_KEY_CHECKS = 0');
        foreach ($db->listTables() as $table) {
            if (in_array($table, self::KEEP, true)) {
                continue;
            }
            $db->table($table)->truncate();
        }
        $db->query('SET FOREIGN_KEY_CHECKS = 1');

        // Resembrar. Los seeders hacen echo; lo capturamos para que no
        // ensucie la respuesta JSON del endpoint.
        ob_start();
        try {
            \Config\Database::seeder()->call('DemoSeeder');
        } finally {
            $log = trim((string) ob_get_clean());
        }
        log_message('info', "DemoResetService: resembrado.\n" . $log);

        return [
            'users'         => (int) $db->table('users')->countAllResults(),
            'class_sessions'=> (int) $db->table('class_sessions')->countAllResults(),
            'conversations' => (int) $db->table('conversations')->countAllResults(),
            'notifications' => (int) $db->table('notifications')->countAllResults(),
            'tickets'       => (int) $db->table('tickets')->countAllResults(),
        ];
    }
}
