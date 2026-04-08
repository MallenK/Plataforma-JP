<?php
// ── Datos del usuario ─────────────────────────────────────
$name  = session('name') ?? 'Usuario';
$role  = session('role') ?? '';
$parts = explode(' ', trim($name));
$initials = strtoupper(substr($parts[0], 0, 1));
if (count($parts) >= 2) {
    $initials = strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
}

$roleLabel = match($role) {
    'superadmin' => 'Super Admin',
    'admin'      => 'Administrador',
    'coach'      => 'Entrenador',
    'alumno'     => 'Alumno',
    'staff'      => 'Staff',
    default      => ucfirst($role),
};

// ── Título de página derivado de la URI ───────────────────
// Las variables $pageTitle / $pageSubtitle definidas en las vistas
// hijo no se propagan aquí porque son variables PHP locales, no del
// data array del controlador. Derivamos el título de la URI para no
// depender de que cada controlador los pase explícitamente.
$uriSegment = service('uri')->getSegment(1) ?: 'dashboard';

$navTitles = [
    'dashboard'    => ['Dashboard',      strtoupper($roleLabel) . ' · PANEL DE CONTROL'],
    'alumnos'      => ['Alumnos',        'Gestión de alumnos'],
    'alumno'       => ['Mi ficha',       'Perfil de alumno'],
    'entrenadores' => ['Entrenadores',   'Equipo técnico'],
    'organizador'  => ['Organizador',    'Calendario y planificación'],
    'clases'       => ['Clases',         'Sesiones de entrenamiento'],
    'bonos'        => ['Bonos',          'Membresías y bonos'],
    'torneos'      => ['Torneos',        'Calendario de competiciones'],
    'documentacion'=> ['Documentación',  'Material formativo'],
    'finanzas'     => ['Finanzas',       'Control económico'],
    'configuracion'=> ['Configuración',  'Ajustes de la plataforma'],
    'perfil'       => ['Mi perfil',      'Información de cuenta'],
];

// Si el controlador pasó $pageTitle/$pageSubtitle explícitamente, se usan esos
if (!isset($pageTitle) || !isset($pageSubtitle)) {
    [$pageTitle, $pageSubtitle] = $navTitles[$uriSegment] ?? ['JP Preparation', ''];
}
?>

<header class="topbar">

    <div class="topbar-title">
        <h1><?= esc($pageTitle) ?></h1>
        <?php if ($pageSubtitle): ?><p><?= esc($pageSubtitle) ?></p><?php endif; ?>
    </div>

    <div class="topbar-right">

        <!-- Notificaciones -->
        <button class="topbar-btn" title="Notificaciones">
            <i class="bi bi-bell"></i>
            <span class="topbar-notif-dot"></span>
        </button>

        <!-- Perfil -->
        <a href="<?= base_url('perfil') ?>" class="topbar-user" style="text-decoration:none">
            <div class="topbar-user-info">
                <div class="topbar-user-name"><?= esc($name) ?></div>
                <div class="topbar-user-role"><?= esc($roleLabel) ?></div>
            </div>
            <div class="topbar-avatar"><?= esc($initials) ?></div>
        </a>

    </div>

</header>
