<?= $this->extend('layouts/app') ?>

<?php
// ── Helpers de vista ──────────────────────────────────────────────────────

function roleBadge(string $role): string {
    $map = [
        'superadmin' => ['label' => 'Superadmin', 'color' => '#7c3aed'],
        'admin'      => ['label' => 'Admin',       'color' => '#2563eb'],
        'staff'      => ['label' => 'Staff',        'color' => '#0891b2'],
        'coach'      => ['label' => 'Entrenador',   'color' => '#059669'],
        'player'     => ['label' => 'Jugador',      'color' => '#d97706'],
    ];
    $r   = $map[$role] ?? ['label' => ucfirst($role), 'color' => '#6b7280'];
    return '<span class="badge-status" style="background:' . $r['color'] . '22;color:' . $r['color'] . ';border:1px solid ' . $r['color'] . '44">' . $r['label'] . '</span>';
}

function locationTypeName(string $type): string {
    return match ($type) {
        'pitch'  => 'Pista / Campo',
        'gym'    => 'Gimnasio',
        'room'   => 'Sala',
        'office' => 'Oficina',
        default  => 'Otro',
    };
}

function locationTypeIcon(string $type): string {
    return match ($type) {
        'pitch'  => 'bi-dribbble',
        'gym'    => 'bi-trophy-fill',
        'room'   => 'bi-door-open-fill',
        'office' => 'bi-building-fill',
        default  => 'bi-geo-alt-fill',
    };
}

$s    = $settings;       // shorthand para los settings
$sec  = $section;        // sección activa
?>

<?= $this->section('page_content') ?>

<div class="page-header">
    <div class="page-header-text">
        <h2>Configuración</h2>
        <p><?= $isAdmin ? 'Gestión completa de la plataforma JP Preparation' : 'Información general de la academia' ?></p>
    </div>
</div>

<?php if ($flash = session()->getFlashdata('success')): ?>
    <div class="alert-jp success mb-3"><i class="bi bi-check-circle-fill me-2"></i><?= esc($flash) ?></div>
<?php endif; ?>
<?php if ($flash = session()->getFlashdata('error')): ?>
    <div class="alert-jp danger mb-3"><i class="bi bi-x-circle-fill me-2"></i><?= esc($flash) ?></div>
<?php endif; ?>

<?php if ($newStaffName = session()->getFlashdata('staff_created_name')): ?>
    <div class="alert-jp success mb-3">
        <i class="bi bi-person-plus-fill me-2"></i>
        Usuario <strong><?= esc($newStaffName) ?></strong> creado.
        Contraseña temporal: <code style="background:rgba(255,255,255,.2);padding:2px 8px;border-radius:4px"><?= esc(session()->getFlashdata('staff_created_password')) ?></code>
        <span style="opacity:.7;font-size:12px"> — Compártela de forma segura y pide que la cambie.</span>
    </div>
<?php endif; ?>

<?php if (!$isAdmin): ?>
    <div class="alert-jp info mb-3">
        <i class="bi bi-info-circle-fill me-2"></i>
        Tienes acceso de solo lectura a la información general de la academia.
    </div>
<?php endif; ?>

