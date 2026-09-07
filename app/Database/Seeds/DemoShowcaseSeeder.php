<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use App\Models\UserModel;
use App\Models\PlayerProfileModel;
use App\Models\PlayerBonoModel;
use App\Models\BonoTypeModel;
use App\Models\NotificationModel;
use App\Services\ClasesService;
use App\Services\DocumentService;

/**
 * Enriquece las TRES cuentas de invitado de la demo (Dirección, Entrenador,
 * Alumno) para que, al entrar como cada una, se vea de un vistazo todo lo
 * que su rol puede hacer:
 *
 *   Entrenador · calendario lleno (grupo recurrente + sesiones sueltas,
 *               pasadas con lista/feedback y futuras con y sin plan),
 *               anotaciones de seguimiento sobre sus alumnos, carpeta de
 *               metodología, conversaciones y una incidencia técnica.
 *   Alumno    · las mismas sesiones desde su lado (asistencia variada,
 *               avisos de ausencia), bono activo con consumo + bono
 *               histórico agotado, feedback del entrenador, anotaciones
 *               públicas, documentos personales y una consulta abierta.
 *   Dirección · bonos a punto de caducar, incidencia asignada, circular
 *               enviada a toda la academia (además de las stats que ya
 *               alimenta BulkDemoDataSeeder).
 *
 * Se ejecuta el ÚLTIMO en la cadena de DemoSeeder (necesita los usuarios,
 * bonos y clases de BulkDemoDataSeeder y las cuentas de DemoGuestsSeeder).
 *
 * Idempotente: si el invitado-entrenador ya tiene sesiones asignadas, no
 * hace nada. Sobrevive al reset nocturno porque DemoSeeder lo vuelve a
 * lanzar sobre las tablas recién vaciadas.
 *
 * Ejecutar a mano:  php spark db:seed DemoShowcaseSeeder
 */
class DemoShowcaseSeeder extends Seeder
{
    private UserModel $users;
    private ClasesService $clases;

    private int $gAdmin  = 0;
    private int $gCoach  = 0;
    private int $gPlayer = 0;
    private int $realAdmin = 0;
    private int $realCoach = 0;

    /** @var int[] alumnos "de mentira" que forman los grupos del invitado */
    private array $pool = [];

    private const REASONS = ['Enfermedad', 'Viaje', 'Personal', 'Sin aviso', 'Lesión', 'Otro'];

    public function run()
    {
        helper('demo');
        $this->db     = $this->db ?: \Config\Database::connect();
        $this->users  = new UserModel();
        $this->clases = new ClasesService();

        $acc     = demo_guest_accounts();
        $gAdmin  = $this->users->where('email', $acc['admin']['email'])->first();
        $gCoach  = $this->users->where('email', $acc['coach']['email'])->first();
        $gPlayer = $this->users->where('email', $acc['player']['email'])->first();

        if (! $gAdmin || ! $gCoach || ! $gPlayer) {
            echo "DemoShowcaseSeeder: faltan cuentas de invitado; siembra DemoGuestsSeeder primero.\n";
            return;
        }
        $this->gAdmin  = (int) $gAdmin['id'];
        $this->gCoach  = (int) $gCoach['id'];
        $this->gPlayer = (int) $gPlayer['id'];

        // Idempotencia global.
        if ($this->db->table('class_session_coaches')->where('user_id', $this->gCoach)->countAllResults() > 0) {
            echo "DemoShowcaseSeeder: ya sembrado, se omite.\n";
            return;
        }

        $this->realAdmin = (int) ($this->users->whereIn('role', ['superadmin', 'admin'])
            ->where('email !=', $acc['admin']['email'])
            ->orderBy('id', 'ASC')->first()['id'] ?? $this->gAdmin);
        $this->realCoach = (int) ($this->users->where('role', 'coach')
            ->where('email !=', $acc['coach']['email'])
            ->orderBy('id', 'ASC')->first()['id'] ?? $this->gCoach);

        $this->pool = array_map('intval', array_column(
            $this->users->where('role', 'player')
                ->where('email !=', $acc['player']['email'])
                ->orderBy('id', 'ASC')->findAll(9),
            'id'
        ));
        if (count($this->pool) < 4) {
            echo "DemoShowcaseSeeder: no hay suficientes alumnos de demo; siembra BulkDemoDataSeeder.\n";
            return;
        }

        $this->tuneGuestPlayerProfile();
        $this->seedGuestBonos();
        $classId = $this->seedRecurringGroup();
        $this->completeRecurringPast($classId);
        $this->seedUpcomingStates($classId);
        $this->seedCoachSingles();
        $this->seedAnnotations();
        $this->seedDocuments();
        $this->seedDireccion();
        $this->seedGuestConversation();
        $this->seedTickets();

        echo "DemoShowcaseSeeder: invitados enriquecidos (dirección / entrenador / alumno).\n";
    }

    // ════════════════════════════════════════════════════════════════
    //  Ficha del alumno invitado
    // ════════════════════════════════════════════════════════════════

