<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use App\Models\UserModel;
use App\Services\DocumentService;

/**
 * SOLO DEMO / DESARROLLO. Segunda pasada de datos falsos que "llena" los
 * módulos que BulkDemoDataSeeder no toca, para que el entorno demo y las
 * pruebas del superadmin tengan volumen realista en TODO:
 *
 *   - métricas físicas/técnicas periódicas por alumno (player_metrics)
 *   - anotaciones de seguimiento (públicas e internas) sobre muchos alumnos
 *   - notificaciones: circulares a toda la academia + avisos individuales
 *   - documentación: carpetas compartidas + PDFs + carpetas personales
 *   - incidencias de academia (tickets) con estados, prioridades, ámbito,
 *     asignación, respuestas internas/públicas y timeline
 *
 * NO toca Mensajes (chat) a propósito: ocupan mucho y ya hay ejemplo en
 * DemoConversationsSeeder / DemoShowcaseSeeder.
 *
 * Idempotente por secciones (comprueba antes de insertar). Se ejecuta
 * después de BulkDemoDataSeeder. Encadenado desde DemoSeeder.
 *
 * A mano:  docker compose exec app php spark db:seed BulkDemoExtrasSeeder
 */
class BulkDemoExtrasSeeder extends Seeder
{
    private UserModel $users;

    /** @var int[] */ private array $adminIds  = [];
    /** @var int[] */ private array $coachIds  = [];
    /** @var int[] */ private array $staffIds  = [];
    /** @var int[] */ private array $playerIds = [];

    public function run()
    {
        $this->db    = $this->db ?: \Config\Database::connect();
        $this->users = new UserModel();

        $this->adminIds  = $this->idsByRole(['superadmin', 'admin']);
        $this->coachIds  = $this->idsByRole(['coach']);
        $this->staffIds  = $this->idsByRole(['staff']);
        $this->playerIds = $this->idsByRole(['player']);

        if (empty($this->playerIds) || empty($this->coachIds)) {
            echo "BulkDemoExtrasSeeder: faltan alumnos o entrenadores; siembra BulkDemoDataSeeder primero.\n";
            return;
        }

        $this->seedMetrics();
        $this->seedAnnotations();
        $this->seedNotifications();
        $this->seedDocuments();
        $this->seedTickets();

        echo "BulkDemoExtrasSeeder: listo.\n";
    }

    // ────────────────────────────────────────────────────────────────
    //  Helpers de selección
    // ────────────────────────────────────────────────────────────────

    private function idsByRole(array $roles): array
    {
        return array_map('intval', array_column(
            $this->users->whereIn('role', $roles)
                ->where("email NOT LIKE '%@demo.local'", null, false) // excluye invitados
                ->orderBy('id', 'ASC')->findAll(),
            'id'
        ));
    }

    private function pick(array $arr, int $i)
    {
        return $arr[$i % count($arr)];
    }

    // ════════════════════════════════════════════════════════════════
    //  Métricas físicas / técnicas
    // ════════════════════════════════════════════════════════════════

