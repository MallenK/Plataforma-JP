<?php

namespace App\Commands;

use App\Services\TestDataService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Alumnos de prueba de antes del lanzamiento («Alumno Prueba», «Prueba»,
 * «Rtrt»…): enseña qué se borraría y, con --aplicar, lo borra TODO (clases
 * solo suyas, bonos, cobros, mensajes, el usuario…). Ver TestDataService.
 *
 *   php spark datos:pruebas                    (prueba en seco: no borra nada)
 *   php spark datos:pruebas --aplicar          (pide escribir BORRAR)
 *   php spark datos:pruebas --aplicar --force  (sin confirmación)
 */
class DatosPruebas extends BaseCommand
{
    protected $group       = 'Mantenimiento';
    protected $name        = 'datos:pruebas';
    protected $description = 'Lista (y con --aplicar elimina) los alumnos de prueba y todos sus registros.';
    protected $usage       = 'datos:pruebas [--aplicar] [--force]';

    public function run(array $params): void
    {
        $apply = in_array('--aplicar', $params, true) || array_key_exists('aplicar', $params);
        $force = in_array('--force', $params, true) || array_key_exists('force', $params);

        $svc   = new TestDataService();
        $users = $svc->findTestPlayers();
        if ($users === []) {
            CLI::write('No hay alumnos de prueba.', 'green');
            return;
        }

        $plan = $svc->plan(array_column($users, 'id'));
        CLI::write('Alumnos de prueba:', 'light_cyan');
        foreach ($plan['users'] as $u) {
            CLI::write("  #{$u['id']}  {$u['name']}  <{$u['email']}>  ({$u['status']})");
        }
        CLI::newLine();
        CLI::write('Se eliminaría:', 'light_cyan');
        foreach ($plan['counts'] as $label => $n) {
            CLI::write(sprintf('  %-34s %5d', $label, $n));
        }
        foreach ($plan['blockers'] as $b) {
            CLI::error('  ' . $b);
        }
        CLI::newLine();

        if (!$apply) {
            CLI::write('Prueba en seco: no se ha borrado nada. Repite con --aplicar para borrarlo.', 'green');
            return;
        }
        if ($plan['blockers']) {
            CLI::error('No se borra nada hasta resolver lo anterior.');
            return;
        }
        if (!$force && CLI::prompt('Escribe "BORRAR" para confirmar', null) !== 'BORRAR') {
            CLI::write('Cancelado.', 'yellow');
            return;
        }

        $r = $svc->purge(array_column($users, 'id'));
        $r['success']
            ? CLI::write("Hecho: {$r['users']} alumno(s) de prueba y {$r['sessions']} clase(s) de prueba eliminados.", 'green')
            : CLI::error($r['error']);
    }
}
