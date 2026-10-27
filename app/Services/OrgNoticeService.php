<?php

namespace App\Services;

use App\Models\NotificationModel;
use CodeIgniter\Database\BaseConnection;
use Config\Database;

/**
 * Avisos de Organización (TICKET-014, F4). Categoría «organizacion» de las preferencias.
 *
 *  - Tarea asignada → al asignado.
 *  - Tarea que otra persona pasa a «En revisión» / «Hecho» → a quien la creó.
 *  - Evento: invitación, cambio de fecha/hora/lugar y cancelación → a los participantes.
 *  - Recordatorios (sin cron: ScheduledNoticeService): evento a ≤25 h y tarea que vence hoy o mañana.
 *
 * El actor nunca recibe su propio aviso. Todo es a prueba de fallos: un aviso que falla
 * NUNCA debe romper el guardado de la tarea/evento.
 */
class OrgNoticeService
{
    public const REMINDER_HOURS = 25;
    private const BATCH = 50;

    private BaseConnection $db;

    public function __construct(?BaseConnection $db = null)
    {
        $this->db = $db ?? Database::connect();
    }

    // ── Textos puros (testeables) ───────────────────────────────────────

    public static function whenLabel(string $startAt, bool $allDay): string
    {
        $ts   = strtotime($startAt);
        $date = date('d/m', $ts);
        return $allDay ? $date . ' (todo el día)' : $date . ' a las ' . date('H:i', $ts);
    }

    public static function dueLabel(string $dueDate, string $today): string
    {
        if ($dueDate === $today) {
            return 'hoy';
        }
        return $dueDate === date('Y-m-d', strtotime($today . ' +1 day')) ? 'mañana' : date('d/m', strtotime($dueDate));
    }

    // ── Tareas ──────────────────────────────────────────────────────────

    /** Avisa al asignado si no es quien lo hace. Solo tareas del equipo (una personal no se asigna a otro). */
    public function taskAssigned(array $task, int $actorId): void
    {
        $this->safely('taskAssigned', function () use ($task, $actorId) {
            $to = (int) ($task['assigned_to'] ?? 0);
            if ($to <= 0 || $to === $actorId || ($task['visibility'] ?? 'team') !== 'team') {
                return;
            }
            $due  = !empty($task['due_date']) ? ' · vence ' . self::dueLabel($task['due_date'], date('Y-m-d')) : '';
            $this->notify($actorId, [$to], '📌 Te han asignado una tarea', '«' . $task['title'] . '»' . $due . '.', NotificationModel::SOURCE_ORG_TASK, (int) $task['id']);
        });
    }

    public function taskStatusChanged(array $task, string $oldStatus, int $actorId): void
    {
        $this->safely('taskStatusChanged', function () use ($task, $oldStatus, $actorId) {
            $new = $task['status'] ?? '';
            if ($new === $oldStatus || !in_array($new, ['revision', 'hecho'], true) || ($task['visibility'] ?? 'team') !== 'team') {
                return;
            }
            $creator = (int) ($task['created_by'] ?? 0);
            if ($creator <= 0 || $creator === $actorId) {
                return;
            }
            $label = OrganizacionService::STATUSES[$new] ?? $new;
            $this->notify($actorId, [$creator], ($new === 'hecho' ? '✅ Tarea hecha' : '👀 Tarea en revisión'), '«' . $task['title'] . '» ha pasado a «' . $label . '».', NotificationModel::SOURCE_ORG_TASK, (int) $task['id']);
        });
    }

    // ── Eventos ─────────────────────────────────────────────────────────

    public function eventInvited(array $event, array $userIds, int $actorId): void
    {
        $this->safely('eventInvited', function () use ($event, $userIds, $actorId) {
            if (($event['visibility'] ?? 'team') !== 'team') {
                return;
            }
            $body = '«' . $event['title'] . '» · ' . self::whenLabel($event['start_at'], (bool) $event['all_day'])
                . (!empty($event['location_text']) ? ' · ' . $event['location_text'] : '') . '.';
            $this->notify($actorId, $userIds, '📅 Te han invitado a un evento', $body, NotificationModel::SOURCE_ORG_EVENT, (int) $event['id']);
        });
    }

    public function eventChanged(array $event, array $userIds, int $actorId): void
    {
        $this->safely('eventChanged', function () use ($event, $userIds, $actorId) {
            if (($event['visibility'] ?? 'team') !== 'team') {
                return;
            }
            $body = '«' . $event['title'] . '» ahora es ' . self::whenLabel($event['start_at'], (bool) $event['all_day'])
                . (!empty($event['location_text']) ? ' · ' . $event['location_text'] : '') . '.';
            $this->notify($actorId, $userIds, '🔄 Evento modificado', $body, NotificationModel::SOURCE_ORG_EVENT, (int) $event['id']);
        });
    }

    public function eventCancelled(array $event, array $userIds, int $actorId): void
    {
        $this->safely('eventCancelled', function () use ($event, $userIds, $actorId) {
            if (($event['visibility'] ?? 'team') !== 'team') {
                return;
            }
            // El evento ya está archivado: el enlace llevaría a nada, así que va sin origen.
            $this->notify($actorId, $userIds, '🚫 Evento cancelado', '«' . $event['title'] . '» del ' . self::whenLabel($event['start_at'], (bool) $event['all_day']) . ' se ha cancelado.', null, 0);
        });
    }

