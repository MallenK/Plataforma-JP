<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use App\Models\UserModel;
use App\Models\PlayerProfileModel;
use App\Models\TicketModel;
use App\Models\TicketReplyModel;
use App\Services\ClasesService;

/**
 * SOLO PARA DESARROLLO LOCAL. No ejecutar contra una base de datos de
 * producción: crea usuarios, clases y tickets de prueba (marcados con
 * el prefijo "TEST" / dominio @test.jppreparation.local) para verificar
 * a mano los 4 fixes de 2026-09-01:
 *
 *  - TICKET-001: admin/staff pueden ser responsables de una clase
 *  - TICKET-002: varias posiciones por alumno, mostradas en lista
 *  - TICKET-003: email de recuperación / bienvenida
 *  - TICKET-004: feedback "Después" desbloqueado sin sesión completada
 *
 * Ejecutar:  docker compose exec app php spark db:seed DevTestDataSeeder
 * Idempotente: si el usuario/sesión/ticket ya existe (por email/título),
 * no lo duplica — se puede re-ejecutar sin problema.
 */
class DevTestDataSeeder extends Seeder
{
    private const DOMAIN = '@test.jppreparation.local';

    public function run()
    {
        $this->seedTestAdmin();
        $playerIds = $this->seedTestPlayers();
        $this->seedTestClasses($playerIds);
        $this->seedOverlappingClasses($playerIds);
        $this->seedResolvedTickets();

        echo "DevTestDataSeeder: listo.\n";
    }

    /**
     * Admin de prueba para poder revisar en el navegador, sin usar
     * credenciales reales, las pantallas que solo ve un admin
     * (asignación de responsable en clases, /tickets, /alumnos...).
     */
    private function seedTestAdmin(): void
    {
        $userModel = new UserModel();
        $email     = 'test.admin' . self::DOMAIN;

        if ($userModel->where('email', $email)->first()) {
            echo "  ya existe: {$email}\n";
            return;
        }

        $userModel->insert([
            'name'     => 'TEST Admin QA',
            'email'    => $email,
            'password' => 'Test1234!',
            'role'     => 'admin',
            'status'   => 'active',
        ], true);

        echo "  creado admin de prueba: {$email}\n";
    }

    // ────────────────────────────────────────────────────────────────
    //  Alumnos de prueba con varias posiciones
    // ────────────────────────────────────────────────────────────────

    private function seedTestPlayers(): array
    {
        $userModel    = new UserModel();
        $profileModel = new PlayerProfileModel();

        $players = [
            [
                'email'     => 'test.multiposicion' . self::DOMAIN,
                'name'      => 'TEST Alumno Multiposición',
                'positions' => PlayerProfileModel::encodePositions(['extremo_derecho', 'mediapunta']),
                'height' => 158, 'weight' => 39, 'category' => 'infantil',
            ],
            [
                // Reproduce el caso real reportado: texto libre legacy con
                // varias posiciones separadas por "/", sin migrar a JSON.
                // Sirve para comprobar que decodePositions()/la ficha lo
                // siguen mostrando bien como lista (sin desbordar).
                'email'     => 'test.legacy' . self::DOMAIN,
                'name'      => 'TEST Alumno Legacy Extremo/Mediapunta',
                'positions' => 'Extremo/mediapunta',
                'height' => 160, 'weight' => 41, 'category' => 'infantil',
            ],
            [
                'email'     => 'test.tresposiciones' . self::DOMAIN,
                'name'      => 'TEST Alumno Tres Posiciones',
                'positions' => PlayerProfileModel::encodePositions(['lateral_derecho', 'interior', 'delantero_centro']),
                'height' => 172, 'weight' => 63, 'category' => 'juvenil',
            ],
        ];

        $ids = [];
        foreach ($players as $p) {
            $existing = $userModel->where('email', $p['email'])->first();
            if ($existing) {
                $ids[$p['email']] = (int) $existing['id'];
                echo "  ya existe: {$p['email']}\n";
                continue;
            }

            $uid = $userModel->insert([
                'name'     => $p['name'],
                'email'    => $p['email'],
                'password' => 'Test1234!',
                'role'     => 'player',
                'status'   => 'active',
            ], true);

            if (!$uid) {
                echo "  ERROR creando {$p['email']}: " . implode(' ', $userModel->errors()) . "\n";
                continue;
            }

            $ids[$p['email']] = (int) $uid;

            $profileModel->insert([
                'player_id' => $uid,
                'position'  => $p['positions'],
                'height'    => $p['height'],
                'weight'    => $p['weight'],
                'category'  => $p['category'],
            ]);

            echo "  creado alumno: {$p['name']} ({$p['email']})\n";
        }

        return $ids;
    }

