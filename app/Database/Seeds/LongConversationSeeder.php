<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use App\Models\UserModel;
use App\Models\PlayerProfileModel;
use App\Models\ConversationModel;

/**
 * SOLO PARA DESARROLLO LOCAL. Reproduce TICKET-009 ("en un chat con mucho
 * texto no se cargan todos los mensajes"): crea un entrenador y un alumno
 * de prueba y una conversación 1-a-1 entre ellos con ~200 mensajes
 * repartidos en ~3 meses, muchos de ellos largos (resúmenes de partido de
 * la familia, respuestas del entrenador con planificación) y algunos muy
 * largos (varios párrafos, miles de caracteres).
 *
 * Los textos imitan el caso real reportado pero son inventados: no hay
 * nombres ni datos de alumnos reales.
 *
 * Login (contraseña de ambos: Test1234!):
 *  - Entrenador: test.coach.chat@test.jppreparation.local
 *  - Alumno:     test.alumno.chat@test.jppreparation.local
 *
 * Ejecutar:  docker compose exec app php spark db:seed LongConversationSeeder
 * Idempotente: reutiliza los usuarios y la conversación si ya existen y
 * REGENERA siempre los mensajes de esa conversación (solo esa), así que se
 * puede re-ejecutar sin duplicar nada.
 */
class LongConversationSeeder extends Seeder
{
    private const DOMAIN      = '@test.jppreparation.local';
    private const PASSWORD    = 'Test1234!';
    private const RANDOM_SEED = 20260916;
    private const DAYS_BACK   = 90;

    /** @var array<int, array<string, mixed>> */
    private array $messages = [];
    private int $cursor     = 0;
    private int $coachId    = 0;
    private int $playerId   = 0;

    public function run()
    {
        mt_srand(self::RANDOM_SEED);

        $this->coachId  = $this->ensureUser('test.coach.chat', 'TEST Entrenador Chat Largo', 'coach');
        $this->playerId = $this->ensureUser('test.alumno.chat', 'TEST Marc Soler (chat largo)', 'player');
        $this->ensurePlayerProfile($this->playerId);

        $conv = (new ConversationModel())->findOrCreate($this->coachId, $this->playerId);
        if (empty($conv['id'])) {
            echo "  ERROR: no se pudo crear la conversación.\n";
            return;
        }
        $convId = (int) $conv['id'];

        $this->buildHistory();
        $this->persist($convId);

        $totalChars = array_sum(array_map(static fn ($m) => mb_strlen($m['body']), $this->messages));
        $longest    = max(array_map(static fn ($m) => mb_strlen($m['body']), $this->messages));
        $over500    = count(array_filter($this->messages, static fn ($m) => mb_strlen($m['body']) > 500));

        echo "LongConversationSeeder: listo.\n";
        echo "  conversación #{$convId}: " . count($this->messages) . " mensajes"
            . " | {$over500} de más de 500 caracteres | el más largo: {$longest}"
            . " | total: {$totalChars} caracteres\n";
        echo "  primer mensaje: " . $this->messages[0]['created_at'] . "\n";
        echo "  último mensaje: " . end($this->messages)['created_at'] . "\n";
        echo "  login entrenador: test.coach.chat" . self::DOMAIN . " / " . self::PASSWORD . "\n";
        echo "  login alumno:     test.alumno.chat" . self::DOMAIN . " / " . self::PASSWORD . "\n";
    }

    // ────────────────────────────────────────────────────────────────
    //  Usuarios
    // ────────────────────────────────────────────────────────────────

    private function ensureUser(string $slug, string $name, string $role): int
    {
        $userModel = new UserModel();
        $email     = $slug . self::DOMAIN;

        $existing = $userModel->where('email', $email)->first();
        if ($existing) {
            echo "  ya existe: {$email}\n";
            return (int) $existing['id'];
        }

        $uid = $userModel->insert([
            'name'     => $name,
            'email'    => $email,
            'password' => self::PASSWORD,
            'role'     => $role,
            'status'   => 'active',
        ], true);

        if (!$uid) {
            throw new \RuntimeException("No se pudo crear {$email}: " . implode(' ', $userModel->errors()));
        }

        echo "  creado {$role}: {$name} ({$email})\n";
        return (int) $uid;
    }

    private function ensurePlayerProfile(int $playerId): void
    {
        $profileModel = new PlayerProfileModel();
        if ($profileModel->where('player_id', $playerId)->first()) {
            return;
        }

        $profileModel->insert([
            'player_id' => $playerId,
            'position'  => PlayerProfileModel::encodePositions(['interior']),
            'height'    => 156,
            'weight'    => 44,
            'category'  => 'infantil',
        ]);
    }

