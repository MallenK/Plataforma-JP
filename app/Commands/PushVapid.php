<?php

namespace App\Commands;

use App\Libraries\WebPush;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Genera el par de claves VAPID de las notificaciones push.
 *
 *   php spark push:vapid           → imprime las 3 líneas para el .env
 *   php spark push:vapid --write   → las añade a .env si aún no existen
 *
 * La clave PRIVADA es un secreto (solo .env del servidor, nunca al repo). Si se
 * cambia una vez en producción, todas las suscripciones existentes dejan de
 * funcionar y cada usuario debe volver a activar las notificaciones.
 */
class PushVapid extends BaseCommand
{
    protected $group       = 'App';
    protected $name        = 'push:vapid';
    protected $description = 'Genera las claves VAPID para las notificaciones push (PWA).';
    protected $options     = ['--write' => 'Añade las claves al .env si no hay ya unas definidas.'];

    public function run(array $params): void
    {
        $envFile = ROOTPATH . '.env';
        $current = is_file($envFile) ? (string) file_get_contents($envFile) : '';

        if (preg_match('/^\s*vapid\.privateKey\s*=/m', $current)) {
            CLI::error('Ya hay unas claves VAPID en .env. Bórralas a mano si de verdad quieres regenerarlas (invalida todas las suscripciones).');
            return;
        }

        $keys  = WebPush::generateVapidKeys();
        $lines = "\n# Notificaciones push (PWA) — php spark push:vapid\n"
            . "vapid.publicKey = {$keys['publicKey']}\n"
            . "vapid.privateKey = {$keys['privateKey']}\n"
            . "vapid.subject = mailto:info@jppreparation.com\n";

        if (CLI::getOption('write') !== null) {
            file_put_contents($envFile, $current . $lines);
            CLI::write('Claves VAPID añadidas a .env (la privada no se muestra).', 'green');
            return;
        }

        CLI::write('Añade esto al .env del servidor:', 'yellow');
        CLI::write($lines);
    }
}
