<?php

/**
 * Helpers del entorno DEMO.
 *
 * La demo es un despliegue aparte (2º servicio de Render + BBDD propia con
 * datos falsos) pensado para enseñar la plataforma a academias. Se activa
 * poniendo la variable de entorno APP_ENV_LABEL = "demo".
 *
 * En ese modo:
 *   - la pantalla de login muestra botones "Entrar como invitado" (rol
 *     admin / coach / player) que no piden contraseña;
 *   - existe la ruta protegida por token GET /demo/reset que borra y
 *     resiembra los datos (la dispara GitHub Actions cada noche);
 *   - el badge del topbar muestra "DEMO".
 *
 * FUERA del modo demo todo esto queda inerte: los controladores devuelven
 * 404 y las vistas no pintan nada.
 */

if (! function_exists('demo_mode')) {
    /**
     * ¿Está la plataforma corriendo como demo pública?
     */
    function demo_mode(): bool
    {
        return strtolower(trim((string) env('APP_ENV_LABEL', ''))) === 'demo';
    }
}

if (! function_exists('demo_guest_accounts')) {
    /**
     * Cuentas de invitado por rol. El email es el identificador; la
     * contraseña solo se usa al sembrar (el login de invitado no la pide).
     *
     * @return array<string, array{email:string, name:string, label:string}>
     */
    function demo_guest_accounts(): array
    {
        return [
            'admin'  => [
                'email' => 'invitado.admin@demo.local',
                'name'  => 'Invitado · Dirección',
                'label' => 'Dirección / Admin',
            ],
            'coach'  => [
                'email' => 'invitado.coach@demo.local',
                'name'  => 'Invitado · Entrenador',
                'label' => 'Entrenador',
            ],
            'player' => [
                'email' => 'invitado.player@demo.local',
                'name'  => 'Invitado · Alumno',
                'label' => 'Alumno',
            ],
        ];
    }
}

if (! function_exists('demo_guest_password')) {
    /**
     * Contraseña con la que se siembran las cuentas de invitado. No se
     * publica en la UI; existe solo para que la cuenta sea válida y para
     * poder entrar a mano si hiciera falta.
     */
    function demo_guest_password(): string
    {
        return (string) (env('DEMO_GUEST_PASSWORD') ?: 'DemoInvitado2026!');
    }
}
