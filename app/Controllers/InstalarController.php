<?php

namespace App\Controllers;

/**
 * Guía para instalar la app (PWA) según el dispositivo.
 *
 *  GET /instalar              → PÚBLICA (sin sesión): enlace que se manda por WhatsApp a alumnos/entrenadores
 *  GET /configuracion/instalar → misma guía dentro de la plataforma + bloque «compartir» (enlace y QR); todos los roles
 */
class InstalarController extends BaseController
{
    public function publico(): string
    {
        return view('instalar', ['title' => 'Instalar la app — JP Preparation']);
    }

    public function ajustes(): string
    {
        return view('configuracion/instalar', ['title' => 'Instalar la app — JP Preparation']);
    }
}