    private function seedMetrics(): void
    {
        if ($this->db->table('player_metrics')->countAllResults() > 0) {
            echo "  = métricas ya sembradas, se omiten.\n";
            return;
        }

        $rows    = [];
        $targets = array_slice($this->playerIds, 0, min(38, count($this->playerIds)));

        foreach ($targets as $i => $pid) {
            $coach   = $this->pick($this->coachIds, $i);
            $entries = 2 + ($i % 3); // 2..4 mediciones

            for ($e = 0; $e < $entries; $e++) {
                $daysAgo = 90 - $e * 28 - ($i % 6);
                $date    = date('Y-m-d', strtotime("-{$daysAgo} days"));

                // Progresión ligera entre mediciones.
                $base = [
                    'height_cm'      => 128 + ($i % 55) + $e,
                    'weight_kg'      => round(28 + ($i % 40) + $e * 0.4, 1),
                    'resting_hr'     => 74 - ($i % 12) - $e,
                    'sprint_20m_s'   => round(4.10 - $e * 0.04 + ($i % 5) * 0.05, 2),
                    'agility_505_s'  => round(2.75 - $e * 0.03 + ($i % 4) * 0.04, 2),
                    'vertical_jump'  => 26 + ($i % 14) + $e,
                    'juggling_reps'  => 15 + ($i % 30) + $e * 4,
                    'pass_accuracy'  => min(98, 62 + ($i % 25) + $e * 3),
                    'category'       => $e % 2 === 0 ? 'physical' : 'technical',
                ];

                $rows[] = [
                    'player_id'  => $pid,
                    'coach_id'   => $coach,
                    'session_id' => null,
                    'date'       => $date,
                    'metrics'    => json_encode($base, JSON_UNESCAPED_UNICODE),
                    'evaluation' => ['A', 'B+', 'B', 'Notable', 'Progresa', '7/10', '8/10'][($i + $e) % 7],
                    'notes'      => $this->pick([
                        'Test trimestral. Mejora clara en la velocidad de reacción.',
                        'Control con superficie interior sólido; a pulir el exterior.',
                        'Salto vertical estancado: incluir pliometría ligera.',
                        'Muy buen dato de conducción en slalom.',
                        'Resistencia por debajo de la media del grupo, trabajar en casa.',
                        'Precisión de pase larga en progreso.',
                    ], $i + $e),
                    'created_at' => $date . ' 19:30:00',
                ];
            }
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            $this->db->table('player_metrics')->insertBatch($chunk);
        }
        echo "  métricas: " . count($rows) . " registros sobre " . count($targets) . " alumnos.\n";
    }

    // ════════════════════════════════════════════════════════════════
    //  Anotaciones de seguimiento
    // ════════════════════════════════════════════════════════════════

    private const ANNOT_PUBLIC = [
        'Gran evolución en el control orientado. Ya recibe de espaldas sin perder el balón.',
        'Actitud competitiva excelente y muy buen compañero de grupo.',
        'A trabajar: la finalización con la pierna menos hábil y el timing de llegada al área.',
        'Muy buen ritmo de aprendizaje en el pase entre líneas. Puede dar el salto de grupo.',
        'Necesita constancia: cuando entrena dos días seguidos el nivel sube mucho.',
        'Lectura de juego por encima de su categoría. Referente del grupo.',
        'Mejora en la concentración durante toda la sesión, ya no baja en los últimos 15 minutos.',
        'Trabajo defensivo 1v1 en progreso; falta agresividad en el momento del robo.',
    ];

    private const ANNOT_INTERNAL = [
        'Nota interna: hablar con la familia sobre la carga de partidos del fin de semana.',
        'Nota interna: revisar con fisio una molestia recurrente en el tobillo antes de forzar 1v1.',
        'Nota interna: candidato a beca parcial el próximo trimestre, comentarlo en dirección.',
        'Nota interna: baja asistencia en las últimas semanas, contactar con los tutores.',
        'Nota interna: pendiente de entregar la autorización de imagen firmada.',
    ];

    private function seedAnnotations(): void
    {
        if ($this->db->table('player_annotations')->countAllResults() > 12) {
            echo "  = anotaciones ya sembradas, se omiten.\n";
            return;
        }

        $rows    = [];
        $authors = array_merge($this->coachIds, $this->adminIds);
        $targets = array_slice($this->playerIds, 0, min(34, count($this->playerIds)));

        foreach ($targets as $i => $pid) {
            $count = 1 + ($i % 3); // 1..3
            for ($k = 0; $k < $count; $k++) {
                $internal = ($k === $count - 1) && ($i % 3 === 0);
                $when     = date('Y-m-d H:i:s', strtotime('-' . (5 + $i * 2 + $k * 9) . ' days'));
                $rows[] = [
                    'player_id'  => $pid,
                    'author_id'  => $this->pick($authors, $i + $k),
                    'type'       => $internal ? 'internal' : 'public',
                    'content'    => $internal
                        ? $this->pick(self::ANNOT_INTERNAL, $i + $k)
                        : $this->pick(self::ANNOT_PUBLIC, $i + $k),
                    'created_at' => $when,
                    'updated_at' => $when,
                ];
            }
        }

        foreach (array_chunk($rows, 200) as $chunk) {
            $this->db->table('player_annotations')->insertBatch($chunk);
        }
        echo "  anotaciones: " . count($rows) . " sobre " . count($targets) . " alumnos.\n";
    }

