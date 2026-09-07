<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;
use Config\Database;

/**
 * Borrado DEFINITIVO de uno o varios usuarios de la base de datos, junto con
 * todas sus filas dependientes (mensajes, tickets, adjuntos, asistencias,
 * bonos, notas, notificaciones…).
 *
 * ⚠️ Esto NO es "dar de baja". La baja normal (status = inactive) se hace
 * desde la plataforma y conserva el historial. Este comando lo elimina todo
 * y no se puede deshacer. Úsalo solo para datos de prueba o por RGPD.
 *
 *   php spark user:purge 49                     (borra el usuario con id 49)
 *   php spark user:purge demo.alumno1@...       (por email)
 *   php spark user:purge 49 51 pepe@correo.com  (varios)
 *   php spark user:purge 49 --dry-run           (solo enseña qué borraría)
 *   php spark user:purge 49 --force             (sin pedir confirmación)
 *
 * Descubre las relaciones (claves foráneas → users.id) en tiempo de
 * ejecución con information_schema, así funciona aunque el esquema cambie.
 */
class PurgeUser extends BaseCommand
{
    protected $group       = 'Mantenimiento';
    protected $name        = 'user:purge';
    protected $description  = 'Borra definitivamente usuarios y todas sus filas dependientes (irreversible).';
    protected $usage        = 'user:purge <id|email> [<id|email> ...] [--dry-run] [--force]';

    public function run(array $params): void
    {
        $dryRun = in_array('--dry-run', $params, true) || array_key_exists('dry-run', $params);
        $force  = in_array('--force', $params, true)   || array_key_exists('force', $params);

        // Los argumentos posicionales llegan con clave entera; las opciones
        // (--dry-run, --force) con clave string → nos quedamos con los primeros.
        $ids = [];
        foreach ($params as $key => $value) {
            if (is_int($key) && is_string($value) && $value !== '' && $value[0] !== '-') {
                $ids[] = $value;
            }
        }
        if ($ids === []) {
            CLI::error('Indica al menos un id o email. Ej: php spark user:purge 49');
            return;
        }

        $db = Database::connect();

        // ── Resolver id/email → usuarios ────────────────────────────────
        $users = [];
        foreach ($ids as $ref) {
            $row = ctype_digit((string) $ref)
                ? $db->table('users')->where('id', (int) $ref)->get()->getRowArray()
                : $db->table('users')->where('email', $ref)->get()->getRowArray();

            if (! $row) {
                CLI::write("· No existe ningún usuario '{$ref}' — se ignora.", 'yellow');
                continue;
            }
            if ($row['role'] === 'superadmin') {
                CLI::error("· '{$row['email']}' es superadmin — NO se borra. Cámbiale el rol primero si de verdad quieres.");
                continue;
            }
            $users[$row['id']] = $row;
        }

        if ($users === []) {
            CLI::write('Nada que borrar.', 'yellow');
            return;
        }

        $userIds = array_keys($users);
        $inList  = implode(',', array_map('intval', $userIds));

        // ── Descubrir tablas hijas (FK → users.id) ─────────────────────
        $fkRows = $db->query(
            "SELECT TABLE_NAME, COLUMN_NAME
               FROM information_schema.KEY_COLUMN_USAGE
              WHERE REFERENCED_TABLE_SCHEMA = DATABASE()
                AND REFERENCED_TABLE_NAME = 'users'
                AND REFERENCED_COLUMN_NAME = 'id'"
        )->getResultArray();

        // ── Resumen ───────────────────────────────────────────────────
        CLI::newLine();
        CLI::write('Usuarios a borrar:', 'light_cyan');
        foreach ($users as $u) {
            CLI::write("  #{$u['id']}  {$u['name']}  <{$u['email']}>  ({$u['role']})");
        }
        CLI::newLine();
        CLI::write('Filas dependientes que se eliminarán:', 'light_cyan');

        $plan = [];
        foreach ($fkRows as $fk) {
            $count = (int) $db->table($fk['TABLE_NAME'])
                ->whereIn($fk['COLUMN_NAME'], $userIds)
                ->countAllResults();
            if ($count > 0) {
                $plan[] = $fk;
                CLI::write(sprintf('  %-34s %-18s %d fila(s)', $fk['TABLE_NAME'], "({$fk['COLUMN_NAME']})", $count));
            }
        }
        if ($plan === []) {
            CLI::write('  (ninguna)');
        }
        CLI::newLine();

        if ($dryRun) {
            CLI::write('--dry-run: no se ha borrado nada.', 'green');
            return;
        }

        if (! $force && CLI::prompt('Escribe "BORRAR" para confirmar', null) !== 'BORRAR') {
            CLI::write('Cancelado.', 'yellow');
            return;
        }

        // ── Ejecutar en transacción ───────────────────────────────────
        $db->transStart();
        $db->query('SET FOREIGN_KEY_CHECKS = 0');

        foreach ($plan as $fk) {
            $db->table($fk['TABLE_NAME'])->whereIn($fk['COLUMN_NAME'], $userIds)->delete();
            CLI::write("  - {$fk['TABLE_NAME']}: limpiada", 'dark_gray');
        }
        $db->query("DELETE FROM users WHERE id IN ($inList)");

        $db->query('SET FOREIGN_KEY_CHECKS = 1');
        $db->transComplete();

        if ($db->transStatus() === false) {
            CLI::error('Falló la transacción — no se ha borrado nada.');
            return;
        }

        CLI::write('Hecho. ' . count($users) . ' usuario(s) eliminados definitivamente.', 'green');
    }
}