<div class="row g-3">

    <!-- ══════════════════════════════════════════════════════════
         SIDEBAR NAV
    ═══════════════════════════════════════════════════════════ -->
    <div class="col-12 col-lg-3">
        <div class="card-jp">
            <div class="card-jp-body p-2">
                <ul class="sidebar-nav mb-0">
                    <li class="sidebar-nav-item">
                        <a href="#" class="sidebar-nav-link cfg-tab <?= $sec === 'general' ? 'active' : '' ?>"
                           data-section="general" style="color:var(--text-h)">
                            <i class="bi bi-sliders me-2"></i>General
                        </a>
                    </li>
                    <?php if ($isAdmin): ?>
                    <li class="sidebar-nav-item">
                        <a href="#" class="sidebar-nav-link cfg-tab <?= $sec === 'staff' ? 'active' : '' ?>"
                           data-section="staff" style="color:var(--text-h)">
                            <i class="bi bi-people-fill me-2"></i>Gestión de Staff
                        </a>
                    </li>
                    <li class="sidebar-nav-item">
                        <a href="#" class="sidebar-nav-link cfg-tab <?= $sec === 'sedes' ? 'active' : '' ?>"
                           data-section="sedes" style="color:var(--text-h)">
                            <i class="bi bi-geo-alt-fill me-2"></i>Campos y Sedes
                        </a>
                    </li>
                    <li class="sidebar-nav-item">
                        <a href="#" class="sidebar-nav-link cfg-tab <?= $sec === 'facturacion' ? 'active' : '' ?>"
                           data-section="facturacion" style="color:var(--text-h)">
                            <i class="bi bi-credit-card-fill me-2"></i>Facturación y Pagos
                        </a>
                    </li>
                    <li class="sidebar-nav-item">
                        <a href="#" class="sidebar-nav-link cfg-tab <?= $sec === 'notificaciones' ? 'active' : '' ?>"
                           data-section="notificaciones" style="color:var(--text-h)">
                            <i class="bi bi-bell-fill me-2"></i>Notificaciones
                        </a>
                    </li>
                    <li class="sidebar-nav-item">
                        <a href="#" class="sidebar-nav-link cfg-tab <?= $sec === 'seguridad' ? 'active' : '' ?>"
                           data-section="seguridad" style="color:var(--text-h)">
                            <i class="bi bi-shield-lock-fill me-2"></i>Seguridad
                        </a>
                    </li>
                    <li class="sidebar-nav-item">
                        <a href="#" class="sidebar-nav-link cfg-tab <?= $sec === 'web' ? 'active' : '' ?>"
                           data-section="web" style="color:var(--text-h)">
                            <i class="bi bi-globe2 me-2"></i>Web Pública
                        </a>
                    </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>

    <!-- ══════════════════════════════════════════════════════════
         CONTENIDO
    ═══════════════════════════════════════════════════════════ -->
    <div class="col-12 col-lg-9">


        <!-- ────────────────────────────────────────────────────
             1. GENERAL
        ──────────────────────────────────────────────────── -->
        <div id="sec-general" class="cfg-section <?= $sec !== 'general' ? 'd-none' : '' ?>">
            <div class="card-jp">
                <div class="card-jp-header">
                    <span class="card-jp-title"><i class="bi bi-sliders me-2" style="color:var(--accent)"></i>General</span>
                </div>
                <div class="card-jp-body">
                    <form action="/configuracion/general/save" method="POST">
                        <?= csrf_field() ?>

                        <p class="mb-3" style="font-size:13px;color:var(--text-muted)">
                            Información básica de la academia visible para todos los miembros de la plataforma.
                        </p>

                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label">Nombre de la academia</label>
                                <input type="text" name="academy_name" class="form-control-jp"
                                       value="<?= esc($s['academy_name'] ?? '') ?>"
                                       <?= !$isAdmin ? 'disabled' : '' ?>>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label">Email de contacto</label>
                                <input type="email" name="academy_email" class="form-control-jp"
                                       value="<?= esc($s['academy_email'] ?? '') ?>"
                                       placeholder="info@academia.com"
                                       <?= !$isAdmin ? 'disabled' : '' ?>>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label">Teléfono</label>
                                <input type="text" name="academy_phone" class="form-control-jp"
                                       value="<?= esc($s['academy_phone'] ?? '') ?>"
                                       placeholder="+34 600 000 000"
                                       <?= !$isAdmin ? 'disabled' : '' ?>>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label">Lugar de entrenamientos (sede principal)</label>
                                <input type="text" name="academy_location" class="form-control-jp"
                                       value="<?= esc($s['academy_location'] ?? '') ?>"
                                       placeholder="Ej: Polideportivo Norte"
                                       <?= !$isAdmin ? 'disabled' : '' ?>>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label">Idioma predeterminado</label>
                                <select name="academy_language" class="form-control-jp" <?= !$isAdmin ? 'disabled' : '' ?>>
                                    <?php foreach (['es' => 'Español', 'en' => 'English', 'fr' => 'Français', 'pt' => 'Português'] as $val => $label): ?>
                                        <option value="<?= $val ?>" <?= ($s['academy_language'] ?? 'es') === $val ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label">Zona horaria</label>
                                <select name="academy_timezone" class="form-control-jp" <?= !$isAdmin ? 'disabled' : '' ?>>
                                    <?php foreach ([
                                        'Europe/Madrid'  => '(GMT+01:00) Madrid, París',
                                        'Europe/London'  => '(GMT+00:00) Londres',
                                        'America/New_York' => '(GMT-05:00) Nueva York',
                                        'America/Los_Angeles' => '(GMT-08:00) Los Ángeles',
                                        'America/Sao_Paulo'   => '(GMT-03:00) São Paulo',
                                        'Asia/Tokyo'     => '(GMT+09:00) Tokio',
                                    ] as $val => $label): ?>
                                        <option value="<?= $val ?>" <?= ($s['academy_timezone'] ?? 'Europe/Madrid') === $val ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label">Moneda</label>
                                <select name="academy_currency" class="form-control-jp" <?= !$isAdmin ? 'disabled' : '' ?>>
                                    <?php foreach (['EUR' => 'Euro (€)', 'USD' => 'Dólar ($)', 'GBP' => 'Libra (£)', 'BRL' => 'Real (R$)'] as $val => $label): ?>
                                        <option value="<?= $val ?>" <?= ($s['academy_currency'] ?? 'EUR') === $val ? 'selected' : '' ?>><?= $label ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Web pública</label>
                                <input type="url" name="academy_website" class="form-control-jp"
                                       value="<?= esc($s['academy_website'] ?? '') ?>"
                                       placeholder="https://www.academia.com"
                                       <?= !$isAdmin ? 'disabled' : '' ?>>
                            </div>
                        </div>

                        <?php if ($isAdmin): ?>
                        <div class="mt-4 d-flex justify-content-end">
                            <button type="submit" class="btn-jp btn-jp-primary">
                                <i class="bi bi-check-lg me-1"></i>Guardar cambios
                            </button>
                        </div>
                        <?php endif; ?>
                    </form>
                </div>
            </div>
        </div><!-- /sec-general -->


        <?php if ($isAdmin): ?>

        <!-- ────────────────────────────────────────────────────
             2. GESTIÓN DE STAFF
        ──────────────────────────────────────────────────── -->
        <div id="sec-staff" class="cfg-section <?= $sec !== 'staff' ? 'd-none' : '' ?>">
            <div class="card-jp">
                <div class="card-jp-header d-flex align-items-center justify-content-between">
                    <span class="card-jp-title"><i class="bi bi-people-fill me-2" style="color:#2563eb"></i>Gestión de Staff</span>
                    <button class="btn-jp btn-jp-primary btn-jp-sm" onclick="openModal('modalNewStaff')">
                        <i class="bi bi-person-plus-fill me-1"></i>Añadir usuario
                    </button>
                </div>
                <div class="card-jp-body p-0">
                    <?php if (empty($staff)): ?>
                        <div class="empty-state p-4">
                            <i class="bi bi-people" style="font-size:2rem;color:var(--text-muted)"></i>
                            <p class="mt-2 mb-0" style="color:var(--text-muted)">No hay usuarios de staff registrados.</p>
                        </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table-jp">
                            <thead>
                                <tr>
                                    <th>Usuario</th>
                                    <th>Rol actual</th>
                                    <th>Estado</th>
                                    <th>Cambiar rol</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($staff as $u): ?>
                                <tr>
                                    <td>
                                        <div class="td-user">
                                            <div class="td-avatar" style="background:var(--accent-dark)">
                                                <?= strtoupper(mb_substr($u['name'], 0, 1)) ?>
                                            </div>
                                            <div>
                                                <div style="font-weight:600;color:var(--text-h)"><?= esc($u['name']) ?></div>
                                                <div style="font-size:12px;color:var(--text-muted)"><?= esc($u['email']) ?></div>
                                            </div>
                                        </div>
                                    </td>
                                    <td><?= roleBadge($u['role']) ?></td>
                                    <td>
                                        <?php if ($u['status'] === 'active'): ?>
                                            <span class="badge-status" style="background:#05966922;color:#059669;border:1px solid #05966944">Activo</span>
                                        <?php else: ?>
                                            <span class="badge-status" style="background:#6b728022;color:#6b7280;border:1px solid #6b728044">Inactivo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ((int)$u['id'] !== $currentUserId && $u['role'] !== 'superadmin'): ?>
                                        <form action="/configuracion/staff/<?= $u['id'] ?>/role" method="POST" class="d-flex gap-2 align-items-center">
                                            <?= csrf_field() ?>
                                            <select name="role" class="form-control-jp" style="font-size:12px;padding:4px 8px;min-width:120px">
                                                <?php foreach (['admin' => 'Admin', 'staff' => 'Staff', 'coach' => 'Entrenador'] as $val => $lbl): ?>
                                                    <option value="<?= $val ?>" <?= $u['role'] === $val ? 'selected' : '' ?>><?= $lbl ?></option>
                                                <?php endforeach; ?>
                                            </select>
                                            <button type="submit" class="btn-jp btn-jp-secondary btn-jp-sm">
                                                <i class="bi bi-arrow-repeat"></i>
                                            </button>
                                        </form>
                                        <?php else: ?>
                                            <span style="font-size:12px;color:var(--text-muted)">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <?php if ((int)$u['id'] !== $currentUserId && $u['role'] !== 'superadmin'): ?>
                                            <?php if ($u['status'] === 'active'): ?>
                                            <form action="/configuracion/staff/<?= $u['id'] ?>/deactivate" method="POST" class="d-inline"
                                                  onsubmit="return confirm('¿Desactivar a <?= esc($u['name']) ?>?')">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn-jp btn-jp-danger btn-jp-sm btn-jp-icon" title="Desactivar">
                                                    <i class="bi bi-person-x-fill"></i>
                                                </button>
                                            </form>
                                            <?php else: ?>
                                            <form action="/configuracion/staff/<?= $u['id'] ?>/activate" method="POST" class="d-inline">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn-jp btn-jp-secondary btn-jp-sm btn-jp-icon" title="Reactivar">
                                                    <i class="bi bi-person-check-fill"></i>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div><!-- /sec-staff -->


        <!-- ────────────────────────────────────────────────────
             3. CAMPOS Y SEDES
        ──────────────────────────────────────────────────── -->
        <div id="sec-sedes" class="cfg-section <?= $sec !== 'sedes' ? 'd-none' : '' ?>">
            <div class="card-jp">
                <div class="card-jp-header d-flex align-items-center justify-content-between">
                    <span class="card-jp-title"><i class="bi bi-geo-alt-fill me-2" style="color:#059669"></i>Campos y Sedes</span>
                    <button class="btn-jp btn-jp-primary btn-jp-sm" onclick="openSedeModal()">
                        <i class="bi bi-plus-lg me-1"></i>Nueva sede
                    </button>
                </div>
                <div class="card-jp-body p-0">
                    <?php if (empty($locations)): ?>
                        <div class="empty-state p-4">
                            <i class="bi bi-geo" style="font-size:2rem;color:var(--text-muted)"></i>
                            <p class="mt-2 mb-0" style="color:var(--text-muted)">No hay sedes registradas todavía.</p>
                        </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table-jp">
                            <thead>
                                <tr>
                                    <th>Sede</th>
                                    <th>Tipo</th>
                                    <th>Dirección</th>
                                    <th>Aforo / Tel.</th>
                                    <th>Estado</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($locations as $loc): ?>
                                <tr>
                                    <td>
                                        <div style="font-weight:600;color:var(--text-h)">
                                            <i class="bi <?= locationTypeIcon($loc['type']) ?> me-1" style="color:var(--accent)"></i>
                                            <?= esc($loc['name']) ?>
                                        </div>
                                        <?php if (!empty($loc['description'])): ?>
                                        <div style="font-size:12px;color:var(--text-muted)"><?= esc($loc['description']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td><span style="font-size:12px"><?= locationTypeName($loc['type']) ?></span></td>
                                    <td style="font-size:12px"><?= esc($loc['address'] ?? '—') ?></td>
                                    <td style="font-size:12px">
                                        <?= !empty($loc['capacity']) ? $loc['capacity'] . ' pers.' : '—' ?>
                                        <?php if (!empty($loc['phone'])): ?>
                                            <div style="color:var(--text-muted)"><?= esc($loc['phone']) ?></div>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($loc['active']): ?>
                                            <span class="badge-status" style="background:#05966922;color:#059669;border:1px solid #05966944">Activa</span>
                                        <?php else: ?>
                                            <span class="badge-status" style="background:#6b728022;color:#6b7280;border:1px solid #6b728044">Inactiva</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-flex gap-2 justify-content-end">
                                            <button class="btn-jp btn-jp-secondary btn-jp-sm btn-jp-icon"
                                                    onclick="openSedeModal(<?= htmlspecialchars(json_encode($loc)) ?>)"
                                                    title="Editar">
                                                <i class="bi bi-pencil-fill"></i>
                                            </button>
                                            <form action="/configuracion/sedes/<?= $loc['id'] ?>/delete" method="POST" class="d-inline"
                                                  onsubmit="return confirm('¿Eliminar la sede «<?= esc($loc['name']) ?>»?')">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn-jp btn-jp-danger btn-jp-sm btn-jp-icon" title="Eliminar">
                                                    <i class="bi bi-trash-fill"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div><!-- /sec-sedes -->


        <!-- ────────────────────────────────────────────────────
             4. FACTURACIÓN Y PAGOS
        ──────────────────────────────────────────────────── -->
        <div id="sec-facturacion" class="cfg-section <?= $sec !== 'facturacion' ? 'd-none' : '' ?>">
            <div class="card-jp mb-3">
                <div class="card-jp-header d-flex align-items-center justify-content-between">
                    <span class="card-jp-title"><i class="bi bi-credit-card-fill me-2" style="color:#d97706"></i>Tipos de Bono</span>
                    <button class="btn-jp btn-jp-primary btn-jp-sm" onclick="openBonoModal()">
                        <i class="bi bi-plus-lg me-1"></i>Nuevo bono
                    </button>
                </div>
                <div class="card-jp-body p-0">
                    <?php if (empty($bonoTypes)): ?>
                        <div class="empty-state p-4">
                            <i class="bi bi-ticket-perforated" style="font-size:2rem;color:var(--text-muted)"></i>
                            <p class="mt-2 mb-0" style="color:var(--text-muted)">No hay tipos de bono configurados todavía.</p>
                        </div>
                    <?php else: ?>
                    <div class="table-responsive">
                        <table class="table-jp">
                            <thead>
                                <tr>
                                    <th>Nombre</th>
                                    <th class="text-center">Sesiones</th>
                                    <th class="text-end">Precio</th>
                                    <th class="text-center">Validez</th>
                                    <th>Estado</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($bonoTypes as $bt): ?>
                                <tr>
                                    <td style="font-weight:600;color:var(--text-h)"><?= esc($bt['name']) ?></td>
                                    <td class="text-center"><?= $bt['sessions'] ?></td>
                                    <td class="text-end" style="font-weight:600"><?= number_format((float)$bt['price'], 2) ?> €</td>
                                    <td class="text-center" style="font-size:12px"><?= $bt['validity_days'] ?> días</td>
                                    <td>
                                        <?php if ($bt['active']): ?>
                                            <span class="badge-status" style="background:#05966922;color:#059669;border:1px solid #05966944">Activo</span>
                                        <?php else: ?>
                                            <span class="badge-status" style="background:#6b728022;color:#6b7280;border:1px solid #6b728044">Inactivo</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-flex gap-2 justify-content-end">
                                            <button class="btn-jp btn-jp-secondary btn-jp-sm btn-jp-icon"
                                                    onclick="openBonoModal(<?= htmlspecialchars(json_encode($bt)) ?>)"
                                                    title="Editar">
                                                <i class="bi bi-pencil-fill"></i>
                                            </button>
                                            <form action="/configuracion/bonos/<?= $bt['id'] ?>/delete" method="POST" class="d-inline"
                                                  onsubmit="return confirm('¿Eliminar el bono «<?= esc($bt['name']) ?>»?')">
                                                <?= csrf_field() ?>
                                                <button type="submit" class="btn-jp btn-jp-danger btn-jp-sm btn-jp-icon" title="Eliminar">
                                                    <i class="bi bi-trash-fill"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="alert-jp info">
                <i class="bi bi-info-circle-fill me-2"></i>
                Los bonos también se pueden asignar y gestionar individualmente desde la pantalla de <strong>Bonos</strong>.
            </div>
        </div><!-- /sec-facturacion -->


        <!-- ────────────────────────────────────────────────────
             5. NOTIFICACIONES
        ──────────────────────────────────────────────────── -->
        <div id="sec-notificaciones" class="cfg-section <?= $sec !== 'notificaciones' ? 'd-none' : '' ?>">

            <!-- SMTP -->
            <div class="card-jp mb-3">
                <div class="card-jp-header">
                    <span class="card-jp-title"><i class="bi bi-envelope-fill me-2" style="color:var(--accent)"></i>Configuración SMTP</span>
                </div>
                <div class="card-jp-body">
                    <form action="/configuracion/notificaciones/smtp" method="POST">
                        <?= csrf_field() ?>
                        <div class="row g-3">
                            <div class="col-12 col-md-6">
                                <label class="form-label">Servidor SMTP</label>
                                <input type="text" name="smtp_host" class="form-control-jp"
                                       value="<?= esc($s['smtp_host'] ?? '') ?>"
                                       placeholder="smtp.gmail.com">
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label">Puerto</label>
                                <input type="number" name="smtp_port" class="form-control-jp"
                                       value="<?= esc($s['smtp_port'] ?? '587') ?>">
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label">Cifrado</label>
                                <select name="smtp_encryption" class="form-control-jp">
                                    <option value="tls"  <?= ($s['smtp_encryption'] ?? 'tls') === 'tls'  ? 'selected' : '' ?>>TLS</option>
                                    <option value="ssl"  <?= ($s['smtp_encryption'] ?? '') === 'ssl'  ? 'selected' : '' ?>>SSL</option>
                                    <option value=""     <?= ($s['smtp_encryption'] ?? '') === ''     ? 'selected' : '' ?>>Ninguno</option>
                                </select>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label">Usuario SMTP</label>
                                <input type="text" name="smtp_user" class="form-control-jp"
                                       value="<?= esc($s['smtp_user'] ?? '') ?>"
                                       placeholder="tu@email.com">
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label">Contraseña SMTP</label>
                                <div style="position:relative">
                                    <input type="password" name="smtp_pass" class="form-control-jp" id="smtpPassInput"
                                           placeholder="<?= !empty($s['smtp_pass']) ? '••••••••' : 'Nueva contraseña' ?>">
                                    <button type="button" onclick="toggleSmtpPass()"
                                            style="position:absolute;right:10px;top:50%;transform:translateY(-50%);background:none;border:none;color:var(--text-muted);cursor:pointer">
                                        <i class="bi bi-eye-fill" id="smtpPassIcon"></i>
                                    </button>
                                </div>
                                <small style="font-size:11px;color:var(--text-muted)">Deja vacío para mantener la contraseña actual.</small>
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label">Nombre del remitente</label>
                                <input type="text" name="smtp_from_name" class="form-control-jp"
                                       value="<?= esc($s['smtp_from_name'] ?? '') ?>"
                                       placeholder="JP Preparation">
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label">Email del remitente</label>
                                <input type="email" name="smtp_from_email" class="form-control-jp"
                                       value="<?= esc($s['smtp_from_email'] ?? '') ?>"
                                       placeholder="noreply@academia.com">
                            </div>
                        </div>
                        <div class="mt-4 d-flex justify-content-end">
                            <button type="submit" class="btn-jp btn-jp-primary">
                                <i class="bi bi-check-lg me-1"></i>Guardar SMTP
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Toggles de notificaciones -->
            <div class="card-jp mb-3">
                <div class="card-jp-header">
                    <span class="card-jp-title"><i class="bi bi-bell-fill me-2" style="color:var(--warning)"></i>Tipos de notificación</span>
                </div>
                <div class="card-jp-body">
                    <form action="/configuracion/notificaciones/toggles" method="POST">
                        <?= csrf_field() ?>
                        <div class="d-flex flex-column gap-3">
                            <?php foreach ([
                                ['notif_new_student',    'Nuevo alumno registrado',           'Notificación al registrar un nuevo alumno en la plataforma'],
                                ['notif_bono_expiry',    'Bono próximo a vencer',             'Aviso automático cuando un bono tiene menos de 7 días o 2 sesiones'],
                                ['notif_class_reminder', 'Recordatorio de clase (24h antes)', 'Envío automático el día anterior a cada clase programada'],
                                ['notif_payment_due',    'Pago pendiente',                    'Aviso cuando hay pagos sin registrar o bonos caducados'],
                            ] as [$key, $label, $desc]): ?>
                            <div class="d-flex align-items-center justify-content-between">
                                <div>
                                    <div style="font-size:13.5px;font-weight:600;color:var(--text-h)"><?= $label ?></div>
                                    <div style="font-size:12px;color:var(--text-muted)"><?= $desc ?></div>
                                </div>
                                <div class="form-check form-switch mb-0 ms-3">
                                    <input class="form-check-input" type="checkbox"
                                           name="<?= $key ?>" value="1"
                                           <?= !empty($s[$key]) ? 'checked' : '' ?>>
                                </div>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="mt-4 d-flex justify-content-end">
                            <button type="submit" class="btn-jp btn-jp-primary">
                                <i class="bi bi-check-lg me-1"></i>Guardar preferencias
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Envío de emails -->
            <div class="card-jp">
                <div class="card-jp-header">
                    <span class="card-jp-title"><i class="bi bi-send-fill me-2" style="color:#7c3aed"></i>Enviar email</span>
                </div>
                <div class="card-jp-body">
                    <form action="/configuracion/notificaciones/send" method="POST" id="emailSendForm">
                        <?= csrf_field() ?>

                        <!-- Tipo de envío -->
                        <div class="mb-3">
                            <label class="form-label">Tipo de destinatario</label>
                            <div class="d-flex gap-3">
                                <label style="cursor:pointer;display:flex;align-items:center;gap:6px;font-size:13.5px">
                                    <input type="radio" name="recipient_type" value="individual"
                                           onchange="toggleRecipientType(this)"
                                           <?= true ? 'checked' : '' ?>>
                                    Individual
                                </label>
                                <label style="cursor:pointer;display:flex;align-items:center;gap:6px;font-size:13.5px">
                                    <input type="radio" name="recipient_type" value="group"
                                           onchange="toggleRecipientType(this)">
                                    Grupal (por rol)
                                </label>
                            </div>
                        </div>

                        <!-- Selector individual -->
                        <div id="recipientIndividual" class="mb-3">
                            <label class="form-label">Usuario destinatario</label>
                            <select name="recipient_id" class="form-control-jp">
                                <option value="">— Seleccionar usuario —</option>
                                <?php foreach ($allUsers as $u): ?>
                                    <option value="<?= $u['id'] ?>"><?= esc($u['name']) ?> (<?= esc($u['email']) ?> · <?= $u['role'] ?>)</option>
                                <?php endforeach; ?>
                            </select>
                        </div>

                        <!-- Selector grupal -->
                        <div id="recipientGroup" class="mb-3 d-none">
                            <label class="form-label">Grupo destinatario</label>
                            <select name="recipient_group" class="form-control-jp">
                                <option value="all">Todos los usuarios activos</option>
                                <option value="admin">Administradores</option>
                                <option value="staff">Staff</option>
                                <option value="coach">Entrenadores</option>
                                <option value="player">Jugadores / Alumnos</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Asunto</label>
                            <input type="text" name="subject" class="form-control-jp" required
                                   placeholder="Asunto del email">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Mensaje</label>
                            <textarea name="message" class="form-control-jp" rows="5" required
                                      placeholder="Escribe el cuerpo del email..."></textarea>
                        </div>

                        <div class="d-flex justify-content-end">
                            <button type="submit" class="btn-jp btn-jp-primary">
                                <i class="bi bi-send-fill me-1"></i>Enviar email
                            </button>
                        </div>
                    </form>
                </div>
            </div>

        </div><!-- /sec-notificaciones -->


        <!-- ────────────────────────────────────────────────────
             6. SEGURIDAD
        ──────────────────────────────────────────────────── -->
        <div id="sec-seguridad" class="cfg-section <?= $sec !== 'seguridad' ? 'd-none' : '' ?>">

            <div class="card-jp mb-3">
                <div class="card-jp-header">
                    <span class="card-jp-title"><i class="bi bi-shield-lock-fill me-2" style="color:#dc2626"></i>Política de contraseñas y sesiones</span>
                </div>
                <div class="card-jp-body">
                    <form action="/configuracion/seguridad/save" method="POST">
                        <?= csrf_field() ?>
                        <div class="row g-3">
                            <div class="col-12 col-md-4">
                                <label class="form-label">Longitud mínima de contraseña</label>
                                <input type="number" name="sec_min_password" class="form-control-jp"
                                       min="6" max="32"
                                       value="<?= (int)($s['sec_min_password'] ?? 8) ?>">
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label">Tiempo de inactividad (minutos)</label>
                                <select name="sec_session_timeout" class="form-control-jp">
                                    <?php foreach ([5 => '5 min', 10 => '10 min', 15 => '15 min', 30 => '30 min', 60 => '1 hora'] as $val => $lbl): ?>
                                        <option value="<?= $val ?>" <?= (int)($s['sec_session_timeout'] ?? 10) === $val ? 'selected' : '' ?>><?= $lbl ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12">
                                <label class="form-label">Requisitos de contraseña</label>
                                <div class="d-flex flex-wrap gap-4 mt-1">
                                    <?php foreach ([
                                        ['sec_require_upper',   'Letra mayúscula'],
                                        ['sec_require_numbers', 'Número'],
                                        ['sec_require_special', 'Carácter especial (!@#$...)'],
                                    ] as [$key, $lbl]): ?>
                                    <label style="cursor:pointer;display:flex;align-items:center;gap:8px;font-size:13.5px">
                                        <input type="checkbox" name="<?= $key ?>" value="1"
                                               <?= !empty($s[$key]) ? 'checked' : '' ?>>
                                        <?= $lbl ?>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <div class="mt-4 d-flex justify-content-end">
                            <button type="submit" class="btn-jp btn-jp-primary">
                                <i class="bi bi-check-lg me-1"></i>Guardar configuración
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Log de actividad -->
            <div class="card-jp">
                <div class="card-jp-header">
                    <span class="card-jp-title"><i class="bi bi-clock-history me-2" style="color:var(--accent)"></i>Registro de actividad</span>
                    <span style="font-size:12px;color:var(--text-muted)">Últimas 50 acciones</span>
                </div>
                <div class="card-jp-body p-0">
                    <?php if (empty($logs)): ?>
                        <div class="empty-state p-4">
                            <i class="bi bi-list-ul" style="font-size:2rem;color:var(--text-muted)"></i>
                            <p class="mt-2 mb-0" style="color:var(--text-muted)">Sin actividad registrada.</p>
                        </div>
                    <?php else: ?>
                    <div class="table-responsive" style="max-height:400px;overflow-y:auto">
                        <table class="table-jp">
                            <thead>
                                <tr>
                                    <th>Fecha</th>
                                    <th>Usuario</th>
                                    <th>Acción</th>
                                    <th>Entidad</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($logs as $log): ?>
                                <tr>
                                    <td style="font-size:12px;white-space:nowrap">
                                        <?= date('d/m/Y H:i', strtotime($log['created_at'])) ?>
                                    </td>
                                    <td>
                                        <span style="font-size:12px;font-weight:600"><?= esc($log['user_name'] ?? 'Sistema') ?></span>
                                        <?php if (!empty($log['user_role'])): ?>
                                            <span style="font-size:11px;color:var(--text-muted)"> (<?= $log['user_role'] ?>)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td><span style="font-size:12px"><?= esc($log['action']) ?></span></td>
                                    <td style="font-size:12px"><?= esc($log['entity'] ?? '') ?><?= !empty($log['entity_id']) ? ' #' . $log['entity_id'] : '' ?></td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

        </div><!-- /sec-seguridad -->


        <!-- ────────────────────────────────────────────────────
             7. WEB PÚBLICA
        ──────────────────────────────────────────────────── -->
        <div id="sec-web" class="cfg-section <?= $sec !== 'web' ? 'd-none' : '' ?>">
            <div class="card-jp">
                <div class="card-jp-header">
                    <span class="card-jp-title"><i class="bi bi-globe2 me-2" style="color:#0891b2"></i>Web Pública</span>
                </div>
                <div class="card-jp-body">
                    <form action="/configuracion/web/save" method="POST">
                        <?= csrf_field() ?>

                        <!-- Estado web -->
                        <div class="d-flex align-items-center justify-content-between mb-4 p-3"
                             style="background:var(--bg-secondary);border-radius:8px;border:1px solid var(--border)">
                            <div>
                                <div style="font-weight:600;color:var(--text-h)">Web pública activa</div>
                                <div style="font-size:12px;color:var(--text-muted)">
                                    Activa cuando la web pública de la academia esté publicada y operativa.
                                </div>
                            </div>
                            <div class="form-check form-switch mb-0 ms-3">
                                <input class="form-check-input" type="checkbox" name="web_active" value="1"
                                       <?= !empty($s['web_active']) ? 'checked' : '' ?>>
                            </div>
                        </div>

                        <!-- Redes sociales -->
                        <p style="font-size:13px;font-weight:600;color:var(--text-h);margin-bottom:12px">
                            <i class="bi bi-share-fill me-1" style="color:var(--accent)"></i>Redes sociales
                        </p>
                        <div class="row g-3">
                            <?php foreach ([
                                ['web_instagram', 'bi-instagram',  '#e1306c', 'Instagram',  'https://instagram.com/academia'],
                                ['web_facebook',  'bi-facebook',   '#1877f2', 'Facebook',   'https://facebook.com/academia'],
                                ['web_twitter',   'bi-twitter-x',  '#000000', 'Twitter / X','https://x.com/academia'],
                                ['web_youtube',   'bi-youtube',    '#ff0000', 'YouTube',    'https://youtube.com/@academia'],
                                ['web_tiktok',    'bi-tiktok',     '#010101', 'TikTok',     'https://tiktok.com/@academia'],
                            ] as [$key, $icon, $color, $label, $placeholder]): ?>
                            <div class="col-12 col-md-6">
                                <label class="form-label">
                                    <i class="bi <?= $icon ?> me-1" style="color:<?= $color ?>"></i><?= $label ?>
                                </label>
                                <input type="url" name="<?= $key ?>" class="form-control-jp"
                                       value="<?= esc($s[$key] ?? '') ?>"
                                       placeholder="<?= $placeholder ?>">
                            </div>
                            <?php endforeach; ?>
                        </div>

                        <div class="mt-4 d-flex justify-content-end">
                            <button type="submit" class="btn-jp btn-jp-primary">
                                <i class="bi bi-check-lg me-1"></i>Guardar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div><!-- /sec-web -->

        <?php endif; // isAdmin ?>

    </div><!-- /col content -->
</div><!-- /row -->


<!-- ══════════════════════════════════════════════════════════
     MODALES
═══════════════════════════════════════════════════════════ -->

<!-- Modal: Añadir usuario de Staff -->
<div id="modalNewStaff" class="cfg-modal-overlay d-none">
    <div class="cfg-modal">
        <div class="cfg-modal-header">
            <span><i class="bi bi-person-plus-fill me-2"></i>Añadir usuario</span>
            <button onclick="closeModal('modalNewStaff')"><i class="bi bi-x-lg"></i></button>
        </div>
        <form action="/configuracion/staff/create" method="POST">
            <?= csrf_field() ?>
            <div class="cfg-modal-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Nombre completo <span style="color:var(--danger)">*</span></label>
                        <input type="text" name="name" class="form-control-jp" required placeholder="Nombre y apellidos">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Email <span style="color:var(--danger)">*</span></label>
                        <input type="email" name="email" class="form-control-jp" required placeholder="usuario@email.com">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Rol <span style="color:var(--danger)">*</span></label>
                        <select name="role" class="form-control-jp" required>
                            <option value="">— Seleccionar rol —</option>
                            <option value="admin">Admin</option>
                            <option value="staff">Staff (Secretaría, Fisio, etc.)</option>
                            <option value="coach">Entrenador</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <div class="alert-jp info" style="margin:0">
                            <i class="bi bi-key-fill me-1"></i>
                            Se generará una contraseña temporal automáticamente. Recuerda compartirla de forma segura.
                        </div>
                    </div>
                </div>
            </div>
            <div class="cfg-modal-footer">
                <button type="button" class="btn-jp btn-jp-secondary" onclick="closeModal('modalNewStaff')">Cancelar</button>
                <button type="submit" class="btn-jp btn-jp-primary"><i class="bi bi-check-lg me-1"></i>Crear usuario</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Sede (crear / editar) -->
<div id="modalSede" class="cfg-modal-overlay d-none">
    <div class="cfg-modal" style="max-width:640px">
        <div class="cfg-modal-header">
            <span id="modalSedeTitle"><i class="bi bi-geo-alt-fill me-2"></i>Nueva sede</span>
            <button onclick="closeModal('modalSede')"><i class="bi bi-x-lg"></i></button>
        </div>
        <form id="modalSedeForm" action="/configuracion/sedes/create" method="POST">
            <?= csrf_field() ?>
            <div class="cfg-modal-body">
                <div class="row g-3">
                    <div class="col-12 col-md-8">
                        <label class="form-label">Nombre <span style="color:var(--danger)">*</span></label>
                        <input type="text" name="name" id="sedeNombre" class="form-control-jp" required placeholder="Ej: Pabellón Municipal Norte">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Tipo <span style="color:var(--danger)">*</span></label>
                        <select name="type" id="sedeTipo" class="form-control-jp" required>
                            <option value="pitch">Pista / Campo</option>
                            <option value="gym">Gimnasio</option>
                            <option value="room">Sala</option>
                            <option value="office">Oficina</option>
                            <option value="other">Otro</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <label class="form-label">Dirección</label>
                        <input type="text" name="address" id="sedeDireccion" class="form-control-jp" placeholder="Calle, número, ciudad">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Descripción</label>
                        <textarea name="description" id="sedeDesc" class="form-control-jp" rows="2" placeholder="Descripción breve de las instalaciones"></textarea>
                    </div>
                    <div class="col-6 col-md-4">
                        <label class="form-label">Aforo (personas)</label>
                        <input type="number" name="capacity" id="sedeAforo" class="form-control-jp" min="1" placeholder="—">
                    </div>
                    <div class="col-6 col-md-4">
                        <label class="form-label">Teléfono</label>
                        <input type="text" name="phone" id="sedeTel" class="form-control-jp" placeholder="+34 600 000 000">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Estado</label>
                        <select name="active" id="sedeActivo" class="form-control-jp">
                            <option value="1">Activa</option>
                            <option value="0">Inactiva</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="cfg-modal-footer">
                <button type="button" class="btn-jp btn-jp-secondary" onclick="closeModal('modalSede')">Cancelar</button>
                <button type="submit" class="btn-jp btn-jp-primary"><i class="bi bi-check-lg me-1"></i>Guardar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Tipo de Bono (crear / editar) -->
<div id="modalBono" class="cfg-modal-overlay d-none">
    <div class="cfg-modal">
        <div class="cfg-modal-header">
            <span id="modalBonoTitle"><i class="bi bi-ticket-perforated-fill me-2"></i>Nuevo tipo de bono</span>
            <button onclick="closeModal('modalBono')"><i class="bi bi-x-lg"></i></button>
        </div>
        <form id="modalBonoForm" action="/configuracion/bonos/create" method="POST">
            <?= csrf_field() ?>
            <div class="cfg-modal-body">
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Nombre <span style="color:var(--danger)">*</span></label>
                        <input type="text" name="name" id="bonoNombre" class="form-control-jp" required placeholder="Ej: Bono 10 clases">
                    </div>
                    <div class="col-4">
                        <label class="form-label">Sesiones <span style="color:var(--danger)">*</span></label>
                        <input type="number" name="sessions" id="bonoSesiones" class="form-control-jp" required min="1" placeholder="10">
                    </div>
                    <div class="col-4">
                        <label class="form-label">Precio (€) <span style="color:var(--danger)">*</span></label>
                        <input type="number" name="price" id="bonoPrecio" class="form-control-jp" required min="0" step="0.01" placeholder="0.00">
                    </div>
                    <div class="col-4">
                        <label class="form-label">Validez (días) <span style="color:var(--danger)">*</span></label>
                        <input type="number" name="validity_days" id="bonoValidez" class="form-control-jp" required min="1" placeholder="90">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Estado</label>
                        <select name="active" id="bonoActivo" class="form-control-jp">
                            <option value="1">Activo</option>
                            <option value="0">Inactivo</option>
                        </select>
                    </div>
                </div>
            </div>
            <div class="cfg-modal-footer">
                <button type="button" class="btn-jp btn-jp-secondary" onclick="closeModal('modalBono')">Cancelar</button>
                <button type="submit" class="btn-jp btn-jp-primary"><i class="bi bi-check-lg me-1"></i>Guardar</button>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>


<?= $this->section('scripts') ?>
<style>
/* ── Modal overlay ───────────────────────────────────────────── */
.cfg-modal-overlay {
    position:fixed;inset:0;background:rgba(0,0,0,.55);
    z-index:1050;display:flex;align-items:center;justify-content:center;padding:16px;
}
.cfg-modal {
    background:var(--card-bg);border:1px solid var(--border);border-radius:12px;
    width:100%;max-width:520px;max-height:90vh;display:flex;flex-direction:column;
    box-shadow:0 20px 60px rgba(0,0,0,.4);
}
.cfg-modal-header {
    display:flex;align-items:center;justify-content:space-between;
    padding:16px 20px;border-bottom:1px solid var(--border);
    font-size:15px;font-weight:700;color:var(--text-h);
}
.cfg-modal-header button {
    background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:18px;line-height:1;
    width:32px;height:32px;display:flex;align-items:center;justify-content:center;border-radius:6px;
    transition:background .15s,color .15s;
}
.cfg-modal-header button:hover { background:var(--bg-secondary);color:var(--text-h); }
.cfg-modal-body { padding:20px;overflow-y:auto;flex:1; }
.cfg-modal-footer {
    display:flex;justify-content:flex-end;gap:8px;
    padding:16px 20px;border-top:1px solid var(--border);
}

/* ── Sidebar active link ─────────────────────────────────────── */
.sidebar-nav-link.active {
    background:var(--accent)18 !important;
    color:var(--accent) !important;
    border-radius:6px;
}
</style>

<script>
// ── Tabs ──────────────────────────────────────────────────────────────
const initialSection = '<?= esc($sec) ?>';

function showSection(name) {
    document.querySelectorAll('.cfg-section').forEach(el => el.classList.add('d-none'));
    document.querySelectorAll('.cfg-tab').forEach(el => el.classList.remove('active'));

    const sec = document.getElementById('sec-' + name);
    if (sec) sec.classList.remove('d-none');

    const tab = document.querySelector('.cfg-tab[data-section="' + name + '"]');
    if (tab) tab.classList.add('active');

    // Update URL without reload
    const url = new URL(window.location);
    url.searchParams.set('section', name);
    window.history.replaceState({}, '', url);
}

document.querySelectorAll('.cfg-tab').forEach(link => {
    link.addEventListener('click', e => {
        e.preventDefault();
        showSection(link.dataset.section);
    });
});

// ── Modales ───────────────────────────────────────────────────────────
function openModal(id) {
    document.getElementById(id).classList.remove('d-none');
    document.body.style.overflow = 'hidden';
}
function closeModal(id) {
    document.getElementById(id).classList.add('d-none');
    document.body.style.overflow = '';
}

// Cerrar con ESC o clic en overlay
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.cfg-modal-overlay:not(.d-none)').forEach(m => {
            closeModal(m.id);
        });
    }
});
document.querySelectorAll('.cfg-modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', e => {
        if (e.target === overlay) closeModal(overlay.id);
    });
});

