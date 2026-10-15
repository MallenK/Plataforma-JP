<?php

namespace App\Controllers;

use App\Services\ClasesService;

/**
 * Ayuda para el alumno y sus familias: cómo avisar de una ausencia, qué pasa
 * si se avisa tarde, dónde ver lo que escribe el entrenador, instalar la app.
 * Los textos de plazos y descuento salen de los ajustes reales de Finanzas.
 *
 *  GET /ayuda → cualquier usuario con sesión
 */
class AyudaController extends BaseController
{
    public function index(): string
    {
        return view('ayuda/index', [
            'title'        => 'Cómo funciona — JP Preparation',
            'noticePolicy' => (new ClasesService())->noticePolicy(),
        ]);
    }
}