    private function tuneGuestPlayerProfile(): void
    {
        $profileModel = new PlayerProfileModel();
        $profile = $profileModel->where('player_id', $this->gPlayer)->first();

        $data = [
            'birth_date' => '2012-03-09',
            'height'     => 159,
            'weight'     => 48,
            'position'   => PlayerProfileModel::encodePositions(['interior', 'mediapunta']),
            'category'   => 'infantil',
            'team'       => 'CF Águilas A',
            'league'     => '1a Territorial',
        ];

        if ($profile) {
            $profileModel->update((int) $profile['id'], $data);
        } else {
            $profileModel->insert(array_merge(['player_id' => $this->gPlayer], $data));
        }
    }

    // ════════════════════════════════════════════════════════════════
    //  Bonos del alumno invitado
    // ════════════════════════════════════════════════════════════════

    private function seedGuestBonos(): void
    {
        $bonoTypeModel = new BonoTypeModel();
        $bonoModel     = new PlayerBonoModel();

        $t20 = $bonoTypeModel->where('sessions', 20)->first()
            ?: $bonoTypeModel->orderBy('sessions', 'DESC')->first();
        $t10 = $bonoTypeModel->where('sessions', 10)->first()
            ?: $bonoTypeModel->orderBy('sessions', 'ASC')->first();

        // Bono histórico ya agotado (temporada pasada) — aparece en el
        // historial de bonos del alumno.
        if ($t10) {
            $start = date('Y-m-d', strtotime('-165 days'));
            $bonoModel->insert([
                'player_id'          => $this->gPlayer,
                'bono_type_id'       => (int) $t10['id'],
                'sessions_total'     => (int) $t10['sessions'],
                'sessions_remaining' => 0,
                'start_date'         => $start,
                'expires_at'         => date('Y-m-d', strtotime($start . ' +' . (int) $t10['validity_days'] . ' days')),
                'notes'              => 'Bono de la temporada anterior. Consumido por completo.',
                'created_by'         => $this->realAdmin,
            ]);
        }

        // Bono activo: se irá descontando al cerrar las sesiones pasadas
        // (ver completeRecurringPast / seedCoachSingles).
        if ($t20) {
            $start = date('Y-m-d', strtotime('-52 days'));
            $bonoModel->insert([
                'player_id'          => $this->gPlayer,
                'bono_type_id'       => (int) $t20['id'],
                'sessions_total'     => (int) $t20['sessions'],
                'sessions_remaining' => (int) $t20['sessions'],
                'start_date'         => $start,
                'expires_at'         => date('Y-m-d', strtotime($start . ' +' . (int) $t20['validity_days'] . ' days')),
                'notes'              => 'Bono en curso. Se descuenta 1 sesión por asistencia confirmada.',
                'created_by'         => $this->realAdmin,
            ]);
        }
    }

    // ════════════════════════════════════════════════════════════════
    //  Grupo recurrente del invitado (coach + alumno comparten calendario)
    // ════════════════════════════════════════════════════════════════

    private function seedRecurringGroup(): ?int
    {
        $title = 'Tecnificación · Grupo del invitado';
        $group = array_merge([$this->gPlayer], array_slice($this->pool, 0, 3)); // 4 alumnos

        $from = date('Y-m-d', strtotime('-6 weeks monday'));
        $to   = date('Y-m-d', strtotime('+7 weeks sunday'));

        $location = (int) ($this->db->table('locations')->where('type', 'pitch')
            ->orderBy('id', 'ASC')->get()->getRowArray()['id'] ?? 0) ?: null;

        $res = $this->clases->quickCreate([
            'type'             => 'recurring',
            'title'            => $title,
            'description'      => 'Grupo de tecnificación de referencia para la demo. Martes y jueves.',
            'class_format'     => 'pareja',
            'session_type'     => 'coach',
            'recurrence_days'  => [2, 4], // martes y jueves
            'recurrence_start' => $from,
            'recurrence_end'   => $to,
            'start_time'       => '17:30',
            'end_time'         => '19:00',
            'location_id'      => $location,
            'coach_ids'        => [$this->gCoach],
            'player_ids'       => $group,
        ], $this->gCoach);

        if (empty($res['success'])) {
            echo "  ERROR grupo recurrente: " . ($res['error'] ?? '?') . "\n";
            return null;
        }
        echo "  grupo recurrente creado: {$res['count']} sesiones.\n";
        return (int) ($res['class_id'] ?? 0) ?: null;
    }

