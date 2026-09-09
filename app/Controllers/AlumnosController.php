<?php

namespace App\Controllers;

/**
 * AlumnosController — gestión completa de alumnos.
 *
 * Tablas implicadas:
 *   - users (role='player')
 *   - player_profiles (datos físicos / médicos)
 *   - player_annotations (notas públicas / internas)
 *
 * Antes este controller estaba dividido en PlayerController (CRUD) y
 * AlumnosController (un wrapper de perfil). La duplicación se eliminó
 * para evitar la confusión "player ↔ alumno".
 */
class AlumnosController extends BaseController
{
    // ----------------------------------------------------------------
    // Listado
    // ----------------------------------------------------------------

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

    // ----------------------------------------------------------------
    // Auto-perfil del alumno autenticado
    // ----------------------------------------------------------------

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

        $annotationModel = new \App\Models\PlayerAnnotationModel();
        $role  = $this->currentRole();
        $types = ($role === 'player') ? ['public'] : ['public', 'internal'];

        $docService     = new \App\Services\DocumentService();
        $personalFolder = $docService->getOrCreatePersonalFolder($userId);
        $documents      = $personalFolder
            ? $docService->getFolderFiles((int)$personalFolder['id'])
            : [];

        return view('alumnos/profile', [
            'title'          => 'Mi ficha — JP Preparation',
            'profile'        => $profile,
            'annotations'    => $annotationModel->getForPlayer($userId, $types),
            'canInternal'    => $role !== 'player',
            'personalFolder' => $personalFolder,
            'documents'      => $documents,
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
            'position'      => \App\Models\PlayerProfileModel::encodePositions((array) $this->request->getPost('position')),
            'level'         => $this->request->getPost('level'),
            'category'      => $this->request->getPost('category') ?: null,
            'team'          => $this->request->getPost('team') ?: null,
            'league'        => $this->request->getPost('league') ?: null,
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
        $password = (new \App\Services\AuthGuardService())->generateTempPassword();

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
            'position'      => \App\Models\PlayerProfileModel::encodePositions((array) $this->request->getPost('position')),
            'level'         => $this->request->getPost('level') ?: null,
            'category'      => $this->request->getPost('category') ?: null,
            'team'          => $this->request->getPost('team') ?: null,
            'league'        => $this->request->getPost('league') ?: null,
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

        $annotationModel = new \App\Models\PlayerAnnotationModel();
        $role  = $this->currentRole();
        $types = ($role === 'player') ? ['public'] : ['public', 'internal'];

        return view('alumnos/show', [
            'title'       => $alumno['name'] . ' — JP Preparation',
            'alumno'      => $alumno,
            'annotations' => $annotationModel->getForPlayer($id, $types),
            'canInternal' => $role !== 'player',
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
            'birth_date'          => $this->request->getPost('birth_date') ?: null,
            'height'              => $this->request->getPost('height') ?: null,
            'weight'              => $this->request->getPost('weight') ?: null,
            'position'            => \App\Models\PlayerProfileModel::encodePositions((array) $this->request->getPost('position')),
            'level'               => $this->request->getPost('level') ?: null,
            'category'            => $this->request->getPost('category') ?: null,
            'team'                => $this->request->getPost('team') ?: null,
            'league'              => $this->request->getPost('league') ?: null,
            'medical_notes'       => $this->request->getPost('medical_notes') ?: null,
            'image_rights_signed' => $this->request->getPost('image_rights_signed') ? 1 : 0,
        ];

        $this->playerService->updateAlumno($id, $userData, $profileData);

        session()->setFlashdata('success', 'Alumno actualizado correctamente.');

        return redirect()->to('/alumnos/' . $id);
    }

    /**
     * Toggle rápido de "derechos de imagen firmados" desde la ficha del
     * alumno. Solo admin / superadmin (ver Routes.php).
     */
    public function updateImageRights(int $id)
    {
        $target = (new \App\Models\UserModel())->find($id);
        if (!$target || ($target['role'] ?? '') !== 'player') {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        $signed = (bool) $this->request->getPost('image_rights_signed');
        $this->playerService->setImageRights($id, $signed);

        session()->setFlashdata('success', $signed
            ? 'Derechos de imagen marcados como firmados.'
            : 'Derechos de imagen marcados como NO firmados.');

        return redirect()->to('/alumnos/' . $id . '#derechos-imagen');
    }

    /**
     * Baja lógica: cambia status a 'inactive'.
     */
    public function destroy(int $id)
    {
        if ($block = $this->blockSensitiveTarget($id)) {
            return $block;
        }

        $this->playerService->deleteAlumno($id);
        session()->setFlashdata('success', 'Alumno dado de baja correctamente.');

        // Desde el listado vuelve al listado; desde la ficha, a la ficha.
        return redirect()->back();
    }

    public function reactivate(int $id)
    {
        $this->playerService->reactivateAlumno($id);
        session()->setFlashdata('success', 'Alumno reactivado correctamente.');

        return redirect()->back();
    }

    /** No se puede dar de baja a un superadmin ni a uno mismo. */
    private function blockSensitiveTarget(int $id)
    {
        $target = (new \App\Models\UserModel())->find($id);

        if (! $target) {
            session()->setFlashdata('error', 'Usuario no encontrado.');
            return redirect()->to('/alumnos');
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
