<?php

namespace App\Controllers;

use App\Models\NotificationModel;
use App\Services\OrganizacionService;

/**
 * Organización interna (TICKET-014) — todos los roles salvo el alumno.
 *
 *  GET  /organizacion                       → pantalla (tareas · agenda)
 *  GET  /organizacion/data?from=&to=        → JSON: tareas, eventos y clases del rango
 *  POST /organizacion/tareas/guardar        → crear/editar tarea (id opcional)
 *  POST /organizacion/tareas/:id/mover      → cambiar estado (+ orden de la columna)
 *  POST /organizacion/tareas/:id/archivar
 *  POST /organizacion/eventos/guardar       → crear/editar evento
 *  POST /organizacion/eventos/:id/archivar
 *
 * La visibilidad (equipo / personal) se aplica en OrganizacionService.
 */
class OrganizacionController extends BaseController
{
    private function svc(): OrganizacionService
    {
        return new OrganizacionService();
    }

    public function index(): string
    {
        $open = $this->resolveOpen((int) $this->currentUserId());

        return view('organizacion/index', [
            'title'    => 'Organización — JP Preparation',
            'statuses' => OrganizacionService::STATUSES,
            'config'   => [
                'userId'     => (int) $this->currentUserId(),
                'role'       => $this->currentRole(),
                'statuses'   => OrganizacionService::STATUSES,
                'priorities' => OrganizacionService::PRIORITIES,
                'people'     => $this->svc()->people(),
                'base'       => rtrim(base_url('organizacion'), '/'),
                'csrfHeader' => 'X-CSRF-TOKEN',
                'csrfHash'   => csrf_hash(),
                'today'      => date('Y-m-d'),
                'open'       => $open,
            ],
        ]);
    }

    /** Elemento a abrir al entrar (enlace de un aviso). Al entrar se dan por leídos sus avisos. */
    private function resolveOpen(int $uid): ?array
    {
        $svc   = $this->svc();
        $notif = new NotificationModel();

        $taskId = (int) $this->request->getGet('tarea');
        if ($taskId > 0 && $svc->visibleTask($taskId, $uid)) {
            $notif->markReadBySource($uid, NotificationModel::SOURCE_ORG_TASK, $taskId);
            return ['type' => 'task', 'id' => $taskId];
        }
        $eventId = (int) $this->request->getGet('evento');
        $event   = $eventId > 0 ? $svc->visibleEvent($eventId, $uid) : null;
        if ($event) {
            $notif->markReadBySource($uid, NotificationModel::SOURCE_ORG_EVENT, $eventId);
            return ['type' => 'event', 'id' => $eventId, 'date' => substr($event['start_at'], 0, 10)];
        }
        return null;
    }

    public function data()
    {
        $from = OrganizacionService::normalizeDate($this->request->getGet('from')) ?? date('Y-m-d');
        $to   = OrganizacionService::normalizeDate($this->request->getGet('to')) ?? $from;
        if ($to < $from || strtotime($to) - strtotime($from) > 62 * 86400) {
            $to = date('Y-m-d', strtotime($from . ' +41 days'));
        }
        $uid = (int) $this->currentUserId();
        $svc = $this->svc();

        return $this->response->setJSON([
            'tasks'   => $svc->listTasks($uid),
            'events'  => $svc->listEvents($uid, $from, $to),
            'classes' => $svc->relatedClasses($uid, $from, $to),
        ]);
    }

    public function taskSave()
    {
        $id   = (int) $this->request->getPost('id') ?: null;
        $task = $this->svc()->saveTask((int) $this->currentUserId(), (string) $this->currentRole(), $this->request->getPost(), $id);
        return $this->response->setJSON(['ok' => true, 'task' => $task]);
    }

    public function taskMove(int $id)
    {
        $order = $this->request->getPost('order');
        $task  = $this->svc()->moveTask(
            (int) $this->currentUserId(),
            (string) $this->currentRole(),
            $id,
            (string) $this->request->getPost('status'),
            is_array($order) ? $order : null
        );
        return $this->response->setJSON(['ok' => true, 'task' => $task]);
    }

    public function taskArchive(int $id)
    {
        $this->svc()->archiveTask((int) $this->currentUserId(), (string) $this->currentRole(), $id);
        return $this->response->setJSON(['ok' => true]);
    }

    public function eventSave()
    {
        $id    = (int) $this->request->getPost('id') ?: null;
        $event = $this->svc()->saveEvent((int) $this->currentUserId(), (string) $this->currentRole(), $this->request->getPost(), $id);
        return $this->response->setJSON(['ok' => true, 'event' => $event]);
    }

    public function eventArchive(int $id)
    {
        $this->svc()->archiveEvent((int) $this->currentUserId(), (string) $this->currentRole(), $id);
        return $this->response->setJSON(['ok' => true]);
    }
}
