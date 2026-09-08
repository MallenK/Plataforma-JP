<?php
helper('avatar');
$role        = session('role');
$currentUri  = current_url(true)->getPath();
$name        = session('name') ?? 'Usuario';
$avatar      = session('avatar');

// Helper: activa la clase 'active' si la URI coincide con el prefijo
function sidebarActive(string $path, string $uri): string {
    return str_starts_with($uri, $path) ? 'active' : '';
}

$isAdmin      = in_array($role, ['superadmin', 'admin']);
$isCoach      = $role === 'coach';
$isAlumno     = in_array($role, ['alumno', 'player']);
$isStaff      = $role === 'staff';
$canManage    = $isAdmin || $isStaff || $isCoach;

// Historial de versiones/despliegues — visible solo para admin/superadmin.
$versionLog  = [];
$versionInfo = null;
if ($isAdmin) {
    $versionFile = APPPATH . 'version.json';
    if (is_file($versionFile)) {
        $versionLog  = json_decode(file_get_contents($versionFile), true) ?: [];
        $versionInfo = $versionLog[0] ?? null;
    }
}
?>

<aside class="sidebar">

    <!-- Logo -->
    <a href="<?= base_url('dashboard') ?>" class="sidebar-logo">
        <div class="sidebar-logo-icon">Mk</div>
        <div class="sidebar-logo-text">
            Mallen&rsquo;k
            <span>Academy Software</span>
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
                    <?= esc(demo_label('alumno_plural')) ?>
                </a>
            </li>
            <?php endif; ?>

            <!-- Mi ficha — solo alumno -->
            <?php if ($isAlumno): ?>
            <li class="sidebar-nav-item">
                <a href="<?= base_url('alumno') ?>"
                   class="sidebar-nav-link <?= sidebarActive('/alumno', $currentUri) ?>">
                    <i class="bi bi-person-badge-fill"></i>
                    <?= esc(demo_label('mi_ficha')) ?>
                </a>
            </li>
            <?php endif; ?>

            <!-- Clases — todos los roles autenticados -->
            <li class="sidebar-nav-item">
                <a href="<?= base_url('clases') ?>"
                   class="sidebar-nav-link <?= sidebarActive('/clases', $currentUri) ?>">
                    <i class="bi bi-collection-play-fill"></i>
                    <?= esc(demo_label('clase_plural')) ?>
                </a>
            </li>

            <!-- Entrenadores — solo admin / superadmin -->
            <?php if ($isAdmin): ?>
            <li class="sidebar-nav-item">
                <a href="<?= base_url('entrenadores') ?>"
                   class="sidebar-nav-link <?= sidebarActive('/entrenadores', $currentUri) ?>">
                    <i class="bi bi-person-workspace"></i>
                    <?= esc(demo_label('entrenador_plural')) ?>
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


            <!-- Mensajes — todos los roles -->
            <li class="sidebar-nav-item">
                <a href="<?= base_url('mensajes') ?>"
                   class="sidebar-nav-link <?= sidebarActive('/mensajes', $currentUri) ?>">
                    <i class="bi bi-chat-dots-fill"></i>
                    Mensajes
                    <span class="sidebar-badge d-none" id="sidebar-msg-badge">0</span>
                </a>
            </li>

            <!-- Documentación — todos los roles -->
            <li class="sidebar-nav-item">
                <a href="<?= base_url('documentacion') ?>"
                   class="sidebar-nav-link <?= sidebarActive('/documentacion', $currentUri) ?>">
                    <i class="bi bi-folder2-open"></i>
                    Documentos
                </a>
            </li>

            <!-- Soporte / Tickets — solo gestores (admin/superadmin).
                 El resto de roles reporta desde el botón de la barra superior y
                 sigue sus tickets desde las notificaciones; la pantalla de
                 listado está oculta temporalmente. -->
            <?php if ($isAdmin): ?>
            <li class="sidebar-nav-item">
                <a href="<?= base_url('tickets/gestion') ?>"
                   class="sidebar-nav-link <?= sidebarActive('/tickets', $currentUri) ?>">
                    <i class="bi bi-headset"></i>
                    Soporte
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

    <?php if (demo_mode()): ?>
    <!-- Selector de vertical — cambia el vocabulario visible en vivo,
         útil para adaptar la demo al negocio del prospecto en llamada. -->
    <div class="sidebar-section sidebar-vertical-switcher">
        <div class="sidebar-section-label">Tipo de negocio (demo)</div>
        <form method="post" action="<?= base_url('demo/vertical') ?>" id="verticalForm">
            <?= csrf_field() ?>
            <input type="hidden" name="redirect" value="<?= esc($currentUri, 'attr') ?>">
            <select name="vertical" class="sidebar-vertical-select" onchange="document.getElementById('verticalForm').submit()">
                <?php $currentVertical = demo_current_vertical(); ?>
                <?php foreach (demo_verticals() as $key => $v): ?>
                <option value="<?= esc($key, 'attr') ?>" <?= $key === $currentVertical ? 'selected' : '' ?>>
                    <?= esc($v['label']) ?>
                </option>
                <?php endforeach; ?>
            </select>
        </form>
    </div>
    <?php endif; ?>

    <!-- Footer: usuario + cerrar sesión -->
    <div class="sidebar-footer">
        <a href="<?= base_url('perfil') ?>" class="sidebar-user">
            <?= avatar_html($avatar, $name, 'sidebar-avatar') ?>
            <div class="sidebar-user-info">
                <div class="sidebar-user-name"><?= esc($name) ?></div>
                <div class="sidebar-user-role"><?= esc($role) ?></div>
            </div>
        </a>
        <form method="post" action="<?= base_url('logout') ?>" class="sidebar-logout-form">
            <?= csrf_field() ?>
            <button type="submit" class="sidebar-logout">
                <i class="bi bi-box-arrow-left"></i>
                Cerrar sesión
            </button>
        </form>

        <?php if ($isAdmin && $versionInfo): ?>
        <button type="button" class="sidebar-version" data-ru-dialog-trigger="modalVersionLog"
                title="Historial de versiones y despliegues">
            <i class="bi bi-clock-history"></i>
            <span>v<?= esc($versionInfo['version']) ?> · <?= esc(date('d/m/Y H:i', strtotime($versionInfo['date']))) ?></span>
        </button>
        <?php endif; ?>
    </div>

