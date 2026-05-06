<?= $this->extend('layouts/app') ?>

<?php
helper('avatar');
$pageTitle    = 'Perfil';
$pageSubtitle = 'Información de cuenta';
$roleLabel = match($user['role'] ?? '') {
    'superadmin' => 'Super Admin',
    'admin'      => 'Administrador',
    'coach'      => 'Entrenador',
    'alumno'     => 'Alumno',
    'player'     => 'Alumno',
    'staff'      => 'Staff',
    default      => ucfirst($user['role'] ?? ''),
};
$name        = $user['name']   ?? '?';
$userAvatar  = $user['avatar'] ?? null;
$staffTitle  = trim((string)($user['staff_title'] ?? ''));

$isSelf  = ((int)($user['id'] ?? 0) === (int)session('id'));
$isAdmin = in_array(session('role'), ['superadmin', 'admin']);

// Usuario protegido (id=2 o email maestro): nadie lo puede editar
$isProtected = ((int)($user['id'] ?? 0) === 2)
    || (strtolower((string)($user['email'] ?? '')) === 'sergimallenweb@gmail.com');

$canEdit       = !$isProtected && ($isSelf || $isAdmin);
$canEditTitle  = !$isProtected && $isAdmin;

$role         = $user['role'] ?? '';
$isStaffRole  = in_array($role, ['staff', 'coach', 'admin'], true);
$showActivity = in_array($role, ['staff', 'coach'], true);
$backUrl      = $isAdmin && !$isSelf
    ? ($isStaffRole ? base_url('configuracion?section=staff') : null)
    : null;

$updateUrl = $isSelf
    ? base_url('perfil/update')
    : base_url('perfil/' . (int)$user['id'] . '/update');

$uploadUrl = $isSelf
    ? base_url('avatar/upload')
    : base_url('avatar/upload/' . $user['id']);
$deleteUrl = $isSelf
    ? base_url('avatar/delete')
    : base_url('avatar/delete/' . $user['id']);

// Stats placeholders — se rellenarán cuando se reescriban CoachService/PlayerService
$sessionsCount  = (int)($user['sessions_count']  ?? 0);
$upcomingCount  = (int)($user['upcoming_count']  ?? 0);
$studentsCount  = (int)($user['students_count']  ?? 0);
?>

<?= $this->section('page_content') ?>

<?php if (session()->getFlashdata('success')): ?>
<div class="alert-jp success mb-3"><i class="bi bi-check-circle-fill me-2"></i><?= esc(session()->getFlashdata('success')) ?></div>
<?php endif; ?>
<?php if (session()->getFlashdata('error')): ?>
<div class="alert-jp error mb-3"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif; ?>

<div class="page-header">
    <div class="page-header-text">
        <h2><?= $isSelf ? 'Mi perfil' : esc($name) ?></h2>
        <p><?= $isSelf ? 'Información y configuración de tu cuenta' : 'Perfil del usuario' ?></p>
    </div>
    <?php if ($backUrl): ?>
    <div class="d-flex gap-2">
        <a href="<?= esc($backUrl) ?>" class="btn-jp btn-jp-secondary">
            <i class="bi bi-arrow-left"></i> Volver
        </a>
    </div>
    <?php endif; ?>
</div>

