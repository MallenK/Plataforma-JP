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
        $isAdminRole = in_array($role, ['superadmin', 'admin']);

        return view('clases/index', [
            'title'         => 'Clases — JP Preparation',
            'stats'         => $this->clasesService->getStats($userId, $role),
            'isAdmin'       => $this->isAdmin(),
            'canManage'     => $canManage,
            'isAdminRole'   => $isAdminRole,
            'currentUserId' => $userId,
            'role'          => $role,
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
    //  AJAX: comprobar conflicto de instalación
    // ────────────────────────────────────────────────────────────────

    public function checkLocation(): \CodeIgniter\HTTP\ResponseInterface
    {
        $locationId = (int)$this->request->getGet('location_id');
        $date       = $this->request->getGet('date')  ?? '';
        $start      = $this->request->getGet('start') ?? '';
        $end        = $this->request->getGet('end')   ?? '';
        $excludeId  = (int)($this->request->getGet('exclude') ?? 0) ?: null;

        if (!$locationId || !$date || !$start || !$end) {
            return $this->response->setJSON(['conflicts' => []]);
        }

        $conflicts = $this->clasesService->checkLocationConflict($locationId, $date, $start, $end, $excludeId);
        return $this->response->setJSON(['conflicts' => $conflicts]);
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

        $userId      = $this->currentUserId();
        $role        = session('role');
        $isPlayer    = in_array($role, ['alumno', 'player']);
        $isCoach     = $role === 'coach';
        $canManage   = in_array($role, ['superadmin', 'admin', 'staff', 'coach']);
        $isAdminRole = in_array($role, ['superadmin', 'admin', 'staff']);

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

        // Coach solo ve sesiones donde está asignado como entrenador
        if ($isCoach) {
            $assigned = false;
            foreach ($session['coaches'] as $c) {
                if ((int)$c['user_id'] === $userId) { $assigned = true; break; }
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
            'isAdminRole'     => $isAdminRole,
            'coachOptions'    => $canManage ? $this->clasesService->getCoachOptions()    : [],
            'playerOptions'   => $isAdminRole ? $this->clasesService->getPlayerOptions() : [],
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
    //  Avisar ausencia (jugador)
    // ────────────────────────────────────────────────────────────────

    public function notifyAbsence(int $id)
    {
        $note   = trim($this->request->getPost('student_note') ?? '');
        $result = $this->clasesService->notifyAbsence(
            $this->currentUserId(),
            $id,
            $note
        );

        if (!$result['success']) {
            session()->setFlashdata('error', $result['error'] ?? 'Error al registrar el aviso.');
        } else {
            $msg = 'Tu aviso de ausencia ha sido registrado.';
            if ($result['lateNotice'] ?? false) {
                $msg .= ' Nota: el aviso se ha enviado después de las 10:00 del día de la clase.';
            }
            session()->setFlashdata('success', $msg);
        }

        return redirect()->to('/clases/' . $id);
    }

    // ────────────────────────────────────────────────────────────────
    //  Confirmar asistencia (jugador, mantenido por compatibilidad)
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
            $this->request->getPost('attendance') ?? [],
            $this->request->getPost('absence_reason') ?? [],
            $this->request->getPost('absence_notes') ?? []
        );
        session()->setFlashdata('success', 'Asistencia registrada.');
        return redirect()->to('/clases/' . $id);
    }

    // ────────────────────────────────────────────────────────────────
    //  Pasar Lista — Vista semanal (admin/superadmin)
    // ────────────────────────────────────────────────────────────────

    public function pasarListaIndex()
    {
        $weekOffset = (int)($this->request->getGet('semana') ?? 0);
        $search     = trim($this->request->getGet('buscar') ?? '');

        $data = $this->clasesService->getWeekSessions($weekOffset, $search);

        return view('clases/pasar_lista_semanal', [
            'title'          => 'Pasar Lista — JP Preparation',
            'isAdmin'        => $this->isAdmin(),
            'weekData'       => $data,
            'search'         => $search,
            'absenceReasons' => ['Enfermedad', 'Viaje', 'Personal', 'Sin aviso', 'Lesión', 'Otro'],
        ]);
    }

    public function guardarListaPasada(int $id)
    {
        $result = $this->clasesService->markListaPasada(
            $id,
            $this->currentUserId(),
            $this->request->getPost('attendance') ?? [],
            $this->request->getPost('absence_reason') ?? [],
            $this->request->getPost('absence_notes') ?? []
        );

        $semana = $this->request->getPost('semana') ?? 0;
        $buscar = $this->request->getPost('buscar') ?? '';
        $qs     = http_build_query(array_filter(['semana' => $semana, 'buscar' => $buscar]));

        session()->setFlashdata('success', 'Lista guardada correctamente.');
        return redirect()->to('/pasar-lista' . ($qs ? '?' . $qs : ''));
    }

    public function completarDiaRapido()
    {
        $date = $this->request->getPost('date');
        if (!$date || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return $this->response->setJSON(['success' => false, 'error' => 'Fecha inválida']);
        }

        $result = $this->clasesService->completarDiaRapido($date, $this->currentUserId());
        return $this->response->setJSON($result);
    }

    // ────────────────────────────────────────────────────────────────
    //  Pasar Lista — por sesión individual (admin/superadmin)
    // ────────────────────────────────────────────────────────────────

    public function pasarLista(int $id)
    {
        $session = $this->clasesService->getSession($id);
        if (!$session) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        // Añadir bono activo a cada jugador
        $bonoModel = new \App\Models\PlayerBonoModel();
        $db        = \Config\Database::connect();
        $today     = date('Y-m-d');

        foreach ($session['players'] as &$p) {
            $activeBono = $db->table('player_bonos pb')
                ->select('pb.id, pb.sessions_remaining, pb.expires_at, bt.name AS bono_name')
                ->join('bono_types bt', 'bt.id = pb.bono_type_id')
                ->where('pb.player_id', (int)$p['user_id'])
                ->where('pb.sessions_remaining >', 0)
                ->groupStart()
                    ->where('pb.expires_at IS NULL')
                    ->orWhere('pb.expires_at >=', $today)
                ->groupEnd()
                ->orderBy('pb.created_at', 'ASC')
                ->get()->getRowArray();

            $p['active_bono'] = $activeBono ?: null;
        }
        unset($p);

        return view('clases/pasar_lista', [
            'title'         => 'Pasar Lista — ' . esc($session['title']),
            'session'       => $session,
            'isAdmin'       => $this->isAdmin(),
            'absenceReasons' => ['Enfermedad', 'Viaje', 'Personal', 'Sin aviso', 'Lesión', 'Otro'],
        ]);
    }

    public function deductBono(int $id, int $playerId)
    {
        $result = $this->clasesService->deductBonoForPlayer($id, $playerId);
        return $this->response->setJSON($result);
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