    /**
     * Cierra las sesiones pasadas del grupo: asistencia variada, lista
     * pasada, feedback en las más recientes y descuento de bono al alumno
     * invitado cuando asiste.
     */
    private function completeRecurringPast(?int $classId): void
    {
        if (! $classId) {
            return;
        }

        $past = $this->db->table('class_sessions')
            ->where('class_id', $classId)
            ->where('session_date <', date('Y-m-d'))
            ->orderBy('session_date', 'ASC')
            ->get()->getResultArray();

        $total   = count($past);
        $deducts = 0;

        foreach ($past as $idx => $s) {
            $sid     = (int) $s['id'];
            $players = array_map(
                'intval',
                array_column($this->clases->getPlayersForSession($sid), 'user_id')
            );
            $recent = $idx >= $total - 3; // las 3 últimas llevan feedback

            $map = $reasons = $notes = [];
            foreach ($players as $pi => $uid) {
                // El invitado falta 2 veces (una justificada, otra sin aviso);
                // el resto rota entre presente y alguna ausencia puntual.
                if ($uid === $this->gPlayer && $idx === 2) {
                    $map[$uid] = 'absent';
                    $reasons[$uid] = 'Enfermedad';
                    $notes[$uid] = 'Avisó la tarde anterior: proceso febril.';
                } elseif ($uid === $this->gPlayer && $idx === 5) {
                    $map[$uid] = 'absent';
                    $reasons[$uid] = 'Sin aviso';
                } elseif ($uid !== $this->gPlayer && (($idx + $pi) % 7 === 0)) {
                    $map[$uid] = 'absent';
                    $reasons[$uid] = self::REASONS[($idx + $pi) % count(self::REASONS)];
                } else {
                    $map[$uid] = 'present';
                }
            }

            $this->clases->guardarLista($sid, $this->gCoach, $map, $reasons, $notes);
            $this->clases->cerrarSesion($sid, $this->gCoach);

            if ($recent) {
                $this->db->table('class_sessions')->where('id', $sid)->update([
                    'focus'      => $this->pick($idx, [
                        'Conducción y salida de presión en espacios reducidos.',
                        'Finalización tras combinación por dentro.',
                        'Perfil de recepción y primer control orientado.',
                    ]),
                    'pre_notes'  => 'Calentamiento con balón (10\'), rondo 4v2 (15\'), parte principal en dos estaciones y juego final 3v3 a 4 porterías.',
                    'post_notes' => 'Buen ritmo de sesión. El grupo entiende la salida por dentro; falta agresividad en el 3v3 final.',
                ]);

                foreach ($map as $uid => $att) {
                    if ($att !== 'present') {
                        continue;
                    }
                    $isGuest = $uid === $this->gPlayer;
                    $this->clases->saveObservations($sid, [
                        'player_obs' => [
                            $uid => [
                                'pre'  => $isGuest
                                    ? 'Objetivo individual: no perder el balón de espaldas, buscar el apoyo del lateral.'
                                    : 'Trabajar la orientación del cuerpo antes de recibir.',
                                'post' => $isGuest
                                    ? 'Muy bien en la recepción entre líneas. A mejorar: la toma de decisión cuando llega la presión por detrás.'
                                    : 'Cumple. Sigue algo justo en el 1v1 defensivo.',
                            ],
                        ],
                    ]);
                }
            }

            // Descuento de bono al invitado cuando asiste.
            if (($map[$this->gPlayer] ?? '') === 'present') {
                $r = $this->clases->deductBonoForPlayer($sid, $this->gPlayer);
                if (! empty($r['success'])) {
                    $deducts++;
                }
            }
        }

        echo "  sesiones pasadas del grupo cerradas: {$total} (bono del invitado: {$deducts} descuentos).\n";
    }

    /**
     * Da estados a las próximas sesiones del grupo: confirmaciones,
     * un aviso de ausencia anticipado y plan de sesión sólo en algunas.
     */
    private function seedUpcomingStates(?int $classId): void
    {
        if (! $classId) {
            return;
        }

        $next = $this->db->table('class_sessions')
            ->where('class_id', $classId)
            ->where('session_date >=', date('Y-m-d'))
            ->where('status', 'scheduled')
            ->orderBy('session_date', 'ASC')
            ->get()->getResultArray();

        foreach (array_values($next) as $i => $s) {
            $sid = (int) $s['id'];

            if ($i === 0) {
                // Próxima sesión: plan preparado + confirmaciones.
                $this->db->table('class_sessions')->where('id', $sid)->update([
                    'focus'     => 'Presión tras pérdida (contrapresión) en zona media.',
                    'pre_notes' => 'Activación 8\', 2 estaciones de contrapresión, juego de posición 4v4+3 y partido final.',
                ]);
                foreach ($this->clases->getPlayersForSession($sid) as $p) {
                    $this->db->table('class_session_players')
                        ->where('id', $p['id'])
                        ->update(['attendance' => 'confirmed', 'responded_at' => date('Y-m-d H:i:s', strtotime('-1 day'))]);
                }
            } elseif ($i === 1) {
                // Segunda: el alumno invitado avisa de que no puede ir.
                $this->clases->notifyAbsence(
                    $this->gPlayer,
                    $sid,
                    'Tengo partido de liga con mi equipo, no podré asistir.'
                );
                $this->db->table('class_session_players')
                    ->where('session_id', $sid)->where('user_id', $this->gPlayer)
                    ->update(['attendance' => 'declined', 'responded_at' => date('Y-m-d H:i:s', strtotime('-6 hours'))]);
            } elseif ($i === 2) {
                $this->db->table('class_sessions')->where('id', $sid)->update([
                    'focus' => 'Finalización: llegada desde segunda línea.',
                ]);
            }
            // El resto se quedan sin plan a propósito (para ver el aviso
            // de "prepara la sesión").
        }
    }

