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
        $password = 'Jp' . bin2hex(random_bytes(3)) . '!';

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

        return view('entrenadores/show', [
            'title' => esc($coach['name']) . ' — JP Preparation',
            'coach' => $coach,
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
        $this->coachService->deleteCoach($id);

        session()->setFlashdata('success', 'Entrenador dado de baja correctamente.');

        return redirect()->to('/entrenadores');
    }
}
