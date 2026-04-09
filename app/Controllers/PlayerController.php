<?php

namespace App\Controllers;

class PlayerController extends BaseController
{
    /**
     * Lista todos los alumnos con estado de perfil.
     * Accesible para admin, superadmin y coach.
     */
    public function index()
    {
        $players = $this->playerService->getPlayersWithProfile();

        return view('alumnos/index', [
            'title'   => 'Alumnos — JP Preparation',
            'players' => $players,
        ]);
    }

    /**
     * Muestra el perfil del alumno autenticado.
     * Sin perfil o con ?edit=1 → formulario de creación/edición.
     */
    public function profile()
    {
        $userId  = $this->currentUserId();
        $profile = $this->playerService->getProfile($userId);

        if (!$profile || $this->request->getGet('edit')) {
            return view('alumnos/create_profile', [
                'title'   => 'Mi ficha — JP Preparation',
                'profile' => $profile,
            ]);
        }

        return view('alumnos/profile', [
            'title'   => 'Mi ficha — JP Preparation',
            'profile' => $profile,
        ]);
    }

    /**
     * Guarda o actualiza el perfil del alumno autenticado.
     */
    public function saveProfile()
    {
        $userId = $this->currentUserId();

        $data = [
            'player_id'     => $userId,
            'birth_date'    => $this->request->getPost('birth_date'),
            'height'        => $this->request->getPost('height'),
            'weight'        => $this->request->getPost('weight'),
            'position'      => $this->request->getPost('position'),
            'level'         => $this->request->getPost('level'),
            'medical_notes' => $this->request->getPost('medical_notes'),
        ];

        $this->playerService->saveProfile($data);

        return redirect()->to('/alumno');
    }

    // ----------------------------------------------------------------
    // CRUD de alumnos — solo admin y superadmin
    // ----------------------------------------------------------------

    /**
     * Formulario para crear un nuevo alumno.
     */
    public function create()
    {
        return view('alumnos/create', [
            'title' => 'Nuevo alumno — JP Preparation',
        ]);
    }

    /**
     * Procesa el formulario de creación.
     * Genera contraseña automática y la muestra en flash al admin.
     */
    public function store()
    {
        $password = 'Jp' . bin2hex(random_bytes(3)) . '!';

        $userData = [
            'name'     => $this->request->getPost('name'),
            'email'    => $this->request->getPost('email'),
            'password' => $password,
            'role'     => 'player',
            'status'   => 'active',
        ];

        $profileData = array_filter([
            'birth_date'    => $this->request->getPost('birth_date') ?: null,
            'height'        => $this->request->getPost('height') ?: null,
            'weight'        => $this->request->getPost('weight') ?: null,
            'position'      => $this->request->getPost('position') ?: null,
            'level'         => $this->request->getPost('level') ?: null,
            'medical_notes' => $this->request->getPost('medical_notes') ?: null,
        ], fn($v) => $v !== null);

        $result = $this->playerService->createAlumno($userData, $profileData);

        if (!$result['success']) {
            return redirect()->back()->withInput()->with('errors', $result['errors']);
        }

        session()->setFlashdata('created_password', $password);
        session()->setFlashdata('created_name', $userData['name']);

        return redirect()->to('/alumnos');
    }

    /**
     * Perfil completo de un alumno (vista admin).
     */
    public function show(int $id)
    {
        $alumno = $this->playerService->getFullProfile($id);

        if (!$alumno) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return view('alumnos/show', [
            'title'  => esc($alumno['name']) . ' — JP Preparation',
            'alumno' => $alumno,
        ]);
    }

    /**
     * Formulario de edición de un alumno existente.
     */
    public function edit(int $id)
    {
        $alumno = $this->playerService->getFullProfile($id);

        if (!$alumno) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        return view('alumnos/edit', [
            'title'  => 'Editar alumno — JP Preparation',
            'alumno' => $alumno,
        ]);
    }

    /**
     * Guarda los cambios de edición de un alumno.
     */
    public function update(int $id)
    {
        $userData = [
            'name'   => $this->request->getPost('name'),
            'email'  => $this->request->getPost('email'),
            'status' => $this->request->getPost('status'),
        ];

        $profileData = [
            'birth_date'    => $this->request->getPost('birth_date') ?: null,
            'height'        => $this->request->getPost('height') ?: null,
            'weight'        => $this->request->getPost('weight') ?: null,
            'position'      => $this->request->getPost('position') ?: null,
            'level'         => $this->request->getPost('level') ?: null,
            'medical_notes' => $this->request->getPost('medical_notes') ?: null,
        ];

        $this->playerService->updateAlumno($id, $userData, $profileData);

        session()->setFlashdata('success', 'Alumno actualizado correctamente.');

        return redirect()->to('/alumnos/' . $id);
    }

    /**
     * Baja lógica: cambia status a 'inactive'.
     */
    public function destroy(int $id)
    {
        $this->playerService->deleteAlumno($id);

        session()->setFlashdata('success', 'Alumno dado de baja correctamente.');

        return redirect()->to('/alumnos');
    }
}
