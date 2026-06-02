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

$role = $user['role'] ?? '';

$isSelf  = ((int)($user['id'] ?? 0) === (int)session('id'));
$isAdmin = in_array(session('role'), ['superadmin', 'admin']);
$isPlayer = ($role === 'player');

// Datos del perfil deportivo (solo para alumnos)
$pfp = $playerFullProfile ?? null;
$categoryLabel = match($pfp['category'] ?? '') {
    'prebenjamin' => 'Prebenjamín',
    'benjamin'    => 'Benjamín',
    'alevin'      => 'Alevín',
    'infantil'    => 'Infantil',
    'cadete'      => 'Cadete',
    'juvenil'     => 'Juvenil',
    'junior'      => 'Júnior',
    'senior'      => 'Sénior',
    'veterano'    => 'Veterano',
    default       => '—',
};
$levelLabel = match($pfp['level'] ?? '') {
    'beginner'     => 'Principiante',
    'intermediate' => 'Intermedio',
    'advanced'     => 'Avanzado',
    default        => '—',
};
$pfpAge = null;
if (!empty($pfp['birth_date'])) {
    $pfpAge = (int)(new \DateTime($pfp['birth_date']))->diff(new \DateTime())->y;
}
$today = date('Y-m-d');

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

<?php if (session()->getFlashdata('success') || session()->getFlashdata('annotation_success')): ?>
<div class="alert-jp success mb-3"><i class="bi bi-check-circle-fill me-2"></i><?= esc(session()->getFlashdata('success') ?: session()->getFlashdata('annotation_success')) ?></div>
<?php endif; ?>
<?php if (session()->getFlashdata('error') || session()->getFlashdata('annotation_error')): ?>
<div class="alert-jp error mb-3"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= esc(session()->getFlashdata('error') ?: session()->getFlashdata('annotation_error')) ?></div>
<?php endif; ?>

<?php $newPassword = session()->getFlashdata('new_password'); if ($newPassword): ?>
<div class="alert-jp warning mb-3" style="border-left:4px solid var(--accent)">
    <div class="d-flex align-items-start gap-3">
        <i class="bi bi-key-fill" style="font-size:20px;color:var(--accent);margin-top:2px"></i>
        <div style="flex:1">
            <div style="font-weight:600;color:var(--text-h);margin-bottom:4px">
                Nueva contraseña generada
                <?php $forName = session()->getFlashdata('new_password_user'); if ($forName): ?>
                    para <?= esc($forName) ?>
                <?php endif; ?>
            </div>
            <div style="font-size:12px;color:var(--text-muted);margin-bottom:8px">
                Cópiala y entrégasela al usuario. Esta contraseña <strong>no se mostrará otra vez</strong>.
            </div>
            <div class="d-flex align-items-center gap-2">
                <code id="newPwdValue"
                      style="font-size:15px;font-weight:700;letter-spacing:1px;padding:6px 12px;background:#fff;border:1px solid var(--border);border-radius:6px;color:var(--accent-dark)">
                    <?= esc($newPassword) ?>
                </code>
                <button type="button" class="btn-jp btn-jp-secondary btn-jp-sm"
                        onclick="navigator.clipboard.writeText(document.getElementById('newPwdValue').textContent.trim()); this.innerHTML='<i class=\'bi bi-check-lg\'></i> Copiada'">
                    <i class="bi bi-clipboard"></i> Copiar
                </button>
            </div>
        </div>
    </div>
