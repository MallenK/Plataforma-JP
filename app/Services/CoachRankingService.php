<?php

namespace App\Services;

use CodeIgniter\Database\BaseConnection;

/**
 * Rankings y estadísticas de entrenadores (solo admin/superadmin).
 *
 * TODO sale de la BD (class_sessions, class_session_coaches, class_session_players):
 * no hay datos de ejemplo ni cifras calculadas a mano.
 *
 * Definiciones (las mismas que se explican en la pantalla):
 *   - «Impartida»  = clase con status `completed` (lista pasada y cerrada) dentro del periodo.
 *   - «Sin cerrar» = clase `scheduled` con fecha ya pasada (nadie la cerró).
 *   - «Cancelada»  = status `cancelled`.
 *   - Una clase con varios entrenadores cuenta para cada uno de ellos.
 *   - Asistencia   = presentes / (presentes + ausentes + sin justificar + avisó ausencia),
 *                    sobre clases impartidas; los `pending`/`confirmed` no cuentan.
 *   - Cierre a tiempo = lista pasada dentro de las 24 h siguientes al fin de la clase.
 *   - Alumno fiel  = alumno con FIDELITY_MIN o más asistencias con ese entrenador en el periodo.
 *
 * Las cuentas excluidas de Finanzas (usuarios de prueba) también quedan fuera de aquí
 * (FinanceExclusion::sessionSql), para que no falseen los rankings.
 *
 * Sin dinero a propósito: el acceso a los importes es por persona (FinanceAccessService)
 * y esta pantalla la ve cualquier admin.
 */
