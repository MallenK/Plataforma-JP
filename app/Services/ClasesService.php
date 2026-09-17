<?php

namespace App\Services;

use App\Models\ClassModel;
use App\Models\ClassSessionModel;
use App\Models\ClassSessionCoachModel;
use App\Models\ClassSessionPlayerModel;
use App\Models\ClassSessionAttachmentModel;
use App\Models\PlayerBonoModel;
use App\Models\NotificationModel;
use App\Models\UserModel;

class ClasesService
{
    /**
     * Roles que pueden figurar como responsable técnico de una sesión
     * (selector "Entrenadores" / session_type = 'coach').
     * admin y superadmin se incluyen porque también imparten clases.
     */
    public const RESPONSABLE_TECNICO_ROLES = ['coach', 'admin', 'superadmin'];

    /**
     * Roles que pueden figurar como responsable de staff de una sesión
     * (selector "Staff responsable" / session_type = 'staff').
     */
    public const RESPONSABLE_STAFF_ROLES = ['staff', 'admin', 'superadmin'];

    protected ClassModel $classModel;
    protected ClassSessionModel $sessionModel;
    protected ClassSessionCoachModel $coachModel;
    protected ClassSessionPlayerModel $playerModel;
    protected ClassSessionAttachmentModel $attachmentModel;
    protected $db;

    public function __construct()
    {
        $this->classModel      = new ClassModel();
        $this->sessionModel    = new ClassSessionModel();
        $this->coachModel      = new ClassSessionCoachModel();
        $this->playerModel     = new ClassSessionPlayerModel();
        $this->attachmentModel = new ClassSessionAttachmentModel();
        $this->db              = \Config\Database::connect();
    }

    // ────────────────────────────────────────────────────────────────
    //  Validación / normalización de horas
    // ────────────────────────────────────────────────────────────────

    /**
     * Normaliza una hora a formato 'HH:MM' de 24h.
     * Acepta '9:5', '09:05', '09:05:00', ' 09:05 '. Devuelve null si no es
     * una hora válida (fuera de rango, texto, vacío…).
     */
    public static function normalizeTime(?string $time): ?string
    {
        $time = trim((string) $time);
        if ($time === '' || ! preg_match('/^(\d{1,2}):(\d{2})(?::\d{2})?$/', $time, $m)) {
            return null;
        }
        $h = (int) $m[1];
        $min = (int) $m[2];
        if ($h > 23 || $min > 59) {
            return null;
        }
        return sprintf('%02d:%02d', $h, $min);
    }

    /**
     * Resuelve y valida el par (start_time, end_time) de una sesión.
     * - start_time es obligatorio y debe ser una hora válida.
     * - end_time es opcional: si falta, se calcula como start + 1h.
     * - end_time, si se indica, debe ser posterior a start_time.
     *
     * @return array{start:?string, end:?string, error:?string}
     */
    private function resolveTimes(array $data): array
    {
        $start = self::normalizeTime($data['start_time'] ?? null);
        if ($start === null) {
            return ['start' => null, 'end' => null, 'error' => 'La hora de inicio es obligatoria y debe tener el formato HH:MM.'];
        }

        $rawEnd = trim((string) ($data['end_time'] ?? ''));
        if ($rawEnd === '') {
            $end = date('H:i', strtotime($start) + 3600);
        } else {
            $end = self::normalizeTime($rawEnd);
            if ($end === null) {
                return ['start' => null, 'end' => null, 'error' => 'La hora de fin no es válida (formato HH:MM).'];
            }
            // '00:00' como fin = medianoche del día siguiente (sesión que cruza medianoche): se permite.
            if ($end !== '00:00' && $end <= $start) {
                return ['start' => null, 'end' => null, 'error' => 'La hora de fin debe ser posterior a la hora de inicio.'];
            }
        }

        return ['start' => $start, 'end' => $end, 'error' => null];
    }

    // ────────────────────────────────────────────────────────────────
    //  Crear
    // ────────────────────────────────────────────────────────────────

    public function createSession(array $data, int $userId): array
    {
        $type = ($data['type'] ?? 'single') === 'recurring' ? 'recurring' : 'single';

        if ($type === 'recurring') {
            return $this->createRecurring($data, $userId);
        }

        if (empty(trim($data['title'] ?? ''))) {
            return ['success' => false, 'error' => 'El título es obligatorio.'];
        }
        if (empty($data['session_date'] ?? '') || strtotime((string) $data['session_date']) === false) {
            return ['success' => false, 'error' => 'La fecha es obligatoria.'];
        }

        $times = $this->resolveTimes($data);
        if ($times['error'] !== null) {
            return ['success' => false, 'error' => $times['error']];
        }
        $data['start_time'] = $times['start'];
        $data['end_time']   = $times['end'];

        $id = $this->insertSingle($data, $userId);
        if (!$id) {
            $errors = $this->sessionModel->errors();
            return ['success' => false, 'error' => !empty($errors) ? implode(' ', $errors) : 'Error al crear la sesión.'];
        }

        $this->syncCoaches($id, $data['coach_ids'] ?? []);
        $this->syncPlayers($id, $data['player_ids'] ?? [], $data['player_coach_map'] ?? []);

        return ['success' => true, 'id' => $id, 'count' => 1];
    }

    private function insertSingle(array $data, int $userId, ?int $classId = null): int
    {
        $fmt  = in_array($data['class_format'] ?? '', ['individual', 'pareja']) ? $data['class_format'] : 'individual';
        $sType = in_array($data['session_type'] ?? '', ['coach', 'staff']) ? $data['session_type'] : 'coach';

        // Salvaguarda: nunca persistir horas inválidas aunque un caller se
        // salte resolveTimes(). start_time es NOT NULL en BD.
        $start = self::normalizeTime($data['start_time'] ?? null);
        if ($start === null) {
            throw new \InvalidArgumentException('ClasesService::insertSingle recibió start_time inválido: ' . var_export($data['start_time'] ?? null, true));
        }
        $end = self::normalizeTime($data['end_time'] ?? null) ?? date('H:i', strtotime($start) + 3600);

        return (int)$this->sessionModel->insert([
            'class_id'        => $classId ?? ($data['class_id'] ?? null),
            'title'           => trim($data['title']),
            'session_date'    => $data['session_date'],
            'start_time'      => $start,
            'end_time'        => $end,
            'location_id'     => ($data['location_id'] ?? '') ?: null,
            'location_custom' => ($data['location_custom'] ?? '') ?: null,
            'focus'           => ($data['focus'] ?? '') ?: null,
            'pre_notes'       => ($data['pre_notes'] ?? '') ?: null,
            'post_notes'      => ($data['post_notes'] ?? '') ?: null,
            'status'          => 'scheduled',
            'created_by'      => $userId,
            'class_format'    => $fmt,
            'session_type'    => $sType,
        ]);
    }

    private function createRecurring(array $data, int $userId): array
    {
        $days = array_map('intval', (array)($data['recurrence_days'] ?? []));

        if (empty(trim($data['title'] ?? ''))) {
            return ['success' => false, 'error' => 'El título es obligatorio.'];
        }
        if (empty($days) || empty($data['recurrence_start']) || empty($data['recurrence_end'])) {
            return ['success' => false, 'error' => 'Faltan datos de recurrencia (días, inicio o fin).'];
        }
        if (strtotime((string) $data['recurrence_end']) < strtotime((string) $data['recurrence_start'])) {
            return ['success' => false, 'error' => 'La fecha "hasta" debe ser posterior a la fecha "desde".'];
        }

        $times = $this->resolveTimes($data);
        if ($times['error'] !== null) {
            return ['success' => false, 'error' => $times['error']];
        }
        $data['start_time'] = $times['start'];
        $data['end_time']   = $times['end'];

        // Guardar plantilla
        $fmt = in_array($data['class_format'] ?? '', ['individual', 'pareja']) ? $data['class_format'] : 'individual';
        try {
            $classId = (int)$this->classModel->insert([
                'title'                   => trim($data['title']),
                'description'             => ($data['description'] ?? '') ?: null,
                'type'                    => 'recurring',
                'class_format'            => $fmt,
                'recurrence_days'         => json_encode($days),
                'recurrence_start'        => $data['recurrence_start'],
                'recurrence_end'          => $data['recurrence_end'],
                'recurrence_time_start'   => $data['start_time'],
                'recurrence_time_end'     => $data['end_time'],
                'default_location_id'     => ($data['location_id'] ?? '') ?: null,
                'default_location_custom' => ($data['location_custom'] ?? '') ?: null,
                'default_focus'           => ($data['focus'] ?? '') ?: null,
                'created_by'              => $userId,
            ]);
        } catch (\Throwable $e) {
            log_message('error', 'ClasesService::createRecurring insert classes failed: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Error al crear la plantilla recurrente: ' . $e->getMessage()];
        }

        if (!$classId) {
            $errors = $this->classModel->errors();
            $msg    = !empty($errors) ? implode(' ', $errors) : 'Error al crear la plantilla recurrente.';
            log_message('error', 'ClasesService::createRecurring insert returned 0. Errors: ' . $msg);
            return ['success' => false, 'error' => $msg];
        }

        // Generar sesiones
        $start   = new \DateTime($data['recurrence_start']);
        $end     = new \DateTime($data['recurrence_end']);
        $current = clone $start;
        $ids     = [];

        while ($current <= $end) {
            $dow = (int)$current->format('N'); // 1=Lun … 7=Dom
            if (in_array($dow, $days)) {
                $sid = $this->insertSingle(array_merge($data, [
                    'session_date' => $current->format('Y-m-d'),
                ]), $userId, $classId);

                if ($sid) {
                    $ids[] = $sid;
                    $this->syncCoaches($sid, $data['coach_ids'] ?? []);
                    $this->syncPlayers($sid, $data['player_ids'] ?? [], $data['player_coach_map'] ?? []);
                }
            }
            $current->modify('+1 day');
        }

        return [
            'success'  => true,
            'id'       => $ids[0] ?? null,
            'class_id' => $classId,
            'count'    => count($ids),
        ];
    }