</aside>

<?php if ($isAdmin && !empty($versionLog)): ?>
<!-- ── Diálogo: historial de versiones (solo admin/superadmin) ────── -->
<div class="ru-overlay" data-ru-dialog id="modalVersionLog" hidden>
    <div class="ru-dialog ru-dialog-sm" role="dialog" aria-modal="true" aria-labelledby="modalVersionLogLabel">
        <div class="ru-dialog-header">
            <h3 id="modalVersionLogLabel">
                <i class="bi bi-clock-history me-2" style="color:var(--accent-dark)"></i>Historial de versiones
            </h3>
            <button type="button" data-ru-dialog-close aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="ru-dialog-body" style="max-height:420px;overflow-y:auto">
            <?php foreach ($versionLog as $entry): ?>
            <div style="padding:10px 0;border-bottom:1px solid var(--border)">
                <div style="display:flex;justify-content:space-between;align-items:baseline;gap:8px">
                    <strong style="font-size:13px;color:var(--text-h)">v<?= esc($entry['version'] ?? '?') ?></strong>
                    <span style="font-size:11px;color:var(--text-muted);white-space:nowrap">
                        <?= esc(date('d/m/Y H:i', strtotime($entry['date'] ?? 'now'))) ?>
                    </span>
                </div>
                <div style="font-size:12.5px;color:var(--text-body);margin-top:4px;line-height:1.5">
                    <?= esc($entry['description'] ?? '') ?>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endif; ?>