</div>
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

        <?php if ($isPlayer && $pfp): ?>
        <!-- Stats de actividad (alumno) -->
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
                        <span style="font-size:13px;color:var(--text-muted)"><i class="bi bi-calendar-check me-2"></i>Clases asistidas</span>
                        <span style="font-size:20px;font-weight:700;color:var(--text-h)"><?= (int)($user['classes_count'] ?? 0) ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span style="font-size:13px;color:var(--text-muted)"><i class="bi bi-calendar3 me-2"></i>Próximas clases</span>
                        <span style="font-size:20px;font-weight:700;color:var(--text-h)"><?= (int)($user['upcoming_count'] ?? 0) ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span style="font-size:13px;color:var(--text-muted)"><i class="bi bi-ticket-perforated-fill me-2"></i>Bonos activos</span>
                        <span style="font-size:20px;font-weight:700;color:var(--text-h)"><?= (int)($user['active_bonos'] ?? 0) ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Datos deportivos -->
        <div class="card-jp">
            <div class="card-jp-header">
                <span class="card-jp-title">
                    <i class="bi bi-person-badge-fill me-2" style="color:var(--accent)"></i>
                    Ficha deportiva
                </span>
            </div>
            <div class="card-jp-body">
                <div class="d-flex flex-column gap-3">
                    <?php if (!empty($pfp['birth_date'])): ?>
                    <div class="d-flex justify-content-between align-items-center">
                        <span style="font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px">Fecha nac.</span>
                        <span style="font-size:13px;font-weight:600;color:var(--text-h)"><?= date('d/m/Y', strtotime($pfp['birth_date'])) ?><?= $pfpAge !== null ? ' (' . $pfpAge . ' años)' : '' ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($pfp['category'])): ?>
                    <div class="d-flex justify-content-between align-items-center">
                        <span style="font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px">Categoría</span>
                        <span style="font-size:13px;font-weight:600;color:var(--text-h)"><?= esc($categoryLabel) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($pfp['level'])): ?>
                    <div class="d-flex justify-content-between align-items-center">
                        <span style="font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px">Nivel</span>
                        <span style="font-size:13px;font-weight:600;color:var(--text-h)"><?= esc($levelLabel) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($pfp['position'])): ?>
                    <div class="d-flex justify-content-between align-items-center">
                        <span style="font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px">Posición</span>
                        <span style="font-size:13px;font-weight:600;color:var(--text-h)"><?= esc($pfp['position']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($pfp['team'])): ?>
                    <div class="d-flex justify-content-between align-items-center">
                        <span style="font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px">Equipo</span>
                        <span style="font-size:13px;font-weight:600;color:var(--text-h)"><?= esc($pfp['team']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($pfp['league'])): ?>
                    <div class="d-flex justify-content-between align-items-center">
                        <span style="font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px">Liga</span>
                        <span style="font-size:13px;font-weight:600;color:var(--text-h)"><?= esc($pfp['league']) ?></span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($pfp['height'])): ?>
                    <div class="d-flex justify-content-between align-items-center">
                        <span style="font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px">Altura</span>
                        <span style="font-size:13px;font-weight:600;color:var(--text-h)"><?= esc($pfp['height']) ?> cm</span>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($pfp['weight'])): ?>
                    <div class="d-flex justify-content-between align-items-center">
                        <span style="font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px">Peso</span>
                        <span style="font-size:13px;font-weight:600;color:var(--text-h)"><?= esc($pfp['weight']) ?> kg</span>
                    </div>
                    <?php endif; ?>
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

        <?php if ($isPlayer && $pfp): ?>
        <!-- Métricas: posición, categoría, altura, peso -->
        <div class="row g-2 mb-1">
            <?php
            $metrics4 = [
                ['label' => 'Posición',  'val' => $pfp['position'] ?? '—',                               'icon' => 'bi-geo-alt-fill',       'color' => 'var(--accent)'],
                ['label' => 'Categoría', 'val' => ($pfp['category'] ?? '') ? $categoryLabel : '—',       'icon' => 'bi-trophy-fill',         'color' => '#f59e0b'],
                ['label' => 'Altura',    'val' => ($pfp['height']   ?? '') ? $pfp['height'] . ' cm' : '—', 'icon' => 'bi-arrows-vertical',   'color' => '#f97316'],
                ['label' => 'Peso',      'val' => ($pfp['weight']   ?? '') ? $pfp['weight'] . ' kg' : '—', 'icon' => 'bi-activity',          'color' => '#8b5cf6'],
            ];
            foreach ($metrics4 as $m): ?>
            <div class="col-6">
                <div class="card-jp" style="padding:12px 14px">
                    <div style="font-size:11px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px">
                        <?= $m['label'] ?>
                    </div>
                    <div style="font-size:18px;font-weight:700;color:var(--text-h)"><?= esc($m['val']) ?></div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>

        <!-- Planes / Bonos -->
        <?php if (!empty($pfp['plans'])): ?>
        <div class="card-jp">
            <div class="card-jp-header">
                <span class="card-jp-title">
                    <i class="bi bi-ticket-perforated-fill me-2" style="color:var(--accent)"></i>
                    Planes / Bonos
                </span>
                <span style="font-size:12px;color:var(--text-muted)"><?= count($pfp['plans']) ?> registrado(s)</span>
            </div>
            <div class="table-responsive">
                <table class="table-jp">
                    <thead>
                        <tr>
                            <th>Bono</th>
                            <th>Sesiones</th>
                            <th>Vigencia</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($pfp['plans'] as $plan):
                        $remaining = (int)($plan['sessions_remaining'] ?? 0);
                        $total     = (int)($plan['sessions_total']     ?? 0);
                        $pct       = $total > 0 ? min(100, round($remaining / $total * 100)) : 0;
                        $expired   = !empty($plan['expires_at']) && $plan['expires_at'] < $today;
                        $active    = !$expired && $remaining > 0;
                        $badgeClass = $expired ? 'badge-status inactive' : ($active ? 'badge-status active' : 'badge-status inactive');
                        $badgeLabel = $expired ? 'Vencido' : ($active ? 'Activo' : 'Agotado');
                        $isActiveBono = isset($pfp['active_bono_id']) && (int)$plan['id'] === (int)$pfp['active_bono_id'];
                    ?>
                    <tr>
                        <td>
                            <div style="font-weight:600;color:var(--text-h);font-size:13px">
                                <?= esc($plan['bono_name'] ?? '—') ?>
                                <?php if ($isActiveBono): ?>
                                    <span style="font-size:10px;background:var(--accent);color:#fff;border-radius:3px;padding:1px 5px;margin-left:4px">ACTIVO</span>
                                <?php endif; ?>
                            </div>
                            <div style="font-size:11px;color:var(--text-muted)"><?= number_format((float)($plan['price'] ?? 0), 2) ?> €</div>
                        </td>
                        <td>
                            <div style="font-size:13px;font-weight:600;color:var(--text-h)"><?= $remaining ?> / <?= $total ?></div>
                            <div style="margin-top:4px;height:4px;background:var(--border);border-radius:2px;width:80px">
                                <div style="height:4px;border-radius:2px;background:var(--accent);width:<?= $pct ?>%"></div>
                            </div>
                        </td>
                        <td style="font-size:12px;color:var(--text-muted);white-space:nowrap">
                            <?php if (!empty($plan['start_date']) && !empty($plan['expires_at'])): ?>
                                <?= date('d/m/Y', strtotime($plan['start_date'])) ?> → <?= date('d/m/Y', strtotime($plan['expires_at'])) ?>
                            <?php elseif (!empty($plan['start_date'])): ?>
                                Desde <?= date('d/m/Y', strtotime($plan['start_date'])) ?>
                            <?php else: ?>
                                —
                            <?php endif; ?>
                        </td>
                        <td><span class="<?= $badgeClass ?>"><?= $badgeLabel ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Notas médicas -->
        <?php if (!empty($pfp['medical_notes'])): ?>
        <div class="card-jp">
            <div class="card-jp-header">
                <span class="card-jp-title">
                    <i class="bi bi-clipboard2-pulse-fill me-2" style="color:var(--danger)"></i>
                    Notas médicas
                </span>
            </div>
            <div class="card-jp-body">
                <p style="font-size:13.5px;color:var(--text-body);margin:0;line-height:1.6">
                    <?= nl2br(esc($pfp['medical_notes'])) ?>
                </p>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>

        <!-- Seguridad -->
        <div class="card-jp">
            <div class="card-jp-header">
                <span class="card-jp-title"><i class="bi bi-shield-lock-fill me-2" style="color:var(--success)"></i>Seguridad</span>
            </div>
            <div class="card-jp-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div style="font-size:13.5px;font-weight:600;color:var(--text-h)">Contraseña</div>
                        <div style="font-size:12px;color:var(--text-muted)">
                            <?php if ($isProtected): ?>
                                <i class="bi bi-lock-fill"></i> Perfil protegido — no modificable desde la plataforma
                            <?php else: ?>
                                Última modificación desconocida
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($isSelf && !$isProtected): ?>
                    <a href="<?= base_url('forgot-password') ?>" class="btn-jp btn-jp-secondary btn-jp-sm">
                        <i class="bi bi-key-fill"></i> Cambiar contraseña
                    </a>
                    <?php elseif ($isAdmin && !$isSelf && !$isProtected): ?>
                    <form action="<?= base_url('perfil/' . (int)$user['id'] . '/reset-password') ?>"
                          method="POST"
                          onsubmit="return confirm('¿Generar una nueva contraseña para <?= esc($name, 'js') ?>?\n\nLa contraseña actual dejará de funcionar inmediatamente.')">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn-jp btn-jp-primary btn-jp-sm">
                            <i class="bi bi-key-fill"></i> Generar nueva contraseña
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Documentos personales -->
        <?php
        $perfDocPreviewExts = ['pdf','jpg','jpeg','png','gif','webp','mp4','webm'];
        function perfilDocIcon(string $ext): array {
            return match(true) {
                $ext === 'pdf'                                    => ['bi-file-earmark-pdf-fill',   '#e53e3e'],
                in_array($ext, ['doc','docx'])                    => ['bi-file-earmark-word-fill',  '#3182ce'],
                in_array($ext, ['xls','xlsx'])                    => ['bi-file-earmark-excel-fill', '#38a169'],
                in_array($ext, ['ppt','pptx'])                    => ['bi-file-earmark-ppt-fill',   '#dd6b20'],
                in_array($ext, ['jpg','jpeg','png','gif','webp']) => ['bi-file-earmark-image-fill', '#805ad5'],
                in_array($ext, ['mp4','mov','avi','webm'])        => ['bi-file-earmark-play-fill',  '#00b5d8'],
                default                                           => ['bi-file-earmark-fill',       'var(--text-muted)'],
            };
        }
        ?>
        <div class="card-jp">
            <div class="card-jp-header">
                <span class="card-jp-title">
                    <i class="bi bi-folder-fill me-2" style="color:var(--accent)"></i>
                    Documentos
                </span>
                <span style="font-size:12px;color:var(--text-muted)"><?= count($documents ?? []) ?> archivo(s)</span>
            </div>
            <?php if (!empty($documents)): ?>
            <div class="table-responsive">
                <table class="table-jp">
                    <thead>
                        <tr>
                            <th>Archivo</th>
                            <th>Fecha</th>
                            <th style="text-align:right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($documents as $doc):
                        [$dicon, $dcolor] = perfilDocIcon($doc['extension'] ?? '');
                        $canPreview = in_array($doc['extension'] ?? '', $perfDocPreviewExts);
                    ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi <?= esc($dicon) ?>" style="font-size:18px;color:<?= $dcolor ?>;flex-shrink:0"></i>
                                <div>
                                    <div style="font-weight:600;color:var(--text-h);font-size:13px"><?= esc($doc['name_original']) ?></div>
                                    <?php if (!empty($doc['description'])): ?>
                                    <div style="font-size:11px;color:var(--text-muted)"><?= esc($doc['description']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td style="font-size:12px;color:var(--text-muted);white-space:nowrap">
                            <?= date('d/m/Y', strtotime($doc['created_at'])) ?>
                        </td>
                        <td>
                            <div class="d-flex gap-1 justify-content-end">
                                <?php if ($canPreview): ?>
                                <a href="<?= base_url('documentacion/file/' . (int)$doc['id'] . '/preview') ?>"
                                   target="_blank"
                                   class="btn-jp btn-jp-secondary btn-jp-sm btn-jp-icon" title="Previsualizar">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <?php endif; ?>
                                <a href="<?= base_url('documentacion/file/' . (int)$doc['id'] . '/download') ?>"
                                   class="btn-jp btn-jp-secondary btn-jp-sm btn-jp-icon" title="Descargar">
                                    <i class="bi bi-download"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="card-jp-body">
                <p style="font-size:13px;color:var(--text-muted);margin:0 0 12px 0">Sin documentos todavía.</p>
            </div>
            <?php endif; ?>
            <?php if (!empty($personalFolder)): ?>
            <div class="card-jp-body" style="border-top:1px solid var(--border)">
                <form method="post" action="<?= base_url('documentacion/upload') ?>" enctype="multipart/form-data"
                      class="d-flex gap-2 align-items-center flex-wrap">
                    <?= csrf_field() ?>
                    <input type="hidden" name="folder_id" value="<?= (int)$personalFolder['id'] ?>">
                    <input type="hidden" name="redirect_to" value="/perfil/<?= (int)($user['id'] ?? 0) ?>">
                    <input type="file" name="archivo" class="form-control-jp" style="flex:1;min-width:200px"
                           accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.webp,.mp4,.mov,.avi,.webm" required>
                    <input type="text" name="description" class="form-control-jp" placeholder="Descripción (opcional)" style="flex:1;min-width:160px">
                    <button type="submit" class="btn-jp btn-jp-primary btn-jp-sm" style="white-space:nowrap">
                        <i class="bi bi-cloud-upload-fill me-1"></i>Subir documento
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>

    </div>

</div>

<?php if ($isPlayer && isset($annotations)): ?>
<?php
$annCurrentUserId  = session('id');
$annCurrentRole    = session('role');
$annIsAdmin        = in_array($annCurrentRole, ['superadmin', 'admin']);
$annCanInternal    = in_array($annCurrentRole, ['superadmin', 'admin', 'coach', 'staff']);
$annIsPlayerSelf   = !$annIsAdmin;

$publicAnnotations   = array_filter($annotations, fn($a) => $a['type'] === 'public');
$internalAnnotations = array_filter($annotations, fn($a) => $a['type'] === 'internal');
?>
<div id="anotaciones" class="d-flex flex-column gap-3 mt-3">

    <!-- Anotaciones públicas -->
    <div class="card-jp">
        <div class="card-jp-header">
            <span class="card-jp-title">
                <i class="bi bi-chat-square-text-fill me-2" style="color:var(--accent)"></i>
                Anotaciones
            </span>
            <span style="font-size:12px;color:var(--text-muted)"><?= count($publicAnnotations) ?> anotación(es)</span>
        </div>

        <div class="card-jp-body d-flex flex-column gap-2">
            <?php if (empty($publicAnnotations)): ?>
                <p style="font-size:13px;color:var(--text-muted);margin:0">Sin anotaciones todavía.</p>
            <?php else: ?>
                <?php foreach ($publicAnnotations as $ann): ?>
                <?php $annCanDelete = (int)$ann['author_id'] === (int)$annCurrentUserId || $annIsAdmin; ?>
                <div style="background:var(--bg-card-inner,rgba(0,0,0,.04));border-radius:8px;padding:12px 14px;position:relative">
                    <div style="font-size:13.5px;color:var(--text-body);line-height:1.55;white-space:pre-wrap"><?= nl2br(esc($ann['content'])) ?></div>
                    <?php if (!empty($ann['document_id']) && !empty($ann['doc_name'])): ?>
                    <div style="margin-top:8px;padding:6px 10px;background:var(--bg-app);border-radius:6px;display:inline-flex;align-items:center;gap:8px;font-size:12px;color:var(--text-muted)">
                        <i class="bi bi-paperclip" style="color:var(--accent)"></i>
                        <span><?= esc($ann['doc_name']) ?></span>
                        <a href="<?= base_url('documentacion/file/' . (int)$ann['document_id'] . '/download') ?>"
                           class="btn-jp btn-jp-secondary btn-jp-sm btn-jp-icon" title="Descargar" style="padding:2px 5px">
                            <i class="bi bi-download" style="font-size:11px"></i>
                        </a>
                    </div>
                    <?php endif; ?>
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-top:8px">
                        <span style="font-size:11px;color:var(--text-muted)">
                            <i class="bi bi-person-fill me-1"></i><?= esc($ann['author_name']) ?>
                            &nbsp;·&nbsp;<?= date('d/m/Y H:i', strtotime($ann['created_at'])) ?>
                        </span>
                        <?php if ($annCanDelete): ?>
                        <form action="<?= base_url('anotaciones/' . $ann['id'] . '/eliminar') ?>" method="post"
                              onsubmit="return confirm('¿Eliminar esta anotación?')">
                            <?= csrf_field() ?>
                            <button type="submit" style="background:none;border:none;color:var(--danger);font-size:12px;cursor:pointer;padding:0">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Formulario nueva anotación pública -->
        <div class="card-jp-body" style="border-top:1px solid var(--border)">
            <form action="<?= base_url('alumnos/' . (int)$user['id'] . '/anotaciones') ?>" method="post" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="type" value="public">
                <div class="form-group mb-2">
                    <textarea name="content" class="form-control-jp" rows="2"
                              placeholder="Añadir anotación..." required
                              style="resize:vertical;min-height:64px"></textarea>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <label style="font-size:12px;color:var(--text-muted);cursor:pointer;display:flex;align-items:center;gap:5px">
                        <i class="bi bi-paperclip" style="color:var(--accent)"></i>
                        <span id="perfil-ann-pub-filename">Adjuntar archivo (opcional)</span>
                        <input type="file" name="annotation_file" style="display:none"
                               accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.webp,.mp4,.mov,.avi,.webm"
                               onchange="document.getElementById('perfil-ann-pub-filename').textContent = this.files[0]?.name || 'Adjuntar archivo (opcional)'">
                    </label>
                    <button type="submit" class="btn-jp btn-jp-primary ms-auto" style="padding:6px 16px;font-size:13px">
                        <i class="bi bi-plus-circle me-1"></i>Añadir anotación
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Anotaciones internas (solo staff/admin/coach) -->
    <?php if ($annCanInternal): ?>
    <div class="card-jp">
        <div class="card-jp-header">
            <span class="card-jp-title">
                <i class="bi bi-shield-lock-fill me-2" style="color:var(--warning,#f59e0b)"></i>
                Notas internas del cuerpo técnico
            </span>
            <span style="font-size:12px;color:var(--text-muted)"><?= count($internalAnnotations) ?> nota(s)</span>
        </div>

        <div class="card-jp-body d-flex flex-column gap-2">
            <?php if (empty($internalAnnotations)): ?>
                <p style="font-size:13px;color:var(--text-muted);margin:0">Sin notas internas todavía.</p>
            <?php else: ?>
                <?php foreach ($internalAnnotations as $ann): ?>
                <?php $annCanDelete = (int)$ann['author_id'] === (int)$annCurrentUserId || $annIsAdmin; ?>
                <div style="background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.2);border-radius:8px;padding:12px 14px">
                    <div style="font-size:13.5px;color:var(--text-body);line-height:1.55;white-space:pre-wrap"><?= nl2br(esc($ann['content'])) ?></div>
                    <?php if (!empty($ann['document_id']) && !empty($ann['doc_name'])): ?>
                    <div style="margin-top:8px;padding:6px 10px;background:var(--bg-app);border-radius:6px;display:inline-flex;align-items:center;gap:8px;font-size:12px;color:var(--text-muted)">
                        <i class="bi bi-paperclip" style="color:#f59e0b"></i>
                        <span><?= esc($ann['doc_name']) ?></span>
                        <a href="<?= base_url('documentacion/file/' . (int)$ann['document_id'] . '/download') ?>"
                           class="btn-jp btn-jp-secondary btn-jp-sm btn-jp-icon" title="Descargar" style="padding:2px 5px">
                            <i class="bi bi-download" style="font-size:11px"></i>
                        </a>
                    </div>
                    <?php endif; ?>
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-top:8px">
                        <span style="font-size:11px;color:var(--text-muted)">
                            <i class="bi bi-person-fill me-1"></i><?= esc($ann['author_name']) ?>
                            &nbsp;·&nbsp;<?= date('d/m/Y H:i', strtotime($ann['created_at'])) ?>
                        </span>
                        <?php if ($annCanDelete): ?>
                        <form action="<?= base_url('anotaciones/' . $ann['id'] . '/eliminar') ?>" method="post"
                              onsubmit="return confirm('¿Eliminar esta nota interna?')">
                            <?= csrf_field() ?>
                            <button type="submit" style="background:none;border:none;color:var(--danger);font-size:12px;cursor:pointer;padding:0">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <div class="card-jp-body" style="border-top:1px solid var(--border)">
            <form action="<?= base_url('alumnos/' . (int)$user['id'] . '/anotaciones') ?>" method="post" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="type" value="internal">
                <div class="form-group mb-2">
                    <textarea name="content" class="form-control-jp" rows="2"
                              placeholder="Añadir nota interna..." required
                              style="resize:vertical;min-height:64px"></textarea>
                </div>
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <label style="font-size:12px;color:var(--text-muted);cursor:pointer;display:flex;align-items:center;gap:5px">
                        <i class="bi bi-paperclip" style="color:#f59e0b"></i>
                        <span id="perfil-ann-int-filename">Adjuntar archivo (opcional)</span>
                        <input type="file" name="annotation_file" style="display:none"
                               accept=".pdf,.doc,.docx,.xls,.xlsx,.jpg,.jpeg,.png,.gif,.webp,.mp4,.mov,.avi,.webm"
                               onchange="document.getElementById('perfil-ann-int-filename').textContent = this.files[0]?.name || 'Adjuntar archivo (opcional)'">
                    </label>
                    <button type="submit" class="btn-jp btn-jp-primary ms-auto" style="padding:6px 16px;font-size:13px;background:var(--warning,#f59e0b);border-color:var(--warning,#f59e0b)">
                        <i class="bi bi-shield-plus me-1"></i>Añadir nota interna
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

</div>
<?php endif; ?>

<?= $this->endSection() ?>
