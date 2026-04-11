<?php

namespace App\Controllers;

use App\Services\TorneosService;

class TorneosController extends BaseController
{
    protected TorneosService $torneosService;

    public function initController(
        \CodeIgniter\HTTP\RequestInterface  $request,
        \CodeIgniter\HTTP\ResponseInterface $response,
        \Psr\Log\LoggerInterface            $logger
    ) {
        parent::initController($request, $response, $logger);
        $this->torneosService = new TorneosService();
    }

    // ────────────────────────────────────────────────────────────────
    //  Listado
    // ────────────────────────────────────────────────────────────────

    public function index()
    {
        $filters = array_filter([
            'type'   => $this->request->getGet('type')   ?? '',
            'status' => $this->request->getGet('status') ?? '',
        ]);

        $events    = $this->torneosService->getEvents($filters);
        $myPending = $this->torneosService->getPendingNotificationsForUser($this->currentUserId());

        return view('torneos/index', [
            'title'     => 'Torneos y Campus — JP Preparation',
            'events'    => $events,
            'myPending' => $myPending,
            'filters'   => $this->request->getGet() ?? [],
            'isAdmin'   => $this->isAdmin(),
        ]);
    }

    // ────────────────────────────────────────────────────────────────
    //  Crear evento
    // ────────────────────────────────────────────────────────────────

    public function create()
    {
        $type = $this->request->getGet('type') ?? 'torneo';
        if (!in_array($type, ['torneo', 'campus'])) $type = 'torneo';

        return view('torneos/create', [
            'title'   => ($type === 'campus' ? 'Nuevo Campus' : 'Nuevo Torneo') . ' — JP Preparation',
            'type'    => $type,
            'event'   => null,
            'isAdmin' => $this->isAdmin(),
        ]);
    }

    public function store()
    {
        $result = $this->torneosService->createEvent(
            $this->request->getPost(),
            $this->currentUserId()
        );

        if (!$result['success']) {
            $errors = isset($result['errors']) ? implode(' ', $result['errors']) : 'Error al crear el evento.';
            session()->setFlashdata('error', $errors);
            return redirect()->back()->withInput();
        }

        session()->setFlashdata('success', 'Evento creado. Ahora puedes añadir equipos y miembros.');
        return redirect()->to('/torneos/' . $result['id']);
    }

    // ────────────────────────────────────────────────────────────────
    //  Detalle
    // ────────────────────────────────────────────────────────────────

    public function show(int $id)
    {
        $event = $this->torneosService->getEvent($id);
        if (!$event) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $userId       = $this->currentUserId();
        $myMembership = $this->torneosService->getUserMembership($userId, $id);

        // Marcar notificaciones como leídas al visitar la página
        $this->torneosService->markNotificationsRead($userId, $id);

        $selectableUsers      = $this->isAdmin() ? $this->torneosService->getSelectableUsers()         : [];
        $externalParticipants = $this->isAdmin() ? $this->torneosService->getExternalParticipants()    : [];

        return view('torneos/show', [
            'title'                => esc($event['name']) . ' — JP Preparation',
            'event'                => $event,
            'myMembership'         => $myMembership,
            'selectableUsers'      => $selectableUsers,
            'externalParticipants' => $externalParticipants,
            'isAdmin'              => $this->isAdmin(),
            'currentUserId'        => $userId,
        ]);
    }

    // ────────────────────────────────────────────────────────────────
    //  Editar
    // ────────────────────────────────────────────────────────────────

    public function edit(int $id)
    {
        $event = $this->torneosService->getEvent($id);
        if (!$event) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return view('torneos/create', [
            'title'   => 'Editar ' . ($event['type'] === 'campus' ? 'Campus' : 'Torneo') . ' — JP Preparation',
            'type'    => $event['type'],
            'event'   => $event,
            'isAdmin' => $this->isAdmin(),
        ]);
    }

    public function update(int $id)
    {
        $ok = $this->torneosService->updateEvent($id, $this->request->getPost());

        if (!$ok) {
            session()->setFlashdata('error', 'No se pudo actualizar el evento.');
            return redirect()->back()->withInput();
        }

        session()->setFlashdata('success', 'Evento actualizado correctamente.');
        return redirect()->to('/torneos/' . $id);
    }

    // ────────────────────────────────────────────────────────────────
    //  Cancelar / Eliminar
    // ────────────────────────────────────────────────────────────────