    /**
     * Punto de entrada AJAX único para crear clases.
     * Soporta sesiones puntuales y recurrentes con todos los campos.
     */
    public function quickCreate(array $data, int $userId): array
    {
        $type = ($data['type'] ?? 'single') === 'recurring' ? 'recurring' : 'single';

        if (empty(trim($data['title'] ?? ''))) {
            return ['success' => false, 'error' => 'El título es obligatorio.'];
        }

        if ($type === 'recurring') {
            $result = $this->createRecurring($data, $userId);
            if (!$result['success']) {
                return $result;
            }
            return [
                'success'  => true,
                'id'       => $result['id'],
                'count'    => $result['count'] ?? 1,
                'class_id' => $result['class_id'] ?? null,
            ];
        }

        if (empty($data['session_date'] ?? '')) {
            return ['success' => false, 'error' => 'La fecha es obligatoria.'];
        }

        $times = $this->resolveTimes($data);
        if ($times['error'] !== null) {
            return ['success' => false, 'error' => $times['error']];
        }
        $data['start_time'] = $times['start'];
        $data['end_time']   = $times['end'];

        try {
            $id = $this->insertSingle($data, $userId);
        } catch (\Throwable $e) {
            log_message('error', 'ClasesService::quickCreate insert error: ' . $e->getMessage());
            return ['success' => false, 'error' => 'Error al crear la sesión: ' . $e->getMessage()];
        }

        if (!$id) {
            $errors = $this->sessionModel->errors();
            $msg    = !empty($errors) ? implode(' ', $errors) : 'Error al crear la sesión.';
            log_message('error', 'ClasesService::quickCreate insert returned 0. Errors: ' . $msg);
            return ['success' => false, 'error' => $msg];
        }

        try {
            $this->syncCoaches($id, $data['coach_ids'] ?? []);
            $this->syncPlayers($id, $data['player_ids'] ?? [], $data['player_coach_map'] ?? []);
        } catch (\Throwable $e) {
            log_message('error', 'ClasesService::quickCreate sync error (session=' . $id . '): ' . $e->getMessage() . ' | ' . $e->getFile() . ':' . $e->getLine());
        }

        $session = $this->sessionModel->find($id);
        return [
            'success' => true,
            'id'      => $id,
            'title'   => $session['title'],
            'date'    => $session['session_date'],
            'count'   => 1,
        ];
    }

    // ────────────────────────────────────────────────────────────────
    //  Leer
    // ────────────────────────────────────────────────────────────────

    /**
     * Decide si el calendario debe filtrarse por "solo mis sesiones"
     * (join por class_session_coaches). Coach/staff lo aplican siempre;
     * admin/superadmin solo cuando piden explícitamente "Mis clases"
     * ($onlyMine) — toggle documentado en
     * docs/clases/PROPUESTA-calendario-dual-admin-coach.md.
     */
    public static function shouldFilterCalendarByOwnSessions(string $role, bool $onlyMine): bool
    {
        return in_array($role, ['coach', 'staff'], true)
            || ($onlyMine && in_array($role, ['admin', 'superadmin'], true));
    }

    /**
     * Traduce el único parámetro `?scope=` del calendario (Clases y
     * Dashboard) a los dos argumentos que espera getSessionsForCalendar().
     * Un solo parámetro en la URL/localStorage es más simple de mantener
     * sincronizado que dos independientes; la vista sigue usando la clave
     * `jp_cal_scope` de antes (TICKET-011 amplía su vocabulario):
     *
     *   'all'            → todo (comportamiento de siempre)
     *   'mine'           → toggle "Mis clases" (admin/superadmin, v1.7.0)
     *   'none'           → "Sin responsable asignado"
     *   'coach:<id>' / 'staff:<id>' → "Ver calendario de <persona>"
     *
     * Se aplica igual para cualquier rol; es getSessionsForCalendar() quien
     * ignora $responsableFilter si el rol no es admin/superadmin.
     *
     * @return array{onlyMine: bool, responsableFilter: ?string}
     */
    public static function parseScopeParam(?string $scope): array
    {
        $scope = (string) $scope;

        if ($scope === 'mine') {
            return ['onlyMine' => true, 'responsableFilter' => null];
        }
        if ($scope === 'none') {
            return ['onlyMine' => false, 'responsableFilter' => 'none'];
        }
        if (preg_match('/^(?:coach|staff):(\d+)$/', $scope, $m)) {
            return ['onlyMine' => false, 'responsableFilter' => $m[1]];
        }
        return ['onlyMine' => false, 'responsableFilter' => null];
    }

    /**
     * Texto de "quién viene" para una tarjeta del calendario: el nombre del
     * alumno (o el título de la clase si no hay ninguno, ver
     * attachResponsable()) importa más que el título — varias clases
     * recurrentes comparten nombre genérico ("Tecnificación Grupo I") y no
     * dice quién asiste.
     *
     * 0 alumnos → null (el caller usa el título); 1 o 2 (pareja) → sus
     * nombres; más (clase de grupo) → el primero + cuántos más.
     *
     * @param string[] $names Nombres ya ordenados (ver attachResponsable()).
     */
    public static function playerLabel(array $names): ?string
    {
        $count = count($names);

        return match (true) {
            $count === 0 => null,
            $count <= 2  => implode(', ', $names),
            default      => $names[0] . ' +' . ($count - 1),
        };
    }

    /**
     * @param bool $onlyMine Fuerza el filtro "solo mis sesiones" también
     *                       para admin/superadmin (ver
     *                       shouldFilterCalendarByOwnSessions()).
     * @param string|null $responsableFilter Solo admin/superadmin (TICKET-011):
     *                       'none' = sesiones sin responsable asignado;
     *                       un ID numérico (string) = solo las de esa persona.
     *                       Independiente de $onlyMine (si ambos llegan,
     *                       gana $responsableFilter). Ignorado para el resto
     *                       de roles: un coach/staff/alumno nunca puede ver
     *                       el calendario de otra persona por esta vía.
     */
    public function getSessionsForCalendar(
        int $year,
        int $month,
        int $userId,
        string $role,
        bool $onlyMine = false,
        ?string $responsableFilter = null
    ): array {
        $isPlayer    = in_array($role, ['alumno', 'player']);
        $isAdminRole = in_array($role, ['admin', 'superadmin'], true);
        $isCoachView = self::shouldFilterCalendarByOwnSessions($role, $onlyMine);
        $responsableFilter = $isAdminRole ? $responsableFilter : null;

        $start = sprintf('%04d-%02d-01', $year, $month);
        $end   = date('Y-m-t', strtotime($start));

        $select = 'cs.id, cs.title, cs.session_date, cs.start_time, cs.end_time, cs.status, cs.session_type';

        if ($isPlayer) {
            $sessions = $this->db->table('class_sessions cs')
                ->select($select)
                ->join('class_session_players csp', 'csp.session_id = cs.id')
                ->where('csp.user_id', $userId)
                ->where('cs.session_date >=', $start)
                ->where('cs.session_date <=', $end)
                ->where('cs.status !=', 'cancelled')
                ->orderBy('cs.session_date', 'ASC')
                ->orderBy('cs.start_time', 'ASC')
                ->get()->getResultArray();
        } elseif ($responsableFilter === 'none') {
            // Filtro "Sin responsable asignado" (solo admin/superadmin).
            $sessions = $this->db->table('class_sessions cs')
                ->select($select)
                ->where('cs.session_date >=', $start)
                ->where('cs.session_date <=', $end)
                ->where('cs.status !=', 'cancelled')
                ->where('NOT EXISTS (SELECT 1 FROM class_session_coaches csc WHERE csc.session_id = cs.id)', null, false)
                ->orderBy('cs.session_date', 'ASC')
                ->orderBy('cs.start_time', 'ASC')
                ->get()->getResultArray();
        } elseif ($responsableFilter !== null && ctype_digit($responsableFilter)) {
            // Filtro "Ver calendario de <entrenador/staff>" (solo admin/superadmin):
            // misma consulta que "Mis clases" pero para un ID elegido, no el propio.
            $sessions = $this->db->table('class_sessions cs')
                ->select($select)
                ->join('class_session_coaches csc', 'csc.session_id = cs.id')
                ->where('csc.user_id', (int) $responsableFilter)
                ->where('cs.session_date >=', $start)
                ->where('cs.session_date <=', $end)
                ->where('cs.status !=', 'cancelled')
                ->orderBy('cs.session_date', 'ASC')
                ->orderBy('cs.start_time', 'ASC')
                ->get()->getResultArray();
        } elseif ($isCoachView) {
            // Coach/staff siempre, y admin/superadmin cuando piden "Mis clases":
            // solo las sesiones donde están asignados como responsable.
            $sessions = $this->db->table('class_sessions cs')
                ->select($select)
                ->join('class_session_coaches csc', 'csc.session_id = cs.id')
                ->where('csc.user_id', $userId)
                ->where('cs.session_date >=', $start)
                ->where('cs.session_date <=', $end)
                ->where('cs.status !=', 'cancelled')
                ->orderBy('cs.session_date', 'ASC')
                ->orderBy('cs.start_time', 'ASC')
                ->get()->getResultArray();
        } else {
            $sessions = $this->sessionModel->getForMonth($year, $month);
        }

        return $this->attachResponsable($sessions);
    }

    /**
     * Añade el responsable (id + nombre) de cada sesión con una única
     * consulta extra (máx. 1 responsable por sesión — ver syncCoaches()) y
     * da forma final al evento para el calendario.
     */
    private function attachResponsable(array $sessions): array
    {
        $ids = array_map(fn($s) => (int) $s['id'], $sessions);

        $coachMap = [];
        if (!empty($ids)) {
            $rows = $this->db->table('class_session_coaches csc')
                ->select('csc.session_id, csc.user_id, u.name')
                ->join('users u', 'u.id = csc.user_id')
                ->whereIn('csc.session_id', $ids)
                ->get()->getResultArray();
            foreach ($rows as $r) {
                $coachMap[(int) $r['session_id']] = ['id' => (int) $r['user_id'], 'name' => $r['name']];
            }
        }

        // Alumno(s) de la sesión: en el calendario interesa más "quién viene"
        // que el título de la clase (varias clases recurrentes comparten
        // nombre genérico, p. ej. "Tecnificación Grupo I"). 1 alumno → su
        // nombre; 2 (pareja) → los dos; más → el primero + "+N".
        $playerNamesMap = [];
        if (!empty($ids)) {
            $rows = $this->db->table('class_session_players csp')
                ->select('csp.session_id, u.name')
                ->join('users u', 'u.id = csp.user_id')
                ->whereIn('csp.session_id', $ids)
                ->orderBy('u.name', 'ASC')
                ->get()->getResultArray();
            foreach ($rows as $r) {
                $playerNamesMap[(int) $r['session_id']][] = $r['name'];
            }
        }

        return array_map(function ($s) use ($coachMap, $playerNamesMap) {
            $sid   = (int) $s['id'];
            $c     = $coachMap[$sid] ?? null;
            $playerLabel = self::playerLabel($playerNamesMap[$sid] ?? []);

            return [
                'id'               => $sid,
                'title'            => $s['title'],
                'date'             => $s['session_date'],
                'start'            => substr($s['start_time'], 0, 5),
                'end'              => substr($s['end_time'], 0, 5),
                'status'           => $s['status'],
                'color'            => $this->statusColor($s['status']),
                'session_type'     => $s['session_type'] ?? 'coach',
                'responsable_id'   => $c['id'] ?? null,
                'responsable_name' => $c['name'] ?? null,
                'player_label'     => $playerLabel,
            ];
        }, $sessions);
    }

    /**
     * Opciones para el selector "Ver calendario de…" (admin/superadmin):
     * solo entrenadores/staff que tienen (o han tenido) alguna sesión
     * asignada, para no llenar el desplegable de gente que nunca ha dado
     * una clase.
     *
     * @return array{coaches: array<int,array{id:int,name:string}>, staff: array<int,array{id:int,name:string}>}
     */
    public function getResponsableFilterOptions(): array
    {
        $rows = $this->db->table('users u')
            ->select('u.id, u.name, u.role')
            ->distinct()
            ->join('class_session_coaches csc', 'csc.user_id = u.id')
            ->whereIn('u.role', ['coach', 'staff'])
            ->where('u.status', 'active')
            ->orderBy('u.name', 'ASC')
            ->get()->getResultArray();

        $result = ['coaches' => [], 'staff' => []];
        foreach ($rows as $r) {
            $bucket = $r['role'] === 'staff' ? 'staff' : 'coaches';
            $result[$bucket][] = ['id' => (int) $r['id'], 'name' => $r['name']];
        }
        return $result;
    }