    // ════════════════════════════════════════════════════════════════
    //  Sesiones sueltas del entrenador invitado
    // ════════════════════════════════════════════════════════════════

    private function seedCoachSingles(): void
    {
        $location = (int) ($this->db->table('locations')->orderBy('id', 'ASC')
            ->get()->getRowArray()['id'] ?? 0) ?: null;

        // ── Pasadas (con lista + feedback) ─────────────────────────────
        // El 4º elemento marca si el alumno invitado participa y cómo
        // termina: 'present' | 'absent' | null (no participa).
        $pastPlan = [
            [-26, 'Sesión individual · control y pase',    'individual', 'present'],
            [-19, 'Sesión individual · finalización',      'individual', 'absent'],
            [-12, 'Sesión en pareja · 1v1 ofensivo',       'pareja',     'present'],
            [-7,  'Sesión individual · toma de decisión',  'individual', null],
            [-4,  'Sesión individual · perfil izquierdo',  'individual', 'present'],
        ];
        foreach ($pastPlan as $k => [$off, $title, $fmt, $guest]) {
            $date = date('Y-m-d', strtotime("{$off} days"));
            $poolPick = $this->pool[$k % count($this->pool)];
            $players = $fmt === 'pareja'
                ? [$this->gPlayer, $this->pool[3] ?? $this->pool[0]]
                : ($guest ? [$this->gPlayer] : [$poolPick]);

            $res = $this->clases->quickCreate([
                'type'         => 'single',
                'title'        => $title,
                'class_format' => $fmt,
                'session_type' => 'coach',
                'session_date' => $date,
                'start_time'   => sprintf('%02d:00', 16 + ($k % 3)),
                'end_time'     => sprintf('%02d:00', 17 + ($k % 3)),
                'location_id'  => $location,
                'focus'        => 'Objetivo de la sesión: ' . mb_strtolower(explode('·', $title)[1] ?? $title),
                'coach_ids'    => [$this->gCoach],
                'player_ids'   => $players,
            ], $this->gCoach);

            if (empty($res['success'])) {
                echo "  ERROR single '{$title}': " . ($res['error'] ?? '?') . "\n";
                continue;
            }
            $sid = (int) $res['id'];

            $map = [];
            foreach ($players as $uid) {
                $map[$uid] = ($uid === $this->gPlayer && $guest === 'absent') ? 'absent' : 'present';
            }
            $reasons = ($map[$this->gPlayer] ?? '') === 'absent'
                ? [$this->gPlayer => 'Lesión'] : [];
            $notes = ($map[$this->gPlayer] ?? '') === 'absent'
                ? [$this->gPlayer => 'Molestia en el gemelo; pautada recuperación de 5 días.'] : [];

            $this->clases->guardarLista($sid, $this->gCoach, $map, $reasons, $notes);
            $this->clases->cerrarSesion($sid, $this->gCoach);
            $this->db->table('class_sessions')->where('id', $sid)->update([
                'post_notes' => 'Sesión cumplida. Se lleva vídeo de las repeticiones buenas para revisar en casa.',
            ]);
            foreach ($map as $uid => $att) {
                if ($att === 'present') {
                    $this->clases->saveObservations($sid, [
                        'player_obs' => [$uid => [
                            'pre'  => 'Repeticiones de calidad por encima de cantidad. Ojo a la postura.',
                            'post' => 'Progresa bien. Consolidar el gesto a velocidad de partido.',
                        ]],
                    ]);
                    if ($uid === $this->gPlayer) {
                        $this->clases->deductBonoForPlayer($sid, $this->gPlayer);
                    }
                }
            }
        }

        // ── Futuras ────────────────────────────────────────────────────
        $futurePlan = [
            [3,  'Sesión individual · conducción',      true],
            [6,  'Sesión en pareja · combinación',      true],
            [9,  'Sesión individual · finalización',    false],
            [13, 'Sesión individual · revisión de vídeo', false],
        ];
        foreach ($futurePlan as $k => [$off, $title, $withPlan]) {
            $date = date('Y-m-d', strtotime("+{$off} days"));
            $fmt  = str_contains($title, 'pareja') ? 'pareja' : 'individual';
            $players = $fmt === 'pareja'
                ? [$this->gPlayer, $this->pool[4] ?? $this->pool[1]]
                : [$k % 2 === 0 ? $this->gPlayer : $this->pool[$k % count($this->pool)]];

            $res = $this->clases->quickCreate([
                'type'         => 'single',
                'title'        => $title,
                'class_format' => $fmt,
                'session_type' => 'coach',
                'session_date' => $date,
                'start_time'   => '17:00',
                'end_time'     => '18:00',
                'location_id'  => $location,
                'focus'        => $withPlan ? ('Objetivo: ' . mb_strtolower(explode('·', $title)[1] ?? $title)) : null,
                'pre_notes'    => $withPlan ? 'Bloque técnico (25\') + aplicación en juego reducido (20\').' : null,
                'coach_ids'    => [$this->gCoach],
                'player_ids'   => $players,
            ], $this->gCoach);

            if (! empty($res['success']) && $k === 3) {
                // Una futura cancelada, para ver ese estado.
                $this->db->table('class_sessions')->where('id', (int) $res['id'])
                    ->update(['status' => 'cancelled']);
            }
        }

        echo "  sesiones sueltas del entrenador: 5 pasadas + 4 futuras.\n";
    }

