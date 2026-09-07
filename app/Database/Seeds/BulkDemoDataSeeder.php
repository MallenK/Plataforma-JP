<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;
use App\Models\UserModel;
use App\Models\PlayerProfileModel;
use App\Models\BonoTypeModel;
use App\Models\PlayerBonoModel;
use App\Services\ClasesService;

/**
 * SOLO PARA DESARROLLO LOCAL. Genera un volumen de datos "de mentira" pero
 * realista (sedes, entrenadores, staff, alumnos, tipos de bono, bonos
 * asignados y clases puntuales + recurrentes, pasadas y futuras) para que
 * el entorno local se parezca al de producción en cantidad de registros.
 *
 * Todos los usuarios creados llevan el dominio @demo.tuplataforma.local
 * (distinto de @test.tuplataforma.local, que usa DevTestDataSeeder para
 * casos de prueba puntuales de tickets concretos). Idempotente: se puede
 * volver a ejecutar sin duplicar nada (se busca por email/nombre/título).
 *
 * Ejecutar:  docker compose exec app php spark db:seed BulkDemoDataSeeder
 */
class BulkDemoDataSeeder extends Seeder
{
    private const DOMAIN = '@demo.tuplataforma.local';

    private UserModel $userModel;
    private PlayerProfileModel $profileModel;
    private BonoTypeModel $bonoTypeModel;
    private PlayerBonoModel $bonoModel;
    private ClasesService $clasesService;

    public function run()
    {
        $this->userModel     = new UserModel();
        $this->profileModel  = new PlayerProfileModel();
        $this->bonoTypeModel = new BonoTypeModel();
        $this->bonoModel     = new PlayerBonoModel();
        $this->clasesService = new ClasesService();
        $this->db            = $this->db ?: \Config\Database::connect();

        $locationIds = $this->seedLocations();
        $bonoTypeIds = $this->seedBonoTypes();
        $coachIds    = $this->seedCoaches();
        $staffIds    = $this->seedStaff();
        $playerIds   = $this->seedPlayers();

        $this->seedBonos($playerIds, $bonoTypeIds);
        $this->seedRecurringClasses($coachIds, $staffIds, $playerIds, $locationIds);
        $this->seedSingleClasses($coachIds, $staffIds, $playerIds, $locationIds);

        echo "BulkDemoDataSeeder: listo.\n";
        echo "  sedes: " . count($locationIds) . " | tipos de bono: " . count($bonoTypeIds) . "\n";
        echo "  entrenadores: " . count($coachIds) . " | staff: " . count($staffIds) . " | alumnos: " . count($playerIds) . "\n";
    }

    // ────────────────────────────────────────────────────────────────
    //  Sedes / campos
    // ────────────────────────────────────────────────────────────────

    private function seedLocations(): array
    {
        $data = [
            ['name' => 'Campo Municipal',          'type' => 'pitch', 'address' => 'Calle del Deporte 1',  'capacity' => 40],
            ['name' => 'Campo 2 - Tecnificación',  'type' => 'pitch', 'address' => 'Calle del Deporte 1',  'capacity' => 22],
            ['name' => 'Gimnasio',                 'type' => 'gym',   'address' => 'Avenida Mayor 25',    'capacity' => 15],
            ['name' => 'Sala de vídeo / análisis',  'type' => 'room',  'address' => 'Avenida Mayor 25',    'capacity' => 12],
            ['name' => 'Oficina',                  'type' => 'office','address' => 'Avenida Mayor 25',    'capacity' => null],
        ];

        $ids = [];
        foreach ($data as $loc) {
            $existing = $this->db->table('locations')->where('name', $loc['name'])->get()->getRowArray();
            if ($existing) {
                $ids[] = (int) $existing['id'];
                continue;
            }
            $now = date('Y-m-d H:i:s');
            $this->db->table('locations')->insert([
                'name'        => $loc['name'],
                'description' => '',
                'address'     => $loc['address'],
                'type'        => $loc['type'],
                'capacity'    => $loc['capacity'],
                'phone'       => '',
                'active'      => 1,
                'created_at'  => $now,
                'updated_at'  => $now,
            ]);
            $ids[] = (int) $this->db->insertID();
        }
        echo "Sedes: " . count($ids) . " disponibles.\n";
        return $ids;
    }

    // ────────────────────────────────────────────────────────────────
    //  Tipos de bono
    // ────────────────────────────────────────────────────────────────

