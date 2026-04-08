<?php

namespace App\Controllers;

class ClasesController extends BaseController
{
    public function index()
    {
        return view('clases/index', [
            'title'       => 'Clases — JP Preparation',
            'pageTitle'   => 'Clases',
            'pageSubtitle'=> 'Sesiones de entrenamiento',
        ]);
    }
}
