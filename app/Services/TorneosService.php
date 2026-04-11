<?php

namespace App\Services;

use App\Models\EventModel;
use App\Models\EventTeamModel;
use App\Models\EventTeamMemberModel;
use App\Models\ExternalParticipantModel;
use App\Models\EventNotificationModel;
use App\Models\EventConfirmationModel;
use App\Models\EventResultModel;
use App\Models\UserModel;

class TorneosService
{
    protected EventModel              $eventModel;
    protected EventTeamModel          $teamModel;
    protected EventTeamMemberModel    $memberModel;
    protected ExternalParticipantModel $extModel;
    protected EventNotificationModel  $notifModel;
    protected EventConfirmationModel  $confModel;
    protected EventResultModel        $resultModel;
    protected UserModel               $userModel;

    public function __construct()
    {
        $this->eventModel  = new EventModel();
        $this->teamModel   = new EventTeamModel();
        $this->memberModel = new EventTeamMemberModel();
        $this->extModel    = new ExternalParticipantModel();
        $this->notifModel  = new EventNotificationModel();
        $this->confModel   = new EventConfirmationModel();
        $this->resultModel = new EventResultModel();
        $this->userModel   = new UserModel();
    }

    // ════════════════════════════════════════════════════════════════
    //  ESTADO — calculado dinámicamente por fecha
    // ════════════════════════════════════════════════════════════════

    public function computeStatus(array $event): string
    {
        if ($event['cancelled']) return 'cancelled';

        $today = date('Y-m-d');
        if ($event['start_date'] > $today) return 'planned';
        if ($event['end_date']   >= $today) return 'active';

        return 'finished';
    }

    // ════════════════════════════════════════════════════════════════
    //  EVENTOS — listado
    // ════════════════════════════════════════════════════════════════

    /**
     * @param array{type?: string, status?: string} $filters
     */
    public function getEvents(array $filters = []): array
    {
        $builder = $this->eventModel;
        $today   = date('Y-m-d');

        if (!empty($filters['type'])) {
            $builder = $builder->where('type', $filters['type']);
        }

        if (!empty($filters['status'])) {
            switch ($filters['status']) {
                case 'planned':
                    $builder = $builder->where('start_date >', $today)->where('cancelled', 0);
                    break;
                case 'active':
                    $builder = $builder->where('start_date <=', $today)->where('end_date >=', $today)->where('cancelled', 0);
                    break;
                case 'finished':
                    $builder = $builder->where('end_date <', $today)->where('cancelled', 0);
                    break;
                case 'cancelled':
                    $builder = $builder->where('cancelled', 1);
                    break;
            }
        }

        $events = $builder->orderBy('start_date', 'ASC')->findAll();

        foreach ($events as &$e) {
            $e['status']      = $this->computeStatus($e);
            $e['teams_count'] = $this->teamModel->where('event_id', $e['id'])->countAllResults();
            $e['members_count'] = \Config\Database::connect()
                ->table('event_team_members etm')
                ->join('event_teams et', 'et.id = etm.team_id')
                ->where('et.event_id', $e['id'])
                ->countAllResults();
        }

        return $events;
    }

    // ════════════════════════════════════════════════════════════════
    //  EVENTOS — detalle completo
    // ════════════════════════════════════════════════════════════════

    public function getEvent(int $id): ?array
    {
        $event = $this->eventModel->find($id);
        if (!$event) return null;

        $event['status'] = $this->computeStatus($event);
        $event['teams']  = $this->getTeamsWithMembers($id);
        $event['results']= $this->resultModel->where('event_id', $id)->findAll();
        $event['confirmation_stats'] = $this->getConfirmationStats($id);

        return $event;
    }

    // ════════════════════════════════════════════════════════════════
    //  EVENTOS — CRUD
    // ════════════════════════════════════════════════════════════════

    /**
     * @return array{success: bool, id?: int, errors?: array}
     */
    public function createEvent(array $data, int $userId): array
    {
        $id = $this->eventModel->insert(array_merge(
            $this->sanitizeEventData($data),
            ['created_by' => $userId, 'cancelled' => 0]
        ));

        if (!$id) {
            return ['success' => false, 'errors' => $this->eventModel->errors()];
        }

        return ['success' => true, 'id' => $id];
    }

    public function updateEvent(int $id, array $data): bool
    {
        return (bool) $this->eventModel->skipValidation(false)->update($id, $this->sanitizeEventData($data));
    }

    public function cancelEvent(int $id): bool
    {
        return (bool) $this->eventModel->skipValidation(true)->update($id, ['cancelled' => 1]);
    }