    // ────────────────────────────────────────────────────────────────
    //  Historial de mensajes
    // ────────────────────────────────────────────────────────────────

    private function buildHistory(): void
    {
        $this->messages = [];
        $today = strtotime('today');
        $start = strtotime('-' . self::DAYS_BACK . ' days', $today);

        $this->cursor = $start + 9 * 3600;
        $this->add('player', "Hola! Soy la madre de Marc. Te escribo desde su cuenta porque él todavía no se aclara mucho con la aplicación.\n\nQuería presentarnos y contarte un poco cómo es: juega de interior en el infantil A de su club, es muy técnico pero le falta intensidad y le cuesta mucho la pierna izquierda. Este año queremos que dé un salto y por eso le hemos apuntado a las sesiones individuales.\n\nCualquier cosa que necesites saber, me escribes por aquí.");
        $this->reply(40, 180);
        $this->add('coach', "Hola! Encantado, y bienvenidos a JP Preparation.\n\nMuchas gracias por la información, nos viene genial para preparar las primeras sesiones. La primera semana haremos una valoración inicial (técnica, velocidad, toma de decisiones) y a partir de ahí os paso un plan.\n\nPor aquí podéis escribirme lo que queráis: cómo van los partidos, molestias, cambios de horario...");

        $veryLongDays = [18, 41, 63, 84];

        for ($day = 1; $day < self::DAYS_BACK; $day++) {
            $date = strtotime("+{$day} days", $start);
            $dow  = (int) date('N', $date); // 1 = lunes … 7 = domingo

            if (in_array($day, $veryLongDays, true)) {
                $this->veryLongThread($date);
                continue;
            }

            if (($dow === 7 || $dow === 1) && mt_rand(1, 100) <= 80) {
                $this->matchReportThread($date);
            } elseif ($dow >= 2 && $dow <= 6 && mt_rand(1, 100) <= 60) {
                $this->logisticsThread($date);
            }
        }

        // Cierre calcado de la captura del ticket: mensaje largo por la
        // mañana del día de hoy + respuesta corta del entrenador.
        $this->cursor = max($this->cursor, $today + 9 * 3600 + 13 * 60);
        $this->add('player', "Buenas! A Marc esta semana le pasó lo que le pasa contigo: le costó bastante entrar en el partido y el primer cuarto estuvo muy desconectado, como todo el equipo, pero en su posición se notó más.\n\nPor otro lado, a partir de ahí fue a más y acabó marcando dos goles, dominando el centro del campo tanto en recuperación como en creación.\n\nSegún el míster hizo muy buen partido, pero yo le conozco y sé que no fue su mejor partido.\n\nMe comentó que debe golpear con la derecha porque no tiene confianza con la izquierda, y busca el pase aunque sea más difícil. Salió del recorte y golpeó con la derecha otra vez.\n\nEstá muy contento con lo que trabajasteis la semana pasada, así que te lo cuento para que lo sepas.");
        $this->reply(10, 30);
        $this->add('coach', 'Perfecto, gracias por contármelo. Cuando llegue hoy que me lo explique y trabajamos eso hoy.');
    }

    private function matchReportThread(int $date): void
    {
        $this->startAt($date, 20, 22);
        $this->add('player', $this->matchReport());

        if (mt_rand(1, 100) <= 25) {
            $this->reply(2, 8);
            $this->add('player', $this->pick(self::PLAYER_SHORT_EXTRA));
        }

        // Respuesta del entrenador a la mañana siguiente.
        $this->cursor = max($this->cursor, strtotime('+1 day', $date) + mt_rand(8 * 3600, 11 * 3600));
        $this->add('coach', $this->coachReply());

        $this->reply(15, 120);
        $this->add('player', $this->pick(self::PLAYER_SHORT));

        if (mt_rand(1, 100) <= 60) {
            $this->reply(5, 60);
            $this->add('coach', $this->pick(self::COACH_SHORT));
        }
    }

    private function logisticsThread(int $date): void
    {
        $this->startAt($date, 11, 18);
        $coachStarts = mt_rand(0, 1) === 1;
        $turns       = mt_rand(2, 4);

        for ($i = 0; $i < $turns; $i++) {
            $isCoach = ($i % 2 === 0) === $coachStarts;
            if ($i === 0) {
                $body = $isCoach ? $this->pick(self::COACH_QUESTIONS) : $this->pick(self::PLAYER_LOGISTICS);
            } else {
                $body = $isCoach ? $this->pick(self::COACH_SHORT) : $this->pick(self::PLAYER_SHORT);
            }
            $this->add($isCoach ? 'coach' : 'player', $body);
            $this->reply(3, 90);
        }
    }

