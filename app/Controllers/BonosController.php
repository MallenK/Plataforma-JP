<?php

namespace App\Controllers;

class BonosController extends BaseController
{
    public function index()
    {
        return view('bonos/index', [
            'title'       => 'Bonos — JP Preparation',
            'pageTitle'   => 'Bonos',
            'pageSubtitle'=> 'Membresías y bonos de entrenamiento',
        ]);
    }
}