    // ════════════════════════════════════════════════════════════════
    //  Anotaciones de seguimiento
    // ════════════════════════════════════════════════════════════════

    private function seedAnnotations(): void
    {
        $rows = [
            // Sobre el alumno invitado (las verá en "Mi ficha" salvo la interna).
            [$this->gPlayer, $this->gCoach, 'public', '-24 days',
                'Gran evolución en el control orientado bajo presión. Ya no pierde el balón de espaldas con tanta facilidad. Seguimos insistiendo en levantar la cabeza antes de recibir.'],
            [$this->gPlayer, $this->realCoach, 'public', '-15 days',
                'Actitud competitiva excelente y muy buen compañero en el grupo. A trabajar: la finalización con pierna izquierda y el timing de la llegada al área.'],
            [$this->gPlayer, $this->realAdmin, 'public', '-5 days',
                'Renovación de bono prevista para el próximo trimestre. Familia informada.'],
            [$this->gPlayer, $this->gCoach, 'internal', '-6 days',
                'Nota interna: hablar con la familia sobre la carga de partidos del fin de semana; llega algo fatigado a la sesión del martes.'],

            // Del entrenador invitado sobre otros alumnos de su grupo.
            [$this->pool[0], $this->gCoach, 'public', '-10 days',
                'Muy buen ritmo de aprendizaje en el pase entre líneas. Puede dar el salto al grupo de rendimiento el próximo trimestre.'],
            [$this->pool[1], $this->gCoach, 'public', '-8 days',
                'Necesita constancia: cuando entrena dos días seguidos, el nivel sube mucho. Insistir en la asistencia.'],
            [$this->pool[2], $this->gCoach, 'internal', '-3 days',
                'Nota interna: revisar con fisio una molestia recurrente en el tobillo derecho antes de forzar en los 1v1.'],
        ];

        foreach ($rows as [$pid, $aid, $type, $when, $content]) {
            $ts = date('Y-m-d H:i:s', strtotime($when));
            $this->db->table('player_annotations')->insert([
                'player_id'  => $pid,
                'author_id'  => $aid,
                'type'       => $type,
                'content'    => $content,
                'created_at' => $ts,
                'updated_at' => $ts,
            ]);
        }
        echo "  anotaciones de seguimiento: " . count($rows) . ".\n";
    }

    // ════════════════════════════════════════════════════════════════
    //  Documentación
    // ════════════════════════════════════════════════════════════════