// ── Modal Sede ────────────────────────────────────────────────────────
function openSedeModal(data) {
    const form  = document.getElementById('modalSedeForm');
    const title = document.getElementById('modalSedeTitle');

    if (data) {
        title.innerHTML = '<i class="bi bi-pencil-fill me-2"></i>Editar sede';
        form.action = '/configuracion/sedes/' + data.id + '/edit';
        document.getElementById('sedeNombre').value   = data.name        || '';
        document.getElementById('sedeTipo').value     = data.type        || 'pitch';
        document.getElementById('sedeDireccion').value= data.address     || '';
        document.getElementById('sedeDesc').value     = data.description || '';
        document.getElementById('sedeAforo').value    = data.capacity    || '';
        document.getElementById('sedeTel').value      = data.phone       || '';
        document.getElementById('sedeActivo').value   = data.active != null ? String(data.active) : '1';
    } else {
        title.innerHTML = '<i class="bi bi-geo-alt-fill me-2"></i>Nueva sede';
        form.action = '/configuracion/sedes/create';
        form.reset();
    }
    openModal('modalSede');
}

// ── Modal Bono ────────────────────────────────────────────────────────
function openBonoModal(data) {
    const form  = document.getElementById('modalBonoForm');
    const title = document.getElementById('modalBonoTitle');

    if (data) {
        title.innerHTML = '<i class="bi bi-pencil-fill me-2"></i>Editar tipo de bono';
        form.action = '/configuracion/bonos/' + data.id + '/edit';
        document.getElementById('bonoNombre').value   = data.name          || '';
        document.getElementById('bonoSesiones').value = data.sessions      || '';
        document.getElementById('bonoPrecio').value   = data.price         || '';
        document.getElementById('bonoValidez').value  = data.validity_days || '';
        document.getElementById('bonoActivo').value   = data.active != null ? String(data.active) : '1';
    } else {
        title.innerHTML = '<i class="bi bi-ticket-perforated-fill me-2"></i>Nuevo tipo de bono';
        form.action = '/configuracion/bonos/create';
        form.reset();
    }
    openModal('modalBono');
}

// ── Email: tipo de destinatario ───────────────────────────────────────
function toggleRecipientType(radio) {
    const individual = document.getElementById('recipientIndividual');
    const group      = document.getElementById('recipientGroup');
    if (radio.value === 'individual') {
        individual.classList.remove('d-none');
        group.classList.add('d-none');
    } else {
        individual.classList.add('d-none');
        group.classList.remove('d-none');
    }
}

// ── SMTP: mostrar/ocultar contraseña ─────────────────────────────────
function toggleSmtpPass() {
    const input = document.getElementById('smtpPassInput');
    const icon  = document.getElementById('smtpPassIcon');
    if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'bi bi-eye-slash-fill';
    } else {
        input.type = 'password';
        icon.className = 'bi bi-eye-fill';
    }
}

// ── Init ──────────────────────────────────────────────────────────────
showSection(initialSection);
</script>
<?= $this->endSection() ?>
