<?php

namespace App\Controllers;

class TorneosController extends BaseController
{
    public function index()
    {
        return view('torneos/index', [
            'title'       => 'Torneos — JP Preparation',
            'pageTitle'   => 'Torneos',
            'pageSubtitle'=> 'Calendario de competiciones',
        ]);
    }
}
