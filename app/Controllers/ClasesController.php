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
    //  AJAX: buscador (clase / entrenador / jugador)
    // ────────────────────────────────────────────────────────────────

    public function buscar(): \CodeIgniter\HTTP\ResponseInterface
    {
        $q = (string) ($this->request->getGet('q') ?? '');

        return $this->response->setJSON(
            $this->clasesService->search($q, (int) $this->currentUserId(), (string) session('role'))
        );
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
            'staffOptions'    => $this->clasesService->getStaffOptions(),
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

        // Coach y staff solo ven sesiones donde están asignados como responsable
        if ($isCoach || $role === 'staff') {
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
            'title'           => $session['title'] . ' — JP Preparation',
            'session'         => $session,
            'isAdmin'         => $this->isAdmin(),
            'canManage'       => $canManage,
            'isAdminRole'     => $isAdminRole,
            'coachOptions'    => $canManage ? $this->clasesService->getCoachOptions()    : [],
            'staffOptions'    => $canManage ? $this->clasesService->getStaffOptions()    : [],
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

        if (!$this->isAssignedOrAdmin($session)) {
            session()->setFlashdata('error', 'No tienes permiso para editar esta sesión.');
            return redirect()->to('/clases');
        }

        return view('clases/create', [
            'title'           => 'Editar Clase — JP Preparation',
            'session'         => $session,
            'isAdmin'         => $this->isAdmin(),
            'coachOptions'    => $this->clasesService->getCoachOptions(),
            'staffOptions'    => $this->clasesService->getStaffOptions(),
            'playerOptions'   => $this->clasesService->getPlayerOptions(),
            'locationOptions' => $this->clasesService->getLocationOptions(),
        ]);
    }

    public function update(int $id)
    {
        $session = $this->clasesService->getSession($id);
        if (!$session) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        if (!$this->isAssignedOrAdmin($session)) {
            session()->setFlashdata('error', 'No tienes permiso para editar esta sesión.');
            return redirect()->to('/clases');
        }

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
        $session = $this->clasesService->getSession($id);
        if (!$session) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        if (!$this->isAssignedOrAdmin($session)) {
            session()->setFlashdata('error', 'No tienes permiso para eliminar esta sesión.');
            return redirect()->to('/clases');
        }

        $refunded = $this->clasesService->countDeductedBonos($id);
        $this->clasesService->deleteSession($id);

        $msg = 'Sesión eliminada.';
        if ($refunded > 0) {
            $msg .= " Se {$this->plural($refunded, 'ha devuelto', 'han devuelto')} {$refunded} "
                  . $this->plural($refunded, 'bono ya descontado', 'bonos ya descontados') . '.';
        }
        session()->setFlashdata('success', $msg);
        return redirect()->to('/clases');
    }

    /** Ayudante mínimo de plural para los mensajes flash. */
    protected function plural(int $n, string $one, string $many): string
    {
        return $n === 1 ? $one : $many;
    }

    public function cerrarSesion(int $id)
    {
        $session = $this->clasesService->getSession($id);
        if (!$session) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        if (!$this->isAssignedOrAdmin($session)) {
            session()->setFlashdata('error', 'No tienes permiso para cerrar esta sesión.');
            return redirect()->to('/clases');
        }

        $this->clasesService->cerrarSesion($id, $this->currentUserId());
        session()->setFlashdata('success', 'Sesión cerrada y marcada como completada.');
        return redirect()->to('/clases/' . $id . '/lista');
    }

    /**
     * Reabre una sesión cerrada (o reactiva una cancelada): vuelve a 'scheduled'.
     * Cerrar/cancelar deja así de ser irreversible.
     */
    public function reabrirSesion(int $id)
    {
        $session = $this->clasesService->getSession($id);
        if (!$session) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        if (!$this->isAssignedOrAdmin($session)) {
            session()->setFlashdata('error', 'No tienes permiso para reabrir esta sesión.');
            return redirect()->to('/clases');
        }

        $result = $this->clasesService->reabrirSesion($id);

        if (!$result['success']) {
            session()->setFlashdata('error', $result['error'] ?? 'No se pudo reabrir la sesión.');
            return redirect()->to('/clases/' . $id);
        }

        if (($result['from'] ?? '') === 'cancelled') {
            session()->setFlashdata('success', 'Sesión reactivada: vuelve a estar programada.');
            return redirect()->to('/clases/' . $id);
        }

        session()->setFlashdata('success', 'Sesión reabierta: ya puedes editar la asistencia de nuevo.');
        return redirect()->to('/clases/' . $id . '/lista');
    }

    public function cancel(int $id)
    {
        $session = $this->clasesService->getSession($id);
        if (!$session) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        if (!$this->isAssignedOrAdmin($session)) {
            session()->setFlashdata('error', 'No tienes permiso para cancelar esta sesión.');
            return redirect()->to('/clases');
        }

        $refunded = $this->clasesService->countDeductedBonos($id);
        $this->clasesService->cancelSession($id);

        $msg = 'Sesión cancelada.';
        if ($refunded > 0) {
            $msg .= " Se {$this->plural($refunded, 'ha devuelto', 'han devuelto')} {$refunded} "
                  . $this->plural($refunded, 'bono ya descontado', 'bonos ya descontados')
                  . ' (la clase no se imparte).';
        }
        session()->setFlashdata('success', $msg);
        return redirect()->to('/clases/' . $id);
    }

    // ────────────────────────────────────────────────────────────────
    //  Helpers de autorización
    // ────────────────────────────────────────────────────────────────

    /**
     * Devuelve true si el usuario puede gestionar la sesión:
     * - superadmin/admin siempre pueden
     * - coach/staff solo si están en la lista de coaches asignados
     */
    protected function isAssignedOrAdmin(array $session): bool
    {
        $role = $this->currentRole();

        if (in_array($role, ['superadmin', 'admin'])) {
            return true;
        }

        if ($role === 'coach' || $role === 'staff') {
            $userId = $this->currentUserId();
            foreach ($session['coaches'] as $c) {
                if ((int)$c['user_id'] === $userId) {
                    return true;
                }
            }
            return false;
        }

        return false;
    }

    // ────────────────────────────────────────────────────────────────
    //  Avisar ausencia (alumno)
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
    //  Observaciones
    // ────────────────────────────────────────────────────────────────

    public function saveObservations(int $id)
    {
        $this->clasesService->saveObservations($id, $this->request->getPost());
        session()->setFlashdata('success', 'Observaciones guardadas.');
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
            'title'        => 'Pasar lista',
            'pageTitle'    => 'Pasar lista',
            'pageSubtitle' => 'Asistencia por sesión de la semana',
            'isAdmin'      => $this->isAdmin(),
            'weekData'     => $data,
            'search'       => $search,
        ]);
    }

    // ────────────────────────────────────────────────────────────────
    //  Pasar Lista — por sesión individual (único punto de marcado)
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
            'title'          => 'Pasar lista — ' . $session['title'],
            'pageTitle'      => 'Pasar lista',
            'pageSubtitle'   => $session['title'],
            'session'        => $session,
            'isAdmin'        => $this->isAdmin(),
            'absenceReasons' => ['Enfermedad', 'Viaje', 'Personal', 'Sin aviso', 'Lesión', 'Otro'],
        ]);
    }

    public function guardarLista(int $id)
    {
        $session = $this->clasesService->getSession($id);
        if (!$session) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        if (!$this->isAssignedOrAdmin($session)) {
            session()->setFlashdata('error', 'No tienes permiso para gestionar esta sesión.');
            return redirect()->to('/clases');
        }

        // Solo se pasa lista sobre sesiones programadas. Una cerrada hay que
        // reabrirla primero (invariante "cerrada = bloqueada").
        if (($session['status'] ?? '') !== 'scheduled') {
            session()->setFlashdata('error', ($session['status'] ?? '') === 'completed'
                ? 'La sesión está cerrada. Pulsa "Reabrir sesión" para editar la asistencia.'
                : 'Esta sesión no admite cambios de asistencia en su estado actual.');
            return redirect()->to('/clases/' . $id . '/lista');
        }

        $res = $this->clasesService->guardarLista(
            $id,
            $this->currentUserId(),
            $this->request->getPost('attendance') ?? [],
            $this->request->getPost('absence_reason') ?? [],
            $this->request->getPost('absence_notes') ?? []
        );

        $devueltos = (int) ($res['bonos_devueltos'] ?? 0);
        $cierre    = '';

        // "Guardar y cerrar": un solo gesto para no dejar la sesión a medias.
        $cerrar = (string) $this->request->getPost('cerrar') === '1';
        if ($cerrar && ($session['status'] ?? '') === 'scheduled') {
            $this->clasesService->cerrarSesion($id, $this->currentUserId());
            $cierre = ' y sesión cerrada (puedes reabrirla si necesitas corregir algo)';
        }

        $msg = 'Asistencia guardada' . $cierre . '.';
        if ($devueltos > 0) {
            $msg .= " Se {$this->plural($devueltos, 'ha devuelto', 'han devuelto')} {$devueltos} "
                  . $this->plural($devueltos, 'bono', 'bonos')
                  . " al cambiar la asistencia de {$this->plural($devueltos, 'un alumno', 'varios alumnos')}.";
        }
        session()->setFlashdata('success', $msg);

        return redirect()->to('/clases/' . $id . '/lista');
    }

    public function deductBono(int $id, int $playerId)
    {
        if ($resp = $this->guardBonoAction($id)) {
            return $resp;
        }
        // La asistencia elegida en el selector (aún sin guardar) viaja en el
        // cuerpo: descontar bono también la registra.
        $want = $this->request->getJsonVar('attendance')
            ?? $this->request->getPost('attendance');

        return $this->response->setJSON(
            $this->clasesService->deductBonoForPlayer($id, $playerId, $want ? (string) $want : null)
        );
    }

    /** Devolver (revertir) el bono descontado a un alumno de una sesión. */
    public function refundBono(int $id, int $playerId)
    {
        if ($resp = $this->guardBonoAction($id)) {
            return $resp;
        }
        return $this->response->setJSON(
            $this->clasesService->refundBonoForPlayer($id, $playerId)
        );
    }

    /**
     * Guarda común de las acciones AJAX de bono: la sesión existe y el usuario
     * puede gestionarla. Devuelve una respuesta JSON de error o null si todo OK.
     */
    private function guardBonoAction(int $sessionId)
    {
        $session = $this->clasesService->getSession($sessionId);
        if (!$session) {
            return $this->response->setStatusCode(404)
                ->setJSON(['success' => false, 'error' => 'Sesión no encontrada.']);
        }
        if (!$this->isAssignedOrAdmin($session)) {
            return $this->response->setStatusCode(403)
                ->setJSON(['success' => false, 'error' => 'No tienes permiso para gestionar esta sesión.']);
        }
        if (($session['status'] ?? '') !== 'scheduled') {
            return $this->response->setStatusCode(409)
                ->setJSON(['success' => false, 'error' => 'La sesión no está programada. Reábrela para gestionar los bonos.']);
        }
        return null;
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