    private function veryLongThread(int $date): void
    {
        $this->startAt($date, 21, 22);

        $paragraphs = [];
        foreach ($this->pickMany(self::REPORT_SENTENCES, 15) as $i => $sentence) {
            $paragraphs[intdiv($i, 3)][] = $sentence;
        }
        $body  = "Hola! Hoy te escribo un mensaje largo porque quería hacerte un resumen de todo el mes de Marc, que han pasado muchas cosas.\n\n";
        $body .= implode("\n\n", array_map(static fn ($p) => implode(' ', $p), $paragraphs));
        $body .= "\n\nPerdona el testamento 😅 pero prefiero que lo tengas todo por escrito. Gracias por todo, de verdad se nota el trabajo.";
        $this->add('player', $body);

        $this->cursor = max($this->cursor, strtotime('+1 day', $date) + mt_rand(9 * 3600, 12 * 3600));
        $plan  = "Buenos días! Muchísimas gracias por el resumen, así da gusto trabajar. Te contesto por partes y te dejo el plan para las próximas semanas.\n\n";
        $plan .= implode("\n\n", $this->pickMany(self::COACH_REPLY_SENTENCES, 5));
        $plan .= "\n\nPlanificación:\n"
            . "- Semana 1: control orientado y pase con pierna izquierda, primero sin oposición y luego con defensor pasivo.\n"
            . "- Semana 2: escaneo antes de recibir (perfiles, mirar por encima del hombro) en rondos 4x2 y juego de posición.\n"
            . "- Semana 3: finalización tras recorte hacia la pierna izquierda, con portero y tiempo limitado.\n"
            . "- Semana 4: reacción tras pérdida y duelos 1x1 defensivos, con objetivo de 5 segundos de presión.\n"
            . "- Todas las semanas: 10 minutos de coordinación y velocidad de reacción al inicio.\n\n"
            . "Al final del mes volvemos a grabar las mismas pruebas de la valoración inicial y comparamos. Cualquier cosa me decís.";
        $this->add('coach', $plan);

        $this->reply(20, 180);
        $this->add('player', 'Perfecto, muchas gracias por tomarte el tiempo de explicarlo todo tan bien. Se lo leo esta noche a Marc.');
    }

    private function matchReport(): string
    {
        $body  = $this->pick(self::REPORT_OPENERS) . "\n\n";
        $body  = str_replace('{rival}', $this->pick(self::RIVALS), $body);
        $body .= implode(' ', $this->pickMany(self::REPORT_SENTENCES, mt_rand(3, 6)));
        $closer = $this->pick(self::REPORT_CLOSERS);
        return $closer === '' ? $body : $body . "\n\n" . $closer;
    }

    private function coachReply(): string
    {
        return $this->pick(self::COACH_REPLY_OPENERS) . "\n\n"
            . implode("\n\n", $this->pickMany(self::COACH_REPLY_SENTENCES, mt_rand(2, 3)));
    }

    // ────────────────────────────────────────────────────────────────
    //  Utilidades de tiempo / aleatoriedad determinista
    // ────────────────────────────────────────────────────────────────

    private function startAt(int $date, int $fromHour, int $toHour): void
    {
        $candidate    = $date + mt_rand($fromHour * 3600, $toHour * 3600 + 59 * 60);
        $this->cursor = max($this->cursor + 600, $candidate);
    }

    private function reply(int $minMinutes, int $maxMinutes): void
    {
        $this->cursor += mt_rand($minMinutes * 60, $maxMinutes * 60);
    }

    private function add(string $who, string $body): void
    {
        $this->cursor += mt_rand(20, 59); // nunca dos mensajes con el mismo segundo
        $this->messages[] = [
            'sender_id'  => $who === 'coach' ? $this->coachId : $this->playerId,
            'body'       => $body,
            'created_at' => date('Y-m-d H:i:s', $this->cursor),
        ];
    }

    private function pick(array $pool): string
    {
        return $pool[mt_rand(0, count($pool) - 1)];
    }

    private function pickMany(array $pool, int $n): array
    {
        $keys = array_keys($pool);
        for ($i = count($keys) - 1; $i > 0; $i--) {
            $j = mt_rand(0, $i);
            [$keys[$i], $keys[$j]] = [$keys[$j], $keys[$i]];
        }
        return array_map(static fn ($k) => $pool[$k], array_slice($keys, 0, min($n, count($keys))));
    }

