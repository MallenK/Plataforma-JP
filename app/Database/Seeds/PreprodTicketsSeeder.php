<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use App\Models\UserModel;

/**
 * Carga los tickets del proyecto (docs/tickets/TICKET-00X.md) + los abiertos
 * en curso, en la tabla `tickets` de PRE-PRODUCCIÓN, para gestionar el
 * backlog desde el propio módulo de Incidencias.
 *
 * - Idempotente: no duplica si el `ticket_number` ya existe.
 * - NO se llama desde DatabaseSeeder. Ejecutar a mano:
 *       php spark db:seed PreprodTicketsSeeder
 */
class PreprodTicketsSeeder extends Seeder
{
    public function run()
    {
        $db = $this->db ?: \Config\Database::connect();

        // Reporter: superadmin/admin de este entorno (el módulo de tickets lo
        // gestiona el superadmin). Si no hay, el primer usuario que exista.
        $userModel = new UserModel();
        $reporter  = $userModel->whereIn('role', ['superadmin', 'admin'])->orderBy('id', 'ASC')->first()
                   ?: $userModel->orderBy('id', 'ASC')->first();

        if (!$reporter) {
            echo "PreprodTicketsSeeder: no hay usuarios; siembra primero DatabaseSeeder.\n";
            return;
        }
        $uid = (int) $reporter['id'];

        foreach ($this->tickets() as $t) {
            $exists = $db->table('tickets')->where('ticket_number', $t['number'])->countAllResults();
            if ($exists) {
                echo "  = {$t['number']} ya existe, se omite.\n";
                continue;
            }

            $created  = $t['created'] . ' 09:00:00';
            $resolved = in_array($t['status'], ['resuelto', 'cerrado'], true) ? ($t['resolved'] ?? $t['created']) . ' 18:00:00' : null;
            $closed   = $t['status'] === 'cerrado' ? ($t['resolved'] ?? $t['created']) . ' 18:30:00' : null;

            $db->table('tickets')->insert([
                'ticket_number' => $t['number'],
                'user_id'       => $uid,
                'category'      => $t['category'],
                'priority'      => $t['priority'],
                'title'         => $t['title'],
                'description'   => $t['description'],
                'status'        => $t['status'],
                'resolved_at'   => $resolved,
                'closed_at'     => $closed,
                'created_at'    => $created,
                'updated_at'    => $resolved ?? $created,
            ]);
            echo "  + {$t['number']}  [{$t['status']}]  {$t['title']}\n";
        }

        echo "PreprodTicketsSeeder: listo.\n";
    }