    private function seedBonoTypes(): array
    {
        $data = [
            ['name' => 'Bono 5 sesiones',       'sessions' => 5,  'price' => 75.00,  'validity_days' => 45],
            ['name' => 'Bono 10 sesiones',      'sessions' => 10, 'price' => 140.00, 'validity_days' => 90],
            ['name' => 'Bono 20 sesiones',      'sessions' => 20, 'price' => 260.00, 'validity_days' => 120],
            ['name' => 'Bono mensual (8 ses.)', 'sessions' => 8,  'price' => 110.00, 'validity_days' => 30],
            ['name' => 'Bono trimestral (24 ses.)', 'sessions' => 24, 'price' => 300.00, 'validity_days' => 100],
        ];

        $ids = [];
        foreach ($data as $t) {
            $existing = $this->bonoTypeModel->where('name', $t['name'])->first();
            if ($existing) {
                $ids[] = (int) $existing['id'];
                continue;
            }
            $id = $this->bonoTypeModel->insert([
                'name'          => $t['name'],
                'sessions'      => $t['sessions'],
                'price'         => $t['price'],
                'validity_days' => $t['validity_days'],
                'active'        => 1,
            ], true);
            $ids[] = (int) $id;
        }
        echo "Tipos de bono: " . count($ids) . " disponibles.\n";
        return $ids;
    }

    // ────────────────────────────────────────────────────────────────
    //  Entrenadores / staff
    // ────────────────────────────────────────────────────────────────

    private const COACH_NAMES = [
        'Marc Puig', 'Laura Ferrer', 'David Soler', 'Anna Vidal', 'Jordi Roca',
        'Núria Camps', 'Alex Riera', 'Cristina Bosch',
    ];

    private const STAFF_NAMES = [
        'Montse Pla', 'Ferran Coll', 'Silvia Torres',
    ];

    private function seedCoaches(): array
    {
        return $this->seedStaffUsers(self::COACH_NAMES, 'coach', 'coach');
    }

    private function seedStaff(): array
    {
        return $this->seedStaffUsers(self::STAFF_NAMES, 'staff', 'staff');
    }

    private function seedStaffUsers(array $names, string $role, string $slugPrefix): array
    {
        $ids = [];
        foreach ($names as $i => $name) {
            $email = 'demo.' . $slugPrefix . ($i + 1) . self::DOMAIN;
            $existing = $this->userModel->where('email', $email)->first();
            if ($existing) {
                $ids[] = (int) $existing['id'];
                continue;
            }
            $uid = $this->userModel->insert([
                'name'     => $name,
                'email'    => $email,
                'password' => 'Demo1234!',
                'role'     => $role,
                'status'   => 'active',
            ], true);
            if ($uid) {
                $ids[] = (int) $uid;
            }
        }
        return $ids;
    }

    // ────────────────────────────────────────────────────────────────
    //  Alumnos
    // ────────────────────────────────────────────────────────────────

    private const PLAYER_FIRST_NAMES = [
        'Pau', 'Marc', 'Nil', 'Biel', 'Eric', 'Bruno', 'Aleix', 'Arnau', 'Gerard', 'Oriol',
        'Martina', 'Laia', 'Emma', 'Julia', 'Aina', 'Carla', 'Clàudia', 'Judith', 'Paula', 'Alba',
        'Marc', 'Hugo', 'Dani', 'Adrià', 'Iker', 'Rayan', 'Youssef', 'Mohamed', 'Diego', 'Mateo',
        'Leo', 'Izan', 'Jan', 'Enzo', 'Roc', 'Guim', 'Ivet', 'Naia', 'Ona', 'Vera',
    ];

    private const PLAYER_LAST_NAMES = [
        'García', 'Martínez', 'López', 'Fernández', 'Pérez', 'Gómez', 'Sánchez', 'Romero', 'Navarro', 'Torres',
        'Vázquez', 'Serra', 'Prat', 'Vila', 'Balcells', 'Bonet', 'Costa', 'Marín', 'Ortega', 'Ramos',
    ];

    private const TEAMS = [
        'CF Águilas A', 'CF Águilas B', 'UD Los Robles', 'CD San Marcos', 'CF Ribera',
        'FC Montecarmelo', 'UD Valdeluz', 'CF Peñalba',
    ];

    private const CATEGORIES = ['prebenjamin', 'benjamin', 'alevin', 'infantil', 'cadete', 'juvenil'];

    private const LEAGUES = ['1a Territorial', '2a Territorial', 'Preferent', 'Divisió d\'Honor'];