    // ════════════════════════════════════════════════════════════════
    //  Notificaciones (SIN mensajes de chat)
    // ════════════════════════════════════════════════════════════════

    private function seedNotifications(): void
    {
        if ($this->db->table('notifications')->like('title', '[demo]', 'after')->countAllResults() > 0) {
            echo "  = notificaciones demo ya sembradas, se omiten.\n";
            return;
        }

        $sender     = $this->adminIds[0] ?? $this->coachIds[0];
        $everyone   = array_merge($this->adminIds, $this->coachIds, $this->staffIds, $this->playerIds);
        $noStudents = array_merge($this->adminIds, $this->coachIds, $this->staffIds);

        // ── Circulares a toda la academia ─────────────────────────────
        $circ = [
            ['[demo] Inicio de temporada 2026/27',
             'Arrancamos la nueva temporada. Los grupos de tecnificación mantienen sus horarios habituales; las sesiones individuales se citan directamente con cada entrenador.'],
            ['[demo] Cierre de instalaciones — 12 de octubre',
             'El 12 de octubre (festivo local) no habrá sesiones. Las clases individuales de ese día se reprograman; el entrenador contactará con cada familia.'],
            ['[demo] Recordatorio: renovación de bonos',
             'Si tu bono está entrando en las últimas sesiones, pásate por recepción o escríbenos para renovarlo y no perder la plaza en el grupo.'],
            ['[demo] Torneo interno de Navidad — inscripciones abiertas',
             'El 21 de diciembre celebramos el torneo interno. Las inscripciones se hacen desde recepción hasta el día 15.'],
        ];
        foreach ($circ as $i => [$title, $body]) {
            $this->pushNotif(
                $this->pick($this->adminIds ?: [$sender], $i),
                'group', $title, $body,
                '-' . (30 - $i * 6) . ' days',
                $everyone
            );
        }

        // Circular solo al cuerpo técnico.
        $this->pushNotif($sender, 'group',
            '[demo] Reunión de coordinación técnica',
            'Jueves a las 20:15 en la sala de vídeo. Repasamos objetivos por grupo y el calendario de evaluaciones.',
            '-9 days', $noStudents);

        // ── Avisos individuales ──────────────────────────────────────
        $indiv = [
            ['[demo] Tu bono está entrando en las últimas sesiones',
             'Te quedan pocas sesiones en el bono actual. Habla con recepción para renovarlo.'],
            ['[demo] Cambio de campo en tu próxima sesión',
             'Tu sesión del jueves se traslada al Campo 2 - Tecnificación. Misma hora.'],
            ['[demo] Informe de seguimiento disponible',
             'El entrenador ha subido tu informe trimestral a tu carpeta personal de documentos.'],
            ['[demo] Recuerda confirmar asistencia',
             'Tienes una sesión sin confirmar esta semana. Confírmala desde el calendario.'],
            ['[demo] Falta tu autorización de imagen',
             'No consta la autorización de uso de imagen firmada. Descárgala, fírmala y súbela a tu carpeta.'],
        ];
        $created = 0;
        foreach ($this->playerIds as $i => $pid) {
            if ($i % 2 === 1) {
                continue; // ~la mitad de los alumnos reciben algún aviso
            }
            [$title, $body] = $this->pick($indiv, $i);
            $this->pushNotif(
                $this->pick(array_merge($this->adminIds, $this->coachIds), $i),
                'individual', $title, $body,
                '-' . (1 + $i) . ' days',
                [$pid],
                $i % 3 !== 0 // ~2/3 ya leídas
            );
            $created++;
        }

        // Avisos a entrenadores (sesiones sin lista, etc.)
        foreach ($this->coachIds as $i => $cid) {
            $this->pushNotif(
                $this->pick($this->adminIds ?: [$sender], $i),
                'individual',
                '[demo] Tienes sesiones sin lista pasada',
                'Recuerda cerrar las sesiones pasadas de tus grupos para que los bonos se descuenten correctamente.',
                '-' . (2 + $i) . ' days',
                [$cid],
                $i % 2 === 0
            );
        }

        echo "  notificaciones: 5 circulares + " . ($created + count($this->coachIds)) . " individuales.\n";
    }