    /**
     * Buscador de sesiones por nombre de clase, entrenador o jugador.
     * Respeta el rol: coach/staff solo ven las suyas, el alumno solo las
     * suyas, admin/superadmin ven todas.
     *
     * @return array<int, array{id:int,title:string,date:string,start:string,
     *                           status:string,coaches:string,players:int}>
     */
    public function search(string $q, int $userId, string $role): array
    {
        $q = trim($q);
        if (mb_strlen($q) < 2) {
            return [];
        }

        $isPlayer     = in_array($role, ['alumno', 'player'], true);
        $isCoachStaff = in_array($role, ['coach', 'staff'], true);

        $b = $this->db->table('class_sessions cs')
            ->select('cs.id, cs.title, cs.session_date, cs.start_time, cs.status')
            ->distinct()
            ->join('class_session_coaches csc', 'csc.session_id = cs.id', 'left')
            ->join('users uc', 'uc.id = csc.user_id', 'left')
            ->join('class_session_players csp', 'csp.session_id = cs.id', 'left')
            ->join('users up', 'up.id = csp.user_id', 'left')
            ->groupStart()
                ->like('cs.title', $q)
                ->orLike('uc.name', $q)
                ->orLike('up.name', $q)
            ->groupEnd()
            ->orderBy('cs.session_date', 'DESC')
            ->orderBy('cs.start_time', 'ASC')
            ->limit(20);

        if ($isPlayer) {
            $b->where('cs.id IN (SELECT session_id FROM class_session_players WHERE user_id = ' . (int) $userId . ')', null, false);
        } elseif ($isCoachStaff) {
            $b->where('cs.id IN (SELECT session_id FROM class_session_coaches WHERE user_id = ' . (int) $userId . ')', null, false);
        }

        $rows = $b->get()->getResultArray();
        $ids  = array_map('intval', array_column($rows, 'id'));
        if (!$ids) {
            return [];
        }

        // Entrenadores y nº de jugadores por sesión (una consulta cada uno).
        $coachMap = [];
        foreach ($this->db->table('class_session_coaches csc')
            ->select('csc.session_id, GROUP_CONCAT(u.name ORDER BY u.name SEPARATOR ", ") AS names')
            ->join('users u', 'u.id = csc.user_id')
            ->whereIn('csc.session_id', $ids)
            ->groupBy('csc.session_id')
            ->get()->getResultArray() as $r) {
            $coachMap[(int) $r['session_id']] = $r['names'];
        }

        $playerMap = [];
        foreach ($this->db->table('class_session_players')
            ->select('session_id, COUNT(*) AS n')
            ->whereIn('session_id', $ids)
            ->groupBy('session_id')
            ->get()->getResultArray() as $r) {
            $playerMap[(int) $r['session_id']] = (int) $r['n'];
        }

        return array_map(fn ($s) => [
            'id'      => (int) $s['id'],
            'title'   => $s['title'],
            'date'    => $s['session_date'],
            'start'   => substr((string) $s['start_time'], 0, 5),
            'status'  => $s['status'],
            'coaches' => $coachMap[(int) $s['id']] ?? '',
            'players' => $playerMap[(int) $s['id']] ?? 0,
        ], $rows);
    }

    public function getSession(int $id): ?array
    {
        $session = $this->sessionModel->find($id);
        if (!$session) return null;

        $session['coaches'] = $this->getCoachesForSession($id);
        $session['players'] = $this->getPlayersForSession($id);

        // Adjuntos: generales de la sesión + agrupados por jugador (player_id).
        $session['attachments'] = $this->attachmentModel->getForSession($id);
        $attachmentsByPlayer = [];
        foreach ($this->attachmentModel->where('session_id', $id)->where('player_id IS NOT NULL')->findAll() as $att) {
            $attachmentsByPlayer[(int) $att['player_id']][] = $att;
        }
        foreach ($session['players'] as &$p) {
            $p['attachments'] = $attachmentsByPlayer[(int) $p['id']] ?? [];
        }
        unset($p);

        // Nombre de instalación si hay location_id
        if (!empty($session['location_id'])) {
            $loc = $this->db->table('locations')->where('id', $session['location_id'])->get()->getRowArray();
            $session['location_name'] = $loc['name'] ?? null;
        } else {
            $session['location_name'] = null;
        }

        // Info de la plantilla si es recurrente
        $session['class_info'] = !empty($session['class_id'])
            ? $this->classModel->find($session['class_id'])
            : null;

        return $session;
    }

    public function getUpcomingSessions(int $limit = 5): array
    {
        return $this->sessionModel->getUpcoming($limit);
    }

    /**
     * ¿Se puede escribir el feedback ("Después") de una sesión?
     *
     * El feedback está disponible cuando la clase ya se ha impartido:
     *   - la sesión está completada, o
     *   - ya se ha pasado lista (lista_pasada_at), o
     *   - al menos un alumno está marcado como presente.
     *
     * @param array $session Sesión tal cual la devuelve getSession()
     *                       (debe incluir 'status', 'lista_pasada_at' y 'players').
     */
    public static function isFeedbackUnlocked(array $session): bool
    {
        if (($session['status'] ?? '') === 'completed') {
            return true;
        }
        if (!empty($session['lista_pasada_at'])) {
            return true;
        }
        foreach ($session['players'] ?? [] as $p) {
            if (($p['attendance'] ?? '') === 'present') {
                return true;
            }
        }
        return false;
    }

    // ────────────────────────────────────────────────────────────────
    //  Actualizar
    // ────────────────────────────────────────────────────────────────

    public function updateSession(int $id, array $data): bool
    {
        // Campos que, si vienen, no pueden quedar vacíos ni ponerse a NULL
        // (son NOT NULL en BD y su vacío rompería la sesión).
        $requiredIfPresent = ['title', 'session_date'];
        foreach ($requiredIfPresent as $key) {
            if (array_key_exists($key, $data) && trim((string) $data[$key]) === '') {
                return false;
            }
        }

        // Horas: validar y normalizar antes de tocar la BD. start_time es NOT NULL.
        if (array_key_exists('start_time', $data) || array_key_exists('end_time', $data)) {
            $current = $this->sessionModel->find($id);
            if (!$current) {
                return false;
            }
            $times = $this->resolveTimes([
                'start_time' => $data['start_time'] ?? $current['start_time'],
                'end_time'   => array_key_exists('end_time', $data) ? $data['end_time'] : $current['end_time'],
            ]);
            if ($times['error'] !== null) {
                return false;
            }
            $data['start_time'] = $times['start'];
            $data['end_time']   = $times['end'];
        }

        $allowed = ['title', 'session_date', 'start_time', 'end_time',
                    'location_id', 'location_custom', 'focus',
                    'pre_notes', 'post_notes', 'status', 'session_type'];

        // Campos opcionales: '' se guarda como NULL.
        $nullable = ['location_id', 'location_custom', 'focus', 'pre_notes', 'post_notes'];
        // Campos NOT NULL: si vienen vacíos se ignoran (no se tocan en BD).
        $notNull  = ['title', 'session_date', 'start_time', 'end_time', 'status'];

        $update = [];
        foreach ($allowed as $key) {
            if (! array_key_exists($key, $data)) {
                continue;
            }
            if ($key === 'session_type') {
                $update[$key] = in_array($data[$key], ['coach', 'staff']) ? $data[$key] : 'coach';
            } elseif (in_array($key, $nullable, true)) {
                $update[$key] = $data[$key] !== '' ? $data[$key] : null;
            } elseif (in_array($key, $notNull, true)) {
                $val = is_string($data[$key]) ? trim($data[$key]) : $data[$key];
                if ($val !== '' && $val !== null) {
                    $update[$key] = $val;
                }
            } else {
                $update[$key] = $data[$key];
            }
        }

        if (!empty($update)) {
            $this->sessionModel->update($id, $update);
        }

        if (isset($data['coach_ids'])) {
            $this->syncCoaches($id, $data['coach_ids']);
        }
        if (isset($data['player_ids'])) {
            $this->syncPlayers($id, $data['player_ids'], $data['player_coach_map'] ?? []);
        }

        return true;
    }

    /**
     * Guarda asistencia y marca la sesión como "lista pasada".
     * Única entrada para el marcado de asistencia por admin/coach.
     */
    public function guardarLista(int $sessionId, int $adminId, array $attendanceMap, array $absenceReasons = [], array $absenceNotes = []): array
    {
        $refunded = 0;
        if (!empty($attendanceMap)) {
            $refunded = $this->updateAttendance($sessionId, $attendanceMap, $absenceReasons, $absenceNotes);
        }

        $this->sessionModel->update($sessionId, [
            'lista_pasada_at' => date('Y-m-d H:i:s'),
            'lista_pasada_by' => $adminId,
        ]);

        return ['success' => true, 'bonos_devueltos' => $refunded];
    }

    /**
     * Cierra la sesión: status='completed' + lista_pasada_at si aún no estaba marcada.
     * Única acción que finaliza una sesión.
     *
     * Si la sesión era la última 'scheduled' de una clase recurrente (y esa
     * serie aún no se ha continuado), devuelve `offer_renewal`+`class_id`
     * para que el controller ofrezca generar el mes siguiente.
     */
    public function cerrarSesion(int $sessionId, int $adminId): array
    {
        $session = $this->sessionModel->find($sessionId);
        if (!$session) {
            return ['success' => false, 'error' => 'Sesión no encontrada.'];
        }

        // Comprobar ANTES de actualizar: la propia sesión aún cuenta como
        // 'scheduled' en este punto, así que si el recuento da 1 es que es
        // la última que quedaba.
        $seriesStatus = !empty($session['class_id'])
            ? $this->getRecurringSeriesStatus((int) $session['class_id'])
            : null;
        $offerRenewal = $seriesStatus !== null && $seriesStatus['is_last'] && !$seriesStatus['already_renewed'];

        $update = ['status' => 'completed'];
        if (empty($session['lista_pasada_at'])) {
            $update['lista_pasada_at'] = date('Y-m-d H:i:s');
            $update['lista_pasada_by'] = $adminId;
        }

        $this->sessionModel->update($sessionId, $update);

        return [
            'success'       => true,
            'offer_renewal' => $offerRenewal,
            'class_id'      => $offerRenewal ? (int) $session['class_id'] : null,
        ];
    }