    // ────────────────────────────────────────────────────────────────
    //  Persistencia
    // ────────────────────────────────────────────────────────────────

    private function persist(int $convId): void
    {
        $this->db->transStart();

        $this->db->table('messages')->where('conversation_id', $convId)->delete();

        $rows  = [];
        $last  = count($this->messages) - 1;
        foreach ($this->messages as $i => $msg) {
            $rows[] = [
                'conversation_id' => $convId,
                'sender_id'       => $msg['sender_id'],
                'body'            => $msg['body'],
                'created_at'      => $msg['created_at'],
                // Todo leído salvo la última respuesta del entrenador.
                'read_at'         => $i === $last ? null : date('Y-m-d H:i:s', strtotime($msg['created_at']) + 300),
            ];
        }
        foreach (array_chunk($rows, 50) as $chunk) {
            $this->db->table('messages')->insertBatch($chunk);
        }

        $this->db->table('conversations')->where('id', $convId)->update([
            'last_message_at' => end($this->messages)['created_at'],
        ]);

        $this->db->transComplete();

        if (!$this->db->transStatus()) {
            throw new \RuntimeException('No se pudieron guardar los mensajes de la conversación.');
        }
    }

    // ────────────────────────────────────────────────────────────────
    //  Textos (inventados, estilo de los mensajes reales de familias)
    // ────────────────────────────────────────────────────────────────

    private const RIVALS = [
        'Cornellà', 'Sant Boi', 'Molins de Rei', 'Pallejà', 'Gavà',
        'Viladecans', 'Esplugues', 'Castelldefels', 'Sant Feliu', 'Martorell',
    ];

    private const REPORT_OPENERS = [
        'Buenas! Te cuento cómo ha ido el partido de Marc contra el {rival}.',
        'Hola, buenas noches. Te escribo para contarte lo del partido de hoy contra el {rival}.',
        'Buenas tardes. Hoy jugaban contra el {rival} y quería comentarte un par de cosas.',
        'Hola! Perdona que te escriba tan tarde, pero quería contártelo antes de que se me olvide. Jugaron contra el {rival}.',
        'Buenas! Te paso un resumen de cómo le ha ido esta semana en el club y el partido contra el {rival}.',
    ];

    private const REPORT_SENTENCES = [
        'Le costó mucho entrar en el partido y el primer cuarto de hora estuvo bastante desconectado, como todo el equipo, pero en su posición se notó más.',
        'A partir de la media parte fue a más y acabó dominando el centro del campo, tanto en recuperación como en creación.',
        'Según el entrenador del club hizo muy buen partido, pero yo le conozco y sé que no fue su mejor partido.',
        'Me comentó que golpea con la derecha porque con la izquierda no tiene confianza, y busca el pase aunque sea más difícil.',
        'En una jugada salió del recorte y golpeó con la izquierda, que es justo lo que estuvisteis trabajando el jueves.',
        'Está muy contento con lo que trabajasteis, dice que ahora se siente más rápido en los primeros metros.',
        'En los balones divididos todavía le falta un poco de agresividad, se frena antes del contacto.',
        'El míster le ha cambiado de posición y ahora juega más por dentro, y le está costando orientarse de espaldas.',
        'Cuando pierde el balón baja los brazos y tarda en reaccionar, eso se lo han dicho ya varias veces en el club.',
        'En la segunda parte le pidieron que presionara la salida del central y lo hizo muy bien, robó dos balones en campo contrario.',
        'Tuvo una ocasión clarísima en el minuto 70 y la tiró fuera; se fue a casa bastante enfadado consigo mismo.',
        'Físicamente llegó cansado, esta semana ha tenido exámenes y ha dormido poco.',
        'El martes se hizo un poco de daño en el tobillo izquierdo, nada grave, pero el miércoles no entrenó en el club por precaución.',
        'Nos ha pedido si podéis trabajar más el control orientado con la pierna mala, dice que es lo que más nota en los partidos.',
        'El entrenador de porteros le dijo que chuta siempre muy cruzado y que los porteros ya se lo leen.',
        'Con el cambio de horario del colegio llega un poco justo a la sesión de los jueves, intentaremos salir antes.',
        'En el vestuario está muy integrado, eso sí, y los compañeros le buscan mucho para jugar.',
        'Nos dijeron que en unas semanas hay pruebas para la selección comarcal y que le van a llamar.',
        'La verdad es que en casa lo vemos más motivado desde que viene a las sesiones con vosotros.',
        'Le cuesta mucho levantar la cabeza antes de recibir: recibe y luego mira, y ahí ya le llegan encima.',
        'En las jugadas a balón parado le ponen al primer palo y ha rematado bastante bien.',
        'El campo estaba muy mal, de tierra casi, y el balón botaba raro; aun así estuvo bastante fino en el control.',
        'Marcó un gol de cabeza en un córner, el primero de cabeza de la temporada, y lo celebró muchísimo.',
        'Le sacaron amarilla por protestar, cosa que no suele hacer, así que igual convendría hablarlo con él.',
    ];

