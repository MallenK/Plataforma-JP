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
}
