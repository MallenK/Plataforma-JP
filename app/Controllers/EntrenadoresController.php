<?php

namespace App\Controllers;

use App\Services\CoachService;

class EntrenadoresController extends BaseController
{
    protected CoachService $coachService;

    public function initController(
        \CodeIgniter\HTTP\RequestInterface $request,
        \CodeIgniter\HTTP\ResponseInterface $response,
        \Psr\Log\LoggerInterface $logger
    ) {
        parent::initController($request, $response, $logger);
        $this->coachService = new CoachService();
    }

    // ----------------------------------------------------------------
    // Listado
    // ----------------------------------------------------------------

    public function index()
    {
        $coaches = $this->coachService->getCoaches();

        return view('entrenadores/index', [
            'title'   => 'Entrenadores — JP Preparation',
            'coaches' => $coaches,
        ]);
    }

    // ----------------------------------------------------------------
    // Rankings y estadísticas (solo lectura, sin dinero)
    // ----------------------------------------------------------------

    private const RANKING_TABS = ['resumen', 'actividad', 'asistencia', 'alumnos', 'cierre', 'horarios'];

    public function rankings()
    {
        $svc    = new \App\Services\CoachRankingService();
        $period = \App\Services\CoachRankingService::period($this->request->getGet() ?? [], date('Y-m-d'));
        $tab    = (string) $this->request->getGet('tab');
        $tab    = in_array($tab, self::RANKING_TABS, true) ? $tab : 'resumen';
        $locId  = (int) $this->request->getGet('sede') ?: null;
        $options = $svc->coachOptions();
        $coachId = (int) $this->request->getGet('entrenador') ?: null;
        // Un id que no es de ningún entrenador se ignora (nada de pantallas vacías por URL manipulada).
        if ($coachId && !in_array($coachId, array_column($options, 'id'), true)) {
            $coachId = null;
        }

        return view('entrenadores/rankings', [
            'title'     => 'Rankings de entrenadores — JP Preparation',
            'tab'       => $tab,
            'period'    => $period,
            'locId'     => $locId,
            'coachId'   => $coachId,
            'coachOpts' => $options,
            'locations' => $svc->locations(),
            'report'    => $svc->report($period['from'], $period['to'], $locId, null, $coachId),
        ]);
    }

    // ----------------------------------------------------------------
    // Crear
    // ----------------------------------------------------------------

    public function create()
    {
        return view('entrenadores/create', [
            'title' => 'Nuevo entrenador — JP Preparation',
        ]);
    }

    public function store()
    {
        $password = (new \App\Services\AuthGuardService())->generateTempPassword();

        $userData = [
            'name'     => $this->request->getPost('name'),
            'email'    => $this->request->getPost('email'),
            'password' => $password,
            'role'     => 'coach',
            'status'   => 'active',
        ];

        $result = $this->coachService->createCoach($userData);

        if (!$result['success']) {
            return redirect()->back()->withInput()->with('errors', $result['errors']);
        }

        session()->setFlashdata('created_password', $password);
        session()->setFlashdata('created_name', $userData['name']);

        return redirect()->to('/entrenadores');
    }

    // ----------------------------------------------------------------
    // Perfil completo
    // ----------------------------------------------------------------

    public function show(int $id)
    {
        $coach = $this->coachService->getFullProfile($id);

        if (!$coach) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $docService     = new \App\Services\DocumentService();
        $personalFolder = $docService->getOrCreatePersonalFolder($id);
        $documents      = $personalFolder ? $docService->getFolderFiles((int)$personalFolder['id']) : [];

        return view('entrenadores/show', [
            'title'          => $coach['name'] . ' — JP Preparation',
            'coach'          => $coach,
            'personalFolder' => $personalFolder,
            'documents'      => $documents,
        ]);
    }

    // ----------------------------------------------------------------
    // Editar
    // ----------------------------------------------------------------

    public function edit(int $id)
    {
        $coach = $this->coachService->getFullProfile($id);

        if (!$coach) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return view('entrenadores/edit', [
            'title' => 'Editar entrenador — JP Preparation',
            'coach' => $coach,
        ]);
    }

    public function update(int $id)
    {
        $userData = [
            'name'   => $this->request->getPost('name'),
            'email'  => $this->request->getPost('email'),
            'status' => $this->request->getPost('status'),
        ];

        $this->coachService->updateCoach($id, $userData);

        session()->setFlashdata('success', 'Entrenador actualizado correctamente.');

        return redirect()->to('/entrenadores/' . $id);
    }

    // ----------------------------------------------------------------
    // Eliminar (baja lógica)
    // ----------------------------------------------------------------

    public function destroy(int $id)
    {
        if ($block = $this->blockSensitiveTarget($id)) {
            return $block;
        }

        $this->coachService->deleteCoach($id);
        session()->setFlashdata('success', 'Entrenador dado de baja correctamente.');

        return redirect()->back();
    }

    public function reactivate(int $id)
    {
        $this->coachService->reactivateCoach($id);
        session()->setFlashdata('success', 'Entrenador reactivado correctamente.');

        return redirect()->back();
    }

    /** No se puede dar de baja a un superadmin ni a uno mismo. */
    private function blockSensitiveTarget(int $id)
    {
        $target = (new \App\Models\UserModel())->find($id);

        if (! $target) {
            session()->setFlashdata('error', 'Usuario no encontrado.');
            return redirect()->to('/entrenadores');
        }
        if ((int) $id === (int) $this->currentUserId()) {
            session()->setFlashdata('error', 'No puedes darte de baja a ti mismo.');
            return redirect()->back();
        }
        if (($target['role'] ?? '') === 'superadmin') {
            session()->setFlashdata('error', 'No se puede dar de baja a un superadministrador.');
            return redirect()->back();
        }
        return null;
    }
}