    public function deleteEvent(int $id): bool
    {
        // Cascade manual: eliminar todo lo relacionado
        $db = \Config\Database::connect();

        // 1. Obtener todos los member_ids del evento
        $memberIds = $db->table('event_team_members etm')
            ->select('etm.id')
            ->join('event_teams et', 'et.id = etm.team_id')
            ->where('et.event_id', $id)
            ->get()->getResultArray();

        $mIds = array_column($memberIds, 'id');

        if (!empty($mIds)) {
            $db->table('event_notifications')->whereIn('member_id', $mIds)->delete();
            $db->table('event_confirmations')->whereIn('member_id', $mIds)->delete();
            $db->table('event_team_members')->whereIn('id', $mIds)->delete();
        }

        $db->table('event_teams')->where('event_id', $id)->delete();
        $db->table('event_results')->where('event_id', $id)->delete();

        return (bool) $this->eventModel->delete($id);
    }

    private function sanitizeEventData(array $d): array
    {
        return [
            'type'                => $d['type']               ?? 'torneo',
            'name'                => $d['name']               ?? '',
            'description'         => $d['description']        ?? null,
            'category'            => $d['category']           ?? null,
            'start_date'          => $d['start_date']         ?? '',
            'end_date'            => $d['end_date']           ?? '',
            'location'            => $d['location']           ?? null,
            'concentration_time'  => !empty($d['concentration_time'])  ? $d['concentration_time']  : null,
            'concentration_place' => $d['concentration_place'] ?? null,
            'equipment_notes'     => $d['equipment_notes']    ?? null,
            'accommodation_info'  => $d['accommodation_info'] ?? null,
            'schedule_info'       => $d['schedule_info']      ?? null,
        ];
    }

    // ════════════════════════════════════════════════════════════════
    //  EQUIPOS
    // ════════════════════════════════════════════════════════════════

    /**
     * @return array{success: bool, id?: int, errors?: array}
     */
    public function createTeam(int $eventId, array $data): array
    {
        $id = $this->teamModel->insert([
            'event_id'   => $eventId,
            'name'       => $data['name'],
            'category'   => $data['category'] ?? null,
            'notes'      => $data['notes']    ?? null,
            'created_at' => date('Y-m-d H:i:s'),
        ]);

        if (!$id) {
            return ['success' => false, 'errors' => $this->teamModel->errors()];
        }

        return ['success' => true, 'id' => $id];
    }

    public function deleteTeam(int $teamId): bool
    {
        $mIds = array_column(
            $this->memberModel->where('team_id', $teamId)->findAll(),
            'id'
        );

        if (!empty($mIds)) {
            $db = \Config\Database::connect();
            $db->table('event_notifications')->whereIn('member_id', $mIds)->delete();
            $db->table('event_confirmations')->whereIn('member_id', $mIds)->delete();
            $db->table('event_team_members')->whereIn('id', $mIds)->delete();
        }

        $db = \Config\Database::connect();
        $db->table('event_results')->where('team_id', $teamId)->delete();

        return (bool) $this->teamModel->delete($teamId);
    }

    // ════════════════════════════════════════════════════════════════
    //  MIEMBROS DE EQUIPO
    // ════════════════════════════════════════════════════════════════

    /**
     * Añade un miembro al equipo. Puede ser usuario interno o externo.
     *
     * @return array{success: bool, id?: int, error?: string}
     */
    public function addMember(int $teamId, array $data): array
    {
        $memberType = $data['member_type'] ?? 'user';

        $insert = [
            'team_id'     => $teamId,
            'member_type' => $memberType,
            'user_id'     => null,
            'external_id' => null,
            'role'        => $data['role']        ?? 'player',
            'dorsal'      => !empty($data['dorsal'])  ? (int)$data['dorsal']  : null,
            'position'    => $data['position']    ?? null,
            'staff_role'  => $data['staff_role']  ?? null,
            'created_at'  => date('Y-m-d H:i:s'),
        ];

        if ($memberType === 'user') {
            $userId = (int)($data['user_id'] ?? 0);
            if (!$userId || !$this->userModel->find($userId)) {
                return ['success' => false, 'error' => 'Usuario no encontrado.'];
            }
            $insert['user_id'] = $userId;
        } else {
            $extId = (int)($data['external_id'] ?? 0);
            if (!$extId || !$this->extModel->find($extId)) {
                return ['success' => false, 'error' => 'Participante externo no encontrado.'];
            }
            $insert['external_id'] = $extId;
        }

        $id = $this->memberModel->insert($insert);
        if (!$id) {
            return ['success' => false, 'error' => 'No se pudo añadir el miembro.'];
        }

        // Crear registro de confirmación en 'pending' automáticamente
        $team    = $this->teamModel->find($teamId);
        $eventId = $team['event_id'] ?? 0;
        if ($eventId) {
            $this->confModel->upsert($eventId, $id, 'pending');
        }

        return ['success' => true, 'id' => $id];
    }