    // ────────────────────────────────────────────────────────────────
    //  Clases de prueba (TICKET-001 y TICKET-004)
    // ────────────────────────────────────────────────────────────────

    private function seedTestClasses(array $playerIds): void
    {
        $db      = \Config\Database::connect();
        $service = new ClasesService();

        $userModel = new UserModel();
        $admin     = $userModel->where('role', 'admin')->where('status', 'active')->first();
        $staff     = $userModel->where('role', 'staff')->where('status', 'active')->first();
        $coach     = $userModel->where('role', 'coach')->where('status', 'active')->first();

        if (!$admin || !$staff || !$coach) {
            echo "  aviso: falta un admin/staff/coach activo en la BD, se omiten algunas clases de prueba.\n";
        }

        $players = array_values($playerIds);
        $p1 = $players[0] ?? null;
        $p2 = $players[1] ?? null;

        $creatorId = (int) ($admin['id'] ?? $userModel->where('role', 'superadmin')->first()['id'] ?? 1);

        // A) Responsable = admin (verifica TICKET-001)
        if ($admin) {
            $this->createTestSession($service, $db, $creatorId, [
                'title'        => 'TEST - Responsable admin',
                'session_date' => date('Y-m-d', strtotime('+2 days')),
                'start_time'   => '17:00',
                'end_time'     => '18:00',
                'session_type' => 'coach',
                'coach_ids'    => [(int) $admin['id']],
                'player_ids'   => $p1 ? [$p1] : [],
            ]);
        }

        // B) Responsable = staff (session_type=staff)
        if ($staff) {
            $this->createTestSession($service, $db, $creatorId, [
                'title'        => 'TEST - Responsable staff',
                'session_date' => date('Y-m-d', strtotime('+2 days')),
                'start_time'   => '18:30',
                'end_time'     => '19:30',
                'session_type' => 'staff',
                'coach_ids'    => [(int) $staff['id']],
                'player_ids'   => $p2 ? [$p2] : [],
            ]);
        }

        // C) Programada (no completada) con un alumno marcado presente
        //    (verifica TICKET-004: feedback desbloqueado sin completar)
        if ($coach) {
            $sid = $this->createTestSession($service, $db, $creatorId, [
                'title'        => 'TEST - Feedback sin completar (alumno presente)',
                'session_date' => date('Y-m-d'),
                'start_time'   => '09:00',
                'end_time'     => '10:00',
                'session_type' => 'coach',
                'coach_ids'    => [(int) $coach['id']],
                'player_ids'   => $p1 ? [$p1] : [],
            ]);
            if ($sid && $p1) {
                $service->updateAttendance($sid, [$p1 => 'present']);
            }
        }

        // D) Sesión completada (caso base: feedback también debe funcionar)
        if ($coach) {
            $sid = $this->createTestSession($service, $db, $creatorId, [
                'title'        => 'TEST - Sesión completada',
                'session_date' => date('Y-m-d', strtotime('-1 day')),
                'start_time'   => '09:00',
                'end_time'     => '10:00',
                'session_type' => 'coach',
                'coach_ids'    => [(int) $coach['id']],
                'player_ids'   => $p2 ? [$p2] : [],
            ]);
            if ($sid) {
                $service->cerrarSesion($sid, $creatorId);
            }
        }
    }

    // ────────────────────────────────────────────────────────────────
    //  Clases a la MISMA HORA (bug del calendario: se solapaban)
    // ────────────────────────────────────────────────────────────────