    /**
     * Reabre una sesión cerrada o cancelada: la devuelve a 'scheduled'.
     * Conserva lista_pasada_at y toda la asistencia ya registrada, de modo
     * que cerrar o cancelar deja de ser una acción irreversible.
     *
     * @return array{success:bool,from?:string,error?:string}
     */
    public function reabrirSesion(int $sessionId): array
    {
        $session = $this->sessionModel->find($sessionId);
        if (!$session) {
            return ['success' => false, 'error' => 'Sesión no encontrada.'];
        }

        $from = $session['status'] ?? '';
        if ($from === 'scheduled') {
            return ['success' => false, 'error' => 'La sesión ya está abierta.'];
        }
        if (!in_array($from, ['completed', 'cancelled'], true)) {
            return ['success' => false, 'error' => 'Esta sesión no se puede reabrir.'];
        }

        $this->sessionModel->update($sessionId, ['status' => 'scheduled']);
        return ['success' => true, 'from' => $from];
    }

    /**
     * Notifica al alumno y a los admins cuando un bono cae a 1 o 0 sesiones.
     * Usa el sistema de notificaciones internas (sin email).
     */
    private function emitBonoLowSessionsNotification(int $playerId, array $bono): void
    {
        $remaining = (int)$bono['sessions_remaining'];

        // Datos del bono (nombre del tipo) — un join puntual
        $bonoTypeRow = $this->db->table('player_bonos pb')
            ->select('bt.name AS bono_name')
            ->join('bono_types bt', 'bt.id = pb.bono_type_id')
            ->where('pb.id', (int)$bono['id'])
            ->get()->getRow();
        $bonoName = $bonoTypeRow->bono_name ?? 'Bono';

        // Nombre del alumno
        $userModel = new UserModel();
        $player    = $userModel->find($playerId);
        $playerName = $player['name'] ?? 'Alumno';

        if ($remaining === 0) {
            $title = "🎟️ Bono agotado: {$playerName}";
            $body  = "El bono \"{$bonoName}\" de {$playerName} se ha agotado (0 sesiones restantes). "
                   . "Si va a continuar entrenando, asígnale un nuevo bono.";
        } else {
            $title = "⚠️ Última sesión del bono: {$playerName}";
            $body  = "Al alumno {$playerName} le queda 1 sesión en su bono \"{$bonoName}\". "
                   . "Considera renovar o asignar un nuevo bono.";
        }

        // Destinatarios: el propio alumno + el creador del bono + todos los admins/superadmins
        $recipients = [$playerId];

        if (!empty($bono['created_by'])) {
            $recipients[] = (int)$bono['created_by'];
        }

        $admins = $userModel
            ->select('id')
            ->whereIn('role', ['admin', 'superadmin'])
            ->where('status', 'active')
            ->findAll();
        foreach ($admins as $a) {
            $recipients[] = (int)$a['id'];
        }

        $recipients = array_values(array_unique(array_filter($recipients, fn($r) => $r > 0)));
        if (empty($recipients)) {
            return;
        }

        // Sender: el creador del bono si existe; si no, el primer superadmin disponible
        $senderId = (int)($bono['created_by'] ?? 0);
        if ($senderId <= 0) {
            $sa = $userModel->select('id')->where('role', 'superadmin')->where('status', 'active')->first();
            $senderId = (int)($sa['id'] ?? 0);
        }
        if ($senderId <= 0) {
            return; // No hay sender válido, abortar
        }

        (new NotificationModel())->createWithRecipients([
            'sender_id' => $senderId,
            'type'      => 'group',
            'title'     => $title,
            'body'      => $body,
        ], $recipients);
    }

    /**
     * Estados de asistencia que "consumen" una sesión del bono. Un jugador en
     * cualquier otro estado (absent, declined, pending) NO debe tener bono
     * descontado: si lo tenía y pasa a uno de estos, se le devuelve.
     */
    public const BONO_CONSUMING_ATTENDANCE = ['present', 'confirmed', 'unjustified'];

    /**
     * Estados de asistencia que representan una falta (el alumno no vino) y en
     * los que tiene sentido registrar razón / nota de ausencia.
     */
    public const ABSENCE_ATTENDANCE = ['absent', 'unjustified'];

    /** ¿Un alumno en este estado de asistencia consume una sesión de bono? */
    public static function attendanceConsumesBono(?string $status): bool
    {
        return in_array($status, self::BONO_CONSUMING_ATTENDANCE, true);
    }

    /** ¿Este estado admite razón / nota de ausencia? */
    public static function attendanceIsAbsence(?string $status): bool
    {
        return in_array($status, self::ABSENCE_ATTENDANCE, true);
    }

    /** Todos los valores válidos de `class_session_players.attendance`. */
    public const ATTENDANCE_VALUES = ['present', 'absent', 'pending', 'confirmed', 'declined', 'unjustified'];

    /**
     * Resuelve el estado de asistencia efectivo para un "Descontar bono":
     * se usa el estado enviado desde el selector ($want) si es un valor válido;
     * si no, el que ya estaba guardado. Devuelve además si ese estado consume
     * bono y si hay que persistir un cambio.
     *
     * @return array{state:string, consumes:bool, persist:bool}
     */
    public static function resolveDeductAttendance(?string $stored, ?string $want): array
    {
        $wanted = ($want !== null && in_array($want, self::ATTENDANCE_VALUES, true)) ? $want : null;
        $state  = $wanted ?? ($stored ?: 'pending');

        return [
            'state'    => $state,
            'consumes' => self::attendanceConsumesBono($state),
            'persist'  => $wanted !== null && $wanted !== $stored,
        ];
    }

    /**
     * Admin descuenta 1 sesión del bono activo de un jugador.
     *
     * Descontar un bono significa "el alumno ha usado una sesión de su bono",
     * así que la acción también REGISTRA la asistencia elegida ($wantAttendance,
     * el valor del selector de la fila) si aún no estaba guardada — de ese modo
     * no hace falta pulsar "Guardar" antes de descontar. La asistencia efectiva
     * debe ser un estado que consuma bono (presente / confirmado / no justif.).
     *
     * Guarda de qué bono se descontó (`bono_deducted_from_id`) para devolverlo
     * con exactitud.
     */
    public function deductBonoForPlayer(int $sessionId, int $playerId, ?string $wantAttendance = null): array
    {
        $player = $this->playerModel
            ->where('session_id', $sessionId)
            ->where('user_id', $playerId)
            ->first();

        if (!$player) {
            return ['success' => false, 'error' => 'Jugador no asignado a esta sesión.'];
        }

        $att = self::resolveDeductAttendance($player['attendance'] ?? null, $wantAttendance);

        if (!$att['consumes']) {
            return ['success' => false, 'error' => 'Solo se puede descontar bono si el alumno está marcado como Presente, Confirmado o No justificado.'];
        }

        if (!empty($player['bono_deducted_at'])) {
            return ['success' => false, 'error' => 'El bono de este jugador ya fue descontado para esta sesión.'];
        }

        $bonoModel = new PlayerBonoModel();

        $this->db->transBegin();
        try {
            $bono = $bonoModel->deductSessionDetailed($playerId);

            if ($bono === null) {
                $this->db->transRollback();
                return ['success' => false, 'error' => 'El jugador no tiene bono activo.'];
            }

            $update = [
                'bono_deducted_at'      => date('Y-m-d H:i:s'),
                'bono_deducted_from_id' => (int)$bono['id'],
            ];
            // Descontar un bono = el alumno usó una sesión: registra la asistencia
            // elegida en el selector si aún no estaba guardada.
            if ($att['persist']) {
                $update['attendance'] = $att['state'];
                if (!self::attendanceIsAbsence($att['state'])) {
                    $update['absence_reason'] = null;
                    $update['absence_notes']  = null;
                }
            }

            // Reclamo atómico: solo lo consigue quien encuentra bono_deducted_at
            // NULL. Evita el doble descuento si dos peticiones concurren.
            $this->db->table('class_session_players')
                ->where('id', $player['id'])
                ->where('bono_deducted_at', null)
                ->update($update);

            if ($this->db->affectedRows() < 1) {
                $this->db->transRollback();
                return ['success' => false, 'error' => 'El bono de este jugador ya fue descontado para esta sesión.'];
            }

            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }

        $remaining = (int)$bono['sessions_remaining'];
        if ($remaining === 1 || $remaining === 0) {
            // El aviso de bono bajo no debe tumbar el descuento si algo falla.
            try {
                $this->emitBonoLowSessionsNotification($playerId, $bono);
            } catch (\Throwable $e) {
                log_message('error', 'emitBonoLowSessionsNotification falló tras descontar bono: ' . $e->getMessage());
            }
        }

        $typeRow   = $this->db->table('bono_types')->select('name')->where('id', $bono['bono_type_id'])->get()->getRowArray();
        $bonoName  = $typeRow['name'] ?? null;

        return [
            'success'            => true,
            'sessions_remaining' => $remaining,
            'bono_name'          => $bonoName,
            'deducted'           => true,
        ];
    }

    /**
     * Devuelve al bono la sesión que esta fila había consumido.
     * Idempotente vía `bono_deducted_at`: si no hay descuento, no hace nada.
     *
     * Prefiere el bono de origen (`bono_deducted_from_id`); si ese ya no sirve
     * (caducado, borrado, o al máximo de sesiones) acredita el bono activo del
     * alumno para no dejarle sin la sesión.
     *
     * NO abre transacción propia: el llamador la envuelve cuando hace falta
     * atomicidad con otras escrituras.
     *
     * @param array $player  fila de class_session_players (tal cual la BD)
     * @return array{refunded:bool, bono_id:?int}  bono_id = bono realmente acreditado
     */
    private function doRefund(array $player): array
    {
        if (empty($player['bono_deducted_at'])) {
            return ['refunded' => false, 'bono_id' => null];
        }

        $bonoModel = new PlayerBonoModel();
        $today     = date('Y-m-d');

        $bonoId = (int)($player['bono_deducted_from_id'] ?? 0);
        $origin = $bonoId ? $bonoModel->find($bonoId) : null;

        // El bono de origen sirve si no ha caducado y aún admite otra sesión.
        $originUsable = $origin
            && (int)$origin['sessions_remaining'] < (int)($origin['sessions_total'] ?? 0)
            && (empty($origin['expires_at']) || $origin['expires_at'] >= $today);

        $target = $originUsable
            ? $origin
            : ($bonoModel->getActiveBono((int)$player['user_id']) ?: $origin);

        $creditedId = null;
        if ($target) {
            $newRemaining = (int)$target['sessions_remaining'] + 1;
            $cap = (int)($target['sessions_total'] ?? 0);
            if ($cap > 0 && $newRemaining > $cap) {
                $newRemaining = $cap;
            }
            $bonoModel->update($target['id'], ['sessions_remaining' => $newRemaining]);
            $creditedId = (int)$target['id'];
        } else {
            log_message('warning', "doRefund: no se pudo devolver el bono del jugador {$player['user_id']} (fila csp {$player['id']}): sin bono destino.");
        }

        $this->playerModel->update($player['id'], [
            'bono_deducted_at'      => null,
            'bono_deducted_from_id' => null,
        ]);

        return ['refunded' => true, 'bono_id' => $creditedId];
    }

