<?php

/**
 * Selector de "vertical" de la DEMO (ver app/Helpers/demo_helper.php).
 *
 * La plataforma nació para academias de fútbol, pero el backoffice (fichas
 * de alumno, calendario de clases, bonos, mensajería...) sirve igual para
 * academias de refuerzo escolar, idiomas o profesionales de clases
 * particulares. En vez de tener contenido/textos separados por tipo de
 * negocio, este helper centraliza el VOCABULARIO visible (menú, cabecera,
 * portada de login) para que un mismo despliegue de demo pueda "vestirse"
 * de cualquiera de esos negocios con un simple cambio de sesión.
 *
 * No cambia rutas, controllers, modelos ni la base de datos — solo las
 * etiquetas que ve el usuario. Fuera del modo demo se usa siempre el
 * vertical por defecto ('futbol'), que es el vocabulario histórico de la
 * plataforma, así que este helper es inerte en producción real de JP.
 */

if (! function_exists('demo_verticals')) {
    /**
     * Catálogo de verticales disponibles.
     *
     * @return array<string, array{
     *   label: string,
     *   words: array<string, string>,
     *   pitch_title: string,
     *   pitch_lead: string,
     *   pitch_for: string,
     * }>
     */
    function demo_verticals(): array
    {
        return [
            'futbol' => [
                'label' => 'Academia de fútbol',
                'words' => [
                    'academia'          => 'academia deportiva',
                    'alumno'            => 'Alumno',
                    'alumno_plural'     => 'Alumnos',
                    'entrenador'        => 'Entrenador',
                    'entrenador_plural' => 'Entrenadores',
                    'clase'             => 'Clase',
                    'clase_plural'      => 'Clases',
                    'clase_subtitle'    => 'Sesiones de entrenamiento',
                    'mi_ficha'          => 'Mi ficha',
                ],
                'pitch_title' => 'La plataforma de gestión para tu academia deportiva',
                'pitch_lead'  => 'Un único panel para llevar el día a día de una academia de tecnificación '
                    . 'o club: sin hojas de cálculo sueltas ni grupos de WhatsApp descontrolados.',
                'pitch_for'   => 'academias de tecnificación, escuelas de fútbol, '
                    . 'clubes de base y entrenadores personales que gestionan varios grupos.',
            ],
            'refuerzo' => [
                'label' => 'Academia de refuerzo escolar',
                'words' => [
                    'academia'          => 'academia de refuerzo escolar',
                    'alumno'            => 'Alumno',
                    'alumno_plural'     => 'Alumnos',
                    'entrenador'        => 'Profesor',
                    'entrenador_plural' => 'Profesores',
                    'clase'             => 'Clase',
                    'clase_plural'      => 'Clases',
                    'clase_subtitle'    => 'Clases de refuerzo',
                    'mi_ficha'          => 'Mi ficha',
                ],
                'pitch_title' => 'La plataforma de gestión para tu academia de refuerzo escolar',
                'pitch_lead'  => 'Un único panel para llevar el día a día de una academia de repaso o '
                    . 'clases particulares: sin hojas de cálculo sueltas ni grupos de WhatsApp descontrolados.',
                'pitch_for'   => 'academias de refuerzo escolar, centros de estudio y '
                    . 'profesores particulares que gestionan varios alumnos o grupos.',
            ],
            'idiomas' => [
                'label' => 'Academia de idiomas',
                'words' => [
                    'academia'          => 'academia de idiomas',
                    'alumno'            => 'Alumno',
                    'alumno_plural'     => 'Alumnos',
                    'entrenador'        => 'Profesor',
                    'entrenador_plural' => 'Profesores',
                    'clase'             => 'Clase',
                    'clase_plural'      => 'Clases',
                    'clase_subtitle'    => 'Clases de idiomas',
                    'mi_ficha'          => 'Mi ficha',
                ],
                'pitch_title' => 'La plataforma de gestión para tu academia de idiomas',
                'pitch_lead'  => 'Un único panel para llevar el día a día de una academia de idiomas: '
                    . 'sin hojas de cálculo sueltas ni grupos de WhatsApp descontrolados.',
                'pitch_for'   => 'academias de idiomas, centros de formación y '
                    . 'profesores particulares con varios grupos y niveles.',
            ],
            'personal' => [
                'label' => 'Clases y entrenamiento personal',
                'words' => [
                    'academia'          => 'negocio de clases particulares',
                    'alumno'            => 'Cliente',
                    'alumno_plural'     => 'Clientes',
                    'entrenador'        => 'Profesional',
                    'entrenador_plural' => 'Profesionales',
                    'clase'             => 'Sesión',
                    'clase_plural'      => 'Sesiones',
                    'clase_subtitle'    => 'Sesiones individuales',
                    'mi_ficha'          => 'Mi ficha',
                ],
                'pitch_title' => 'La plataforma de gestión para tus clases y sesiones particulares',
                'pitch_lead'  => 'Un único panel para llevar el día a día de tu actividad: '
                    . 'sin hojas de cálculo sueltas ni grupos de WhatsApp descontrolados.',
                'pitch_for'   => 'entrenadores personales, profesores particulares y '
                    . 'profesionales que gestionan clientes y sesiones por su cuenta.',
            ],
        ];
    }
}

if (! function_exists('demo_default_vertical')) {
    function demo_default_vertical(): string
    {
        return 'futbol';
    }
}

if (! function_exists('demo_current_vertical')) {
    /**
     * Vertical activo para la sesión actual. Fuera de la demo siempre
     * devuelve el vertical por defecto (fútbol).
     */
    function demo_current_vertical(): string
    {
        if (! demo_mode()) {
            return demo_default_vertical();
        }

        $verticals = demo_verticals();
        $current   = (string) (session('demo_vertical') ?? demo_default_vertical());

        return isset($verticals[$current]) ? $current : demo_default_vertical();
    }
}

if (! function_exists('demo_label')) {
    /**
     * Palabra/etiqueta según el vertical activo. $key es una de las claves
     * de 'words' en demo_verticals() (p. ej. 'alumno_plural').
     */
    function demo_label(string $key): string
    {
        $verticals = demo_verticals();
        $vertical  = $verticals[demo_current_vertical()] ?? $verticals[demo_default_vertical()];

        return $vertical['words'][$key] ?? $key;
    }
}