    private function seedDocuments(): void
    {
        $docs = new DocumentService();

        // ── Carpeta interna del cuerpo técnico (el coach tiene lectura) ──
        $internalId = (int) $this->db->table('document_folders')->insert([
            'name'       => 'Metodología y recursos del cuerpo técnico',
            'slug'       => 'metodologia-cuerpo-tecnico-demo',
            'type'       => 'internal',
            'icon'       => 'bi-briefcase-fill',
            'color'      => 'indigo',
            'owner_id'   => null,
            'created_by' => $this->realAdmin,
            'status'     => 'active',
            'created_at' => date('Y-m-d H:i:s', strtotime('-40 days')),
            'updated_at' => date('Y-m-d H:i:s', strtotime('-40 days')),
        ]) ? (int) $this->db->insertID() : 0;

        if ($internalId) {
            $this->db->table('folder_permissions')->insert([
                'folder_id'  => $internalId,
                'user_id'    => $this->gCoach,
                'can_read'   => 1,
                'can_write'  => 0,
                'granted_by' => $this->realAdmin,
                'created_at' => date('Y-m-d H:i:s', strtotime('-40 days')),
            ]);
            $this->addPdf($internalId, 'internal', null, $this->realAdmin,
                'Guia-metodologica-tecnificacion-2026.pdf',
                'Principios de juego y progresiones por edad.', '-38 days', [
                    'Metodologia de tecnificacion 2026',
                    '1. Principios: iniciativa, ritmo, ocupacion de espacios.',
                    '2. Progresiones por categoria (prebenjamin a juvenil).',
                    '3. Bateria de ejercicios por objetivo tecnico.',
                    '4. Criterios de evaluacion y paso de grupo.',
                ]);
            $this->addPdf($internalId, 'internal', null, $this->realAdmin,
                'Modelo-informe-individual.pdf',
                'Plantilla para el informe trimestral del alumno.', '-30 days', [
                    'Modelo de informe individual del alumno',
                    'Datos, categoria y posicion.',
                    'Valoracion tecnica / tactica / fisica / actitudinal.',
                    'Objetivos para el proximo trimestre.',
                ]);
        }

        // ── Carpeta pública de circulares ──────────────────────────────
        $publicId = (int) $this->db->table('document_folders')->insert([
            'name'       => 'Circulares y calendarios',
            'slug'       => 'circulares-calendarios-demo',
            'type'       => 'public',
            'icon'       => 'bi-megaphone-fill',
            'color'      => 'blue',
            'owner_id'   => null,
            'created_by' => $this->realAdmin,
            'status'     => 'active',
            'created_at' => date('Y-m-d H:i:s', strtotime('-20 days')),
            'updated_at' => date('Y-m-d H:i:s', strtotime('-20 days')),
        ]) ? (int) $this->db->insertID() : 0;

        if ($publicId) {
            $this->addPdf($publicId, 'public', null, $this->realAdmin,
                'Calendario-octubre.pdf', 'Calendario de sesiones del mes.', '-12 days', [
                    'Calendario de octubre',
                    'Grupos de tecnificacion: martes y jueves 17:30-19:00.',
                    'Sesiones individuales: segun cita con el entrenador.',
                    'Cierre por festivo local: 12 de octubre.',
                ]);
            $this->addPdf($publicId, 'public', null, $this->realAdmin,
                'Normativa-de-uso-de-instalaciones.pdf', null, '-18 days', [
                    'Normativa de uso de instalaciones',
                    'Acceso 10 minutos antes de la sesion.',
                    'Uso obligatorio de espinilleras en campo.',
                    'Zona de familias delimitada.',
                ]);
        }

        // ── Carpeta personal del alumno invitado ───────────────────────
        $pf = $docs->getOrCreatePersonalFolder($this->gPlayer);
        $informeId = 0;
        if ($pf) {
            $informeId = $this->addPdf((int) $pf['id'], 'personal', $this->gPlayer, $this->realCoach,
                'Informe-seguimiento-septiembre.pdf',
                'Informe trimestral elaborado por el entrenador.', '-4 days', [
                    'Informe de seguimiento - septiembre',
                    'Tecnica: control orientado en progreso, buen pase corto.',
                    'Tactica: entiende la salida por dentro.',
                    'Fisico: mejorar resistencia en la parte final.',
                    'Objetivos: pierna izquierda y llegada al area.',
                ]);
            $this->addPdf((int) $pf['id'], 'personal', $this->gPlayer, $this->realAdmin,
                'Autorizacion-de-imagen-firmada.pdf', 'Documento entregado por la familia.', '-45 days', [
                    'Autorizacion de uso de imagen',
                    'Firmada por el tutor legal.',
                    'Valida para la temporada en curso.',
                ]);
        }

        // Enlaza una anotación pública al informe (aparece con adjunto).
        if ($informeId) {
            $ts = date('Y-m-d H:i:s', strtotime('-4 days'));
            $this->db->table('player_annotations')->insert([
                'player_id'   => $this->gPlayer,
                'author_id'   => $this->realCoach,
                'type'        => 'public',
                'content'     => 'Adjunto el informe de seguimiento de septiembre. Lo comentamos en la próxima sesión.',
                'document_id' => $informeId,
                'created_at'  => $ts,
                'updated_at'  => $ts,
            ]);
        }

        // ── Carpeta personal del entrenador invitado ───────────────────
        $cf = $docs->getOrCreatePersonalFolder($this->gCoach);
        if ($cf) {
            $this->addPdf((int) $cf['id'], 'personal', $this->gCoach, $this->gCoach,
                'Planificacion-mensual-grupo-invitado.pdf',
                'Mi planificación del mes para el grupo.', '-9 days', [
                    'Planificacion mensual - Grupo del invitado',
                    'Semana 1-2: salida de balon y contrapresion.',
                    'Semana 3: finalizacion desde segunda linea.',
                    'Semana 4: evaluacion y sesion de juego libre.',
                ]);
        }

        echo "  documentos: carpeta interna + pública + personales.\n";
    }

    // ════════════════════════════════════════════════════════════════
    //  Dirección
    // ════════════════════════════════════════════════════════════════

    private function seedDireccion(): void
    {
        $bonoTypeModel = new BonoTypeModel();
        $bonoModel     = new PlayerBonoModel();
        $t = $bonoTypeModel->orderBy('sessions', 'ASC')->first();

        // 2 bonos de otros alumnos a punto de caducar (panel "Bonos por caducar").
        if ($t) {
            foreach ([array_slice($this->pool, 4, 1), array_slice($this->pool, 5, 1)] as $i => $slice) {
                $pid = $slice[0] ?? null;
                if (! $pid) {
                    continue;
                }
                $expires   = date('Y-m-d', strtotime('+' . (4 + $i) . ' days'));
                $startDays = (int) $t['validity_days'] - (4 + $i);
                $bonoModel->insert([
                    'player_id'          => (int) $pid,
                    'bono_type_id'       => (int) $t['id'],
                    'sessions_total'     => (int) $t['sessions'],
                    'sessions_remaining' => 1 + $i,
                    'start_date'         => date('Y-m-d', strtotime("-{$startDays} days")),
                    'expires_at'         => $expires,
                    'notes'              => 'Pendiente de renovación: avisar a la familia.',
                    'created_by'         => $this->realAdmin,
                ]);
            }
        }

        $notif = new NotificationModel();

        // Circular a toda la academia, ENVIADA por la dirección invitada.
        $everyone = array_map('intval', array_column(
            $this->users->where('status', 'active')->findAll(),
            'id'
        ));
        $notif->createWithRecipients([
            'sender_id' => $this->gAdmin,
            'type'      => 'group',
            'title'     => 'Cierre de instalaciones — 12 de octubre',
            'body'      => 'El próximo 12 de octubre (festivo local) no habrá sesiones. Las clases individuales de ese día se reprograman; el entrenador contactará con cada familia.',
        ], $everyone);

        // Aviso individual de la dirección al entrenador invitado.
        $notif->createWithRecipients([
            'sender_id' => $this->gAdmin,
            'type'      => 'individual',
            'title'     => 'Tienes 2 sesiones sin lista pasada esta semana',
            'body'      => 'Recuerda cerrar las sesiones del grupo de tecnificación para que los bonos se descuenten correctamente.',
        ], [$this->gCoach]);

        // Aviso al alumno invitado: bono por agotarse.
        $notif->createWithRecipients([
            'sender_id' => $this->realAdmin,
            'type'      => 'individual',
            'title'     => 'Tu bono está entrando en las últimas sesiones',
            'body'      => 'Te quedan pocas sesiones en el bono actual. Habla con recepción para renovarlo y no perder tu plaza en el grupo.',
        ], [$this->gPlayer]);

        echo "  dirección: circular enviada + 2 bonos por caducar + avisos.\n";
    }

