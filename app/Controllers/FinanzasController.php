<?php

namespace App\Controllers;

class FinanzasController extends BaseController
{
    public function index()
    {
        return view('finanzas/index', [
            'title'       => 'Finanzas — JP Preparation',
            'pageTitle'   => 'Finanzas',
            'pageSubtitle'=> 'Control económico de la academia',
        ]);
    }
}