    public function removeMember(int $memberId): bool
    {
        \Config\Database::connect()->table('event_notifications')->where('member_id', $memberId)->delete();
        \Config\Database::connect()->table('event_confirmations')->where('member_id', $memberId)->delete();
        return (bool) $this->memberModel->delete($memberId);
    }

    // ════════════════════════════════════════════════════════════════
    //  MIEMBROS — lectura con datos enriquecidos
    // ════════════════════════════════════════════════════════════════

    public function getTeamsWithMembers(int $eventId): array
    {
        $teams = $this->teamModel->where('event_id', $eventId)->orderBy('name', 'ASC')->findAll();

        foreach ($teams as &$team) {
            $team['members'] = $this->getMembersWithDetails($team['id']);
        }

        return $teams;
    }

    public function getMembersWithDetails(int $teamId): array
    {
        $rows = \Config\Database::connect()
            ->query(
                'SELECT etm.*,
                        COALESCE(u.name,  ep.name)  AS display_name,
                        COALESCE(u.email, ep.email) AS display_email,
                        ec.status       AS conf_status,
                        ec.notes        AS conf_notes,
                        en.sent_at      AS notified_at,
                        en.read_at      AS notif_read_at
                 FROM event_team_members etm
                 LEFT JOIN users                  u  ON etm.member_type = \'user\'     AND u.id  = etm.user_id
                 LEFT JOIN external_participants  ep ON etm.member_type = \'external\' AND ep.id = etm.external_id
                 LEFT JOIN event_confirmations    ec ON ec.member_id = etm.id
                 LEFT JOIN event_notifications    en ON en.member_id  = etm.id
                 WHERE etm.team_id = ?
                 ORDER BY etm.role ASC, etm.dorsal ASC',
                [$teamId]
            )->getResultArray();

        return $rows;
    }

    // ════════════════════════════════════════════════════════════════
    //  NOTIFICACIONES INTERNAS
    // ════════════════════════════════════════════════════════════════

    /**
     * Envía (crea) notificaciones internas para todos los miembros con user_id.
     * Los externos se omiten (no tienen cuenta).
     *
     * @return array{sent: int, skipped_external: int}
     */
    public function sendNotifications(int $eventId): array
    {
        $db  = \Config\Database::connect();
        $now = date('Y-m-d H:i:s');

        // Todos los miembros internos (user_id) del evento
        $members = $db->query(
            'SELECT etm.id AS member_id
             FROM event_team_members etm
             JOIN event_teams et ON et.id = etm.team_id
             WHERE et.event_id = ? AND etm.member_type = \'user\'',
            [$eventId]
        )->getResultArray();

        // Externos (no reciben notificación interna)
        $extCount = $db->query(
            'SELECT COUNT(*) AS cnt
             FROM event_team_members etm
             JOIN event_teams et ON et.id = etm.team_id
             WHERE et.event_id = ? AND etm.member_type = \'external\'',
            [$eventId]
        )->getRow()->cnt ?? 0;

        $sent = 0;
        foreach ($members as $m) {
            // INSERT IGNORE via unique key — evita duplicados
            $exists = $this->notifModel
                ->where('event_id', $eventId)
                ->where('member_id', $m['member_id'])
                ->first();

            if (!$exists) {
                $this->notifModel->insert([
                    'event_id'  => $eventId,
                    'member_id' => $m['member_id'],
                    'sent_at'   => $now,
                ]);
                $sent++;
            }
        }

        return ['sent' => $sent, 'skipped_external' => (int)$extCount];
    }

    /**
     * Convocatorias pendientes de leer para un usuario.
     */
    public function getPendingNotificationsForUser(int $userId): array
    {
        return \Config\Database::connect()
            ->query(
                'SELECT en.*, e.name AS event_name, e.type AS event_type,
                        e.start_date, et.name AS team_name
                 FROM event_notifications en
                 JOIN event_team_members etm ON etm.id = en.member_id
                 JOIN event_teams et         ON et.id  = etm.team_id
                 JOIN events e               ON e.id   = en.event_id
                 WHERE etm.user_id = ? AND etm.member_type = \'user\'
                   AND en.read_at IS NULL
                 ORDER BY en.sent_at DESC',
                [$userId]
            )->getResultArray();
    }

    /**
     * Marca como leídas las notificaciones de un usuario para un evento.
     */
    public function markNotificationsRead(int $userId, int $eventId): void
    {
        $this->notifModel->markReadForUserEvent($userId, $eventId);
    }

    // ════════════════════════════════════════════════════════════════
    //  CONFIRMACIONES
    // ════════════════════════════════════════════════════════════════