    // ── Recordatorios ───────────────────────────────────────────────────

    /** @return int recordatorios enviados */
    public function sendReminders(?int $now = null): int
    {
        $now = $now ?? time();
        return $this->remindEvents($now) + $this->remindTasks($now);
    }

    private function remindEvents(int $now): int
    {
        try {
            if (!$this->db->tableExists('org_events') || !$this->db->fieldExists('reminder_sent_at', 'org_events')) {
                return 0;
            }
            $rows = $this->db->table('org_events')
                ->where('archived_at', null)->where('reminder_sent_at', null)
                ->where('start_at >', date('Y-m-d H:i:s', $now))
                ->where('start_at <=', date('Y-m-d H:i:s', $now + self::REMINDER_HOURS * 3600))
                ->orderBy('start_at')->limit(self::BATCH)->get()->getResultArray();

            $sent = 0;
            foreach ($rows as $e) {
                // Marcar ANTES de enviar: dos disparos a la vez no duplican el aviso.
                $this->db->table('org_events')->where('id', $e['id'])->where('reminder_sent_at', null)
                    ->update(['reminder_sent_at' => date('Y-m-d H:i:s', $now)]);
                if ($this->db->affectedRows() < 1) {
                    continue;
                }
                $ids = [(int) $e['created_by']];
                if ($e['visibility'] === 'team') {
                    foreach ($this->db->table('org_event_users')->select('user_id')->where('event_id', $e['id'])->get()->getResultArray() as $a) {
                        $ids[] = (int) $a['user_id'];
                    }
                }
                $body = '«' . $e['title'] . '» · ' . self::whenLabel($e['start_at'], (bool) $e['all_day'])
                    . (!empty($e['location_text']) ? ' · ' . $e['location_text'] : '') . '.';
                // El «remitente» solo firma el aviso: notify_sender hace que él también reciba el push.
                $this->notify((int) $e['created_by'], $ids, '⏰ Evento próximo', $body, NotificationModel::SOURCE_ORG_EVENT, (int) $e['id'], true);
                $sent++;
            }
            return $sent;
        } catch (\Throwable $e) {
            log_message('error', 'OrgNoticeService::remindEvents falló: ' . $e->getMessage());
            return 0;
        }
    }

    private function remindTasks(int $now): int
    {
        try {
            if (!$this->db->tableExists('org_tasks') || !$this->db->fieldExists('due_notified_at', 'org_tasks')) {
                return 0;
            }
            $today = date('Y-m-d', $now);
            $rows  = $this->db->table('org_tasks')
                ->where('archived_at', null)->where('due_notified_at', null)
                ->where('assigned_to IS NOT NULL')->where('status !=', 'hecho')
                ->where('due_date >=', $today)
                ->where('due_date <=', date('Y-m-d', strtotime($today . ' +1 day')))
                ->orderBy('due_date')->limit(self::BATCH)->get()->getResultArray();

            $sent = 0;
            foreach ($rows as $t) {
                $this->db->table('org_tasks')->where('id', $t['id'])->where('due_notified_at', null)
                    ->update(['due_notified_at' => date('Y-m-d H:i:s', $now)]);
                if ($this->db->affectedRows() < 1) {
                    continue;
                }
                $this->notify((int) $t['created_by'], [(int) $t['assigned_to']], '⏰ Tarea con fecha límite ' . self::dueLabel($t['due_date'], $today),
                    '«' . $t['title'] . '» vence ' . self::dueLabel($t['due_date'], $today) . '.', NotificationModel::SOURCE_ORG_TASK, (int) $t['id'], true);
                $sent++;
            }
            return $sent;
        } catch (\Throwable $e) {
            log_message('error', 'OrgNoticeService::remindTasks falló: ' . $e->getMessage());
            return 0;
        }
    }

    // ── Envío ───────────────────────────────────────────────────────────

    private function notify(int $senderId, array $recipientIds, string $title, string $body, ?string $sourceType, int $sourceId, bool $notifySender = false): void
    {
        $ids = array_values(array_unique(array_filter(
            array_map('intval', $recipientIds),
            static fn (int $i) => $i > 0 && ($notifySender || $i !== $senderId)
        )));
        if ($ids === [] || $senderId <= 0) {
            return;
        }
        $data = [
            'sender_id'     => $senderId,
            'type'          => 'individual',
            'title'         => $title,
            'body'          => $body,
            'created_at'    => date('Y-m-d H:i:s'),
            'category'      => 'organizacion',
            'notify_sender' => $notifySender,
        ];
        if ($sourceType !== null && $sourceId > 0) {
            $data['source_type'] = $sourceType;
            $data['source_id']   = $sourceId;
        }
        (new NotificationModel())->createWithRecipients($data, $ids);
    }

    private function safely(string $what, callable $fn): void
    {
        try {
            $fn();
        } catch (\Throwable $e) {
            log_message('error', 'OrgNoticeService::' . $what . ' falló: ' . $e->getMessage());
        }
    }
}