    private const REPORT_CLOSERS = [
        'Te lo comento para que lo sepas y lo podáis trabajar.',
        'Ya me dirás qué te parece.',
        'Gracias por todo, de verdad se nota el trabajo.',
        'Si necesitas que te pase el vídeo del partido, me lo dices.',
        'Mañana le llevo a la sesión de las 18:00.',
        '',
    ];

    private const COACH_REPLY_OPENERS = [
        'Buenos días! Muchas gracias por el resumen, es muy útil para preparar la sesión.',
        'Buenas! Gracias por contármelo con tanto detalle.',
        'Hola! Qué bien que me lo expliques, así lo tenemos en cuenta esta semana.',
    ];

    private const COACH_REPLY_SENTENCES = [
        'Lo de entrar frío en los partidos lo hemos visto también en las sesiones: los primeros ejercicios le cuestan. Vamos a meter un calentamiento más intenso, con toma de decisiones desde el minuto uno.',
        'Lo de la pierna izquierda lo tenemos apuntado. Esta semana haremos un circuito de control orientado y finalización solo con la izquierda, sin presión al principio y luego con oposición.',
        'Es normal que después de un cambio de posición le cueste. Trabajaremos perfiles corporales y escaneo antes de recibir, que es justo lo que necesita jugando por dentro.',
        'Sobre el tobillo: si el jueves todavía le molesta, hacemos sesión técnica sin cambios de dirección y lo valoramos allí mismo.',
        'Lo de bajar los brazos tras la pérdida es muy habitual a su edad. Le pondremos un objetivo concreto: 5 segundos de presión tras pérdida en cada ejercicio.',
        'Que no se preocupe por la ocasión fallada, lo importante es que llegó. Trabajaremos definición con poco tiempo y mirando al portero.',
        'Si podéis, pasadme el vídeo y lo analizamos con él en la sala antes de la sesión; le va muy bien verse.',
        'Genial lo de la selección comarcal. Preparamos unas sesiones más enfocadas a lo que suelen evaluar: primer control, orientación y velocidad de ejecución.',
        'El tema del descanso es clave. Si esta semana va justo por los exámenes, hacemos una sesión más corta y de calidad, no pasa nada.',
        'Lo de la amarilla por protestar lo hablo con él con calma, sin darle más importancia de la que tiene, pero es bueno que aprenda a gestionar la frustración.',
    ];

    private const PLAYER_SHORT = [
        'Gracias!', 'Vale, perfecto.', 'Ok, allí estaremos.', '👍', 'Genial, muchas gracias.',
        'Perfecto, se lo digo.', 'De acuerdo!', 'Gracias a ti.',
    ];

    private const PLAYER_SHORT_EXTRA = [
        'Ah, y se me olvidaba: el sábado no juega porque tienen jornada de descanso.',
        'Te paso luego el vídeo del partido por aquí si quieres.',
        'Por cierto, el jueves llegaremos 10 minutos tarde por el colegio.',
    ];

    private const PLAYER_LOGISTICS = [
        'Hola! ¿La sesión del sábado sigue en pie con la lluvia que están dando?',
        'Buenas, mañana no podrá venir, tiene médico. ¿Podemos recuperarla otro día?',
        'Llegamos 5 minutos tarde, perdona.',
        'Ya estamos en el campo.',
        'Hola! ¿Tiene que traer botas de tacos o de multitaco para el campo 2?',
        '¿Le queda alguna sesión del bono o ya hay que renovarlo?',
    ];

    private const COACH_QUESTIONS = [
        '¿Qué tal ha ido hoy el partido?',
        '¿Cómo está del tobillo?',
        '¿Mañana viene a la sesión de las 18:00?',
        'Hola! Esta semana cambiamos la sesión del jueves al viernes a la misma hora, ¿os va bien?',
        'Recordad traer la ropa de recambio, que hoy hacemos trabajo en el campo de tierra.',
    ];

    private const COACH_SHORT = [
        'Perfecto, gracias!', 'Recibido 👍', 'Ok, lo hablamos en la sesión.', 'Sin problema.',
        'Genial!', 'Nos vemos el jueves.', 'Apuntado.', 'Gracias a vosotros.',
    ];
}
