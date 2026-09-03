<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Mueve los adjuntos privados (mensajes, notificaciones, tickets) desde
 * public/uploads/ (dentro del webroot) a writable/uploads/ (fuera).
 *
 * Los adjuntos NUEVOS ya se guardan en writable/uploads/. Este comando es
 * para migrar los que se subieron antes del cambio. Es idempotente y
 * seguro: copia, verifica y solo entonces borra el original.
 *
 *   php spark uploads:migrate           (ejecuta el movimiento)
 *   php spark uploads:migrate --dry-run (solo lista lo que haría)
 */
class MigrateUploadsOutOfWebroot extends BaseCommand
{
    protected $group       = 'Mantenimiento';
    protected $name        = 'uploads:migrate';
    protected $description  = 'Mueve adjuntos privados de public/uploads a writable/uploads (fuera del webroot).';
    protected $usage        = 'uploads:migrate [--dry-run]';

    /** Subcarpetas de adjuntos privados a mover. */
    private const SUBFOLDERS = ['mensajes', 'notificaciones', 'tickets'];

    public function run(array $params): void
    {
        $dryRun = array_key_exists('dry-run', $params) || in_array('--dry-run', $params, true);

        helper('upload');

        // En hosting compartido (Hostinger) la CLI y el servidor web corren con
        // el mismo usuario y esto no aplica. En Docker/Render puede que no.
        if (!$dryRun && function_exists('posix_getuid') && posix_getuid() === 0) {
            CLI::write('AVISO: ejecutando como root. Si el servidor web usa otro usuario '
                . '(p. ej. www-data), haz "chown -R <usuario-web> writable/uploads" después.', 'yellow');
            CLI::newLine();
        }

        $movedTotal = 0;
        $failTotal  = 0;

        foreach (self::SUBFOLDERS as $sub) {
            $src = rtrim(FCPATH, "/\\") . '/uploads/' . $sub . '/';
            $dst = upload_private_dir($sub);

            if (!is_dir($src)) {
                CLI::write("  {$sub}: nada que migrar (no existe {$src})", 'dark_gray');
                continue;
            }

            if (!$dryRun && !is_dir($dst)) {
                upload_harden_dir($dst);
            }

            $files = array_filter(
                scandir($src) ?: [],
                static fn ($f) => $f !== '.' && $f !== '..' && $f !== '.htaccess' && is_file($src . $f)
            );

            if (empty($files)) {
                CLI::write("  {$sub}: 0 ficheros", 'dark_gray');
                continue;
            }

            foreach ($files as $file) {
                $from = $src . $file;
                $to   = $dst . $file;

                if (is_file($to)) {
                    CLI::write("  {$sub}/{$file}: ya existe en destino, se omite", 'yellow');
                    continue;
                }

                if ($dryRun) {
                    CLI::write("  [dry-run] {$sub}/{$file} → " . $to, 'cyan');
                    $movedTotal++;
                    continue;
                }

                if (@copy($from, $to) && is_file($to) && filesize($to) === filesize($from)) {
                    @unlink($from);
                    CLI::write("  movido {$sub}/{$file}", 'green');
                    $movedTotal++;
                } else {
                    @unlink($to);
                    CLI::write("  ERROR al mover {$sub}/{$file}", 'red');
                    $failTotal++;
                }
            }
        }

        CLI::newLine();
        if ($dryRun) {
            CLI::write("Dry-run: {$movedTotal} ficheros se moverían.", 'yellow');
        } else {
            CLI::write("Hecho. {$movedTotal} movidos, {$failTotal} con error.", $failTotal ? 'red' : 'green');
            CLI::write('Los adjuntos antiguos que no se hayan podido mover siguen sirviéndose desde public/ (fallback en upload_resolve_stored).', 'dark_gray');
        }
    }
}
