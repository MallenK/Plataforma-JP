<?php

namespace App\Services;

use App\Models\OrgEventModel;
use App\Models\OrgTaskModel;
use Config\Database;

/**
 * Organización interna (TICKET-014): tareas por estados, planificador semanal y agenda.
 *
 * Visibilidad: cada ítem es 'team' (lo ve todo el equipo con acceso) o 'personal'
 * (solo su creador; ni admin ni superadmin). Eso se aplica SIEMPRE aquí, nunca en la vista.
 * Nada se borra: archivar = archived_at + registro en audit_log.
 */
class OrganizacionService
{
    public const STATUSES = [
        'pendiente' => 'Pendiente',
        'en_curso'  => 'En curso',
        'revision'  => 'En revisión',
        'hecho'     => 'Hecho',
    ];
    public const PRIORITIES = ['baja' => 'Baja', 'normal' => 'Normal', 'alta' => 'Alta'];
    public const ROLES      = ['superadmin', 'admin', 'staff', 'coach'];

    private \CodeIgniter\Database\BaseConnection $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    // ── Reglas puras (testeables sin BD) ────────────────────────────────────

    public static function canAccess(?string $role): bool
    {
        return in_array($role, self::ROLES, true);
    }

    public static function canSee(array $item, int $userId): bool
    {
        if (($item['visibility'] ?? 'team') === 'personal') {
            return (int) $item['created_by'] === $userId;
        }
        return true;
    }

    /** Editar todo: el creador, o admin/superadmin si el ítem es del equipo. */
    public static function canEdit(array $item, int $userId, string $role): bool
    {
        if (!self::canSee($item, $userId)) {
            return false;
        }
        if ((int) $item['created_by'] === $userId) {
            return true;
        }
        return ($item['visibility'] ?? 'team') === 'team' && in_array($role, ['admin', 'superadmin'], true);
    }

    /** Cambiar solo el estado/planificación: además de quien edita, el asignado. */
    public static function canMove(array $item, int $userId, string $role): bool
    {
        return self::canEdit($item, $userId, $role)
            || (self::canSee($item, $userId) && !empty($item['assigned_to']) && (int) $item['assigned_to'] === $userId);
    }

    public static function normalizeStatus(?string $s): string
    {
        return isset(self::STATUSES[$s ?? '']) ? $s : 'pendiente';
    }

    public static function normalizePriority(?string $p): string
    {
        return isset(self::PRIORITIES[$p ?? '']) ? $p : 'normal';
    }

    public static function normalizeVisibility(?string $v): string
    {
        return $v === 'personal' ? 'personal' : 'team';
    }

    public static function normalizeDate(?string $d): ?string
    {
        $d = trim((string) $d);
        $dt = \DateTime::createFromFormat('Y-m-d', $d);
        return ($dt && $dt->format('Y-m-d') === $d) ? $d : null;
    }