    /**
     * Devuelve el bono descontado a un jugador concreto de una sesión (acción
     * manual del admin: inverso de "Descontar bono").
     *
     * @return array{success:bool,sessions_remaining?:int,bono_name?:?string,error?:string}
     */
    public function refundBonoForPlayer(int $sessionId, int $playerId): array
    {
        $player = $this->playerModel
            ->where('session_id', $sessionId)
            ->where('user_id', $playerId)
            ->first();

        if (!$player) {
            return ['success' => false, 'error' => 'Alumno no asignado a esta sesión.'];
        }
        if (empty($player['bono_deducted_at'])) {
            return ['success' => false, 'error' => 'Este alumno no tiene ningún bono descontado en esta sesión.'];
        }

        $this->db->transBegin();
        try {
            $credited = $this->doRefund($player)['bono_id'];
            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }

        // Reportar sobre el bono realmente acreditado (así el contador de la
        // pantalla refleja el cambio exacto). Si no se pudo acreditar ninguno,
        // caemos al bono activo del alumno.
        $bono = $credited
            ? $this->db->table('player_bonos pb')
                ->select('pb.sessions_remaining, bt.name AS bono_name')
                ->join('bono_types bt', 'bt.id = pb.bono_type_id')
                ->where('pb.id', $credited)
                ->get()->getRowArray()
            : null;

        if (!$bono) {
            $bono = $this->db->table('player_bonos pb')
                ->select('pb.sessions_remaining, bt.name AS bono_name')
                ->join('bono_types bt', 'bt.id = pb.bono_type_id')
                ->where('pb.player_id', $playerId)
                ->where('pb.sessions_remaining >', 0)
                ->groupStart()
                    ->where('pb.expires_at IS NULL')
                    ->orWhere('pb.expires_at >=', date('Y-m-d'))
                ->groupEnd()
                ->orderBy('pb.created_at', 'ASC')
                ->get()->getRowArray();
        }

        return [
            'success'            => true,
            'sessions_remaining' => $bono ? (int)$bono['sessions_remaining'] : 0,
            'bono_name'          => $bono['bono_name'] ?? null,
        ];
    }

    /** Nº de bonos aún descontados (no revertidos) en una sesión. */
    public function countDeductedBonos(int $sessionId): int
    {
        return (int)$this->playerModel
            ->where('session_id', $sessionId)
            ->where('bono_deducted_at IS NOT NULL', null, false)
            ->countAllResults();
    }

