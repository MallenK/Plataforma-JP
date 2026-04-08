<?php

namespace App\Controllers;

class OrganizadorController extends BaseController
{
    public function index()
    {
        return view('organizador/index', [
            'title'       => 'Organizador — JP Preparation',
            'pageTitle'   => 'Organizador',
            'pageSubtitle'=> 'Calendario y planificación',
        ]);
    }
}
