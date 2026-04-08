<?php

namespace App\Controllers;

class EntrenadoresController extends BaseController
{
    public function index()
    {
        return view('entrenadores/index', [
            'title'       => 'Entrenadores — JP Preparation',
            'pageTitle'   => 'Entrenadores',
            'pageSubtitle'=> 'Equipo técnico de la academia',
        ]);
    }
}