    /**
     * El usuario (por su user_id) confirma o rechaza su asistencia.
     *
     * @return array{success: bool, error?: string}
     */
    public function respondToConvocation(int $userId, int $eventId, string $status, ?string $notes): array
    {
        $allowed = ['confirmed', 'declined'];
        if (!in_array($status, $allowed)) {
            return ['success' => false, 'error' => 'Estado no válido.'];
        }

        // Buscar el member_id del usuario en este evento
        $member = \Config\Database::connect()
            ->query(
                'SELECT etm.id
                 FROM event_team_members etm
                 JOIN event_teams et ON et.id = etm.team_id
                 WHERE et.event_id = ? AND etm.user_id = ? AND etm.member_type = \'user\'
                 LIMIT 1',
                [$eventId, $userId]
            )->getRow();

        if (!$member) {
            return ['success' => false, 'error' => 'No estás convocado en este evento.'];
        }

        $this->confModel->upsert($eventId, $member->id, $status, $notes);

        return ['success' => true];
    }

    /**
     * Estadísticas de confirmación para un evento.
     */
    public function getConfirmationStats(int $eventId): array
    {
        $rows = \Config\Database::connect()
            ->query(
                'SELECT ec.status, COUNT(*) AS cnt
                 FROM event_confirmations ec
                 JOIN event_team_members etm ON etm.id = ec.member_id
                 JOIN event_teams et         ON et.id  = etm.team_id
                 WHERE et.event_id = ?
                 GROUP BY ec.status',
                [$eventId]
            )->getResultArray();

        $stats = ['pending' => 0, 'confirmed' => 0, 'declined' => 0];
        foreach ($rows as $r) {
            $stats[$r['status']] = (int)$r['cnt'];
        }

        return $stats;
    }

    /**
     * Comprueba si un usuario es miembro de un evento y devuelve su confirmación.
     */
    public function getUserMembership(int $userId, int $eventId): ?array
    {
        return \Config\Database::connect()
            ->query(
                'SELECT etm.*, et.name AS team_name, ec.status AS conf_status, ec.notes AS conf_notes
                 FROM event_team_members etm
                 JOIN event_teams et ON et.id = etm.team_id
                 LEFT JOIN event_confirmations ec ON ec.member_id = etm.id
                 WHERE et.event_id = ? AND etm.user_id = ? AND etm.member_type = \'user\'
                 LIMIT 1',
                [$eventId, $userId]
            )->getRowArray();
    }

    // ════════════════════════════════════════════════════════════════
    //  RESULTADOS
    // ════════════════════════════════════════════════════════════════

    public function saveResult(int $eventId, array $data): bool
    {
        $teamId = !empty($data['team_id']) ? (int)$data['team_id'] : null;

        $existing = $this->resultModel
            ->where('event_id', $eventId)
            ->where($teamId ? 'team_id' : 'team_id IS NULL', $teamId ?? null)
            ->first();

        if ($existing) {
            return (bool) $this->resultModel->update($existing['id'], [
                'result_text' => $data['result_text'] ?? null,
                'notes'       => $data['notes']       ?? null,
                'team_id'     => $teamId,
            ]);
        }

        return (bool) $this->resultModel->insert([
            'event_id'    => $eventId,
            'team_id'     => $teamId,
            'result_text' => $data['result_text'] ?? null,
            'notes'       => $data['notes']       ?? null,
        ]);
    }

    // ════════════════════════════════════════════════════════════════
    //  PARTICIPANTES EXTERNOS
    // ════════════════════════════════════════════════════════════════

    public function getExternalParticipants(?string $type = null): array
    {
        $q = $this->extModel;
        if ($type) $q = $q->where('type', $type);
        return $q->orderBy('name', 'ASC')->findAll();
    }

    /**
     * @return array{success: bool, id?: int, errors?: array}
     */
    public function createExternalParticipant(array $data): array
    {
        $id = $this->extModel->insert([
            'name'       => $data['name'],
            'type'       => $data['type']       ?? 'player',
            'position'   => $data['position']   ?? null,
            'birth_date' => !empty($data['birth_date']) ? $data['birth_date'] : null,
            'phone'      => $data['phone']       ?? null,
            'email'      => $data['email']       ?? null,
            'notes'      => $data['notes']       ?? null,
        ]);

        if (!$id) {
            return ['success' => false, 'errors' => $this->extModel->errors()];
        }

        return ['success' => true, 'id' => $id];
    }

    // ════════════════════════════════════════════════════════════════
    //  HELPERS para formularios
    // ════════════════════════════════════════════════════════════════

    /**
     * Usuarios de la plataforma que pueden ser añadidos a un evento
     * (todos los roles excepto player — los players también se incluyen).
     */
    public function getSelectableUsers(): array
    {
        return $this->userModel
            ->where('status', 'active')
            ->orderBy('role', 'ASC')
            ->orderBy('name', 'ASC')
            ->findAll();
    }
}