    // ════════════════════════════════════════════════════════════════
    //  Conversación entrenador ↔ alumno (une las dos cuentas destacadas)
    // ════════════════════════════════════════════════════════════════

    private function seedGuestConversation(): void
    {
        [$u1, $u2] = $this->gCoach < $this->gPlayer
            ? [$this->gCoach, $this->gPlayer]
            : [$this->gPlayer, $this->gCoach];

        $first = date('Y-m-d H:i:s', strtotime('-2 days 18:30'));
        $this->db->table('conversations')->insert([
            'user1_id'        => $u1,
            'user2_id'        => $u2,
            'created_at'      => $first,
            'last_message_at' => $first,
        ]);
        $cid = (int) $this->db->insertID();

        $msgs = [
            [$this->gCoach,  'Buen trabajo hoy en la recepción entre líneas 👏 El jueves subimos la intensidad.', '-2 days 18:30'],
            [$this->gPlayer, '¡Gracias! ¿Llevo las botas de tacos o multitaco?', '-2 days 19:05'],
            [$this->gCoach,  'Multitaco, jugamos en el campo 2. Y confirma asistencia desde la app cuando puedas.', '-2 days 19:20'],
            [$this->gPlayer, 'Hecho, confirmada. Nos vemos el jueves.', '-1 days 09:10'],
        ];
        $last = $first;
        foreach ($msgs as $i => [$sender, $body, $when]) {
            $ts = date('Y-m-d H:i:s', strtotime($when));
            $this->db->table('messages')->insert([
                'conversation_id' => $cid,
                'sender_id'       => $sender,
                'body'            => $body,
                'read_at'         => $i === count($msgs) - 1 ? null : $ts,
                'created_at'      => $ts,
            ]);
            $last = $ts;
        }
        $this->db->table('conversations')->where('id', $cid)->update(['last_message_at' => $last]);

        echo "  conversación entrenador ↔ alumno creada.\n";
    }

    // ════════════════════════════════════════════════════════════════
    //  Incidencias
    // ════════════════════════════════════════════════════════════════

    private function seedTickets(): void
    {
        // Consulta abierta del alumno invitado.
        $this->db->table('tickets')->insert([
            'ticket_number' => 'DEMO-A-001',
            'user_id'       => $this->gPlayer,
            'category'      => 'consulta',
            'scope'         => 'academia',
            'priority'      => 'baja',
            'title'         => '¿Cómo renuevo mi bono?',
            'description'   => 'Me aparece que me quedan pocas sesiones. ¿Puedo renovar el bono desde la app o tengo que pasar por recepción?',
            'status'        => 'abierto',
            'origin'        => 'manual',
            'created_at'    => date('Y-m-d H:i:s', strtotime('-1 day')),
            'updated_at'    => date('Y-m-d H:i:s', strtotime('-1 day')),
        ]);

        // Incidencia técnica del entrenador invitado, ya asignada a dirección.
        $this->db->table('tickets')->insert([
            'ticket_number'     => 'DEMO-E-001',
            'user_id'           => $this->gCoach,
            'category'          => 'tecnico',
            'scope'             => 'academia',
            'priority'          => 'media',
            'reported_urgency'  => 'media',
            'title'             => 'No me carga el vídeo de una sesión',
            'description'       => 'Al abrir la sesión individual del lunes, el vídeo adjunto se queda cargando y no llega a reproducirse. En el móvil tampoco.',
            'status'            => 'en_progreso',
            'assigned_to'       => $this->gAdmin,
            'origin'            => 'manual',
            'first_response_at' => date('Y-m-d H:i:s', strtotime('-20 hours')),
            'created_at'        => date('Y-m-d H:i:s', strtotime('-1 day 8 hours')),
            'updated_at'        => date('Y-m-d H:i:s', strtotime('-20 hours')),
        ]);
        $tid = (int) $this->db->insertID();

        if ($tid) {
            $this->db->table('ticket_replies')->insert([
                'ticket_id'   => $tid,
                'user_id'     => $this->gAdmin,
                'body'        => 'Gracias por avisar. Lo estamos revisando con el proveedor de vídeo; te confirmamos en cuanto esté resuelto.',
                'is_internal' => 0,
                'created_at'  => date('Y-m-d H:i:s', strtotime('-20 hours')),
            ]);
            if ($this->db->tableExists('ticket_events')) {
                $this->db->table('ticket_events')->insertBatch([
                    ['ticket_id' => $tid, 'actor_id' => $this->gAdmin, 'event_type' => 'assigned', 'from_value' => null, 'to_value' => 'Invitado · Dirección', 'created_at' => date('Y-m-d H:i:s', strtotime('-22 hours'))],
                    ['ticket_id' => $tid, 'actor_id' => $this->gAdmin, 'event_type' => 'status', 'from_value' => 'abierto', 'to_value' => 'en_progreso', 'created_at' => date('Y-m-d H:i:s', strtotime('-20 hours'))],
                ]);
            }
        }

        echo "  incidencias: consulta del alumno + incidencia técnica asignada.\n";
    }