    /**
     * Reproduce el error reportado: varias clases distintas en la misma
     * franja horaria. Crea:
     *   - 2 clases simultáneas (miércoles siguiente, 11:00-12:00) → deben
     *     verse una al lado de otra en las vistas Semana y Día.
     *   - 4 clases simultáneas (jueves siguiente, 10:00-11:00) → no caben
     *     en columnas, debe aparecer el chip "4 clases · 10:00" que abre
     *     el pop-up para elegir cuál ver.
     *
     * Se colocan en la semana siguiente (fechas dentro de un mismo mes)
     * para que se vean también en la vista Semana sin cruzar el límite
     * de mes.
     */
    private function seedOverlappingClasses(array $playerIds): void
    {
        $db      = \Config\Database::connect();
        $service = new ClasesService();

        $userModel = new UserModel();
        $admin     = $userModel->where('role', 'admin')->where('status', 'active')->first();
        $coach     = $userModel->where('role', 'coach')->where('status', 'active')->first();
        $staff     = $userModel->where('role', 'staff')->where('status', 'active')->first();

        $creatorId = (int) ($admin['id']
            ?? $userModel->where('role', 'superadmin')->first()['id']
            ?? 1);

        $players = array_values($playerIds);

        // Responsable por defecto: el primer coach; si no hay, admin.
        $resp = (int) ($coach['id'] ?? $admin['id'] ?? $creatorId);
        $respStaff = (int) ($staff['id'] ?? $resp);

        // Semana siguiente, mismo mes a poder ser (la vista Semana solo
        // carga los eventos del mes del lunes de esa semana).
        $mondayNext = strtotime('monday next week');
        $date2 = date('Y-m-d', strtotime('+2 days', $mondayNext)); // miércoles
        $date4 = date('Y-m-d', strtotime('+3 days', $mondayNext)); // jueves

        $sessions = [
            // 2 simultáneas → una al lado de otra
            ['title' => 'TEST - Solape A (misma hora)', 'session_date' => $date2,
             'start_time' => '11:00', 'end_time' => '12:00', 'session_type' => 'coach',
             'coach_ids' => [$resp], 'player_ids' => isset($players[0]) ? [$players[0]] : []],
            ['title' => 'TEST - Solape B (misma hora)', 'session_date' => $date2,
             'start_time' => '11:00', 'end_time' => '12:30', 'session_type' => 'staff',
             'coach_ids' => [$respStaff], 'player_ids' => isset($players[1]) ? [$players[1]] : []],

            // 4 simultáneas → chip + pop-up
            ['title' => 'TEST - Solape lleno 1', 'session_date' => $date4,
             'start_time' => '10:00', 'end_time' => '11:00', 'session_type' => 'coach',
             'coach_ids' => [$resp], 'player_ids' => isset($players[0]) ? [$players[0]] : []],
            ['title' => 'TEST - Solape lleno 2', 'session_date' => $date4,
             'start_time' => '10:00', 'end_time' => '11:00', 'session_type' => 'coach',
             'coach_ids' => [$resp], 'player_ids' => isset($players[1]) ? [$players[1]] : []],
            ['title' => 'TEST - Solape lleno 3', 'session_date' => $date4,
             'start_time' => '10:00', 'end_time' => '11:30', 'session_type' => 'staff',
             'coach_ids' => [$respStaff], 'player_ids' => isset($players[2]) ? [$players[2]] : []],
            ['title' => 'TEST - Solape lleno 4', 'session_date' => $date4,
             'start_time' => '10:00', 'end_time' => '11:00', 'session_type' => 'coach',
             'coach_ids' => [$resp], 'player_ids' => []],
        ];

        foreach ($sessions as $s) {
            $this->createTestSession($service, $db, $creatorId, $s);
        }
    }

    /**
     * Crea una sesión de prueba si no existe ya una con el mismo título
     * (idempotente). Devuelve el id de la sesión (nueva o existente).
     */
    private function createTestSession(ClasesService $service, $db, int $creatorId, array $data): ?int
    {
        $existing = $db->table('class_sessions')->where('title', $data['title'])->get()->getRowArray();
        if ($existing) {
            echo "  ya existe sesión: {$data['title']}\n";
            return (int) $existing['id'];
        }

        $data['type']         = 'single';
        $data['class_format'] = 'individual';
        $result = $service->quickCreate($data, $creatorId);

        if (!$result['success']) {
            echo "  ERROR creando sesión '{$data['title']}': " . ($result['error'] ?? '?') . "\n";
            return null;
        }

        echo "  creada sesión: {$data['title']}\n";
        return (int) $result['id'];
    }

    // ────────────────────────────────────────────────────────────────
    //  Tickets de soporte de los 4 fixes (visibles en /tickets)
    // ────────────────────────────────────────────────────────────────