    public static function normalizeTime(?string $t): ?string
    {
        $t = trim((string) $t);
        if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)(:[0-5]\d)?$/', $t, $m)) {
            return null;
        }
        return $m[1] . ':' . $m[2];
    }

    // ── Personas ────────────────────────────────────────────────────────────

    public function people(): array
    {
        return $this->db->table('users')
            ->select('id, name, role')
            ->whereIn('role', self::ROLES)
            ->where('status', 'active')
            ->orderBy('name', 'ASC')
            ->get()->getResultArray();
    }

    // ── Tareas ──────────────────────────────────────────────────────────────

    /** Tareas visibles; las «hecho» de hace más de 30 días se ocultan del tablero. */
    public function listTasks(int $userId): array
    {
        $rows = $this->db->table('org_tasks t')
            ->select('t.*, a.name AS assigned_name, c.name AS creator_name')
            ->join('users a', 'a.id = t.assigned_to', 'left')
            ->join('users c', 'c.id = t.created_by', 'left')
            ->where('t.archived_at', null)
            ->groupStart()
                ->where('t.visibility', 'team')
                ->orWhere('t.created_by', $userId)
            ->groupEnd()
            ->groupStart()
                ->where('t.status !=', 'hecho')
                ->orWhere('t.completed_at >=', date('Y-m-d H:i:s', strtotime('-30 days')))
                ->orWhere('t.completed_at', null)
            ->groupEnd()
            ->orderBy('t.position', 'ASC')
            ->orderBy('t.id', 'ASC')
            ->get()->getResultArray();

        return array_map(fn ($r) => $this->shapeTask($r), $rows);
    }

    private function shapeTask(array $r): array
    {
        return [
            'id'            => (int) $r['id'],
            'title'         => $r['title'],
            'description'   => $r['description'] ?? '',
            'status'        => $r['status'],
            'priority'      => $r['priority'],
            'due_date'      => $r['due_date'],
            'assigned_to'   => $r['assigned_to'] !== null ? (int) $r['assigned_to'] : null,
            'assigned_name' => $r['assigned_name'] ?? null,
            'created_by'    => (int) $r['created_by'],
            'creator_name'  => $r['creator_name'] ?? null,
            'visibility'    => $r['visibility'],
            'position'      => (int) $r['position'],
        ];
    }

    private function findTask(int $id): array
    {
        $t = (new OrgTaskModel())->find($id);
        if (!$t || $t['archived_at'] !== null) {
            throw new \DomainException('La tarea no existe o ya está archivada.');
        }
        return $t;
    }

    public function saveTask(int $userId, string $role, array $in, ?int $id = null): array
    {
        $title = trim((string) ($in['title'] ?? ''));
        if ($title === '') {
            throw new \DomainException('Escribe un título para la tarea.');
        }
        if (mb_strlen($title) > 200) {
            throw new \DomainException('El título es demasiado largo (máx. 200 caracteres).');
        }

        $model = new OrgTaskModel();
        $before = null;
        if ($id) {
            $before = $this->findTask($id);
            if (!self::canEdit($before, $userId, $role)) {
                throw new \DomainException('No tienes permiso para editar esta tarea.');
            }
        }

        $visibility = self::normalizeVisibility($in['visibility'] ?? 'team');
        if ($before && (int) $before['created_by'] !== $userId) {
            $visibility = $before['visibility'];
        }
        $assigned = isset($in['assigned_to']) && $in['assigned_to'] !== '' ? (int) $in['assigned_to'] : null;
        if ($assigned !== null && !$this->isAssignable($assigned)) {
            throw new \DomainException('La persona asignada no es válida.');
        }
        if ($visibility === 'personal' && $assigned !== null && $assigned !== $userId) {
            throw new \DomainException('Una tarea «Solo yo» no se puede asignar a otra persona: cámbiala a «Equipo» primero.');
        }

        $status = self::normalizeStatus($in['status'] ?? ($before['status'] ?? 'pendiente'));
        $data = [
            'title'         => $title,
            'description'   => trim((string) ($in['description'] ?? '')) ?: null,
            'status'        => $status,
            'priority'      => self::normalizePriority($in['priority'] ?? 'normal'),
            'due_date'      => self::normalizeDate($in['due_date'] ?? null),
            'assigned_to'   => $assigned,
            'visibility'    => $visibility,
        ];
        $data['completed_at'] = $status === 'hecho' ? ($before['completed_at'] ?? date('Y-m-d H:i:s')) : null;

        if ($before) {
            if ($assigned !== (isset($before['assigned_to']) ? (int) $before['assigned_to'] ?: null : null)) {
                $data['due_notified_at'] = null; // nueva persona → puede recibir su recordatorio
            }
            $model->update($id, $data);
            AuditService::record('org_task', $id, AuditService::UPDATE, $before, $data, null, $userId);
            $changedAssignee = $assigned !== null && (int) ($before['assigned_to'] ?? 0) !== $assigned;
        } else {
            $data['created_by'] = $userId;
            $data['position']   = $this->nextPosition($status);
            $id = (int) $model->insert($data);
            AuditService::record('org_task', $id, AuditService::CREATE, null, $data, null, $userId);
            $changedAssignee = $assigned !== null;
        }

        $task = $this->taskById($id);
        $notice = new OrgNoticeService($this->db);
        if ($changedAssignee) {
            $notice->taskAssigned($task, $userId);
        }
        if ($before) {
            $notice->taskStatusChanged($task, $before['status'], $userId);
        }
        return $task;
    }

    public function moveTask(int $userId, string $role, int $id, string $status, ?array $orderedIds = null): array
    {
        $t = $this->findTask($id);
        if (!self::canMove($t, $userId, $role)) {
            throw new \DomainException('No tienes permiso para mover esta tarea.');
        }
        $status = self::normalizeStatus($status);
        $model  = new OrgTaskModel();
        $model->update($id, [
            'status'       => $status,
            'completed_at' => $status === 'hecho' ? ($t['completed_at'] ?? date('Y-m-d H:i:s')) : null,
        ]);

        // Orden dentro de la columna: solo reordenamos ids que el usuario puede ver.
        if ($orderedIds) {
            $pos = 0;
            foreach ($orderedIds as $oid) {
                $oid = (int) $oid;
                $row = $model->find($oid);
                if ($row && $row['archived_at'] === null && self::canSee($row, $userId)) {
                    $model->update($oid, ['position' => $pos]);
                }
                $pos++;
            }
        }
        if ($t['status'] !== $status) {
            AuditService::record('org_task', $id, AuditService::UPDATE, ['status' => $t['status']], ['status' => $status], null, $userId);
        }
        $task = $this->taskById($id);
        (new OrgNoticeService($this->db))->taskStatusChanged($task, $t['status'], $userId);
        return $task;
    }

    public function archiveTask(int $userId, string $role, int $id): void
    {
        $t = $this->findTask($id);
        if (!self::canEdit($t, $userId, $role)) {
            throw new \DomainException('No tienes permiso para archivar esta tarea.');
        }
        (new OrgTaskModel())->update($id, ['archived_at' => date('Y-m-d H:i:s')]);
        AuditService::record('org_task', $id, AuditService::ARCHIVE, $t, null, null, $userId);
    }

    private function taskById(int $id): array
    {
        $r = $this->db->table('org_tasks t')
            ->select('t.*, a.name AS assigned_name, c.name AS creator_name')
            ->join('users a', 'a.id = t.assigned_to', 'left')
            ->join('users c', 'c.id = t.created_by', 'left')
            ->where('t.id', $id)->get()->getRowArray();
        return $this->shapeTask($r);
    }

    private function nextPosition(string $status): int
    {
        $row = $this->db->table('org_tasks')->selectMax('position', 'm')
            ->where('status', $status)->where('archived_at', null)->get()->getRowArray();
        return (int) ($row['m'] ?? 0) + 1;
    }

    private function isAssignable(int $userId): bool
    {
        return (bool) $this->db->table('users')->where('id', $userId)
            ->whereIn('role', self::ROLES)->where('status', 'active')->countAllResults();
    }

    // ── Eventos ─────────────────────────────────────────────────────────────

    /** Eventos visibles en [from, to] (creados por mí, del equipo, o donde estoy invitado). */
    public function listEvents(int $userId, string $from, string $to): array
    {
        $rows = $this->db->table('org_events e')
            ->select('e.*, c.name AS creator_name')
            ->join('users c', 'c.id = e.created_by', 'left')
            ->where('e.archived_at', null)
            ->where('e.start_at <=', $to . ' 23:59:59')
            ->groupStart()
                ->where('e.end_at >=', $from . ' 00:00:00')
                ->orGroupStart()->where('e.end_at', null)->where('e.start_at >=', $from . ' 00:00:00')->groupEnd()
            ->groupEnd()
            ->groupStart()
                ->where('e.visibility', 'team')
                ->orWhere('e.created_by', $userId)
            ->groupEnd()
            ->orderBy('e.start_at', 'ASC')
            ->get()->getResultArray();

        $ids = array_column($rows, 'id');
        $att = [];
        if ($ids) {
            foreach ($this->db->table('org_event_users eu')
                ->select('eu.event_id, u.id, u.name')
                ->join('users u', 'u.id = eu.user_id')
                ->whereIn('eu.event_id', $ids)->get()->getResultArray() as $a) {
                $att[(int) $a['event_id']][] = ['id' => (int) $a['id'], 'name' => $a['name']];
            }
        }

        return array_map(fn ($r) => [
            'id'            => (int) $r['id'],
            'title'         => $r['title'],
            'description'   => $r['description'] ?? '',
            'start_at'      => $r['start_at'],
            'end_at'        => $r['end_at'],
            'all_day'       => (bool) $r['all_day'],
            'location_text' => $r['location_text'] ?? '',
            'created_by'    => (int) $r['created_by'],
            'creator_name'  => $r['creator_name'] ?? null,
            'visibility'    => $r['visibility'],
            'attendees'     => $att[(int) $r['id']] ?? [],
        ], $rows);
    }

    public function saveEvent(int $userId, string $role, array $in, ?int $id = null): array
    {
        $title = trim((string) ($in['title'] ?? ''));
        if ($title === '') {
            throw new \DomainException('Escribe un título para el evento.');
        }
        $date = self::normalizeDate($in['date'] ?? null);
        if (!$date) {
            throw new \DomainException('Elige la fecha del evento.');
        }
        $allDay = !empty($in['all_day']);
        $start  = $allDay ? '00:00' : self::normalizeTime($in['start_time'] ?? null);
        if (!$start) {
            throw new \DomainException('Indica la hora de inicio (o marca «Todo el día»).');
        }
        $end = $allDay ? null : self::normalizeTime($in['end_time'] ?? null);
        if ($end && $end <= $start) {
            throw new \DomainException('La hora de fin debe ser posterior a la de inicio.');
        }

        $model  = new OrgEventModel();
        $before = null;
        if ($id) {
            $before = $this->findEvent($id);
            if (!self::canEdit($before, $userId, $role)) {
                throw new \DomainException('No tienes permiso para editar este evento.');
            }
        }

        $data = [
            'title'         => $title,
            'description'   => trim((string) ($in['description'] ?? '')) ?: null,
            'start_at'      => $date . ' ' . $start . ':00',
            'end_at'        => $end ? $date . ' ' . $end . ':00' : null,
            'all_day'       => $allDay ? 1 : 0,
            'location_text' => trim((string) ($in['location_text'] ?? '')) ?: null,
            'visibility'    => self::normalizeVisibility($in['visibility'] ?? 'team'),
        ];
        if ($before && (int) $before['created_by'] !== $userId) {
            $data['visibility'] = $before['visibility'];
        }

        $oldAttendees = $before ? $this->attendeeIds($id) : [];
        // Un evento «Solo yo» no tiene participantes: no lo verían.
        $newAttendees = $data['visibility'] === 'team' ? $this->validAttendees((array) ($in['attendees'] ?? [])) : [];

        $moved = $before && ($before['start_at'] !== $data['start_at'] || $before['end_at'] !== $data['end_at']
            || (string) $before['location_text'] !== (string) $data['location_text'] || (int) $before['all_day'] !== $data['all_day']);
        if ($moved) {
            $data['reminder_sent_at'] = null; // nueva fecha → vuelve a recordarse
        }

        if ($before) {
            $model->update($id, $data);
            AuditService::record('org_event', $id, AuditService::UPDATE, $before, $data, null, $userId);
        } else {
            $data['created_by'] = $userId;
            $id = (int) $model->insert($data);
            AuditService::record('org_event', $id, AuditService::CREATE, null, $data, null, $userId);
        }

        $this->syncAttendees($id, $newAttendees);

        $evt = $data + ['id' => $id, 'created_by' => $before['created_by'] ?? $userId];
        $notice = new OrgNoticeService($this->db);
        $invited = array_values(array_diff($newAttendees, $oldAttendees));
        if ($invited) {
            $notice->eventInvited($evt, $invited, $userId);
        }
        if ($moved) {
            $notice->eventChanged($evt, array_values(array_intersect($newAttendees, $oldAttendees)), $userId);
        }

        $res = $this->listEvents($userId, $date, $date);
        foreach ($res as $e) {
            if ($e['id'] === $id) {
                return $e;
            }
        }
        return ['id' => $id];
    }

    public function archiveEvent(int $userId, string $role, int $id): void
    {
        $e = $this->findEvent($id);
        if (!self::canEdit($e, $userId, $role)) {
            throw new \DomainException('No tienes permiso para archivar este evento.');
        }
        (new OrgEventModel())->update($id, ['archived_at' => date('Y-m-d H:i:s')]);
        AuditService::record('org_event', $id, AuditService::ARCHIVE, $e, null, null, $userId);
        (new OrgNoticeService($this->db))->eventCancelled($e, $this->attendeeIds($id), $userId);
    }

    /** Para abrir desde una notificación: la tarea, solo si la persona puede verla. */
    public function visibleTask(int $id, int $userId): ?array
    {
        $t = (new OrgTaskModel())->find($id);
        return ($t && $t['archived_at'] === null && self::canSee($t, $userId)) ? $t : null;
    }

    /** Ídem para un evento. */
    public function visibleEvent(int $id, int $userId): ?array
    {
        $e = (new OrgEventModel())->find($id);
        return ($e && $e['archived_at'] === null && self::canSee($e, $userId)) ? $e : null;
    }

    private function findEvent(int $id): array
    {
        $e = (new OrgEventModel())->find($id);
        if (!$e || $e['archived_at'] !== null) {
            throw new \DomainException('El evento no existe o ya está archivado.');
        }
        return $e;
    }

    /** @return int[] */
    private function attendeeIds(int $eventId): array
    {
        return array_map('intval', array_column(
            $this->db->table('org_event_users')->select('user_id')->where('event_id', $eventId)->get()->getResultArray(),
            'user_id'
        ));
    }

    /** @return int[] solo personas con acceso a Organización y activas */
    private function validAttendees(array $userIds): array
    {
        $valid = [];
        foreach (array_unique(array_map('intval', $userIds)) as $uid) {
            if ($uid > 0 && $this->isAssignable($uid)) {
                $valid[] = $uid;
            }
        }
        return $valid;
    }

    private function syncAttendees(int $eventId, array $valid): void
    {
        $this->db->table('org_event_users')->where('event_id', $eventId)->delete();
        foreach ($valid as $uid) {
            $this->db->table('org_event_users')->insert(['event_id' => $eventId, 'user_id' => $uid]);
        }
    }

    // ── Clases relacionadas con el usuario (solo lectura) ───────────────────

    /** Clases donde la persona es responsable (class_session_coaches) o entrenador de algún alumno. */
    public function relatedClasses(int $userId, string $from, string $to): array
    {
        $rows = $this->db->query(
            "SELECT DISTINCT cs.id, cs.title, cs.session_date, cs.start_time, cs.end_time, cs.status
             FROM class_sessions cs
             WHERE cs.session_date BETWEEN ? AND ?
               AND cs.status != 'cancelled'
               AND (
                    EXISTS (SELECT 1 FROM class_session_coaches csc WHERE csc.session_id = cs.id AND csc.user_id = ?)
                 OR EXISTS (SELECT 1 FROM class_session_players csp WHERE csp.session_id = cs.id AND csp.coach_id = ?)
               )
             ORDER BY cs.session_date ASC, cs.start_time ASC",
            [$from, $to, $userId, $userId]
        )->getResultArray();

        return array_map(fn ($r) => [
            'id'    => (int) $r['id'],
            'title' => $r['title'],
            'date'  => $r['session_date'],
            'start' => substr((string) $r['start_time'], 0, 5),
            'end'   => $r['end_time'] ? substr((string) $r['end_time'], 0, 5) : null,
            'status' => $r['status'],
        ], $rows);
    }
}
