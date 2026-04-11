<?php

namespace App\Controllers;

use App\Services\ClasesService;

class ClasesController extends BaseController
{
    protected ClasesService $clasesService;

    public function initController(
        \CodeIgniter\HTTP\RequestInterface  $request,
        \CodeIgniter\HTTP\ResponseInterface $response,
        \Psr\Log\LoggerInterface            $logger
    ) {
        parent::initController($request, $response, $logger);
        $this->clasesService = new ClasesService();
    }

    // ────────────────────────────────────────────────────────────────
    //  Calendario (index)
    // ────────────────────────────────────────────────────────────────

    public function index()
    {
        $userId    = $this->currentUserId();
        $role      = session('role');
        $canManage = in_array($role, ['superadmin', 'admin', 'staff', 'coach']);

        return view('clases/index', [
            'title'      => 'Clases — JP Preparation',
            'stats'      => $this->clasesService->getStats($userId, $role),
            'isAdmin'    => $this->isAdmin(),
            'canManage'  => $canManage,
            'currentUserId' => $userId,
            'role'       => $role,
        ]);
    }

    // ────────────────────────────────────────────────────────────────
    //  AJAX: datos del calendario
    // ────────────────────────────────────────────────────────────────

    public function calendario()
    {
        $year  = (int)($this->request->getGet('year')  ?: date('Y'));
        $month = (int)($this->request->getGet('month') ?: date('n'));

        $sessions = $this->clasesService->getSessionsForCalendar(
            $year, $month,
            $this->currentUserId(),
            session('role')
        );

        return $this->response->setJSON($sessions);
    }

    // ────────────────────────────────────────────────────────────────
    //  AJAX: opciones para quick-create
    // ────────────────────────────────────────────────────────────────

    public function opciones()
    {
        return $this->response->setJSON($this->clasesService->getAllOptions());
    }

    // ────────────────────────────────────────────────────────────────
    //  Crear
    // ────────────────────────────────────────────────────────────────

    public function create()
    {
        return view('clases/create', [
            'title'           => 'Nueva Clase — JP Preparation',
            'session'         => null,
            'isAdmin'         => $this->isAdmin(),
            'coachOptions'    => $this->clasesService->getCoachOptions(),
            'playerOptions'   => $this->clasesService->getPlayerOptions(),
            'locationOptions' => $this->clasesService->getLocationOptions(),
        ]);
    }

    public function store()
    {
        $result = $this->clasesService->createSession(
            $this->request->getPost(),
            $this->currentUserId()
        );

        if (!$result['success']) {
            session()->setFlashdata('error', $result['error'] ?? 'Error al crear la clase.');
            return redirect()->back()->withInput();
        }

        $count = $result['count'] ?? 1;
        $msg   = $count > 1
            ? "Clase recurrente creada: {$count} sesiones generadas."
            : 'Sesión creada correctamente.';

        session()->setFlashdata('success', $msg);
        return redirect()->to('/clases/' . $result['id']);
    }

    // ────────────────────────────────────────────────────────────────
    //  AJAX: quick-create desde Dashboard / Torneos
    // ────────────────────────────────────────────────────────────────

    public function quickCreate()
    {
        $result = $this->clasesService->quickCreate(
            $this->request->getPost(),
            $this->currentUserId()
        );

        return $this->response->setJSON($result);
    }

    // ────────────────────────────────────────────────────────────────
    //  Detalle
    // ────────────────────────────────────────────────────────────────

    public function show(int $id)
    {
        $session = $this->clasesService->getSession($id);
        if (!$session) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $userId    = $this->currentUserId();
        $role      = session('role');
        $isPlayer  = in_array($role, ['alumno', 'player']);
        $canManage = in_array($role, ['superadmin', 'admin', 'staff', 'coach']);

        // Jugadores solo ven sesiones en las que están asignados
        if ($isPlayer) {
            $assigned = false;
            foreach ($session['players'] as $p) {
                if ((int)$p['user_id'] === $userId) { $assigned = true; break; }
            }
            if (!$assigned) {
                throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
            }
        }

        // Entrada propia del jugador
        $myPlayer = null;
        foreach ($session['players'] as $p) {
            if ((int)$p['user_id'] === $userId) { $myPlayer = $p; break; }
        }

        return view('clases/show', [
            'title'           => esc($session['title']) . ' — JP Preparation',
            'session'         => $session,
            'isAdmin'         => $this->isAdmin(),
            'canManage'       => $canManage,
            'coachOptions'    => $canManage ? $this->clasesService->getCoachOptions()    : [],
            'playerOptions'   => $canManage ? $this->clasesService->getPlayerOptions()   : [],
            'locationOptions' => $canManage ? $this->clasesService->getLocationOptions() : [],
            'currentUserId'   => $userId,
            'myPlayer'        => $myPlayer,
        ]);
    }

