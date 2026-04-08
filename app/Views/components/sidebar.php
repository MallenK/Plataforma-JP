<?php
$role        = session('role');
$currentUri  = current_url(true)->getPath();
$name        = session('name') ?? 'Usuario';
$initials    = strtoupper(substr($name, 0, 1));

// Genera las iniciales del nombre completo (hasta 2 letras)
$parts = explode(' ', trim($name));
if (count($parts) >= 2) {
    $initials = strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
}

// Helper: activa la clase 'active' si la URI coincide con el prefijo
function sidebarActive(string $path, string $uri): string {
    return str_starts_with($uri, $path) ? 'active' : '';
}

$isAdmin      = in_array($role, ['superadmin', 'admin']);
$isCoach      = $role === 'coach';
$isAlumno     = $role === 'alumno';
$isStaff      = $role === 'staff';
?>

<aside class="sidebar">

    <!-- Logo -->
    <a href="<?= base_url('dashboard') ?>" class="sidebar-logo">
        <div class="sidebar-logo-icon">JP</div>
        <div class="sidebar-logo-text">
            JP Preparation
            <span>Plataforma</span>
        </div>
    </a>

    <!-- Navegación principal -->
    <div class="sidebar-section">
        <div class="sidebar-section-label">Menú</div>
        <ul class="sidebar-nav">

            <!-- Dashboard — todos los roles -->
            <li class="sidebar-nav-item">
                <a href="<?= base_url('dashboard') ?>"
                   class="sidebar-nav-link <?= sidebarActive('/dashboard', $currentUri) ?>">
                    <i class="bi bi-grid-1x2-fill"></i>
                    Dashboard
                </a>
            </li>

            <!-- Alumnos — admin, superadmin, coach -->
            <?php if ($isAdmin || $isCoach): ?>
            <li class="sidebar-nav-item">
                <a href="<?= base_url('alumnos') ?>"
                   class="sidebar-nav-link <?= sidebarActive('/alumnos', $currentUri) ?>">
                    <i class="bi bi-people-fill"></i>
                    Alumnos
                </a>
            </li>
            <?php endif; ?>

            <!-- Mi ficha — solo alumno -->
            <?php if ($isAlumno): ?>
            <li class="sidebar-nav-item">
                <a href="<?= base_url('alumno') ?>"
                   class="sidebar-nav-link <?= sidebarActive('/alumno', $currentUri) ?>">
                    <i class="bi bi-person-badge-fill"></i>
                    Mi ficha
                </a>
            </li>
            <?php endif; ?>

            <!-- Organizador — admin, superadmin, coach -->
            <?php if ($isAdmin || $isCoach): ?>
            <li class="sidebar-nav-item">
                <a href="<?= base_url('organizador') ?>"
                   class="sidebar-nav-link <?= sidebarActive('/organizador', $currentUri) ?>">
                    <i class="bi bi-calendar3"></i>
                    Organizador
                </a>
            </li>
            <?php endif; ?>

            <!-- Clases — admin, superadmin, coach -->
            <?php if ($isAdmin || $isCoach): ?>
            <li class="sidebar-nav-item">
                <a href="<?= base_url('clases') ?>"
                   class="sidebar-nav-link <?= sidebarActive('/clases', $currentUri) ?>">
                    <i class="bi bi-collection-play-fill"></i>
                    Clases
                </a>
            </li>
            <?php endif; ?>

            <!-- Entrenadores — solo admin / superadmin -->
            <?php if ($isAdmin): ?>
            <li class="sidebar-nav-item">
                <a href="<?= base_url('entrenadores') ?>"
                   class="sidebar-nav-link <?= sidebarActive('/entrenadores', $currentUri) ?>">
                    <i class="bi bi-person-workspace"></i>
                    Entrenadores
                </a>
            </li>
            <?php endif; ?>

            <!-- Bonos — admin, superadmin -->
            <?php if ($isAdmin): ?>
            <li class="sidebar-nav-item">
                <a href="<?= base_url('bonos') ?>"
                   class="sidebar-nav-link <?= sidebarActive('/bonos', $currentUri) ?>">
                    <i class="bi bi-ticket-perforated-fill"></i>
                    Bonos
                </a>
            </li>
            <?php endif; ?>

            <!-- Torneos — todos menos alumno -->
            <?php if ($isAdmin || $isCoach || $isStaff): ?>
            <li class="sidebar-nav-item">
                <a href="<?= base_url('torneos') ?>"
                   class="sidebar-nav-link <?= sidebarActive('/torneos', $currentUri) ?>">
                    <i class="bi bi-trophy-fill"></i>
                    Torneos
                </a>
            </li>
            <?php endif; ?>

            <!-- Documentación — todos los roles -->
            <li class="sidebar-nav-item">
                <a href="<?= base_url('documentacion') ?>"
                   class="sidebar-nav-link <?= sidebarActive('/documentacion', $currentUri) ?>">
                    <i class="bi bi-folder2-open"></i>
                    Documentos
                </a>
            </li>

            <!-- Finanzas — admin, superadmin -->
            <?php if ($isAdmin): ?>
            <li class="sidebar-nav-item">
                <a href="<?= base_url('finanzas') ?>"
                   class="sidebar-nav-link <?= sidebarActive('/finanzas', $currentUri) ?>">
                    <i class="bi bi-graph-up-arrow"></i>
                    Finanzas
                </a>
            </li>
            <?php endif; ?>

            <!-- Configuración — admin, superadmin -->
            <?php if ($isAdmin): ?>
            <li class="sidebar-nav-item">
                <a href="<?= base_url('configuracion') ?>"
                   class="sidebar-nav-link <?= sidebarActive('/configuracion', $currentUri) ?>">
                    <i class="bi bi-gear-fill"></i>
                    Configuración
                </a>
            </li>
            <?php endif; ?>

        </ul>
    </div>

    <!-- Footer: usuario + cerrar sesión -->
    <div class="sidebar-footer">
        <a href="<?= base_url('perfil') ?>" class="sidebar-user">
            <div class="sidebar-avatar"><?= esc($initials) ?></div>
            <div class="sidebar-user-info">
                <div class="sidebar-user-name"><?= esc($name) ?></div>
                <div class="sidebar-user-role"><?= esc($role) ?></div>
            </div>
        </a>
        <a href="<?= base_url('logout') ?>" class="sidebar-logout">
            <i class="bi bi-box-arrow-left"></i>
            Cerrar sesión
        </a>
    </div>

</aside>