    // ════════════════════════════════════════════════════════════════
    //  Utilidades
    // ════════════════════════════════════════════════════════════════

    private function pick(int $i, array $opts): string
    {
        return $opts[$i % count($opts)];
    }

    /**
     * Crea un documento en una carpeta: escribe un PDF mínimo real en
     * WRITEPATH/uploads/... y registra la fila en `documents`.
     *
     * @return int id del documento insertado (0 si falló)
     */
    private function addPdf(int $folderId, string $folderType, ?int $ownerId, int $uploaderId, string $niceName, ?string $desc, string $whenRel, array $lines): int
    {
        $base = WRITEPATH . 'uploads/';
        $dir  = match ($folderType) {
            'public'   => $base . 'public/' . $folderId . '/',
            'personal' => $base . 'personal/' . ($ownerId ?? $folderId) . '/',
            'internal' => $base . 'internal/' . $folderId . '/',
            default    => $base . 'misc/',
        };
        if (! is_dir($dir)) {
            @mkdir($dir, 0755, true);
        }
        $ht = dirname($dir) . '/.htaccess';
        if (! file_exists($ht)) {
            @file_put_contents($ht, "Options -Indexes\nDeny from all\n");
        }

        $pdf    = $this->buildPdf($lines);
        $stored = bin2hex(random_bytes(16)) . '.pdf';
        if (@file_put_contents($dir . $stored, $pdf) === false) {
            echo "  aviso: no se pudo escribir {$niceName} en {$dir}\n";
            return 0;
        }

        $ts = date('Y-m-d H:i:s', strtotime($whenRel));
        $ok = $this->db->table('documents')->insert([
            'folder_id'     => $folderId,
            'uploader_id'   => $uploaderId,
            'name_original' => $niceName,
            'name_stored'   => $stored,
            'mime_type'     => 'application/pdf',
            'extension'     => 'pdf',
            'size_bytes'    => strlen($pdf),
            'description'   => $desc,
            'sensitive'     => $folderType === 'personal' ? 1 : 0,
            'created_at'    => $ts,
        ]);

        return $ok ? (int) $this->db->insertID() : 0;
    }

    /**
     * PDF de una página, válido (xref con offsets calculados). Suficiente
     * para previsualizar y descargar en la demo.
     */
    private function buildPdf(array $lines): string
    {
        $content = "BT\n/F1 16 Tf\n40 300 Td\n22 TL\n";
        foreach ($lines as $i => $ln) {
            $safe = $this->pdfEscape($ln);
            $content .= $i === 0 ? "({$safe}) Tj\n" : "T* ({$safe}) Tj\n";
        }
        $content .= "ET";

        $objs = [
            1 => "<< /Type /Catalog /Pages 2 0 R >>",
            2 => "<< /Type /Pages /Kids [3 0 R] /Count 1 >>",
            3 => "<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 380] "
               . "/Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>",
            4 => "<< /Length " . strlen($content) . " >>\nstream\n{$content}\nendstream",
            5 => "<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>",
        ];

        $pdf     = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objs as $n => $body) {
            $offsets[$n] = strlen($pdf);
            $pdf .= "{$n} 0 obj\n{$body}\nendobj\n";
        }
        $xref = strlen($pdf);
        $pdf .= "xref\n0 " . (count($objs) + 1) . "\n0000000000 65535 f \n";
        foreach ($offsets as $off) {
            $pdf .= sprintf("%010d 00000 n \n", $off);
        }
        $pdf .= "trailer\n<< /Size " . (count($objs) + 1) . " /Root 1 0 R >>\n"
              . "startxref\n{$xref}\n%%EOF";

        return $pdf;
    }

    private function pdfEscape(string $s): string
    {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT', $s);
        if ($ascii !== false) {
            $s = $ascii;
        }
        return strtr($s, ['\\' => '\\\\', '(' => '\\(', ')' => '\\)', "\r" => '', "\n" => ' ']);
    }
}