<div class="row g-3">

    <!-- Card principal -->
    <div class="col-12 col-lg-4 d-flex flex-column gap-3">
        <div class="card-jp">
            <div class="profile-header">

                <!-- Avatar -->
                <div style="position:relative;display:inline-block">
                    <?= avatar_html($userAvatar, $name, 'profile-avatar-lg') ?>
                    <?php if ($canEdit): ?>
                    <button onclick="document.getElementById('avatarInput').click()"
                            title="Cambiar foto"
                            style="position:absolute;bottom:2px;right:2px;width:28px;height:28px;border-radius:50%;background:var(--accent);border:2px solid #fff;color:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:13px;padding:0;">
                        <i class="bi bi-camera-fill"></i>
                    </button>
                    <?php endif; ?>
                </div>

                <div>
                    <div class="profile-name"><?= esc($name) ?></div>
                    <div class="profile-email"><?= esc($user['email'] ?? '') ?></div>
                    <span class="badge-status active mt-2 d-inline-block"><?= esc($roleLabel) ?></span>
                    <?php if ($staffTitle !== ''): ?>
                        <div style="font-size:12px;color:var(--accent);font-weight:600;margin-top:6px">
                            <i class="bi bi-briefcase-fill"></i> <?= esc($staffTitle) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Formularios de avatar (ocultos) -->
            <?php if ($canEdit): ?>
            <div class="card-jp-body pt-0 pb-3 text-center">
                <form id="avatarForm" action="<?= esc($uploadUrl) ?>" method="post" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="file" id="avatarInput" name="avatar"
                           accept="image/jpeg,image/png,image/webp,image/gif"
                           style="display:none"
                           onchange="this.form.submit()">
                </form>
                <?php if ($userAvatar): ?>
                <form action="<?= esc($deleteUrl) ?>" method="post" style="margin-top:4px">
                    <?= csrf_field() ?>
                    <button type="submit"
                            onclick="return confirm('¿Eliminar el avatar?')"
                            style="background:none;border:none;font-size:12px;color:var(--danger);cursor:pointer;padding:0;text-decoration:underline">
                        <i class="bi bi-trash"></i> Eliminar foto
                    </button>
                </form>
                <?php else: ?>
                <p style="font-size:11px;color:var(--text-muted);margin:4px 0 0">
                    Haz clic en la cámara para subir una foto
                </p>
                <?php endif; ?>
            </div>
            <?php endif; ?>

            <div class="card-jp-body">
                <div class="d-flex flex-column gap-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <span style="font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px">ID de usuario</span>
                        <span style="font-size:13px;font-weight:600;color:var(--text-h)">#<?= esc($user['id'] ?? '—') ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span style="font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px">Estado</span>
                        <span class="badge-status <?= esc($user['status'] ?? 'active') ?>">
                            <?= match($user['status'] ?? 'active') {
                                'active'   => 'Activo',
                                'inactive' => 'Inactivo',
                                'banned'   => 'Bloqueado',
                                default    => 'Activo',
                            } ?>
                        </span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span style="font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px">Miembro desde</span>
                        <span style="font-size:13px;font-weight:600;color:var(--text-h)">
                            <?= isset($user['created_at']) ? date('d/m/Y', strtotime($user['created_at'])) : '—' ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <?php if ($showActivity): ?>
        <!-- Stats de actividad (visible para staff/coach) -->
        <div class="card-jp">
            <div class="card-jp-header">
                <span class="card-jp-title">
                    <i class="bi bi-bar-chart-fill me-2" style="color:var(--accent)"></i>
                    Actividad
                </span>
            </div>
            <div class="card-jp-body">
                <div class="d-flex flex-column gap-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <span style="font-size:13px;color:var(--text-muted)"><i class="bi bi-calendar-check me-2"></i>Sesiones dirigidas</span>
                        <span style="font-size:20px;font-weight:700;color:var(--text-h)"><?= $sessionsCount ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span style="font-size:13px;color:var(--text-muted)"><i class="bi bi-calendar3 me-2"></i>Próximas sesiones</span>
                        <span style="font-size:20px;font-weight:700;color:var(--text-h)"><?= $upcomingCount ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span style="font-size:13px;color:var(--text-muted)"><i class="bi bi-people-fill me-2"></i>Alumnos trabajados</span>
                        <span style="font-size:20px;font-weight:700;color:var(--text-h)"><?= $studentsCount ?></span>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- Información detallada -->
    <div class="col-12 col-lg-8 d-flex flex-column gap-3">

        <!-- Datos personales -->
        <div class="card-jp">
            <div class="card-jp-header">
                <span class="card-jp-title"><i class="bi bi-person-fill me-2" style="color:var(--accent)"></i>Datos personales</span>
                <?php if ($isProtected): ?>
                    <span style="font-size:11px;color:var(--text-muted)">
                        <i class="bi bi-lock-fill"></i> Perfil protegido
                    </span>
                <?php endif; ?>
            </div>
            <form action="<?= esc($updateUrl) ?>" method="POST">
                <?= csrf_field() ?>
                <div class="card-jp-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <div class="form-group mb-0">
                                <label class="form-label">Nombre completo</label>
                                <input type="text" name="name" class="form-control-jp"
                                       value="<?= esc(old('name', $user['name'] ?? '')) ?>"
                                       <?= $canEdit ? 'required minlength="3" maxlength="150"' : 'readonly' ?>>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="form-group mb-0">
                                <label class="form-label">Email</label>
                                <input type="email" name="email" class="form-control-jp"
                                       value="<?= esc(old('email', $user['email'] ?? '')) ?>"
                                       <?= $canEdit ? 'required' : 'readonly' ?>>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="form-group mb-0">
                                <label class="form-label">Rol</label>
                                <input type="text" class="form-control-jp" value="<?= esc($roleLabel) ?>" readonly>
                            </div>
                        </div>
                        <?php if ($isStaffRole): ?>
                        <div class="col-12 col-md-6">
                            <div class="form-group mb-0">
                                <label class="form-label">Cargo / puesto específico</label>
                                <input type="text" name="staff_title" class="form-control-jp"
                                       value="<?= esc(old('staff_title', $staffTitle)) ?>"
                                       maxlength="100"
                                       placeholder="Ej: Director técnico, Recepción..."
                                       <?= $canEditTitle ? '' : 'readonly' ?>>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php if ($canEdit): ?>
                    <div class="d-flex justify-content-end mt-3">
                        <button type="submit" class="btn-jp btn-jp-primary">
                            <i class="bi bi-check-lg"></i> Guardar cambios
                        </button>
                    </div>
                    <?php endif; ?>
                </div>
            </form>
        </div>

        <!-- Seguridad -->
        <div class="card-jp">
            <div class="card-jp-header">
                <span class="card-jp-title"><i class="bi bi-shield-lock-fill me-2" style="color:var(--success)"></i>Seguridad</span>
            </div>
            <div class="card-jp-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div style="font-size:13.5px;font-weight:600;color:var(--text-h)">Contraseña</div>
                        <div style="font-size:12px;color:var(--text-muted)">Última modificación desconocida</div>
                    </div>
                    <?php if ($isSelf): ?>
                    <a href="<?= base_url('forgot-password') ?>" class="btn-jp btn-jp-secondary btn-jp-sm">
                        <i class="bi bi-key-fill"></i> Cambiar contraseña
                    </a>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>

</div>

<?= $this->endSection() ?>