    private function seedPlayers(int $count = 60): array
    {
        $ids       = [];
        $positions = array_keys(PlayerProfileModel::POSITIONS);

        for ($i = 1; $i <= $count; $i++) {
            $email = 'demo.alumno' . $i . self::DOMAIN;
            $existing = $this->userModel->where('email', $email)->first();
            if ($existing) {
                $ids[] = (int) $existing['id'];
                continue;
            }

            $first = self::PLAYER_FIRST_NAMES[$i % count(self::PLAYER_FIRST_NAMES)];
            $last1 = self::PLAYER_LAST_NAMES[$i % count(self::PLAYER_LAST_NAMES)];
            $last2 = self::PLAYER_LAST_NAMES[($i * 3 + 1) % count(self::PLAYER_LAST_NAMES)];
            $name  = "{$first} {$last1} {$last2}";

            $uid = $this->userModel->insert([
                'name'     => $name,
                'email'    => $email,
                'password' => 'Demo1234!',
                'role'     => 'player',
                'status'   => 'active',
            ], true);

            if (!$uid) {
                continue;
            }
            $ids[] = (int) $uid;

            // 1 o 2 posiciones por alumno.
            $numPositions = ($i % 4 === 0) ? 2 : 1;
            $chosen = [];
            for ($p = 0; $p < $numPositions; $p++) {
                $chosen[] = $positions[($i + $p * 5) % count($positions)];
            }

            $birthYear = 2026 - (8 + ($i % 12)); // entre ~8 y ~19 años
            $this->profileModel->insert([
                'player_id'  => $uid,
                'birth_date' => sprintf('%04d-%02d-%02d', $birthYear, 1 + ($i % 12), 1 + ($i % 28)),
                'height'     => 130 + ($i % 55),
                'weight'     => 30 + ($i % 45),
                'position'   => PlayerProfileModel::encodePositions($chosen),
                'category'   => self::CATEGORIES[$i % count(self::CATEGORIES)],
                'team'       => self::TEAMS[$i % count(self::TEAMS)],
                'league'     => self::LEAGUES[$i % count(self::LEAGUES)],
            ]);
        }

        echo "Alumnos: " . count($ids) . " disponibles.\n";
        return $ids;
    }

    // ────────────────────────────────────────────────────────────────
    //  Bonos asignados
    // ────────────────────────────────────────────────────────────────

    private function seedBonos(array $playerIds, array $bonoTypeIds): void
    {
        if (empty($bonoTypeIds)) {
            return;
        }

        $created = 0;
        foreach ($playerIds as $i => $playerId) {
            // Al ~75% de alumnos se les asigna bono; el resto se queda sin
            // bono activo (para poder probar también ese caso).
            if ($i % 4 === 3) {
                continue;
            }

            $existing = $this->bonoModel->where('player_id', $playerId)->first();
            if ($existing) {
                continue;
            }

            $type = $this->bonoTypeModel->find($bonoTypeIds[$i % count($bonoTypeIds)]);
            if (!$type) {
                continue;
            }

            // Variedad de estados: recién comprado, a medias, casi agotado.
            $daysAgo   = [2, 15, 30, 45, 60][$i % 5];
            $startDate = date('Y-m-d', strtotime("-{$daysAgo} days"));
            $expiresAt = date('Y-m-d', strtotime($startDate . " +{$type['validity_days']} days"));

            $total     = (int) $type['sessions'];
            $usedRatio = [0, 0.2, 0.5, 0.8][$i % 4];
            $remaining = max(0, $total - (int) round($total * $usedRatio));

            $this->bonoModel->insert([
                'player_id'          => $playerId,
                'bono_type_id'       => $type['id'],
                'sessions_total'     => $total,
                'sessions_remaining' => $remaining,
                'start_date'         => $startDate,
                'expires_at'         => $expiresAt,
                'notes'              => 'Bono de demostración (seed local).',
            ]);
            $created++;
        }

        echo "Bonos asignados: {$created} nuevos.\n";
    }

    // ────────────────────────────────────────────────────────────────
    //  Clases recurrentes (plantillas semanales)
    // ────────────────────────────────────────────────────────────────

    private function seedRecurringClasses(array $coachIds, array $staffIds, array $playerIds, array $locationIds): void
    {
        if (empty($coachIds) || empty($playerIds)) {
            echo "  aviso: faltan entrenadores o alumnos, se omiten clases recurrentes.\n";
            return;
        }

        $creatorId = $coachIds[0];
        $from = date('Y-m-d', strtotime('-8 weeks monday'));
        $to   = date('Y-m-d', strtotime('+8 weeks sunday'));

        // 6 días laborables x 2 franjas = 12 plantillas repartidas entre
        // entrenadores, sedes y grupos de alumnos distintos.
        $slots = [
            [1, '17:00', '18:00'], [1, '19:00', '20:00'],
            [2, '17:00', '18:00'], [2, '19:00', '20:00'],
            [3, '17:00', '18:00'], [3, '19:00', '20:00'],
            [4, '17:00', '18:00'], [4, '19:00', '20:00'],
            [5, '17:00', '18:00'], [5, '19:00', '20:00'],
            [6, '10:00', '11:00'], [6, '11:30', '13:00'],
        ];

        $groupSize = 4;
        $created   = 0;

        foreach ($slots as $i => [$day, $start, $end]) {
            $title = "Tecnificación Grupo " . chr(65 + $i) . " (recurrente demo)";

            if ($this->db->table('classes')->where('title', $title)->countAllResults() > 0) {
                continue;
            }

            $coach    = $coachIds[$i % count($coachIds)];
            $location = $locationIds[$i % max(1, count($locationIds))] ?? null;

            $slice = array_slice($playerIds, ($i * $groupSize) % max(1, count($playerIds)), $groupSize);
            if (empty($slice)) {
                $slice = array_slice($playerIds, 0, $groupSize);
            }

            $result = $this->clasesService->quickCreate([
                'type'             => 'recurring',
                'title'            => $title,
                'class_format'     => count($slice) > 1 ? 'pareja' : 'individual',
                'session_type'     => 'coach',
                'recurrence_days'  => [$day],
                'recurrence_start' => $from,
                'recurrence_end'   => $to,
                'start_time'       => $start,
                'end_time'         => $end,
                'location_id'      => $location,
                'coach_ids'        => [$coach],
                'player_ids'       => $slice,
            ], $creatorId);

            if (!empty($result['success'])) {
                $created++;
            } else {
                echo "  ERROR plantilla '{$title}': " . ($result['error'] ?? '?') . "\n";
            }
        }

        echo "Plantillas recurrentes creadas: {$created}.\n";
    }