    /** @return list<array<string,string>> */
    private function tickets(): array
    {
        return [
            [
                'number' => 'TKT-2026-00001', 'category' => 'bug', 'priority' => 'alta',
                'status' => 'cerrado', 'created' => '2026-08-28', 'resolved' => '2026-09-01',
                'title' => 'Los admin y el staff no pueden impartir clases',
                'description' => "Al crear/editar una sesión, el selector de responsable solo lista usuarios con rol coach (o staff en clases de staff). Un admin o superadmin nunca aparece, así que no se le puede asignar como responsable aunque en la práctica también imparten.\n\nEntregado en v1.1.0. Detalle: docs/tickets/TICKET-001-clases-admin-staff.md",
            ],
            [
                'number' => 'TKT-2026-00002', 'category' => 'bug', 'priority' => 'media',
                'status' => 'cerrado', 'created' => '2026-08-28', 'resolved' => '2026-09-01',
                'title' => 'Desbordamiento de texto en la ficha de alumno (posición)',
                'description' => "En la ficha del alumno, la tarjeta POSICIÓN con \"Extremo/mediapunta\" desborda el ancho y se solapa con la tarjeta CATEGORÍA contigua en móvil. Causa visual + de fondo (posiciones múltiples).\n\nEntregado en v1.1.0. Detalle: docs/tickets/TICKET-002-overflow-texto-ficha-alumno.md",
            ],
            [
                'number' => 'TKT-2026-00003', 'category' => 'mejora', 'priority' => 'alta',
                'status' => 'cerrado', 'created' => '2026-08-29', 'resolved' => '2026-09-01',
                'title' => 'Recuperación de contraseña por email + correo de bienvenida',
                'description' => "El flujo de \"¿Olvidaste tu contraseña?\" no entregaba el correo en producción (remitente de pruebas de Resend). Tampoco existía correo de bienvenida al dar de alta un usuario.\n\nEntregado en v1.1.0. Detalle: docs/tickets/TICKET-003-email-recuperacion-y-bienvenida.md",
            ],
            [
                'number' => 'TKT-2026-00004', 'category' => 'bug', 'priority' => 'media',
                'status' => 'cerrado', 'created' => '2026-08-29', 'resolved' => '2026-09-01',
                'title' => 'El feedback "Después" de una sesión estaba bloqueado sin motivo',
                'description' => "El textarea de feedback post-sesión (post_notes / post_obs) solo era editable si la sesión estaba en estado completed. Se desbloquea también cuando ya se ha pasado lista o hay algún presente.\n\nEntregado en v1.1.0. Detalle: docs/tickets/TICKET-004-feedback-textarea-bloqueado.md",
            ],
            [
                'number' => 'TKT-2026-00005', 'category' => 'mejora', 'priority' => 'alta',
                'status' => 'cerrado', 'created' => '2026-08-30', 'resolved' => '2026-09-01',
                'title' => 'Capa de seguridad para login, alta de usuarios y cambio de contraseña',
                'description' => "Anti-fuerza-bruta (AuthGuardService + tabla auth_events), reset de contraseña con token hasheado, must_change_password, panel de actividad de seguridad, cabeceras HTTP + HSTS. Sin 2FA ni CAPTCHA.\n\nEntregado en v1.1.0. Detalle: docs/tickets/TICKET-005-endurecimiento-auth.md",
            ],
            [
                'number' => 'TKT-2026-00006', 'category' => 'tecnico', 'priority' => 'urgente',
                'status' => 'resuelto', 'created' => '2026-09-02', 'resolved' => '2026-09-03',
                'title' => 'Endurecimiento de subida de archivos (RCE) + puntos de auditoría — Tanda A',
                'description' => "Los adjuntos de Notificaciones/Incidencias/Mensajes no validaban la extensión final y se guardaban en public/. Ahora: lista blanca + almacenamiento fuera del webroot + descarga por PHP. Más open-redirect, XSS latentes, robots.txt, www→https.\n\nMergeado (v1.1.5), pendiente de desplegar a Hostinger. Detalle: docs/tickets/TICKET-006-seguridad-subida-archivos.md",
            ],
            [
                'number' => 'TKT-2026-00007', 'category' => 'bug', 'priority' => 'media',
                'status' => 'resuelto', 'created' => '2026-09-03', 'resolved' => '2026-09-03',
                'title' => 'Calendario móvil: vista Mes se descuadra + clases solapadas ilegibles',
                'description' => "En móvil, una chip de evento con texto largo ensanchaba la columna del día y rompía la cuadrícula. Vista Semana/Día: clases a la misma hora se pisaban.\n\nMergeado (v1.1.6), pendiente de desplegar a Hostinger. Detalle: docs/tickets/TICKET-007-calendario-mes-desborde-movil.md",
            ],
            [
                'number' => 'TKT-2026-00008', 'category' => 'bug', 'priority' => 'alta',
                'status' => 'resuelto', 'created' => '2026-09-05', 'resolved' => '2026-09-05',
                'title' => '"Descontar bono" desde Pasar Lista falla con 403 Forbidden',
                'description' => "El fetch de pasar_lista.php mandaba X-CSRF-TOKEN vacío (leído de un <meta> que no existe). El bono nunca se descontaba.\n\nMergeado (v1.1.7), pendiente de desplegar a Hostinger. Detalle: docs/tickets/TICKET-008-descontar-bono-csrf-403.md",
            ],

            // ─── Abiertos / en curso ────────────────────────────────────
            [
                'number' => 'TKT-2026-00009', 'category' => 'tecnico', 'priority' => 'media',
                'status' => 'abierto', 'created' => '2026-09-02',
                'title' => 'Seguridad Tanda B: logout a POST + renombrar tokens CSRF',
                'description' => "Pendiente de la auditoría (hallazgos L1/L2):\n1. GET /logout debe pasar a POST-only + botón/form con csrf_field() (hoy un <img src=.../logout> cierra la sesión).\n2. Renombrar tokenName/cookieName a jp_csrf_token / jp_csrf_cookie (hoy siguen los del esqueleto).\nInvalida formularios abiertos → hacer en ventana de bajo tráfico. Ver docs/auditoria/2026-09-02-auditoria-seguridad.md.",
            ],
            [
                'number' => 'TKT-2026-00010', 'category' => 'tecnico', 'priority' => 'media',
                'status' => 'abierto', 'created' => '2026-09-06',
                'title' => 'Las migraciones no corren limpias desde cero (MySQL 8 / MariaDB)',
                'description' => "Un `php spark migrate` desde una BBDD vacía falla:\n- CreateUsers crea users.id SIGNED, pero ~30 migraciones lo referencian como INT UNSIGNED → FK errno 150 en CreatePlayerBonos.\n- 18 columnas usan utf8mb4_0900_ai_ci (colación de MySQL 8) que MariaDB no soporta.\nHoy la BBDD local sobrevive por parches manuales acumulados. Pre-producción se montó clonando el esquema local en vez de migrar. Arreglar para que Render/Hostinger puedan migrar solos.",
            ],
            [
                'number' => 'TKT-2026-00011', 'category' => 'tecnico', 'priority' => 'media',
                'status' => 'en_progreso', 'created' => '2026-09-06',
                'title' => 'Montar entorno de Pre-Producción (Render + 2ª BBDD Hostinger)',
                'description' => "Render (rama main, auto-deploy) apuntando a una 2ª BBDD de la cuenta de Hostinger con datos de prueba. Hecho: BBDD creada, remote MySQL, esquema + seed cargados, Render conectado.\nPendiente: PR con start.sh (encrypt configurable + seed idempotente), banner PRE-PRODUCCIÓN, y docs/operaciones actualizada. Credenciales solo en el panel de Render.",
            ],
            [
                'number' => 'TKT-2026-00012', 'category' => 'mejora', 'priority' => 'media',
                'status' => 'resuelto', 'created' => '2026-09-06', 'resolved' => '2026-09-06',
                'title' => 'Auditoría local: validación de horas de clase + calendario + buscador',
                'description' => "PR #54 (v1.1.8): la hora de una sesión se valida al crear/editar (no más 00:00); el calendario reparte en columnas las clases solapadas aunque empiecen a distinta hora; vista Semana entre dos meses; buscador de clases por nombre/entrenador/jugador; higiene de la auditoría.\n\nMergeado a main, pendiente de desplegar a Hostinger.",
            ],
        ];
    }
}