    private function seedResolvedTickets(): void
    {
        $ticketModel = new TicketModel();
        $replyModel  = new TicketReplyModel();
        $userModel   = new UserModel();

        $reporter = $userModel->where('role', 'superadmin')->first()
            ?? $userModel->where('role', 'admin')->first();
        if (!$reporter) {
            echo "  aviso: no hay superadmin/admin para autoría de tickets, se omiten.\n";
            return;
        }
        $reporterId = (int) $reporter['id'];

        $tickets = [
            [
                'title'       => 'Los admin y el staff no pueden impartir clases',
                'description' => "Al crear/editar una sesión, el selector de responsable solo lista usuarios "
                    . "con rol coach (o staff en clases de staff). Un admin o superadmin nunca aparece, así "
                    . "que no se le puede asignar como responsable de una clase.",
                'category' => 'bug',
                'priority' => 'alta',
                'reply'    => "Corregido en la rama fix/clases-asignar-admin-staff: "
                    . "ClasesService::getCoachOptions()/getStaffOptions() ahora incluyen también admin y "
                    . "superadmin (RESPONSABLE_TECNICO_ROLES / RESPONSABLE_STAFF_ROLES). "
                    . "Ver docs/tickets/TICKET-001-clases-admin-staff.md y "
                    . "tests/unit/ClasesServiceResponsablesTest.php. Probado con las sesiones "
                    . "\"TEST - Responsable admin\" / \"TEST - Responsable staff\".",
            ],
            [
                'title'       => 'Desbordamiento de texto en la ficha de alumno (posición)',
                'description' => "En la ficha del alumno, la tarjeta POSICIÓN con el valor "
                    . "\"Extremo/mediapunta\" desborda el ancho de la tarjeta y se solapa con la tarjeta "
                    . "CATEGORÍA contigua en móvil.",
                'category' => 'bug',
                'priority' => 'media',
                'reply'    => "Corregido en dos capas: (1) fix/overflow-texto-ficha añade overflow-wrap/"
                    . "min-width a .metric-card y .metric-value; (2) feat/multiples-posiciones-alumno "
                    . "resuelve la causa de fondo — la posición ya no es un único texto libre, se elige de "
                    . "un catálogo (checkboxes múltiples) y se muestra como lista en la ficha. Los valores "
                    . "antiguos en texto libre se siguen leyendo bien. Ver "
                    . "docs/tickets/TICKET-002-overflow-texto-ficha-alumno.md, "
                    . "tests/unit/MetricCardOverflowTest.php y tests/unit/PlayerProfilePositionsTest.php. "
                    . "Probado con los alumnos TEST Alumno Multiposición / TEST Alumno Legacy / "
                    . "TEST Alumno Tres Posiciones.",
            ],
            [
                'title'       => 'Habilitar recuperación de contraseña por email + correo de bienvenida',
                'description' => "El flujo de \"¿Olvidaste tu contraseña?\" no entregaba el correo en "
                    . "producción (remitente de pruebas de Resend). Tampoco existía correo de confirmación/"
                    . "bienvenida al dar de alta un alumno, entrenador o staff.",
                'category' => 'mejora',
                'priority' => 'alta',
                'reply'    => "Implementado en feat/email-recuperar-password-y-bienvenida: MAIL_FROM pasa a "
                    . "un remitente con dominio propio, MailService registra cada envío/fallo en email_log, "
                    . "y se añade MailService::sendWelcomeEmail() disparado al crear alumno/entrenador/"
                    . "staff. Bloqueante detectado y corregido en curso: la RESEND_API_KEY estaba revocada "
                    . "(rotada) y falta verificar el dominio jppreparation.com en Resend antes de que la "
                    . "entrega funcione en real. Ver docs/tickets/TICKET-003-email-recuperacion-y-bienvenida.md "
                    . "y tests/unit/MailServiceTest.php.",
            ],
            [
                'title'       => 'El feedback "Después" de una sesión estaba bloqueado sin motivo',
                'description' => "El textarea de feedback post-sesión (post_notes / post_obs) solo era "
                    . "editable si la sesión estaba en estado completed. Mientras seguía scheduled — aunque "
                    . "ya se hubiera impartido y pasado lista — quedaba bloqueado.",
                'category' => 'bug',
                'priority' => 'media',
                'reply'    => "Corregido en fix/feedback-textarea-desbloqueo: nuevo helper estático "
                    . "ClasesService::isFeedbackUnlocked() — se desbloquea si la sesión está completed, si "
                    . "ya se pasó lista, o si algún alumno está marcado como presente. Ver "
                    . "docs/tickets/TICKET-004-feedback-textarea-bloqueado.md y "
                    . "tests/unit/ClasesFeedbackUnlockTest.php. Probado con la sesión "
                    . "\"TEST - Feedback sin completar (alumno presente)\".",
            ],
        ];

        foreach ($tickets as $t) {
            $existing = $ticketModel->where('title', $t['title'])->first();
            if ($existing) {
                echo "  ya existe ticket: {$t['title']}\n";
                continue;
            }

            $id = $ticketModel->createTicket([
                'user_id'     => $reporterId,
                'title'       => $t['title'],
                'description' => $t['description'],
                'category'    => $t['category'],
                'priority'    => $t['priority'],
            ]);

            if (!$id) {
                echo "  ERROR creando ticket: {$t['title']}\n";
                continue;
            }

            $replyModel->createReply($id, $reporterId, $t['reply']);
            $ticketModel->updateStatus($id, 'resuelto');

            echo "  creado ticket resuelto: {$t['title']}\n";
        }
    }
}