    // ────────────────────────────────────────────────────────────────
    //  Clases puntuales (pasadas y futuras)
    // ────────────────────────────────────────────────────────────────

    private function seedSingleClasses(array $coachIds, array $staffIds, array $playerIds, array $locationIds): void
    {
        if (empty($coachIds) || empty($playerIds)) {
            echo "  aviso: faltan entrenadores o alumnos, se omiten clases puntuales.\n";
            return;
        }

        $creatorId  = $coachIds[0];
        $bonoModel  = $this->bonoModel;
        $created    = 0;
        $completed  = 0;

        // 25 sesiones individuales repartidas entre -30 y +20 días.
        for ($i = 0; $i < 25; $i++) {
            $offset = -30 + ($i * 2); // de -30 a +18
            $date   = date('Y-m-d', strtotime("{$offset} days"));
            $title  = "Sesión individual demo #{$i} ({$date})";

            $existing = $this->db->table('class_sessions')->where('title', $title)->get()->getRowArray();
            if ($existing) {
                continue;
            }

            $coach     = $coachIds[$i % count($coachIds)];
            $player    = $playerIds[$i % count($playerIds)];
            $location  = $locationIds[$i % max(1, count($locationIds))] ?? null;
            $startHour = 9 + ($i % 10);

            $result = $this->clasesService->quickCreate([
                'type'         => 'single',
                'title'        => $title,
                'class_format' => 'individual',
                'session_type' => 'coach',
                'session_date' => $date,
                'start_time'   => sprintf('%02d:00', $startHour),
                'end_time'     => sprintf('%02d:00', $startHour + 1),
                'location_id'  => $location,
                'coach_ids'    => [$coach],
                'player_ids'   => [$player],
            ], $creatorId);

            if (empty($result['success'])) {
                echo "  ERROR sesión '{$title}': " . ($result['error'] ?? '?') . "\n";
                continue;
            }
            $created++;
            $sid = $result['id'];

            // Las sesiones pasadas se marcan como completadas, con lista
            // pasada y feedback; algunas descuentan bono si el alumno tiene
            // uno activo.
            if ($offset < 0) {
                $this->clasesService->updateAttendance($sid, [$player => 'present']);
                $this->clasesService->cerrarSesion($sid, $creatorId);
                if ($bonoModel->getActiveBono($player)) {
                    $this->clasesService->deductBonoForPlayer($sid, $player);
                }
                $completed++;
            }
        }

        // Un par de sesiones canceladas, para completar los estados.
        for ($i = 0; $i < 3; $i++) {
            $date  = date('Y-m-d', strtotime(($i + 1) . ' days'));
            $title = "Sesión cancelada demo #{$i} ({$date})";

            $existing = $this->db->table('class_sessions')->where('title', $title)->get()->getRowArray();
            if ($existing) {
                continue;
            }

            $coach  = $coachIds[$i % count($coachIds)];
            $player = $playerIds[($i + 5) % count($playerIds)];

            $result = $this->clasesService->quickCreate([
                'type'         => 'single',
                'title'        => $title,
                'class_format' => 'individual',
                'session_type' => 'coach',
                'session_date' => $date,
                'start_time'   => '16:00',
                'end_time'     => '17:00',
                'coach_ids'    => [$coach],
                'player_ids'   => [$player],
            ], $creatorId);

            if (!empty($result['success'])) {
                $this->db->table('class_sessions')->where('id', $result['id'])->update(['status' => 'cancelled']);
            }
        }

        echo "Sesiones puntuales creadas: {$created} ({$completed} completadas con feedback/lista).\n";
    }
}
