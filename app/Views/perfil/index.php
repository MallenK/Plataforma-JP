<?= $this->extend('layouts/app') ?>

<?php
$pageTitle    = 'Perfil';
$pageSubtitle = 'Información de cuenta';
$roleLabel = match($user['role'] ?? '') {
    'superadmin' => 'Super Admin',
    'admin'      => 'Administrador',
    'coach'      => 'Entrenador',
    'alumno'     => 'Alumno',
    'staff'      => 'Staff',
    default      => ucfirst($user['role'] ?? ''),
};
$name     = $user['name'] ?? '?';
$parts    = explode(' ', trim($name));
$initials = strtoupper(substr($parts[0], 0, 1));
if (count($parts) >= 2) {
    $initials = strtoupper(substr($parts[0], 0, 1) . substr($parts[1], 0, 1));
}
?>

<?= $this->section('page_content') ?>

<div class="page-header">
    <div class="page-header-text">
        <h2>Perfil de usuario</h2>
        <p>Información y configuración de la cuenta</p>
    </div>
    <a href="#" class="btn-jp btn-jp-secondary">
        <i class="bi bi-pencil"></i> Editar perfil
    </a>
</div>

<div class="row g-3">

    <!-- Card principal -->
    <div class="col-12 col-lg-4">
        <div class="card-jp">
            <div class="profile-header">
                <div class="profile-avatar-lg"><?= esc($initials) ?></div>
                <div>
                    <div class="profile-name"><?= esc($name) ?></div>
                    <div class="profile-email"><?= esc($user['email'] ?? '') ?></div>
                    <span class="badge-status active mt-2 d-inline-block"><?= esc($roleLabel) ?></span>
                </div>
            </div>
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
                            <?= isset($user['created_at']) ? date('M Y', strtotime($user['created_at'])) : '—' ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Información detallada -->
    <div class="col-12 col-lg-8 d-flex flex-column gap-3">

        <!-- Datos personales -->
        <div class="card-jp">
            <div class="card-jp-header">
                <span class="card-jp-title"><i class="bi bi-person-fill me-2" style="color:var(--accent)"></i>Datos personales</span>
                <a href="#" class="btn-jp btn-jp-secondary btn-jp-sm">
                    <i class="bi bi-pencil"></i> Editar
                </a>
            </div>
            <div class="card-jp-body">
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <div class="form-group mb-0">
                            <label class="form-label">Nombre completo</label>
                            <input type="text" class="form-control-jp" value="<?= esc($user['name'] ?? '') ?>" readonly>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="form-group mb-0">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control-jp" value="<?= esc($user['email'] ?? '') ?>" readonly>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="form-group mb-0">
                            <label class="form-label">Rol</label>
                            <input type="text" class="form-control-jp" value="<?= esc($roleLabel) ?>" readonly>
                        </div>
                    </div>
                </div>
            </div>
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
                    <a href="<?= base_url('forgot-password') ?>" class="btn-jp btn-jp-secondary btn-jp-sm">
                        <i class="bi bi-key-fill"></i> Cambiar contraseña
                    </a>
                </div>
            </div>
        </div>

    </div>

</div>

<?= $this->endSection() ?>