    /**
     * Inserta notificación + destinatarios directamente para conservar la
     * fecha (NotificationModel::createWithRecipients pone created_at = ahora).
     */
    private function pushNotif(int $senderId, string $type, string $title, string $body, string $whenRel, array $recipientIds, bool $read = false): void
    {
        $ts = date('Y-m-d H:i:s', strtotime($whenRel));
        $this->db->table('notifications')->insert([
            'sender_id'  => $senderId,
            'type'       => $type,
            'title'      => $title,
            'body'       => $body,
            'created_at' => $ts,
        ]);
        $nid = (int) $this->db->insertID();
        if (! $nid || empty($recipientIds)) {
            return;
        }
        $rows = [];
        foreach (array_unique($recipientIds) as $j => $rid) {
            $rows[] = [
                'notification_id' => $nid,
                'recipient_id'    => (int) $rid,
                // Para grupos: parte leída, parte no. Para individuales: según $read.
                'read_at'         => ($type === 'group' ? ($j % 3 === 0) : $read)
                    ? date('Y-m-d H:i:s', strtotime($ts . ' +2 hours'))
                    : null,
            ];
        }
        foreach (array_chunk($rows, 300) as $chunk) {
            $this->db->table('notification_recipients')->insertBatch($chunk);
        }
    }

    // ════════════════════════════════════════════════════════════════
    //  Documentación
    // ════════════════════════════════════════════════════════════════

    private function seedDocuments(): void
    {
        if ($this->db->table('document_folders')->where('slug', 'circulares-generales-demo')->countAllResults() > 0) {
            echo "  = documentos demo ya sembrados, se omiten.\n";
            return;
        }

        $owner = $this->adminIds[0] ?? $this->coachIds[0];
        $docs  = new DocumentService();

        // ── Carpeta pública: circulares generales ─────────────────────
        $pubId = $this->makeFolder('Circulares generales', 'circulares-generales-demo', 'public', 'bi-megaphone-fill', 'blue', null, $owner, '-35 days');
        if ($pubId) {
            $this->addPdf($pubId, 'public', null, $owner, 'Circular-inicio-temporada.pdf',
                'Información general de arranque de temporada.', '-34 days', [
                    'Circular de inicio de temporada 2026/27',
                    'Horarios de los grupos de tecnificacion.',
                    'Politica de asistencia y avisos de ausencia.',
                    'Canales de contacto con el cuerpo tecnico.',
                ]);
            $this->addPdf($pubId, 'public', null, $owner, 'Calendario-trimestre-1.pdf',
                'Calendario del primer trimestre.', '-20 days', [
                    'Calendario - primer trimestre',
                    'Grupos: martes y jueves 17:30-19:00.',
                    'Evaluaciones trimestrales: ultima semana de cada mes.',
                    'Festivos sin sesion: 12 de octubre, 6 y 8 de diciembre.',
                ]);
        }

        // ── Carpeta pública: vídeos y recursos ────────────────────────
        $recId = $this->makeFolder('Vídeos y recursos para alumnos', 'videos-recursos-demo', 'public', 'bi-play-btn-fill', 'green', null, $owner, '-28 days');
        if ($recId) {
            $this->addPdf($recId, 'public', null, $this->pick($this->coachIds, 0), 'Rutina-tecnica-casa.pdf',
                'Ejercicios de técnica individual para hacer en casa.', '-25 days', [
                    'Rutina de tecnica individual en casa',
                    '10 min de toques y control.',
                    '10 min de conduccion en slalom.',
                    '10 min de pase contra pared, ambos perfiles.',
                ]);
            $this->addPdf($recId, 'public', null, $this->pick($this->coachIds, 1), 'Guia-alimentacion-dia-de-partido.pdf',
                'Recomendaciones básicas de nutrición.', '-14 days', [
                    'Alimentacion el dia de partido',
                    'Comida principal 3-4 h antes.',
                    'Hidratacion constante durante el dia.',
                    'Evitar ultraprocesados y bebidas azucaradas.',
                ]);
        }

        // ── Carpeta interna: cuerpo técnico (ampliación) ──────────────
        $intId = $this->makeFolder('Sesiones tipo y progresiones', 'sesiones-tipo-progresiones-demo', 'internal', 'bi-diagram-3-fill', 'indigo', null, $owner, '-30 days');
        if ($intId) {
            foreach ($this->coachIds as $cid) {
                $this->db->table('folder_permissions')->insert([
                    'folder_id'  => $intId,
                    'user_id'    => $cid,
                    'can_read'   => 1,
                    'can_write'  => 0,
                    'granted_by' => $owner,
                    'created_at' => date('Y-m-d H:i:s', strtotime('-30 days')),
                ]);
            }
            $this->addPdf($intId, 'internal', null, $owner, 'Banco-de-sesiones-tipo.pdf',
                'Sesiones tipo por objetivo técnico.', '-29 days', [
                    'Banco de sesiones tipo',
                    'Salida de balon: rondo 4v2 + juego de posicion 4v4+3.',
                    'Finalizacion: circuito de 3 estaciones + 3v3 a 4 porterias.',
                    'Presion tras perdida: 6v6 con zonas + transiciones.',
                ]);
            $this->addPdf($intId, 'internal', null, $owner, 'Criterios-de-evaluacion.pdf',
                'Rúbrica de evaluación trimestral.', '-22 days', [
                    'Criterios de evaluacion trimestral',
                    'Tecnica: control, pase, conduccion, golpeo.',
                    'Tactica: lectura, toma de decision, ocupacion de espacios.',
                    'Fisico y actitudinal: 1 a 5.',
                ]);
        }

        // ── Carpetas personales de algunos alumnos ────────────────────
        $personal = 0;
        foreach (array_slice($this->playerIds, 0, 12) as $i => $pid) {
            $pf = $docs->getOrCreatePersonalFolder($pid);
            if (! $pf) {
                continue;
            }
            $this->addPdf((int) $pf['id'], 'personal', $pid, $this->pick($this->coachIds, $i),
                'Informe-seguimiento-trimestre-1.pdf', 'Informe trimestral del entrenador.', '-' . (3 + $i) . ' days', [
                    'Informe de seguimiento - primer trimestre',
                    'Tecnica: control orientado en progreso.',
                    'Tactica: entiende la salida por dentro.',
                    'Fisico: mejorar resistencia en la parte final.',
                    'Objetivos: pierna menos habil y llegada al area.',
                ]);
            $personal++;
        }

        echo "  documentos: 3 carpetas compartidas + {$personal} carpetas personales con informe.\n";
    }