    /**
     * Devuelve TODOS los bonos descontados de una sesión (al cancelar o
     * eliminar la sesión: la clase no se imparte, no debe consumir bonos).
     *
     * @return int  nº de bonos devueltos
     */
    private function refundAllDeductedForSession(int $sessionId): int
    {
        $rows = $this->playerModel
            ->where('session_id', $sessionId)
            ->where('bono_deducted_at IS NOT NULL', null, false)
            ->findAll();

        if (empty($rows)) {
            return 0;
        }

        $n = 0;
        $this->db->transBegin();
        try {
            foreach ($rows as $row) {
                if ($this->doRefund($row)['refunded']) {
                    $n++;
                }
            }
            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
        return $n;
    }

    /**
     * Historial de sesiones completadas con resumen de asistencia y estado de bonos.
     */
    public function getAttendanceHistorial(int $limit = 50): array
    {
        $sessions = $this->db->table('class_sessions cs')
            ->select('cs.id, cs.title, cs.session_date, cs.start_time, cs.end_time, cs.status')
            ->where('cs.status', 'completed')
            ->orderBy('cs.session_date', 'DESC')
            ->orderBy('cs.start_time', 'DESC')
            ->limit($limit)
            ->get()->getResultArray();

        $bonoModel = new PlayerBonoModel();

        foreach ($sessions as &$s) {
            $sid = (int)$s['id'];

            // Coaches
            $s['coaches'] = $this->db->table('class_session_coaches csc')
                ->select('u.name')
                ->join('users u', 'u.id = csc.user_id')
                ->where('csc.session_id', $sid)
                ->get()->getResultArray();

            // Jugadores con estado bono actual
            $players = $this->db->table('class_session_players csp')
                ->select('csp.user_id, csp.attendance, csp.absence_reason, csp.bono_deducted_at, u.name')
                ->join('users u', 'u.id = csp.user_id')
                ->where('csp.session_id', $sid)
                ->orderBy('u.name')
                ->get()->getResultArray();

            $counts = ['present' => 0, 'absent' => 0, 'unjustified' => 0, 'pending' => 0, 'other' => 0];
            $today = date('Y-m-d');
            foreach ($players as &$p) {
                $activeBono = $this->db->table('player_bonos pb')
                    ->select('pb.sessions_remaining, bt.name AS bono_name')
                    ->join('bono_types bt', 'bt.id = pb.bono_type_id')
                    ->where('pb.player_id', (int)$p['user_id'])
                    ->where('pb.sessions_remaining >', 0)
                    ->groupStart()
                        ->where('pb.expires_at IS NULL')
                        ->orWhere('pb.expires_at >=', $today)
                    ->groupEnd()
                    ->orderBy('pb.created_at', 'ASC')
                    ->get()->getRowArray();
                $p['sessions_remaining'] = $activeBono ? (int)$activeBono['sessions_remaining'] : null;
                $p['bono_name']          = $activeBono ? $activeBono['bono_name'] : null;

                if ($p['attendance'] === 'present')          $counts['present']++;
                elseif ($p['attendance'] === 'absent')       $counts['absent']++;
                elseif ($p['attendance'] === 'unjustified')  $counts['unjustified']++;
                elseif ($p['attendance'] === 'pending')      $counts['pending']++;
                else                                         $counts['other']++;
            }
            unset($p);

            $s['players']       = $players;
            $s['player_counts'] = $counts;
        }
        unset($s);

        return $sessions;
    }

    /**
     * Devuelve todas las sesiones de una semana, agrupadas por día.
     * $weekOffset: 0 = semana actual, -1 = semana anterior, etc.
     * $search: filtra por nombre de alumno o entrenador (parcial, case-insensitive).
     */
    public function getWeekSessions(int $weekOffset = 0, string $search = ''): array
    {
        $monday = new \DateTime('monday this week');
        if ($weekOffset !== 0) {
            $monday->modify(($weekOffset > 0 ? '+' : '') . $weekOffset . ' weeks');
        }
        $sunday = clone $monday;
        $sunday->modify('+6 days');

        $weekStart = $monday->format('Y-m-d');
        $weekEnd   = $sunday->format('Y-m-d');

        // Obtener sesiones del período
        $sessions = $this->db->table('class_sessions cs')
            ->select('cs.id, cs.title, cs.session_date, cs.start_time, cs.end_time, cs.status, cs.lista_pasada_at, cs.lista_pasada_by, u.name AS lista_pasada_by_name')
            ->join('users u', 'u.id = cs.lista_pasada_by', 'left')
            ->where('cs.session_date >=', $weekStart)
            ->where('cs.session_date <=', $weekEnd)
            ->where('cs.status !=', 'cancelled')
            ->orderBy('cs.session_date', 'ASC')
            ->orderBy('cs.start_time', 'ASC')
            ->get()->getResultArray();

        $today  = date('Y-m-d');
        $search = strtolower(trim($search));

        foreach ($sessions as &$s) {
            $sid = (int)$s['id'];

            $s['coaches'] = $this->db->table('class_session_coaches csc')
                ->select('csc.user_id, u.name')
                ->join('users u', 'u.id = csc.user_id')
                ->where('csc.session_id', $sid)
                ->orderBy('u.name')
                ->get()->getResultArray();

            $players = $this->db->table('class_session_players csp')
                ->select('csp.id AS csp_id, csp.user_id, csp.attendance, csp.absence_reason, csp.absence_notes, csp.bono_deducted_at, u.name, u.email')
                ->join('users u', 'u.id = csp.user_id')
                ->where('csp.session_id', $sid)
                ->orderBy('u.name')
                ->get()->getResultArray();

            // Enriquecer con bono activo
            foreach ($players as &$p) {
                $activeBono = $this->db->table('player_bonos pb')
                    ->select('pb.sessions_remaining, bt.name AS bono_name')
                    ->join('bono_types bt', 'bt.id = pb.bono_type_id')
                    ->where('pb.player_id', (int)$p['user_id'])
                    ->where('pb.sessions_remaining >', 0)
                    ->groupStart()
                        ->where('pb.expires_at IS NULL')
                        ->orWhere('pb.expires_at >=', $today)
                    ->groupEnd()
                    ->orderBy('pb.created_at', 'ASC')
                    ->get()->getRowArray();
                $p['sessions_remaining'] = $activeBono ? (int)$activeBono['sessions_remaining'] : null;
                $p['bono_name']          = $activeBono ? $activeBono['bono_name'] : null;
            }
            unset($p);

            $s['players'] = $players;

            // Contadores
            $counts = ['present' => 0, 'absent' => 0, 'unjustified' => 0, 'pending' => 0];
            foreach ($players as $p) {
                if (isset($counts[$p['attendance']])) $counts[$p['attendance']]++;
                else $counts['pending']++;
            }
            $s['player_counts'] = $counts;
        }
        unset($s);

        // Filtrar por búsqueda (alumno o entrenador)
        if ($search !== '') {
            $sessions = array_filter($sessions, function ($s) use ($search) {
                foreach ($s['players'] as $p) {
                    if (str_contains(strtolower($p['name']), $search)) return true;
                }
                foreach ($s['coaches'] as $c) {
                    if (str_contains(strtolower($c['name']), $search)) return true;
                }
                return false;
            });
            $sessions = array_values($sessions);
        }

        // Agrupar por día
        $byDay = [];
        foreach ($sessions as $s) {
            $day = $s['session_date'];
            if (!isset($byDay[$day])) $byDay[$day] = [];
            $byDay[$day][] = $s;
        }

        // Asegurar todos los días de la semana (incluso sin sesiones)
        $result = [];
        $cursor = clone $monday;
        for ($i = 0; $i < 7; $i++) {
            $key = $cursor->format('Y-m-d');
            $result[$key] = $byDay[$key] ?? [];
            $cursor->modify('+1 day');
        }

        return [
            'week_start' => $weekStart,
            'week_end'   => $weekEnd,
            'week_offset' => $weekOffset,
            'by_day'     => $result,
        ];
    }


    public function cancelSession(int $id): bool
    {
        // Una clase cancelada no se imparte: se devuelven los bonos ya descontados.
        $this->refundAllDeductedForSession($id);
        return (bool)$this->sessionModel->update($id, ['status' => 'cancelled']);
    }

    // ────────────────────────────────────────────────────────────────
    //  Eliminar
    // ────────────────────────────────────────────────────────────────

    public function deleteSession(int $id): bool
    {
        // Antes de borrar las filas: devolver los bonos descontados (si no,
        // se perdería el saldo y el rastro para siempre).
        $this->refundAllDeductedForSession($id);
        $this->db->table('class_session_coaches')->where('session_id', $id)->delete();
        $this->db->table('class_session_players')->where('session_id', $id)->delete();
        return (bool)$this->sessionModel->delete($id);
    }

    // ────────────────────────────────────────────────────────────────
    //  Entrenadores
    // ────────────────────────────────────────────────────────────────

    public function addCoach(int $sessionId, int $userId): array
    {
        // Max 1 coach per session
        $count = (int)$this->coachModel->where('session_id', $sessionId)->countAllResults();
        if ($count >= 1) {
            return ['success' => false, 'error' => 'Solo se permite 1 entrenador por sesión.'];
        }
        $this->coachModel->insert(['session_id' => $sessionId, 'user_id' => $userId]);
        return ['success' => true];
    }

    public function removeCoach(int $sessionId, int $userId): bool
    {
        $this->db->table('class_session_coaches')
            ->where('session_id', $sessionId)
            ->where('user_id', $userId)
            ->delete();
        return true;
    }

    /**
     * ¿Este usuario aparece como responsable en alguna sesión (pasada o
     * futura)? Decide si un admin/superadmin ve el toggle "Todas" / "Mis
     * clases": si nunca ha sido responsable de nada, no hay nada que aislar.
     */
    public function hasOwnAssignedSessions(int $userId): bool
    {
        return $this->coachModel->where('user_id', $userId)->countAllResults() > 0;
    }

    public function getCoachesForSession(int $sessionId): array
    {
        return $this->db->table('class_session_coaches csc')
            ->select('csc.session_id, csc.user_id, u.name, u.email')
            ->join('users u', 'u.id = csc.user_id')
            ->where('csc.session_id', $sessionId)
            ->orderBy('u.name')
            ->get()->getResultArray();
    }

    /**
     * Nº de sesiones programadas (incluida esta) de la misma clase
     * recurrente a partir de la fecha de esta sesión — para la UI "esta y
     * las siguientes (N sesiones)" al cambiar de responsable (TICKET-011).
     * 0 si la sesión no pertenece a una clase recurrente.
     */
    public function countFutureSeriesSessions(int $sessionId): int
    {
        $session = $this->sessionModel->find($sessionId);
        if (!$session || empty($session['class_id'])) {
            return 0;
        }

        return (int) $this->db->table('class_sessions')
            ->where('class_id', $session['class_id'])
            ->where('session_date >=', $session['session_date'])
            ->where('status', 'scheduled')
            ->countAllResults();
    }

    // ────────────────────────────────────────────────────────────────
    //  Continuación de clases recurrentes (renovación de la serie)
    // ────────────────────────────────────────────────────────────────

    /**
     * Estado de renovación de una clase recurrente: cuántas sesiones
     * 'scheduled' le quedan y si ya se generó su continuación.
     * `is_last` es true tanto si queda exactamente 1 sesión programada
     * (la serie está a punto de terminar) como si ya no queda ninguna (la
     * serie ya terminó) — en ambos casos tiene sentido ofrecer continuarla.
     *
     * @return array{class:array,scheduled_remaining:int,is_last:bool,already_renewed:bool}|null
     *         null si $classId no existe o no es una clase recurrente.
     */
    public function getRecurringSeriesStatus(int $classId): ?array
    {
        $class = $this->classModel->find($classId);
        if (!$class || ($class['type'] ?? '') !== 'recurring') {
            return null;
        }

        $scheduledRemaining = (int) $this->db->table('class_sessions')
            ->where('class_id', $classId)
            ->where('status', 'scheduled')
            ->countAllResults();

        return [
            'class'               => $class,
            'scheduled_remaining' => $scheduledRemaining,
            'is_last'             => $scheduledRemaining <= 1,
            'already_renewed'     => !empty($class['renewed_to_class_id']),
        ];
    }

    /**
     * Valores por defecto para el modal "Continuar clases recurrentes":
     * mismo patrón de días un mes después, mismos horarios/lugar/objetivo
     * de la plantilla, y responsable/alumnos heredados de la última sesión
     * de la serie (esos datos no se guardan en la plantilla, solo por
     * sesión). Todo queda editable en el modal antes de confirmar.
     *
     * @return array|null null si $classId no es una clase recurrente.
     */
    public function getRenewalDefaults(int $classId): ?array
    {
        $class = $this->classModel->find($classId);
        if (!$class || ($class['type'] ?? '') !== 'recurring') {
            return null;
        }

        $refSession = $this->db->table('class_sessions')
            ->where('class_id', $classId)
            ->orderBy('session_date', 'DESC')
            ->orderBy('id', 'DESC')
            ->get(1)->getRowArray();

        $sessionType = $refSession['session_type'] ?? 'coach';
        $coachIds    = $refSession ? array_map('intval', array_column($this->getCoachesForSession((int) $refSession['id']), 'user_id')) : [];
        $playerIds   = $refSession ? array_map('intval', array_column($this->getPlayersForSession((int) $refSession['id']), 'user_id')) : [];

        $days = json_decode((string) $class['recurrence_days'], true);
        $days = is_array($days) ? array_map('intval', $days) : [];

        $newStart = $class['recurrence_start'] ? (new \DateTime($class['recurrence_start']))->modify('+1 month')->format('Y-m-d') : null;
        $newEnd   = $class['recurrence_end']   ? (new \DateTime($class['recurrence_end']))->modify('+1 month')->format('Y-m-d')   : null;

        return [
            'title'            => $class['title'],
            'description'      => $class['description'],
            'class_format'     => $class['class_format'] ?? 'individual',
            'session_type'     => in_array($sessionType, ['coach', 'staff']) ? $sessionType : 'coach',
            'recurrence_days'  => $days,
            'recurrence_start' => $newStart,
            'recurrence_end'   => $newEnd,
            'start_time'       => $class['recurrence_time_start'] ? substr((string) $class['recurrence_time_start'], 0, 5) : null,
            'end_time'         => $class['recurrence_time_end'] ? substr((string) $class['recurrence_time_end'], 0, 5) : null,
            'location_id'      => $class['default_location_id'],
            'location_custom'  => $class['default_location_custom'],
            'focus'            => $class['default_focus'],
            'coach_ids'        => $coachIds,
            'player_ids'       => $playerIds,
        ];
    }

    /**
     * Continúa una clase recurrente ya terminada (o a punto de terminar)
     * generando una nueva plantilla enlazada, con sus sesiones. Reutiliza
     * createRecurring() para la validación y generación — $data ya debe
     * traer los mismos campos que la creación de una clase recurrente
     * (title, recurrence_days[], recurrence_start/end, start_time/end_time,
     * class_format, session_type, location_id/custom, focus, coach_ids[],
     * player_ids[]), normalmente precargados desde getRenewalDefaults() y
     * editados en el modal antes de confirmar.
     *
     * Una serie solo se puede continuar una vez (`renewed_to_class_id`).
     */
    public function renewRecurringClass(int $sourceClassId, array $data, int $userId): array
    {
        $source = $this->classModel->find($sourceClassId);
        if (!$source || ($source['type'] ?? '') !== 'recurring') {
            return ['success' => false, 'error' => 'La clase original no es una serie recurrente.'];
        }
        if (!empty($source['renewed_to_class_id'])) {
            return ['success' => false, 'error' => 'Esta serie recurrente ya se ha continuado.'];
        }
        // El responsable es obligatorio al continuar una serie (a diferencia
        // de crear/editar una clase suelta, donde sí puede quedar sin
        // asignar) — no puede colar en silencio sin entrenador/staff.
        if (empty(array_filter(array_map('intval', (array) ($data['coach_ids'] ?? []))))) {
            return ['success' => false, 'error' => 'Debes asignar un responsable (entrenador o staff) para continuar la serie.'];
        }

        $data['type'] = 'recurring';
        $result = $this->createRecurring($data, $userId);
        if (!$result['success']) {
            return $result;
        }
        if ($result['count'] === 0) {
            // El rango de fechas indicado no contiene ningún día de la
            // semana marcado: no tiene sentido dejar una plantilla vacía.
            $this->classModel->delete((int) $result['class_id']);
            return ['success' => false, 'error' => 'El rango de fechas indicado no genera ninguna sesión con los días de la semana marcados.'];
        }

        $this->classModel->update($sourceClassId, ['renewed_to_class_id' => $result['class_id']]);
        $this->classModel->update((int) $result['class_id'], ['renewed_from_class_id' => $sourceClassId]);

        return $result;
    }

    /**
     * Cambia el responsable (entrenador o staff) de una sesión y, si se
     * pide, de las siguientes sesiones programadas de la misma clase
     * recurrente (TICKET-011 — "controlar el calendario de los
     * entrenadores").
     *
     * - Solo sesiones `scheduled`: una cerrada o cancelada no se toca
     *   (misma invariante que el cierre ágil de sesiones, v1.4.0).
     * - El nuevo responsable (si se indica) debe existir, estar activo y
     *   tener un rol válido para el `session_type` de la sesión.
     * - Arrastra a los alumnos cuyo `coach_id` era el responsable anterior
     *   (o no tenían ninguno) al nuevo, para no dejar referencias colgando
     *   a alguien que ya no da la clase. Un alumno con un responsable
     *   distinto asignado a propósito (caso raro, ver TICKET-011 §1) no se
     *   toca.
     * - Avisa por notificación al responsable anterior (si cambia) y al
     *   nuevo, con un solo aviso resumido si afecta a varias sesiones.
     *
     * @param string $scope 'single' = solo esta sesión; 'series' = esta y
     *                       las siguientes sesiones programadas de la misma
     *                       clase recurrente (si no pertenece a una, se
     *                       comporta igual que 'single').
     */
    public function changeResponsible(int $sessionId, ?int $newUserId, string $scope, int $actorId): array
    {
        $session = $this->sessionModel->find($sessionId);
        if (!$session) {
            return ['success' => false, 'error' => 'Sesión no encontrada.'];
        }
        if ($session['status'] !== 'scheduled') {
            return ['success' => false, 'error' => 'No se puede cambiar el responsable de una sesión cerrada o cancelada.'];
        }

        $sessionType  = $session['session_type'] ?? 'coach';
        $allowedRoles = $sessionType === 'staff' ? self::RESPONSABLE_STAFF_ROLES : self::RESPONSABLE_TECNICO_ROLES;

        if ($newUserId !== null) {
            $newUser = (new UserModel())->find($newUserId);
            if (!$newUser || $newUser['status'] !== 'active' || !in_array($newUser['role'], $allowedRoles, true)) {
                return ['success' => false, 'error' => 'La persona seleccionada no puede ser responsable de esta sesión.'];
            }
        }

        // Sesiones afectadas: solo esta, o esta y las siguientes programadas
        // de la misma clase recurrente.
        $targetIds = [$sessionId];
        if ($scope === 'series' && !empty($session['class_id'])) {
            $rows = $this->db->table('class_sessions')
                ->select('id')
                ->where('class_id', $session['class_id'])
                ->where('session_date >=', $session['session_date'])
                ->where('status', 'scheduled')
                ->get()->getResultArray();
            $targetIds = array_map('intval', array_column($rows, 'id')) ?: [$sessionId];
        }

        $previousCoachIds = [];

        $this->db->transStart();
        foreach ($targetIds as $sid) {
            $current = $this->getCoachesForSession($sid);
            $oldId   = isset($current[0]) ? (int) $current[0]['user_id'] : null;

            if ($oldId !== null && $oldId !== $newUserId) {
                $previousCoachIds[] = $oldId;
            }

            $this->syncCoaches($sid, $newUserId !== null ? [$newUserId] : []);

            // Los alumnos que tenían al responsable anterior (o ninguno) pasan
            // al nuevo; uno con otro responsable asignado a propósito no se toca.
            $builder = $this->db->table('class_session_players')->where('session_id', $sid);
            if ($oldId !== null) {
                $builder->groupStart()->where('coach_id', $oldId)->orWhere('coach_id', null)->groupEnd();
            } else {
                // whereNull() no existe en esta versión de CI4/MySQLi Builder
                // (misma trampa que orWhereNull(), ver Routes.php/CLAUDE.md).
                $builder->where('coach_id', null);
            }
            $builder->update(['coach_id' => $newUserId, 'updated_at' => date('Y-m-d H:i:s')]);
        }
        $this->db->transComplete();

        if (!$this->db->transStatus()) {
            return ['success' => false, 'error' => 'No se pudo guardar el cambio de responsable.'];
        }

        $this->notifyResponsibleChange(
            $session,
            array_values(array_unique($previousCoachIds)),
            $newUserId,
            count($targetIds),
            $actorId
        );

        return [
            'success'          => true,
            'sessions_changed' => count($targetIds),
            'session_ids'      => $targetIds,
        ];
    }

    /**
     * Aviso de cambio de responsable: al anterior (si tenía y cambia) y al
     * nuevo (si se asigna), sin duplicar si el propio actor es uno de ellos.
     * Un solo aviso resumido cuando afecta a varias sesiones de una serie.
     */
    private function notifyResponsibleChange(array $session, array $previousCoachIds, ?int $newUserId, int $sessionsChanged, int $actorId): void
    {
        if (empty($previousCoachIds) && $newUserId === null) {
            return;
        }

        $title = $sessionsChanged > 1
            ? sprintf('📅 Cambio de responsable: %d sesiones de "%s"', $sessionsChanged, $session['title'])
            : sprintf('📅 Cambio de responsable: %s', $session['title']);
        $dateLabel  = date('d/m/Y', strtotime($session['session_date']));
        $notifModel = new NotificationModel();

        foreach ($previousCoachIds as $oldId) {
            if ($oldId === $actorId) {
                continue;
            }
            $body = $sessionsChanged > 1
                ? "Ya no eres responsable de {$sessionsChanged} sesiones de \"{$session['title']}\" (desde el {$dateLabel})."
                : "Ya no eres responsable de la clase \"{$session['title']}\" del {$dateLabel}.";
            $notifModel->createWithRecipients([
                'sender_id'   => $actorId,
                'type'        => 'individual',
                'title'       => $title,
                'body'        => $body,
                'created_at'  => date('Y-m-d H:i:s'),
                'source_type' => NotificationModel::SOURCE_CLASS,
                'source_id'   => (int) $session['id'],
            ], [$oldId]);
        }

        if ($newUserId !== null && $newUserId !== $actorId) {
            $body = $sessionsChanged > 1
                ? "Se te han asignado {$sessionsChanged} sesiones de \"{$session['title']}\" (desde el {$dateLabel})."
                : "Se te ha asignado como responsable de la clase \"{$session['title']}\" del {$dateLabel}.";
            $notifModel->createWithRecipients([
                'sender_id'   => $actorId,
                'type'        => 'individual',
                'title'       => $title,
                'body'        => $body,
                'created_at'  => date('Y-m-d H:i:s'),
                'source_type' => NotificationModel::SOURCE_CLASS,
                'source_id'   => (int) $session['id'],
            ], [$newUserId]);
        }
    }

    // ────────────────────────────────────────────────────────────────
    //  Jugadores
    // ────────────────────────────────────────────────────────────────

    public function addPlayer(int $sessionId, array $data): array
    {
        $userId = (int)($data['user_id'] ?? 0);
        if (!$userId) return ['success' => false, 'error' => 'Usuario no válido.'];

        if ($this->playerModel->where('session_id', $sessionId)->where('user_id', $userId)->first()) {
            return ['success' => false, 'error' => 'El jugador ya está en esta sesión.'];
        }

        $session = $this->sessionModel->find($sessionId);
        $fmt = $session['class_format'] ?? 'individual';
        $maxPlayers = $fmt === 'pareja' ? 2 : 1;

        $currentCount = $this->db->table('class_session_players')
            ->where('session_id', $sessionId)
            ->countAllResults();
        if ($currentCount >= $maxPlayers) {
            $label = $maxPlayers === 1 ? '1 alumno (clase individual)' : '2 alumnos (clase en pareja)';
            return ['success' => false, 'error' => "Sesión completa: máximo {$label}."];
        }

        $now = date('Y-m-d H:i:s');
        $this->db->table('class_session_players')->insert([
            'id'         => $this->nextPlayerRowId(),
            'session_id' => $sessionId,
            'user_id'    => $userId,
            'coach_id'   => ($data['coach_id'] ?? '') ?: null,
            'attendance' => 'pending',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        return ['success' => true];
    }

    public function removePlayer(int $sessionId, int $userId): bool
    {
        // Si al alumno se le había descontado un bono en esta sesión, se le
        // devuelve antes de eliminar la fila (si no, se pierde el saldo).
        $row = $this->playerModel
            ->where('session_id', $sessionId)
            ->where('user_id', $userId)
            ->first();

        $this->db->transBegin();
        try {
            if ($row) {
                $this->doRefund($row);
            }
            $this->db->table('class_session_players')
                ->where('session_id', $sessionId)
                ->where('user_id', $userId)
                ->delete();
            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }
        return true;
    }

    public function getPlayersForSession(int $sessionId): array
    {
        return $this->db->table('class_session_players csp')
            ->select('csp.*, u.name, u.email, coach.name AS coach_name')
            ->join('users u', 'u.id = csp.user_id')
            ->join('users coach', 'coach.id = csp.coach_id', 'left')
            ->where('csp.session_id', $sessionId)
            ->orderBy('u.name')
            ->get()->getResultArray();
    }

    // ────────────────────────────────────────────────────────────────
    //  Aviso de ausencia del alumno
    // ────────────────────────────────────────────────────────────────

    /**
     * El alumno indica que no puede asistir, opcionalmente con un motivo.
     * Se advierte si se notifica después de las 10:00 del día de la clase,
     * pero igualmente se registra el aviso (el rechazo de guardar es opcional
     * según la regla de negocio; aquí dejamos pasar con advertencia).
     */
    public function notifyAbsence(int $userId, int $sessionId, string $note): array
    {
        $player = $this->playerModel
            ->where('session_id', $sessionId)
            ->where('user_id', $userId)
            ->first();

        if (!$player) {
            return ['success' => false, 'error' => 'No estás asignado a esta sesión.'];
        }

        $session = $this->sessionModel->find($sessionId);
        if (!$session) {
            return ['success' => false, 'error' => 'Sesión no encontrada.'];
        }

        if ($session['status'] !== 'scheduled') {
            return ['success' => false, 'error' => 'No se puede notificar ausencia en una sesión que no está programada.'];
        }

        $now = new \DateTime();
        $sessionDate = $session['session_date'];
        $todayStr    = $now->format('Y-m-d');
        $lateNotice  = false;

        if ($sessionDate === $todayStr && $now->format('H:i') > '10:00') {
            $lateNotice = true;
        }

        $this->playerModel->update($player['id'], [
            'student_note'      => $note ?: null,
            'student_noted_at'  => date('Y-m-d H:i:s'),
        ]);

        return [
            'success'    => true,
            'lateNotice' => $lateNotice,
        ];
    }

    // ────────────────────────────────────────────────────────────────
    //  Observaciones
    // ────────────────────────────────────────────────────────────────

    public function saveObservations(int $sessionId, array $data): bool
    {
        // Observaciones globales
        $update = [];
        if (array_key_exists('pre_notes', $data))  $update['pre_notes']  = $data['pre_notes']  ?: null;
        if (array_key_exists('post_notes', $data)) $update['post_notes'] = $data['post_notes'] ?: null;
        if (!empty($update)) $this->sessionModel->update($sessionId, $update);

        // Observaciones por jugador
        foreach ((array)($data['player_obs'] ?? []) as $userId => $obs) {
            $player = $this->playerModel
                ->where('session_id', $sessionId)
                ->where('user_id', (int)$userId)
                ->first();

            if ($player) {
                $pu = [];
                if (array_key_exists('pre', $obs))  $pu['pre_obs']  = $obs['pre']  ?: null;
                if (array_key_exists('post', $obs)) $pu['post_obs'] = $obs['post'] ?: null;
                if (!empty($pu)) $this->playerModel->update($player['id'], $pu);
            }
        }

        return true;
    }

    // ────────────────────────────────────────────────────────────────
    //  Adjuntos de observaciones (fotos/vídeos/documentos)
    // ────────────────────────────────────────────────────────────────

    /**
     * Registra un adjunto ya subido a disco (ver ClasesController::handleFileUpload).
     * $playerUserId: si viene, se resuelve la fila de class_session_players
     * de ese alumno en esta sesión y el adjunto queda ligado a su observación
     * individual; si es null, queda como adjunto general de la sesión.
     */
    public function addAttachment(int $sessionId, ?int $playerUserId, int $uploadedBy, array $fileData): array
    {
        $playerId = null;
        if ($playerUserId !== null) {
            $player = $this->playerModel
                ->where('session_id', $sessionId)
                ->where('user_id', $playerUserId)
                ->first();
            if (!$player) {
                return ['success' => false, 'error' => 'El jugador no está asignado a esta sesión.'];
            }
            $playerId = (int) $player['id'];
        }

        $id = $this->attachmentModel->addAttachment($sessionId, $playerId, $uploadedBy, $fileData);

        return ['success' => $id > 0, 'id' => $id];
    }

    public function getAttachment(int $attachmentId): ?array
    {
        return $this->attachmentModel->find($attachmentId);
    }

    /**
     * Todos los adjuntos individuales de un alumno a lo largo de sus
     * sesiones, para mostrarlos en su ficha de jugador.
     */
    public function getAttachmentsForUser(int $userId): array
    {
        return $this->attachmentModel->getForUserAcrossSessions($userId);
    }

    public function deleteAttachment(int $attachmentId): bool
    {
        $attach = $this->attachmentModel->find($attachmentId);
        if (!$attach) {
            return false;
        }

        helper('upload');
        $fullPath = upload_resolve_stored($attach['file_path']);
        if ($fullPath !== null && is_file($fullPath)) {
            @unlink($fullPath);
        }

        return (bool) $this->attachmentModel->delete($attachmentId);
    }

    // ────────────────────────────────────────────────────────────────
    //  Control de asistencia (admin/coach marca presente/ausente)
    // ────────────────────────────────────────────────────────────────

    /**
     * Guarda asistencia y motivo de ausencia por jugador.
     * $attendanceMap: [userId => status]
     * $absenceReasons: [userId => reason]  (valor predefinido)
     * $absenceNotes: [userId => notes]     (texto libre adicional)
     *
     * Concilia el bono: si una fila tenía un bono descontado y el nuevo
     * estado ya no consume bono (absent / declined / pending), se devuelve
     * automáticamente. El descuento en sí sigue siendo manual.
     *
     * @return int  nº de bonos devueltos automáticamente por el cambio de estado
     */
    public function updateAttendance(int $sessionId, array $attendanceMap, array $absenceReasons = [], array $absenceNotes = []): int
    {
        $refunded = 0;

        // Todo el guardado de la lista es atómico: si falla a media lista, no
        // deja unos alumnos con la asistencia nueva y otros con la vieja.
        $this->db->transBegin();
        try {
            foreach ($attendanceMap as $userId => $status) {
                if (!in_array($status, self::ATTENDANCE_VALUES, true)) continue;

                $player = $this->playerModel
                    ->where('session_id', $sessionId)
                    ->where('user_id', (int)$userId)
                    ->first();

                if (!$player) continue;

                $update = ['attendance' => $status];
                if (self::attendanceIsAbsence($status)) {
                    // absent y unjustified admiten razón/nota (la vista deja
                    // editarlas en ambos; antes solo se guardaban para 'absent').
                    $update['absence_reason'] = ($absenceReasons[$userId] ?? '') ?: null;
                    $update['absence_notes']  = ($absenceNotes[$userId] ?? '') ?: null;
                } else {
                    $update['absence_reason'] = null;
                    $update['absence_notes']  = null;
                }
                $this->playerModel->update($player['id'], $update);

                if (!empty($player['bono_deducted_at'])
                    && !self::attendanceConsumesBono($status)
                    && $this->doRefund($player)['refunded']) {
                    $refunded++;
                }
            }
            $this->db->transCommit();
        } catch (\Throwable $e) {
            $this->db->transRollback();
            throw $e;
        }

        return $refunded;
    }

    // ────────────────────────────────────────────────────────────────
    //  Estadísticas
    // ────────────────────────────────────────────────────────────────

    public function getStats(int $userId, string $role): array
    {
        $isPlayer  = in_array($role, ['alumno', 'player']);
        $weekStart = date('Y-m-d', strtotime('monday this week'));
        $weekEnd   = date('Y-m-d', strtotime('sunday this week'));
        $mStart    = date('Y-m-01');
        $mEnd      = date('Y-m-t');

        $isStaff = $role === 'staff';

        if ($isPlayer) {
            $weekCount = (int)$this->db->table('class_sessions cs')
                ->join('class_session_players csp', 'csp.session_id = cs.id')
                ->where('csp.user_id', $userId)
                ->where('cs.session_date >=', $weekStart)
                ->where('cs.session_date <=', $weekEnd)
                ->countAllResults();

            $monthCount = (int)$this->db->table('class_sessions cs')
                ->join('class_session_players csp', 'csp.session_id = cs.id')
                ->where('csp.user_id', $userId)
                ->where('cs.session_date >=', $mStart)
                ->where('cs.session_date <=', $mEnd)
                ->countAllResults();
        } elseif ($isStaff) {
            // Staff: solo sesiones donde está asignado como responsable
            $weekCount = (int)$this->db->table('class_sessions cs')
                ->join('class_session_coaches csc', 'csc.session_id = cs.id')
                ->where('csc.user_id', $userId)
                ->where('cs.session_date >=', $weekStart)
                ->where('cs.session_date <=', $weekEnd)
                ->where('cs.status !=', 'cancelled')
                ->countAllResults();

            $monthCount = (int)$this->db->table('class_sessions cs')
                ->join('class_session_coaches csc', 'csc.session_id = cs.id')
                ->where('csc.user_id', $userId)
                ->where('cs.session_date >=', $mStart)
                ->where('cs.session_date <=', $mEnd)
                ->where('cs.status !=', 'cancelled')
                ->countAllResults();
        } else {
            $weekCount = (int)$this->db->table('class_sessions')
                ->where('session_date >=', $weekStart)
                ->where('session_date <=', $weekEnd)
                ->where('status !=', 'cancelled')
                ->countAllResults();

            $monthCount = (int)$this->db->table('class_sessions')
                ->where('session_date >=', $mStart)
                ->where('session_date <=', $mEnd)
                ->where('status !=', 'cancelled')
                ->countAllResults();
        }

        // Jugadores únicos activos este mes (staff: solo de sus sesiones)
        $playersQuery = $this->db->table('class_session_players csp')
            ->select('csp.user_id')
            ->join('class_sessions cs', 'cs.id = csp.session_id')
            ->where('cs.session_date >=', $mStart)
            ->where('cs.session_date <=', $mEnd)
            ->where('cs.status !=', 'cancelled');
        if ($isStaff) {
            $playersQuery->join('class_session_coaches csc', 'csc.session_id = cs.id')
                         ->where('csc.user_id', $userId);
        }
        $activePlayers = (int)$playersQuery->groupBy('csp.user_id')->countAllResults();

        // Asistencia media últimas 4 semanas (staff: solo sus sesiones)
        $since = date('Y-m-d', strtotime('-4 weeks'));
        $presentQuery = $this->db->table('class_session_players csp')
            ->join('class_sessions cs', 'cs.id = csp.session_id')
            ->where('cs.status', 'completed')
            ->where('cs.session_date >=', $since)
            ->where('csp.attendance', 'present');
        $totalQuery = $this->db->table('class_session_players csp')
            ->join('class_sessions cs', 'cs.id = csp.session_id')
            ->where('cs.status', 'completed')
            ->where('cs.session_date >=', $since);
        if ($isStaff) {
            $presentQuery->join('class_session_coaches csc', 'csc.session_id = cs.id')
                         ->where('csc.user_id', $userId);
            $totalQuery->join('class_session_coaches csc', 'csc.session_id = cs.id')
                       ->where('csc.user_id', $userId);
        }
        $present = (int)$presentQuery->countAllResults();
        $total   = (int)$totalQuery->countAllResults();

        $avgAttendance = ($total > 0) ? round(($present / $total) * 100) : null;

        return [
            'this_week'      => $weekCount,
            'this_month'     => $monthCount,
            'active_players' => $activePlayers,
            'avg_attendance' => $avgAttendance,
        ];
    }

    // ────────────────────────────────────────────────────────────────
    //  Opciones para selectores
    // ────────────────────────────────────────────────────────────────

    public function getCoachOptions(): array
    {
        return $this->db->table('users')
            ->select('id, name, email, role')
            ->whereIn('role', self::RESPONSABLE_TECNICO_ROLES)
            ->where('status', 'active')
            ->orderBy('name')
            ->get()->getResultArray();
    }

    public function getPlayerOptions(): array
    {
        return $this->db->table('users')
            ->select('id, name, email')
            ->whereIn('role', ['alumno', 'player'])
            ->where('status', 'active')
            ->orderBy('name')
            ->get()->getResultArray();
    }

    public function getStaffOptions(): array
    {
        return $this->db->table('users')
            ->select('id, name, email, role')
            ->whereIn('role', self::RESPONSABLE_STAFF_ROLES)
            ->where('status', 'active')
            ->orderBy('name')
            ->get()->getResultArray();
    }

    public function getLocationOptions(): array
    {
        return $this->db->table('locations')
            ->select('id, name, type, address')
            ->where('active', 1)
            ->orderBy('name')
            ->get()->getResultArray();
    }

    public function getAllOptions(): array
    {
        return [
            'coaches'   => $this->getCoachOptions(),
            'staff'     => $this->getStaffOptions(),
            'players'   => $this->getPlayerOptions(),
            'locations' => $this->getLocationOptions(),
        ];
    }

    // ────────────────────────────────────────────────────────────────
    //  Helpers internos
    // ────────────────────────────────────────────────────────────────

    private function syncCoaches(int $sessionId, array $userIds): void
    {
        $this->db->table('class_session_coaches')->where('session_id', $sessionId)->delete();

        // Max 1 coach per session — take only the first valid ID
        $filtered = array_values(array_unique(array_filter(array_map('intval', (array)$userIds))));
        if (empty($filtered)) return;

        $uid = $filtered[0];
        $this->db->table('class_session_coaches')->insert([
            'session_id' => $sessionId,
            'user_id'    => $uid,
        ]);
        if ($this->db->affectedRows() === 0) {
            log_message('error', 'syncCoaches: insert failed for session=' . $sessionId . ' user=' . $uid . ' | ' . $this->db->error()['message']);
        }
    }

    public function checkLocationConflict(int $locationId, string $date, string $startTime, string $endTime, ?int $excludeSessionId = null): array
    {
        $builder = $this->db->table('class_sessions cs')
            ->select('cs.id, cs.title, cs.start_time, cs.end_time')
            ->where('cs.location_id', $locationId)
            ->where('cs.session_date', $date)
            ->where('cs.status !=', 'cancelled')
            ->where('cs.start_time <', $endTime)
            ->where('cs.end_time >', $startTime);

        if ($excludeSessionId) {
            $builder->where('cs.id !=', $excludeSessionId);
        }

        return $builder->get()->getResultArray();
    }

    /**
     * Sincroniza la lista de alumnos de una sesión SIN destruir el trabajo ya
     * hecho: conserva asistencia, observaciones y descuentos de bono de los
     * alumnos que siguen; solo inserta los nuevos y elimina los que se quitan
     * (devolviéndoles el bono si se les había descontado).
     */
    private function syncPlayers(int $sessionId, array $userIds, array $coachMap): void
    {
        $wanted = array_values(array_unique(array_filter(array_map('intval', (array)$userIds))));

        $existing = $this->db->table('class_session_players')
            ->select('id, user_id')
            ->where('session_id', $sessionId)
            ->get()->getResultArray();

        $existingByUser = [];
        foreach ($existing as $r) {
            $existingByUser[(int)$r['user_id']] = (int)$r['id'];
        }

        // Quitar los que ya no están: devolver bono + borrar fila.
        foreach ($existingByUser as $uid => $rowId) {
            if (!in_array($uid, $wanted, true)) {
                $this->removePlayer($sessionId, $uid);
            }
        }

        $now = date('Y-m-d H:i:s');
        foreach ($wanted as $uid) {
            $coachId = isset($coachMap[$uid]) ? ((int)$coachMap[$uid] ?: null) : null;

            if (isset($existingByUser[$uid])) {
                // Ya estaba: solo actualiza el responsable asignado.
                $this->db->table('class_session_players')
                    ->where('id', $existingByUser[$uid])
                    ->update(['coach_id' => $coachId, 'updated_at' => $now]);
                continue;
            }

            $this->db->table('class_session_players')->insert([
                'id'         => $this->nextPlayerRowId(),
                'session_id' => $sessionId,
                'user_id'    => $uid,
                'coach_id'   => $coachId,
                'attendance' => 'pending',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            if ($this->db->affectedRows() === 0) {
                log_message('error', 'syncPlayers: insert failed for session=' . $sessionId . ' user=' . $uid . ' | ' . $this->db->error()['message']);
            }
        }
    }

    private function nextPlayerRowId(): int
    {
        $row = $this->db->query('SELECT COALESCE(MAX(id), 0) + 1 AS next_id FROM class_session_players')->getRowArray();
        return (int)($row['next_id'] ?? 1);
    }

    private function statusColor(string $status): string
    {
        return [
            'scheduled' => '#3b82f6',
            'completed' => '#10b981',
            'cancelled' => '#6b7280',
        ][$status] ?? '#3b82f6';
    }
}