    // ────────────────────────────────────────────────────────────────
    //  Editar
    // ────────────────────────────────────────────────────────────────

    public function edit(int $id)
    {
        $session = $this->clasesService->getSession($id);
        if (!$session) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return view('clases/create', [
            'title'           => 'Editar Clase — JP Preparation',
            'session'         => $session,
            'isAdmin'         => $this->isAdmin(),
            'coachOptions'    => $this->clasesService->getCoachOptions(),
            'playerOptions'   => $this->clasesService->getPlayerOptions(),
            'locationOptions' => $this->clasesService->getLocationOptions(),
        ]);
    }

    public function update(int $id)
    {
        $ok = $this->clasesService->updateSession($id, $this->request->getPost());

        if (!$ok) {
            session()->setFlashdata('error', 'Error al actualizar la sesión.');
            return redirect()->back()->withInput();
        }

        session()->setFlashdata('success', 'Sesión actualizada correctamente.');
        return redirect()->to('/clases/' . $id);
    }

    // ────────────────────────────────────────────────────────────────
    //  Eliminar / Completar / Cancelar
    // ────────────────────────────────────────────────────────────────

    public function destroy(int $id)
    {
        $this->clasesService->deleteSession($id);
        session()->setFlashdata('success', 'Sesión eliminada.');
        return redirect()->to('/clases');
    }

    public function complete(int $id)
    {
        $this->clasesService->markComplete($id);
        session()->setFlashdata('success', 'Sesión marcada como completada.');
        return redirect()->to('/clases/' . $id);
    }

    public function cancel(int $id)
    {
        $this->clasesService->cancelSession($id);
        session()->setFlashdata('success', 'Sesión cancelada.');
        return redirect()->to('/clases/' . $id);
    }

    // ────────────────────────────────────────────────────────────────
    //  Confirmar asistencia (jugador)
    // ────────────────────────────────────────────────────────────────

    public function respond(int $id)
    {
        $result = $this->clasesService->respondToSession(
            $this->currentUserId(),
            $id,
            $this->request->getPost('status')
        );

        if (!$result['success']) {
            session()->setFlashdata('error', $result['error'] ?? 'Error al registrar respuesta.');
        } else {
            $label = $this->request->getPost('status') === 'confirmed' ? 'confirmada' : 'declinada';
            session()->setFlashdata('success', "Asistencia {$label}.");
        }

        return redirect()->to('/clases/' . $id);
    }

    // ────────────────────────────────────────────────────────────────
    //  Observaciones
    // ────────────────────────────────────────────────────────────────

    public function saveObservations(int $id)
    {
        $this->clasesService->saveObservations($id, $this->request->getPost());
        session()->setFlashdata('success', 'Observaciones guardadas.');
        return redirect()->to('/clases/' . $id);
    }

    // ────────────────────────────────────────────────────────────────
    //  Asistencia (admin/coach)
    // ────────────────────────────────────────────────────────────────

    public function saveAttendance(int $id)
    {
        $this->clasesService->updateAttendance(
            $id,
            $this->request->getPost('attendance') ?? []
        );
        session()->setFlashdata('success', 'Asistencia registrada.');
        return redirect()->to('/clases/' . $id);
    }

    // ────────────────────────────────────────────────────────────────
    //  Entrenadores
    // ────────────────────────────────────────────────────────────────

    public function addCoach(int $id)
    {
        $result = $this->clasesService->addCoach($id, (int)$this->request->getPost('user_id'));

        session()->setFlashdata(
            $result['success'] ? 'success' : 'error',
            $result['success'] ? 'Entrenador añadido.' : ($result['error'] ?? 'Error.')
        );

        return redirect()->to('/clases/' . $id);
    }

    public function removeCoach(int $id, int $coachId)
    {
        $this->clasesService->removeCoach($id, $coachId);
        session()->setFlashdata('success', 'Entrenador eliminado.');
        return redirect()->to('/clases/' . $id);
    }

    // ────────────────────────────────────────────────────────────────
    //  Jugadores
    // ────────────────────────────────────────────────────────────────

    public function addPlayer(int $id)
    {
        $result = $this->clasesService->addPlayer($id, $this->request->getPost());

        session()->setFlashdata(
            $result['success'] ? 'success' : 'error',
            $result['success'] ? 'Jugador añadido.' : ($result['error'] ?? 'Error.')
        );

        return redirect()->to('/clases/' . $id);
    }

    public function removePlayer(int $id, int $playerId)
    {
        $this->clasesService->removePlayer($id, $playerId);
        session()->setFlashdata('success', 'Jugador eliminado.');
        return redirect()->to('/clases/' . $id);
    }
}