class CoachRankingService
{
    public const FIDELITY_MIN   = 4;   // asistencias para contar como alumno fiel
    public const MIN_CLASSES    = 5;   // mínimo de clases para entrar en rankings de porcentaje
    public const ON_TIME_HOURS  = 24;  // plazo para cerrar la lista
    public const TREND_MONTHS   = 12;

    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? \Config\Database::connect();
    }

    // ================================================================
    //  Puras (testeadas sin BD)
    // ================================================================

    /**
     * Periodo elegido en la pantalla. Admite un atajo (?p=) o un rango (?desde/?hasta).
     *
     * @param array<string,mixed> $get
     * @return array{key:string,from:string,to:string,label:string}
     */
    public static function period(array $get, string $today): array
    {
        $d = static fn(string $s): ?string => preg_match('/^\d{4}-\d{2}-\d{2}$/', $s) && strtotime($s) !== false ? $s : null;
        $from = $d((string) ($get['desde'] ?? ''));
        $to   = $d((string) ($get['hasta'] ?? ''));

        if ($from !== null && $to !== null) {
            if ($from > $to) {
                [$from, $to] = [$to, $from];
            }
            return ['key' => 'rango', 'from' => $from, 'to' => $to,
                    'label' => date('d/m/Y', strtotime($from)) . ' – ' . date('d/m/Y', strtotime($to))];
        }

        $key = (string) ($get['p'] ?? '90');
        $ts  = strtotime($today);
        switch ($key) {
            case 'mes':
                return ['key' => 'mes', 'from' => date('Y-m-01', $ts), 'to' => date('Y-m-t', $ts), 'label' => 'Este mes'];
            case 'mes_ant':
                $first = strtotime('first day of last month', $ts);
                return ['key' => 'mes_ant', 'from' => date('Y-m-01', $first), 'to' => date('Y-m-t', $first), 'label' => 'Mes anterior'];
            case '30':
                return ['key' => '30', 'from' => date('Y-m-d', strtotime('-29 days', $ts)), 'to' => $today, 'label' => 'Últimos 30 días'];
            case 'anio':
                return ['key' => 'anio', 'from' => date('Y-01-01', $ts), 'to' => $today, 'label' => 'Este año'];
            case 'todo':
                return ['key' => 'todo', 'from' => '2000-01-01', 'to' => $today, 'label' => 'Todo el histórico'];
            default:
                return ['key' => '90', 'from' => date('Y-m-d', strtotime('-89 days', $ts)), 'to' => $today, 'label' => 'Últimos 90 días'];
        }
    }

    /** Porcentaje 0-100 con un decimal, o null si no hay base. */
    public static function rate(int|float $part, int|float $whole): ?float
    {
        return $whole > 0 ? round($part / $whole * 100, 1) : null;
    }

    /**
     * Top N de filas por una clave numérica. Las filas con valor null o que no cumplen
     * $eligible se descartan; el empate se resuelve por nombre para que el orden sea estable.
     *
     * @param array<int,array<string,mixed>> $rows
     * @return array<int,array<string,mixed>>
     */
    public static function top(array $rows, string $key, int $limit = 3, bool $desc = true, ?callable $eligible = null): array
    {
        $rows = array_values(array_filter($rows, static function ($r) use ($key, $eligible) {
            return isset($r[$key]) && $r[$key] !== null && ($eligible === null || $eligible($r));
        }));
        usort($rows, static function ($a, $b) use ($key, $desc) {
            $c = $desc ? ($b[$key] <=> $a[$key]) : ($a[$key] <=> $b[$key]);
            return $c !== 0 ? $c : strcasecmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });
        return array_slice($rows, 0, $limit);
    }

    /**
     * Puesto de un entrenador en cada métrica frente al resto (1 = el mejor) y media del equipo.
     * Las métricas de porcentaje solo puntúan con base suficiente; si no la tiene, 'pos' es null.
     * Los empates comparten puesto.
     *
     * @param array<int,array<string,mixed>> $rows todos los entrenadores del periodo
     * @return array<string,array{pos:?int,of:int,value:mixed,avg:?float}>
     */
    public static function positions(array $rows, int $coachId): array
    {
        $metrics = [
            'done'         => ['desc', null],
            'hours'        => ['desc', null],
            'students'     => ['desc', null],
            'att_rate'     => ['desc', static fn($r) => $r['done'] >= self::MIN_CLASSES],
            'loyal_rate'   => ['desc', static fn($r) => $r['students'] >= self::MIN_CLASSES],
            'on_time_rate' => ['desc', static fn($r) => $r['closed'] >= self::MIN_CLASSES],
            'unclosed'     => ['asc',  null],   // menos es mejor
        ];
        $me  = null;
        foreach ($rows as $r) {
            if ($r['id'] === $coachId) {
                $me = $r;
                break;
            }
        }
        $out = [];
        foreach ($metrics as $key => [$dir, $eligible]) {
            $pool  = array_values(array_filter($rows, static fn($r) => $r[$key] !== null && ($eligible === null || $eligible($r))));
            $value = $me[$key] ?? null;
            $pos   = null;
            if ($me !== null && $value !== null && ($eligible === null || $eligible($me))) {
                $better = count(array_filter($pool, static fn($o) => $dir === 'desc' ? $o[$key] > $value : $o[$key] < $value));
                $pos    = 1 + $better;
            }
            $avg = $pool ? round(array_sum(array_column($pool, $key)) / count($pool), 1) : null;
            $out[$key] = ['pos' => $pos, 'of' => count($pool), 'value' => $value, 'avg' => $avg];
        }
        return $out;
    }

    /** Nivel 0-4 de intensidad para pintar un mapa de calor. */
    public static function heatLevel(int $value, int $max): int
    {
        if ($value <= 0 || $max <= 0) {
            return 0;
        }
        return max(1, min(4, (int) ceil($value / $max * 4)));
    }

    // ================================================================
    //  Con BD
    // ================================================================

    /** Sedes activas, para el desplegable de filtro. */
    public function locations(): array
    {
        return $this->db->query('SELECT id, name FROM locations WHERE archived_at IS NULL ORDER BY name')->getResultArray();
    }

    /**
     * Informe completo del periodo.
     *
     * @return array<string,mixed>
     */
    public function report(string $from, string $to, ?int $locationId = null, ?string $today = null, ?int $coachId = null): array
    {
        $today ??= date('Y-m-d');
        $coaches = $this->activity($from, $to, $locationId, $today);
        $att     = $this->attendance($from, $to, $locationId);
        $stu     = $this->students($from, $to, $locationId);

        foreach ($coaches as $id => &$c) {
            $a = $att[$id] ?? ['present' => 0, 'absent' => 0, 'unjustified' => 0, 'declined' => 0];
            $c += $a;
            $base = $a['present'] + $a['absent'] + $a['unjustified'] + $a['declined'];
            $c['att_base']      = $base;
            $c['att_rate']      = self::rate($a['present'], $base);
            $c['noshow_rate']   = self::rate($a['unjustified'], $base);
            $s = $stu[$id] ?? ['distinct' => 0, 'loyal' => 0, 'new' => 0, 'present' => 0];
            $c['students']      = $s['distinct'];
            $c['loyal']         = $s['loyal'];
            $c['new_students']  = $s['new'];
            $c['loyal_rate']    = self::rate($s['loyal'], $s['distinct']);
            $c['per_student']   = $s['distinct'] > 0 ? round($s['present'] / $s['distinct'], 1) : null;
            $c['duo_rate']      = self::rate($c['duo'], $c['done']);
            $c['cancel_rate']   = self::rate($c['cancelled'], $c['done'] + $c['cancelled'] + $c['unclosed']);
            $c['on_time_rate']  = self::rate($c['on_time'], $c['closed']);
        }
        unset($c);

        $rows = array_values($coaches);
        usort($rows, static fn($a, $b) => [$b['done'], $a['name']] <=> [$a['done'], $b['name']]);

        // Vista de un solo entrenador: sus cifras, su posición frente al equipo y sus alumnos.
        $focus = null;
        $all   = $rows;
        if ($coachId) {
            $mine  = array_values(array_filter($rows, static fn($r) => $r['id'] === $coachId));
            $rows  = $mine;
            $focus = [
                'id'       => $coachId,
                'row'      => $mine[0] ?? null,
                'position' => $mine ? self::positions($all, $coachId) : [],
                'team'     => $this->totals($all, $this->countDone($from, $to, $locationId)),
                'students' => $this->topStudents($coachId, $from, $to, $locationId),
            ];
        }

        return [
            'coaches'   => $rows,
            'totals'    => $this->totals($rows, $this->countDone($from, $to, $locationId, $coachId)),
            'heatmap'   => $this->heatmap($from, $to, $locationId, $coachId),
            'trend'     => $this->trend($locationId, $today, $coachId),
            'locations' => $this->byLocation($from, $to, $locationId, $coachId),
            'focus'     => $focus,
        ];
    }

    /** SQL común: periodo + sede + exclusiones. Devuelve [sql, binds]. */
    private function scope(string $from, string $to, ?int $locationId, ?int $coachId = null): array
    {
        $sql   = ' cs.session_date BETWEEN ? AND ? ';
        $binds = [$from, $to];
        if ($locationId) {
            $sql    .= ' AND cs.location_id = ? ';
            $binds[] = $locationId;
        }
        if ($coachId) {
            // Solo las clases en las que participa ese entrenador (con o sin otros).
            $sql    .= ' AND EXISTS (SELECT 1 FROM class_session_coaches fcc WHERE fcc.session_id = cs.id AND fcc.user_id = ?) ';
            $binds[] = $coachId;
        }
        return [$sql . FinanceExclusion::sessionSql('cs'), $binds];
    }

    /** Clases, horas, cierre y formato por entrenador. */
    private function activity(string $from, string $to, ?int $loc, string $today): array
    {
        [$where, $binds] = $this->scope($from, $to, $loc);
        $dur = 'IF(cs.end_time > cs.start_time, TIME_TO_SEC(cs.end_time) - TIME_TO_SEC(cs.start_time), 3600)';
        $endDt = "TIMESTAMP(cs.session_date, cs.end_time)";

        $sql = "SELECT u.id, u.name, u.role, u.status,
                    SUM(cs.status = 'completed')                                  AS done,
                    SUM(cs.status = 'cancelled')                                  AS cancelled,
                    SUM(cs.status = 'scheduled' AND cs.session_date < ?)          AS unclosed,
                    SUM(cs.status = 'scheduled' AND cs.session_date >= ?)         AS upcoming,
                    COALESCE(SUM(IF(cs.status = 'completed', {$dur}, 0)), 0) / 3600 AS hours,
                    SUM(cs.status = 'completed' AND cs.class_format = 'pareja')   AS duo,
                    SUM(cs.status = 'completed' AND cs.lista_pasada_at IS NOT NULL) AS closed,
                    SUM(cs.status = 'completed' AND cs.lista_pasada_at IS NOT NULL
                        AND TIMESTAMPDIFF(HOUR, {$endDt}, cs.lista_pasada_at) <= " . self::ON_TIME_HOURS . ") AS on_time,
                    AVG(IF(cs.status = 'completed' AND cs.lista_pasada_at IS NOT NULL,
                        GREATEST(TIMESTAMPDIFF(MINUTE, {$endDt}, cs.lista_pasada_at), 0) / 60, NULL)) AS avg_close_hours,
                    COUNT(DISTINCT IF(cs.status = 'completed', cs.location_id, NULL)) AS locations,
                    COUNT(DISTINCT IF(cs.status = 'completed', cs.session_date, NULL)) AS days_worked
                FROM class_session_coaches csc
                JOIN class_sessions cs ON cs.id = csc.session_id
                JOIN users u ON u.id = csc.user_id
                WHERE {$where}
                GROUP BY u.id, u.name, u.role, u.status";

        $out = [];
        foreach ($this->db->query($sql, array_merge([$today, $today], $binds))->getResultArray() as $r) {
            $out[(int) $r['id']] = [
                'id'              => (int) $r['id'],
                'name'            => (string) $r['name'],
                'role'            => (string) $r['role'],
                'status'          => (string) $r['status'],
                'done'            => (int) $r['done'],
                'cancelled'       => (int) $r['cancelled'],
                'unclosed'        => (int) $r['unclosed'],
                'upcoming'        => (int) $r['upcoming'],
                'hours'           => round((float) $r['hours'], 1),
                'duo'             => (int) $r['duo'],
                'closed'          => (int) $r['closed'],
                'on_time'         => (int) $r['on_time'],
                'avg_close_hours' => $r['avg_close_hours'] !== null ? round((float) $r['avg_close_hours'], 1) : null,
                'locations'       => (int) $r['locations'],
                'days_worked'     => (int) $r['days_worked'],
            ];
        }
        return $out;
    }

    /** Asistencia de los alumnos en las clases impartidas de cada entrenador. */
    private function attendance(string $from, string $to, ?int $loc): array
    {
        [$where, $binds] = $this->scope($from, $to, $loc);
        $sql = "SELECT csc.user_id AS id,
                    SUM(csp.attendance = 'present')     AS present,
                    SUM(csp.attendance = 'absent')      AS absent,
                    SUM(csp.attendance = 'unjustified') AS unjustified,
                    SUM(csp.attendance = 'declined')    AS declined
                FROM class_session_coaches csc
                JOIN class_sessions cs ON cs.id = csc.session_id AND cs.status = 'completed'
                JOIN class_session_players csp ON csp.session_id = cs.id"
              . FinanceExclusion::playerSql('csp.user_id') . "
                WHERE {$where}
                GROUP BY csc.user_id";
        $out = [];
        foreach ($this->db->query($sql, $binds)->getResultArray() as $r) {
            $out[(int) $r['id']] = [
                'present'     => (int) $r['present'],
                'absent'      => (int) $r['absent'],
                'unjustified' => (int) $r['unjustified'],
                'declined'    => (int) $r['declined'],
            ];
        }
        return $out;
    }

    /** Alumnos distintos, fieles y nuevos por entrenador (solo asistencias reales). */
    private function students(string $from, string $to, ?int $loc): array
    {
        [$where, $binds] = $this->scope($from, $to, $loc);
        // «Nuevo» = su primera asistencia con ese entrenador cae dentro del periodo.
        $sql = "SELECT t.coach, t.student, t.n,
                       (SELECT MIN(cs2.session_date)
                          FROM class_session_coaches c2
                          JOIN class_sessions cs2 ON cs2.id = c2.session_id AND cs2.status = 'completed'
                          JOIN class_session_players p2 ON p2.session_id = cs2.id AND p2.attendance = 'present'
                         WHERE c2.user_id = t.coach AND p2.user_id = t.student) AS first_date
                FROM (
                    SELECT csc.user_id AS coach, csp.user_id AS student, COUNT(*) AS n
                    FROM class_session_coaches csc
                    JOIN class_sessions cs ON cs.id = csc.session_id AND cs.status = 'completed'
                    JOIN class_session_players csp ON csp.session_id = cs.id AND csp.attendance = 'present'"
                  . FinanceExclusion::playerSql('csp.user_id') . "
                    WHERE {$where}
                    GROUP BY csc.user_id, csp.user_id
                ) t";
        $out = [];
        foreach ($this->db->query($sql, $binds)->getResultArray() as $r) {
            $c = (int) $r['coach'];
            $out[$c] ??= ['distinct' => 0, 'loyal' => 0, 'new' => 0, 'present' => 0];
            $out[$c]['distinct']++;
            $out[$c]['present'] += (int) $r['n'];
            if ((int) $r['n'] >= self::FIDELITY_MIN) {
                $out[$c]['loyal']++;
            }
            if ($r['first_date'] !== null && $r['first_date'] >= $from && $r['first_date'] <= $to) {
                $out[$c]['new']++;
            }
        }
        return $out;
    }

    /** @param array<int,array<string,mixed>> $rows */
    private function totals(array $rows, int $sessionsDone): array
    {
        $t = ['coaches' => 0, 'done' => 0, 'hours' => 0.0, 'cancelled' => 0, 'unclosed' => 0, 'upcoming' => 0,
              'present' => 0, 'att_base' => 0, 'closed' => 0, 'on_time' => 0, 'duo' => 0];
        foreach ($rows as $r) {
            if ($r['done'] + $r['cancelled'] + $r['unclosed'] + $r['upcoming'] > 0) {
                $t['coaches']++;
            }
            foreach (['done', 'hours', 'cancelled', 'unclosed', 'upcoming', 'present', 'att_base', 'closed', 'on_time', 'duo'] as $k) {
                $t[$k] += $r[$k];
            }
        }
        $t['participations'] = $t['done'];   // clases-entrenador (una clase con 2 entrenadores suma 2)
        $t['done']           = $sessionsDone; // clases reales, sin duplicar
        $t['hours']        = round($t['hours'], 1);
        $t['att_rate']     = self::rate($t['present'], $t['att_base']);
        $t['on_time_rate'] = self::rate($t['on_time'], $t['closed']);
        $t['duo_rate']     = self::rate($t['duo'], $t['participations']);
        return $t;
    }

    /** Clases impartidas distintas en el periodo (una clase con 2 entrenadores cuenta una vez). */
    private function countDone(string $from, string $to, ?int $loc, ?int $coach = null): int
    {
        [$where, $binds] = $this->scope($from, $to, $loc, $coach);
        return (int) ($this->db->query("SELECT COUNT(*) AS n FROM class_sessions cs WHERE cs.status = 'completed' AND {$where}", $binds)
            ->getRow('n') ?? 0);
    }

    /**
     * Clases impartidas por día de la semana y hora de inicio (1 = lunes … 7 = domingo).
     *
     * @return array{cells:array<int,array<int,int>>,max:int,hours:int[]}
     */
    private function heatmap(string $from, string $to, ?int $loc, ?int $coach = null): array
    {
        [$where, $binds] = $this->scope($from, $to, $loc, $coach);
        $sql = "SELECT WEEKDAY(cs.session_date) + 1 AS dow, HOUR(cs.start_time) AS h, COUNT(*) AS n
                FROM class_sessions cs
                WHERE cs.status = 'completed' AND {$where}
                GROUP BY dow, h";
        $cells = [];
        $max   = 0;
        $hours = [];
        foreach ($this->db->query($sql, $binds)->getResultArray() as $r) {
            $cells[(int) $r['dow']][(int) $r['h']] = (int) $r['n'];
            $max     = max($max, (int) $r['n']);
            $hours[] = (int) $r['h'];
        }
        $hours = $hours ? range(min($hours), max($hours)) : [];
        return ['cells' => $cells, 'max' => $max, 'hours' => $hours];
    }

    /**
     * Clases impartidas por entrenador y mes, últimos TREND_MONTHS meses (ventana fija
     * hasta hoy, no depende del periodo elegido).
     *
     * @return array{months:string[],rows:array<int,array<string,mixed>>,max:int}
     */
    private function trend(?int $loc, string $today, ?int $coach = null): array
    {
        $months = [];
        $ts = strtotime(date('Y-m-01', strtotime($today)));
        for ($i = self::TREND_MONTHS - 1; $i >= 0; $i--) {
            $months[] = date('Y-m', strtotime("-{$i} months", $ts));
        }
        $from = $months[0] . '-01';
        [$where, $binds] = $this->scope($from, date('Y-m-t', strtotime($today)), $loc, $coach);
        $sql = "SELECT u.id, u.name, DATE_FORMAT(cs.session_date, '%Y-%m') AS m, COUNT(*) AS n
                FROM class_session_coaches csc
                JOIN class_sessions cs ON cs.id = csc.session_id AND cs.status = 'completed'
                JOIN users u ON u.id = csc.user_id
                WHERE {$where}" . ($coach ? ' AND csc.user_id = ' . (int) $coach : '') . "
                GROUP BY u.id, u.name, m";
        $rows = [];
        $max  = 0;
        foreach ($this->db->query($sql, $binds)->getResultArray() as $r) {
            $id = (int) $r['id'];
            $rows[$id] ??= ['id' => $id, 'name' => (string) $r['name'], 'by_month' => [], 'total' => 0];
            $rows[$id]['by_month'][$r['m']] = (int) $r['n'];
            $rows[$id]['total'] += (int) $r['n'];
            $max = max($max, (int) $r['n']);
        }
        usort($rows, static fn($a, $b) => [$b['total'], $a['name']] <=> [$a['total'], $b['name']]);
        return ['months' => $months, 'rows' => $rows, 'max' => $max];
    }

    /** Clases impartidas por sede y formato (individual / dúo) en el periodo. */
    private function byLocation(string $from, string $to, ?int $loc = null, ?int $coach = null): array
    {
        [$where, $binds] = $this->scope($from, $to, $loc, $coach);
        $sql = "SELECT t.name, COUNT(*) AS n, SUM(t.duo) AS duo
                FROM (
                    SELECT COALESCE(l.name, NULLIF(cs.location_custom, ''), 'Sin sede') AS name,
                           (cs.class_format = 'pareja') AS duo
                    FROM class_sessions cs
                    LEFT JOIN locations l ON l.id = cs.location_id
                    WHERE cs.status = 'completed' AND {$where}
                ) t
                GROUP BY t.name ORDER BY n DESC";
        return array_map(static fn($r) => ['name' => $r['name'], 'n' => (int) $r['n'], 'duo' => (int) $r['duo']],
            $this->db->query($sql, $binds)->getResultArray());
    }

    /** Entrenadores con alguna clase alguna vez, para el desplegable de filtro. */
    public function coachOptions(): array
    {
        $sql = "SELECT DISTINCT u.id, u.name, u.status FROM class_session_coaches csc
                JOIN users u ON u.id = csc.user_id ORDER BY (u.status = 'active') DESC, u.name";
        return array_map(static fn($r) => ['id' => (int) $r['id'], 'name' => (string) $r['name'], 'status' => (string) $r['status']],
            $this->db->query($sql)->getResultArray());
    }

    /**
     * Alumnos con más asistencias a clases de ese entrenador en el periodo, con sus ausencias.
     *
     * @return array<int,array{id:int,name:string,present:int,absent:int,unjustified:int,last:?string}>
     */
    private function topStudents(int $coachId, string $from, string $to, ?int $loc, int $limit = 10): array
    {
        [$where, $binds] = $this->scope($from, $to, $loc, $coachId);
        $sql = "SELECT u.id, u.name,
                    SUM(csp.attendance = 'present')              AS present,
                    SUM(csp.attendance IN ('absent','declined')) AS absent,
                    SUM(csp.attendance = 'unjustified')          AS unjustified,
                    MAX(IF(csp.attendance = 'present', cs.session_date, NULL)) AS last_date
                FROM class_sessions cs
                JOIN class_session_players csp ON csp.session_id = cs.id
                JOIN users u ON u.id = csp.user_id
                WHERE cs.status = 'completed' AND {$where}" . FinanceExclusion::playerSql('csp.user_id') . "
                GROUP BY u.id, u.name
                HAVING present > 0
                ORDER BY present DESC, u.name
                LIMIT " . (int) $limit;
        return array_map(static fn($r) => [
            'id' => (int) $r['id'], 'name' => (string) $r['name'], 'present' => (int) $r['present'],
            'absent' => (int) $r['absent'], 'unjustified' => (int) $r['unjustified'], 'last' => $r['last_date'],
        ], $this->db->query($sql, $binds)->getResultArray());
    }
}
