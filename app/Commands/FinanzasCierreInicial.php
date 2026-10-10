<?php

namespace App\Commands;

use App\Services\RevisionService;
use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Cierre de revisión inicial (v1.33.0) — ver RevisionService::initialClose().
 *
 *   php spark finanzas:cierre-inicial            (prueba en seco: solo cuenta)
 *   php spark finanzas:cierre-inicial --aplicar  (lo aplica; idempotente)
 *
 * También se puede lanzar desde Finanzas › Revisión (botón «Cierre inicial»).
 */
class FinanzasCierreInicial extends BaseCommand
{
    protected $group       = 'Finanzas';
    protected $name        = 'finanzas:cierre-inicial';
    protected $description = 'Da por bueno lo anterior: cierra sesiones con asistencia, acepta clases sin descontar y confirma precios.';
    protected $usage       = 'finanzas:cierre-inicial [--aplicar]';

    public function run(array $params): void
    {
        $apply = in_array('--aplicar', $params, true) || array_key_exists('aplicar', $params);
        $r     = (new RevisionService())->initialClose($apply);

        CLI::write($apply ? 'Cierre de revisión inicial APLICADO' : 'Prueba en seco (no se ha cambiado nada)', $apply ? 'green' : 'yellow');
        CLI::write(sprintf('  Sesiones pasadas con asistencia que se cierran:   %d', $r['sessions']));
        CLI::write(sprintf('  Clases sin descontar que se dan por buenas:       %d', $r['debts']));
        CLI::write(sprintf('  Precios estimados que se confirman:               %d', $r['prices']));
        if (!$apply) {
            CLI::write('Para aplicarlo: php spark finanzas:cierre-inicial --aplicar', 'light_cyan');
        }
    }
}
