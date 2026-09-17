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
        $showScopeToggle = $isAdminRole && $this->clasesService->hasOwnAssignedSessions($userId);
        // Selector "Ver calendario de…" (TICKET-011): quién puede figurar
        // como responsable, para el desplegable. Solo hace falta calcularlo
        // para admin/superadmin.
        $responsableOptions = $isAdminRole ? $this->clasesService->getResponsableFilterOptions() : ['coaches' => [], 'staff' => []];

        return view('clases/index', [
            'title'               => 'Clases — JP Preparation',
            'stats'               => $this->clasesService->getStats($userId, $role),
            'isAdmin'             => $this->isAdmin(),
            'canManage'           => $canManage,
            'isAdminRole'         => $isAdminRole,
            'showScopeToggle'     => $showScopeToggle,
            'responsableOptions'  => $responsableOptions,
            'currentUserId'       => $userId,
            'role'                => $role,
        ]);
    }

    // ────────────────────────────────────────────────────────────────
    //  AJAX: datos del calendario
    // ────────────────────────────────────────────────────────────────

    public function calendario()
    {
        $year  = (int)($this->request->getGet('year')  ?: date('Y'));
        $month = (int)($this->request->getGet('month') ?: date('n'));
        $parsed = ClasesService::parseScopeParam($this->request->getGet('scope'));

        $sessions = $this->clasesService->getSessionsForCalendar(
            $year, $month,
            $this->currentUserId(),
            session('role'),
            $parsed['onlyMine'],
            $parsed['responsableFilter']
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

        // Continuación de clases recurrentes: solo admin/superadmin pueden
        // generar el mes siguiente. seriesRenewal viene null si la sesión no
        // pertenece a una clase recurrente.
        $canRenewSeries  = in_array($role, ['superadmin', 'admin']);
        $seriesRenewal   = null;
        $renewalDefaults = null;
        if ($canRenewSeries && !empty($session['class_id'])) {
            $seriesRenewal = $this->clasesService->getRecurringSeriesStatus((int) $session['class_id']);
            if ($seriesRenewal !== null && $seriesRenewal['is_last'] && !$seriesRenewal['already_renewed']) {
                $renewalDefaults = $this->clasesService->getRenewalDefaults((int) $session['class_id']);
            }
        }

        return view('clases/show', [
            'title'              => $session['title'] . ' — JP Preparation',
            'session'            => $session,
            'isAdmin'            => $this->isAdmin(),
            'canManage'          => $canManage,
            'isAdminRole'        => $isAdminRole,
            'coachOptions'       => $canManage ? $this->clasesService->getCoachOptions()    : [],
            'staffOptions'       => $canManage ? $this->clasesService->getStaffOptions()    : [],
            'playerOptions'      => $isAdminRole ? $this->clasesService->getPlayerOptions() : [],
            'locationOptions'    => $canManage ? $this->clasesService->getLocationOptions() : [],
            'currentUserId'      => $userId,
            'myPlayer'           => $myPlayer,
            // "Cambiar responsable" (TICKET-011): nº de sesiones futuras de
            // la misma serie, para ofrecer el alcance "esta y las siguientes".
            'seriesFutureCount'  => $canManage ? $this->clasesService->countFutureSeriesSessions($id) : 0,
            'canRenewSeries'     => $canRenewSeries,
            'seriesRenewal'      => $seriesRenewal,
            'renewalDefaults'    => $renewalDefaults,
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
            // Para ofrecer "solo esta sesión" / "esta y las siguientes" al
            // cambiar el responsable, igual que el modal de show.php (TICKET-011).
            'seriesFutureCount' => $this->clasesService->countFutureSeriesSessions($id),
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

        $data = $this->request->getPost();

        // Si la sesión pertenece a una clase recurrente y se pidió aplicar el
        // cambio de responsable a "esta y las siguientes", ese campo se
        // gestiona aparte con changeResponsible() (misma lógica que el modal
        // de la ficha, TICKET-011) — updateSession() no sabe de "series".
        // coach_ids_present distingue "el formulario incluye la tarjeta de
        // responsable" de "no venía ese campo": si se deja sin nadie
        // asignado, el navegador no manda coach_ids[] en absoluto.
        $coachScope = (string) ($data['coach_scope'] ?? 'single');
        if ($coachScope === 'series' && !empty($session['class_id']) && !empty($data['coach_ids_present'])) {
            $newCoachId = !empty($data['coach_ids'][0]) ? (int) $data['coach_ids'][0] : null;
            $resp = $this->clasesService->changeResponsible($id, $newCoachId, 'series', $this->currentUserId());
            if (!$resp['success']) {
                session()->setFlashdata('error', $resp['error'] ?? 'No se pudo cambiar el responsable de la serie.');
                return redirect()->back()->withInput();
            }
            unset($data['coach_ids']); // ya aplicado; que updateSession() no lo vuelva a tocar solo para esta sesión
        }

        $ok = $this->clasesService->updateSession($id, $data);

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

        $result = $this->clasesService->cerrarSesion($id, $this->currentUserId());
        session()->setFlashdata('success', 'Sesión cerrada y marcada como completada.');
        $this->flagRenewalOffer($result);
        return redirect()->to('/clases/' . $id . '/lista');
    }

    /**
     * Si cerrarSesion() indica que era la última sesión programada de una
     * clase recurrente sin continuar, deja el aviso en flash para que la
     * vista de "pasar lista" ofrezca generar el mes siguiente.
     */
    private function flagRenewalOffer(array $cerrarSesionResult): void
    {
        if (!empty($cerrarSesionResult['offer_renewal']) && in_array($this->currentRole(), ['superadmin', 'admin'])) {
            session()->setFlashdata('offer_series_renewal_class_id', $cerrarSesionResult['class_id']);
        }
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

    /**
     * Continúa una clase recurrente terminada generando el mes siguiente
     * (mismo patrón de días, editable en el modal antes de confirmar).
     * Solo admin/superadmin (filtro de ruta); $classId es classes.id, no
     * una sesión.
     */
    public function renewSeries(int $classId)
    {
        $result = $this->clasesService->renewRecurringClass($classId, $this->request->getPost(), $this->currentUserId());

        if (!$result['success']) {
            session()->setFlashdata('error', $result['error'] ?? 'No se pudo continuar la clase recurrente.');
            return redirect()->back()->withInput();
        }

        session()->setFlashdata('success', sprintf(
            'Clases recurrentes continuadas: se %s %d sesión%s más.',
            $result['count'] === 1 ? 'ha creado' : 'han creado',
            $result['count'],
            $result['count'] === 1 ? '' : 'es'
        ));
        return redirect()->to('/clases/' . $result['id']);
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
    //  Adjuntos de observaciones (fotos/vídeos/documentos)
    // ────────────────────────────────────────────────────────────────

    /** Extensiones permitidas en adjuntos de observaciones de clase. */
    private const ATTACHMENT_EXTENSIONS = [
        'jpg', 'jpeg', 'png', 'webp', 'gif',
        'pdf', 'doc', 'docx',
        'mp4', 'mov',
    ];

    private const ATTACHMENT_VIDEO_EXTENSIONS = ['mp4', 'mov'];

    /**
     * Sube un adjunto ligado a las observaciones de la sesión.
     * `player_uid` (opcional, POST): si viene, el adjunto queda ligado a la
     * observación individual de ese alumno en vez de a la sesión completa.
     */
    public function uploadAttachment(int $id)
    {
        $session = $this->clasesService->getSession($id);
        if (!$session) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        if (!$this->isAssignedOrAdmin($session)) {
            session()->setFlashdata('error', 'No tienes permiso para adjuntar archivos a esta sesión.');
            return redirect()->to('/clases/' . $id);
        }

        $file = $this->request->getFile('attachment');
        if (!$file || !$file->isValid()) {
            session()->setFlashdata('error', 'No se ha recibido ningún archivo válido.');
            return redirect()->to('/clases/' . $id);
        }

        $result = $this->handleAttachmentUpload($file);
        if (isset($result['error'])) {
            session()->setFlashdata('error', $result['error']);
            return redirect()->to('/clases/' . $id);
        }

        $playerUid = $this->request->getPost('player_uid');
        $playerUid = $playerUid !== null && $playerUid !== '' ? (int) $playerUid : null;

        $saved = $this->clasesService->addAttachment($id, $playerUid, $this->currentUserId(), $result);
        if (!$saved['success']) {
            session()->setFlashdata('error', $saved['error'] ?? 'No se pudo guardar el adjunto.');
            return redirect()->to('/clases/' . $id);
        }

        session()->setFlashdata('success', 'Adjunto guardado.');
        return redirect()->to('/clases/' . $id);
    }

    public function downloadAttachment(int $attachId)
    {
        $attach = $this->clasesService->getAttachment($attachId);
        if (!$attach) {
            return $this->response->setStatusCode(404);
        }

        $session = $this->clasesService->getSession($attach['session_id']);
        if (!$session || !$this->canAccessAttachment($session, $attach)) {
            return $this->response->setStatusCode(403);
        }

        helper('upload');
        $fullPath = upload_resolve_stored($attach['file_path']);
        if ($fullPath === null) {
            return $this->response->setStatusCode(404);
        }

        return $this->response->download($fullPath, null)->setFileName($attach['file_name']);
    }

    public function deleteAttachment(int $attachId)
    {
        $attach = $this->clasesService->getAttachment($attachId);
        if (!$attach) {
            return $this->response->setStatusCode(404);
        }

        $session = $this->clasesService->getSession($attach['session_id']);
        if (!$session || !$this->isAssignedOrAdmin($session)) {
            session()->setFlashdata('error', 'No tienes permiso para borrar este adjunto.');
            return redirect()->to('/clases/' . $attach['session_id']);
        }

        $this->clasesService->deleteAttachment($attachId);
        session()->setFlashdata('success', 'Adjunto eliminado.');
        return redirect()->to('/clases/' . $attach['session_id']);
    }

    /**
     * ¿Puede el usuario actual descargar este adjunto?
     * admin/superadmin y coach/staff asignados a la sesión: siempre.
     * Alumno: solo si está en la sesión y el adjunto es general o suyo.
     */
    private function canAccessAttachment(array $session, array $attach): bool
    {
        if ($this->isAssignedOrAdmin($session)) {
            return true;
        }

        $role = $this->currentRole();
        if (!in_array($role, ['alumno', 'player'], true)) {
            return false;
        }

        $userId = $this->currentUserId();
        $myPlayer = null;
        foreach ($session['players'] as $p) {
            if ((int) $p['user_id'] === $userId) { $myPlayer = $p; break; }
        }
        if (!$myPlayer) {
            return false;
        }

        // Adjunto general de la sesión: cualquier alumno asignado lo ve.
        if ($attach['player_id'] === null) {
            return true;
        }

        // Adjunto individual: solo el propio alumno.
        return (int) $attach['player_id'] === (int) $myPlayer['id'];
    }

    private function handleAttachmentUpload(\CodeIgniter\HTTP\Files\UploadedFile $file): array
    {
        helper('upload');

        $maxSizeDefault = 5 * 1024 * 1024;   // 5 MB (imágenes/documentos)
        $maxSizeVideo    = 80 * 1024 * 1024;  // 80 MB (vídeo)
        $allowed = [
            'image/jpeg', 'image/png', 'image/webp', 'image/gif',
            'application/pdf', 'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ];

        $clientExt = strtolower(pathinfo($file->getClientName(), PATHINFO_EXTENSION));
        $isVideo   = in_array($clientExt, self::ATTACHMENT_VIDEO_EXTENSIONS, true);
        $maxSize   = $isVideo ? $maxSizeVideo : $maxSizeDefault;

        if ($file->getSize() > $maxSize) {
            $limitMb = (int) ($maxSize / (1024 * 1024));
            return ['error' => "El archivo supera el límite de {$limitMb} MB."];
        }

        try {
            $mime = $file->getMimeType();
        } catch (\Throwable $e) {
            log_message('error', 'ClasesController::handleAttachmentUpload getMimeType failed: ' . $e->getMessage());
            return ['error' => 'No se pudo procesar el archivo. Inténtalo de nuevo.'];
        }

        // Igual que en Mensajes: para vídeo aceptamos cualquier subtipo
        // "video/*", el control de seguridad real es la lista blanca de
        // extensión + nombre aleatorio + fuera del webroot + sin ejecución.
        $mimeOk = in_array($mime, $allowed, true)
            || ($isVideo && is_string($mime) && str_starts_with($mime, 'video/'));

        if (!$mimeOk) {
            return ['error' => 'Tipo de archivo no permitido.'];
        }

        $ext = upload_allowed_extension($file->getClientExtension(), self::ATTACHMENT_EXTENSIONS);
        if ($ext === null) {
            return ['error' => 'Extensión de archivo no permitida.'];
        }

        // Fuera del webroot: solo se sirve por ClasesController::downloadAttachment().
        $uploadDir = upload_private_dir('clases');
        upload_harden_dir($uploadDir);

        $newName = bin2hex(random_bytes(16)) . '.' . $ext;
        try {
            $file->move($uploadDir, $newName);
        } catch (\Throwable $e) {
            log_message('error', 'ClasesController::handleAttachmentUpload move failed: ' . $e->getMessage());
            return ['error' => 'No se pudo guardar el archivo. Inténtalo de nuevo.'];
        }

        return [
            'path' => upload_stored_path('clases', $newName),
            'name' => $file->getClientName(),
            'size' => $file->getSize(),
            'mime' => $mime,
        ];
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
            $cierreResult = $this->clasesService->cerrarSesion($id, $this->currentUserId());
            $this->flagRenewalOffer($cierreResult);
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

    /**
     * Cambia el responsable de una sesión (y, si se pide, de las siguientes
     * de la misma clase recurrente). TICKET-011.
     */
    public function changeResponsible(int $id)
    {
        $session = $this->clasesService->getSession($id);
        if (!$session) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }
        if (!$this->isAssignedOrAdmin($session)) {
            session()->setFlashdata('error', 'No tienes permiso para cambiar el responsable de esta sesión.');
            return redirect()->to('/clases/' . $id);
        }

        $raw       = $this->request->getPost('user_id');
        $newUserId = ($raw === '' || $raw === null) ? null : (int) $raw;
        $scope     = $this->request->getPost('scope') === 'series' ? 'series' : 'single';

        $result = $this->clasesService->changeResponsible($id, $newUserId, $scope, (int) $this->currentUserId());

        if (!$result['success']) {
            session()->setFlashdata('error', $result['error'] ?? 'No se pudo cambiar el responsable.');
            return redirect()->to('/clases/' . $id);
        }

        $count = $result['sessions_changed'];
        $msg   = $count > 1
            ? "Responsable actualizado en {$count} sesiones."
            : 'Responsable actualizado.';
        session()->setFlashdata('success', $msg);
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