    private function makeFolder(string $name, string $slug, string $type, string $icon, string $color, ?int $ownerId, int $createdBy, string $whenRel): int
    {
        $ts = date('Y-m-d H:i:s', strtotime($whenRel));
        $ok = $this->db->table('document_folders')->insert([
            'name'       => $name,
            'slug'       => $slug,
            'type'       => $type,
            'icon'       => $icon,
            'color'      => $color,
            'owner_id'   => $ownerId,
            'created_by' => $createdBy,
            'status'     => 'active',
            'created_at' => $ts,
            'updated_at' => $ts,
        ]);
        return $ok ? (int) $this->db->insertID() : 0;
    }

    // ════════════════════════════════════════════════════════════════
    //  Incidencias de academia
    // ════════════════════════════════════════════════════════════════

    private function seedTickets(): void
    {
        if ($this->db->table('tickets')->like('ticket_number', 'ACA-', 'after')->countAllResults() > 0) {
            echo "  = incidencias de academia ya sembradas, se omiten.\n";
            return;
        }

        $hasScope  = $this->db->fieldExists('scope', 'tickets');
        $hasAssign = $this->db->fieldExists('assigned_to', 'tickets');
        $hasFirst  = $this->db->fieldExists('first_response_at', 'tickets');
        $hasArch   = $this->db->fieldExists('archived_at', 'tickets');
        $hasUrg    = $this->db->fieldExists('reported_urgency', 'tickets');
        $hasEvents = $this->db->tableExists('ticket_events');

        // [nº, autor-rol, categoría, prioridad, estado, díasCreado, título, descripción, asignar?, respuesta?]
        $defs = [
            ['P', 'consulta', 'baja',    'abierto',     3,  '¿Cómo renuevo el bono de mi hijo?',
             'Me aparece que quedan 2 sesiones. ¿Se puede renovar desde la app o hay que pasar por recepción?', false, null],
            ['P', 'consulta', 'media',   'abierto',     2,  'No puedo confirmar asistencia desde el móvil',
             'Cuando entro al calendario y pulso "Confirmar" no pasa nada. En el ordenador sí funciona.', true, null],
            ['C', 'tecnico',  'media',   'en_progreso', 5,  'El vídeo de una sesión no se reproduce',
             'Subí un vídeo a la sesión del lunes y se queda cargando. En el móvil tampoco carga.', true,
             'Gracias por avisar. Lo estamos revisando con el proveedor de vídeo.'],
            ['C', 'mejora',   'baja',    'abierto',     8,  'Poder duplicar una sesión al día siguiente',
             'Muchas veces repito la misma sesión dos días seguidos. Estaría bien un botón de "duplicar".', false, null],
            ['P', 'bug',      'alta',    'en_progreso', 4,  'Me descontó dos sesiones del bono el mismo día',
             'El martes solo entrené una vez pero el bono bajó de 8 a 6. Creo que se descontó doble.', true,
             'Lo comprobamos con el entrenador y te ajustamos el saldo. Disculpa las molestias.'],
            ['P', 'consulta', 'baja',    'resuelto',    14, '¿Dónde veo el informe trimestral?',
             '¿El informe del entrenador se envía por email o está en algún sitio de la plataforma?', false,
             'Está en tu carpeta personal de Documentos. Te he dejado también un aviso con el enlace.'],
            ['C', 'tecnico',  'media',   'resuelto',    20, 'No me deja marcar "no justificado" en pasar lista',
             'Al pasar lista, la opción de ausencia sin justificar no guardaba. Ya parece que va.', false,
             'Se corrigió en la última actualización. Gracias por el reporte.'],
            ['P', 'otro',     'baja',    'cerrado',     30, 'Cambio de grupo de mi hija',
             '¿Sería posible pasarla del grupo del martes al del jueves? Le coincide con otra actividad.', true,
             'Hablado con el cuerpo técnico: a partir de la semana que viene entra en el grupo del jueves.'],
            ['C', 'mejora',   'media',   'abierto',     6,  'Exportar la lista de asistencia a Excel',
             'Para las reuniones con dirección me vendría bien exportar la asistencia del mes en una hoja.', false, null],
            ['P', 'bug',      'media',   'abierto',     1,  'La app me muestra una sesión antigua como "hoy"',
             'En el dashboard aparece una sesión de hace dos semanas como si fuera de hoy.', false, null],
            ['C', 'consulta', 'baja',    'resuelto',    25, '¿Puedo tener dos grupos a la misma hora?',
             'Necesito llevar dos grupos en paralelo el miércoles. ¿El calendario lo permite?', false,
             'Sí, se pueden solapar. El calendario los reparte en columnas.'],
            ['P', 'otro',     'urgente', 'en_progreso', 2,  'Lesión: pausar el bono unas semanas',
             'Mi hijo se ha lesionado y estará 3 semanas de baja. ¿Se puede congelar el bono para no perder sesiones?', true,
             'Lo gestionamos. Te confirmamos las fechas de pausa por aquí.'],
        ];

        $n = 0;
        foreach ($defs as $d) {
            [$rrole, $cat, $prio, $status, $daysAgo, $title, $desc, $assign, $reply] = $d;
            $n++;
            $author = $rrole === 'P'
                ? $this->pick($this->playerIds, $n * 3)
                : $this->pick($this->coachIds, $n);

            $created = date('Y-m-d H:i:s', strtotime("-{$daysAgo} days 09:15:00"));
            $row = [
                'ticket_number' => sprintf('ACA-2026-%03d', $n),
                'user_id'       => $author,
                'category'      => $cat,
                'priority'      => $prio,
                'title'         => $title,
                'description'   => $desc,
                'status'        => $status,
                'created_at'    => $created,
                'updated_at'    => $created,
            ];
            if (in_array($status, ['resuelto', 'cerrado'], true)) {
                $row['resolved_at'] = date('Y-m-d H:i:s', strtotime("-" . max(0, $daysAgo - 3) . " days 17:00:00"));
            }
            if ($status === 'cerrado') {
                $row['closed_at'] = date('Y-m-d H:i:s', strtotime("-" . max(0, $daysAgo - 2) . " days 18:00:00"));
            }
            if ($hasScope) {
                $row['scope'] = in_array($cat, ['tecnico', 'bug'], true) ? 'plataforma' : 'academia';
            }
            if ($hasUrg && $rrole === 'P') {
                $row['reported_urgency'] = $prio === 'urgente' ? 'urgente' : ($prio === 'alta' ? 'alta' : 'media');
            }
            if ($hasAssign && $assign) {
                $row['assigned_to'] = $this->pick($this->adminIds ?: $this->coachIds, $n);
            }
            if ($hasFirst && ($reply !== null || $status !== 'abierto')) {
                $row['first_response_at'] = date('Y-m-d H:i:s', strtotime("-" . max(0, $daysAgo - 1) . " days 11:00:00"));
            }
            if ($hasArch && $status === 'cerrado' && $n % 2 === 0) {
                $row['archived_at'] = date('Y-m-d H:i:s', strtotime("-" . max(0, $daysAgo - 1) . " days 18:30:00"));
            }

            $this->db->table('tickets')->insert($row);
            $tid = (int) $this->db->insertID();
            if (! $tid) {
                continue;
            }

            $manager = $row['assigned_to'] ?? ($this->adminIds[0] ?? $this->coachIds[0]);

            if ($reply !== null) {
                $this->db->table('ticket_replies')->insert([
                    'ticket_id'   => $tid,
                    'user_id'     => $manager,
                    'body'        => $reply,
                    'is_internal' => 0,
                    'created_at'  => date('Y-m-d H:i:s', strtotime("-" . max(0, $daysAgo - 1) . " days 11:00:00")),
                ]);
            }
            // Nota interna en algunos.
            if ($n % 3 === 0) {
                $this->db->table('ticket_replies')->insert([
                    'ticket_id'   => $tid,
                    'user_id'     => $manager,
                    'body'        => 'Nota interna: confirmar con el entrenador antes de responder a la familia.',
                    'is_internal' => 1,
                    'created_at'  => date('Y-m-d H:i:s', strtotime("-" . max(0, $daysAgo - 1) . " days 12:30:00")),
                ]);
            }

            if ($hasEvents) {
                $events = [[
                    'ticket_id'  => $tid, 'actor_id' => $author, 'event_type' => 'created',
                    'from_value' => null, 'to_value' => null,
                    'created_at' => $created,
                ]];
                if (isset($row['assigned_to'])) {
                    $events[] = [
                        'ticket_id' => $tid, 'actor_id' => $manager, 'event_type' => 'assigned',
                        'from_value' => null, 'to_value' => (string) $row['assigned_to'],
                        'created_at' => date('Y-m-d H:i:s', strtotime("-" . max(0, $daysAgo - 1) . " days 10:45:00")),
                    ];
                }
                if ($status !== 'abierto') {
                    $events[] = [
                        'ticket_id' => $tid, 'actor_id' => $manager, 'event_type' => 'status',
                        'from_value' => 'abierto', 'to_value' => $status,
                        'created_at' => date('Y-m-d H:i:s', strtotime("-" . max(0, $daysAgo - 2) . " days 16:00:00")),
                    ];
                }
                $this->db->table('ticket_events')->insertBatch($events);
            }
        }

        echo "  incidencias de academia: {$n} tickets con respuestas / notas / timeline.\n";
    }

    // ════════════════════════════════════════════════════════════════
    //  PDF mínimo real (mismo enfoque que DemoShowcaseSeeder)
    // ════════════════════════════════════════════════════════════════

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
            echo "  aviso: no se pudo escribir {$niceName}\n";
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
        foreach ($objs as $num => $body) {
            $offsets[$num] = strlen($pdf);
            $pdf .= "{$num} 0 obj\n{$body}\nendobj\n";
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