    public function cancel(int $id)
    {
        $this->torneosService->cancelEvent($id);
        session()->setFlashdata('success', 'Evento cancelado.');
        return redirect()->to('/torneos/' . $id);
    }

    public function destroy(int $id)
    {
        $this->torneosService->deleteEvent($id);
        session()->setFlashdata('success', 'Evento eliminado correctamente.');
        return redirect()->to('/torneos');
    }

    // ────────────────────────────────────────────────────────────────
    //  Equipos
    // ────────────────────────────────────────────────────────────────

    public function createTeam(int $eventId)
    {
        $result = $this->torneosService->createTeam($eventId, $this->request->getPost());

        session()->setFlashdata(
            $result['success'] ? 'success' : 'error',
            $result['success'] ? 'Equipo creado correctamente.' : (implode(' ', $result['errors'] ?? ['Error al crear el equipo.']))
        );

        return redirect()->to('/torneos/' . $eventId);
    }

    public function deleteTeam(int $eventId, int $teamId)
    {
        $this->torneosService->deleteTeam($teamId);
        session()->setFlashdata('success', 'Equipo eliminado.');
        return redirect()->to('/torneos/' . $eventId);
    }

    // ────────────────────────────────────────────────────────────────
    //  Miembros
    // ────────────────────────────────────────────────────────────────

    public function addMember(int $eventId, int $teamId)
    {
        $result = $this->torneosService->addMember($teamId, $this->request->getPost());

        session()->setFlashdata(
            $result['success'] ? 'success' : 'error',
            $result['success'] ? 'Miembro añadido al equipo.' : ($result['error'] ?? 'Error al añadir el miembro.')
        );

        return redirect()->to('/torneos/' . $eventId);
    }

    public function removeMember(int $eventId, int $memberId)
    {
        $this->torneosService->removeMember($memberId);
        session()->setFlashdata('success', 'Miembro eliminado del equipo.');
        return redirect()->to('/torneos/' . $eventId);
    }

    // ────────────────────────────────────────────────────────────────
    //  Notificaciones de convocatoria
    // ────────────────────────────────────────────────────────────────

    public function sendNotifications(int $id)
    {
        $result = $this->torneosService->sendNotifications($id);

        $msg = "Convocatoria enviada a {$result['sent']} miembro(s).";
        if ($result['skipped_external'] > 0) {
            $msg .= " {$result['skipped_external']} externo(s) omitido(s) (sin cuenta en la plataforma).";
        }

        session()->setFlashdata('success', $msg);
        return redirect()->to('/torneos/' . $id);
    }

    // ────────────────────────────────────────────────────────────────
    //  Confirmación de asistencia (cualquier usuario convocado)
    // ────────────────────────────────────────────────────────────────

    public function respond(int $id)
    {
        $status = $this->request->getPost('status');
        $notes  = $this->request->getPost('notes') ?? null;

        $result = $this->torneosService->respondToConvocation(
            $this->currentUserId(), $id, $status, $notes
        );

        if (!$result['success']) {
            session()->setFlashdata('error', $result['error'] ?? 'No se pudo registrar tu respuesta.');
        } else {
            $label = $status === 'confirmed' ? 'confirmada' : 'declinada';
            session()->setFlashdata('success', "Asistencia {$label} correctamente.");
        }

        return redirect()->to('/torneos/' . $id);
    }

    // ────────────────────────────────────────────────────────────────
    //  Resultados
    // ────────────────────────────────────────────────────────────────

    public function saveResult(int $id)
    {
        $this->torneosService->saveResult($id, $this->request->getPost());
        session()->setFlashdata('success', 'Resultado guardado correctamente.');
        return redirect()->to('/torneos/' . $id);
    }

    // ────────────────────────────────────────────────────────────────
    //  Participantes externos
    // ────────────────────────────────────────────────────────────────

    public function createExternal()
    {
        $result   = $this->torneosService->createExternalParticipant($this->request->getPost());
        $backEvent = (int)($this->request->getPost('back_event') ?? 0);

        if (!$result['success']) {
            $errors = isset($result['errors']) ? implode(' ', $result['errors']) : 'Error al crear participante.';
            session()->setFlashdata('error', $errors);
        } else {
            session()->setFlashdata('success', 'Participante externo creado.');
            session()->setFlashdata('new_external_id', $result['id']);
        }

        return $backEvent
            ? redirect()->to('/torneos/' . $backEvent)
            : redirect()->to('/torneos');
    }
}
