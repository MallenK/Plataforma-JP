<?= $this->extend('layouts/app') ?>

<?php
helper('avatar');
$pageTitle    = esc($coach['name'] ?? 'Entrenador');
$pageSubtitle = 'Perfil del entrenador';

$name        = $coach['name']   ?? '?';
$userAvatar  = $coach['avatar'] ?? null;
$staffTitle  = trim((string)($coach['staff_title'] ?? ''));
$isAdminUser = in_array(session('role'), ['superadmin', 'admin']);

$statusLabel = match($coach['status'] ?? 'active') {
    'active'   => 'Activo',
    'inactive' => 'Inactivo',
    'banned'   => 'Bloqueado',
    default    => ucfirst($coach['status'] ?? ''),
};

$sessionsCount = (int)($coach['sessions_count'] ?? 0);
$upcomingCount = (int)($coach['upcoming_count'] ?? 0);
$playersCount  = (int)($coach['players_count']  ?? 0);

$sessionsList = $coach['sessions'] ?? [];
$upcomingList = $coach['upcoming'] ?? [];
$playersList  = $coach['players']  ?? [];

$formatTime = static function (?string $hms): string {
    if (!$hms) return '';
    return substr($hms, 0, 5);
};
?>

<?= $this->section('page_content') ?>

<?php if (session()->getFlashdata('success')): ?>
<div class="alert-jp success" style="display:flex;align-items:center;gap:10px;margin-bottom:16px">
    <i class="bi bi-check-circle-fill"></i>
    <?= esc(session()->getFlashdata('success')) ?>
</div>
<?php endif; ?>

<!-- Cabecera -->
<div class="page-header">
    <div class="page-header-text">
        <h2><?= esc($name) ?></h2>
        <p>Perfil del entrenador</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= base_url('entrenadores') ?>" class="btn-jp btn-jp-secondary">
            <i class="bi bi-arrow-left"></i> Listado
        </a>
        <?php if ($isAdminUser): ?>
        <a href="<?= base_url('entrenadores/' . $coach['id'] . '/editar') ?>" class="btn-jp btn-jp-primary">
            <i class="bi bi-pencil"></i> Editar
        </a>
        <?php endif; ?>
    </div>
</div>

<div class="row g-3">

    <!-- ── Columna izquierda: identidad ─────────────────── -->
    <div class="col-12 col-lg-4 d-flex flex-column gap-3">

        <!-- Tarjeta de identidad -->
        <div class="card-jp">
            <div class="profile-header">
                <div style="position:relative;display:inline-block">
                    <?= avatar_html($userAvatar, $name, 'profile-avatar-lg') ?>
                    <?php if ($isAdminUser): ?>
                    <button onclick="document.getElementById('avatarInputCoach').click()"
                            title="Cambiar foto"
                            style="position:absolute;bottom:2px;right:2px;width:28px;height:28px;border-radius:50%;background:var(--accent);border:2px solid #fff;color:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:13px;padding:0;">
                        <i class="bi bi-camera-fill"></i>
                    </button>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="profile-name"><?= esc($name) ?></div>
                    <div class="profile-email"><?= esc($coach['email'] ?? '') ?></div>
                    <span class="badge-status <?= esc($coach['status'] ?? 'active') ?> mt-2 d-inline-block">
                        <?= esc($statusLabel) ?>
                    </span>
                    <?php if ($staffTitle !== ''): ?>
                        <div style="font-size:12px;color:var(--accent);font-weight:600;margin-top:6px">
                            <i class="bi bi-briefcase-fill"></i> <?= esc($staffTitle) ?>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php if ($isAdminUser): ?>
            <div class="card-jp-body pt-0 pb-2 text-center">
                <form id="avatarFormCoach" action="<?= base_url('avatar/upload/' . $coach['id']) ?>" method="post" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="file" id="avatarInputCoach" name="avatar"
                           accept="image/jpeg,image/png,image/webp,image/gif"
                           style="display:none"
                           onchange="this.form.submit()">
                </form>
                <?php if ($userAvatar): ?>
                <form action="<?= base_url('avatar/delete/' . $coach['id']) ?>" method="post">
                    <?= csrf_field() ?>
                    <button type="submit"
                            onclick="return confirm('¿Eliminar el avatar?')"
                            style="background:none;border:none;font-size:12px;color:var(--danger);cursor:pointer;padding:0;text-decoration:underline">
                        <i class="bi bi-trash"></i> Eliminar foto
                    </button>
                </form>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <div class="card-jp-body">
                <div class="d-flex flex-column gap-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <span style="font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px">ID</span>
                        <span style="font-size:13px;font-weight:600;color:var(--text-h)">#<?= esc($coach['id']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span style="font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px">Rol</span>
                        <span style="font-size:13px;font-weight:600;color:var(--text-h)">Entrenador</span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span style="font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px">Miembro desde</span>
                        <span style="font-size:13px;font-weight:600;color:var(--text-h)">
                            <?= isset($coach['created_at']) ? date('d M Y', strtotime($coach['created_at'])) : '—' ?>
                        </span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Stats resumen -->
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
                        <span style="font-size:20px;font-weight:700;color:var(--text-h)"><?= $playersCount ?></span>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- ── Columna derecha: historial ───────────────────── -->
    <div class="col-12 col-lg-8 d-flex flex-column gap-3">

        <!-- Próximas sesiones -->
        <?php if (!empty($upcomingList)): ?>
        <div class="card-jp">
            <div class="card-jp-header">
                <span class="card-jp-title">
                    <i class="bi bi-calendar3 me-2" style="color:var(--success)"></i>
                    Próximas sesiones
                </span>
                <span style="font-size:12px;color:var(--text-muted)"><?= $upcomingCount ?> programada(s)</span>
            </div>
            <div class="table-responsive">
                <table class="table-jp">
                    <thead>
                        <tr>
                            <th>Sesión</th>
                            <th>Fecha</th>
                            <th>Horario</th>
                            <th>Sede</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($upcomingList as $s): ?>
                        <tr>
                            <td style="font-weight:600;color:var(--text-h)"><?= esc($s['title'] ?? '—') ?></td>
                            <td style="white-space:nowrap;font-size:12px;color:var(--text-muted)">
                                <?= !empty($s['session_date']) ? date('d/m/Y', strtotime($s['session_date'])) : '—' ?>
                            </td>
                            <td style="white-space:nowrap;font-size:12px;color:var(--text-muted)">
                                <?= esc($formatTime($s['start_time'] ?? null)) ?> – <?= esc($formatTime($s['end_time'] ?? null)) ?>
                            </td>
                            <td style="font-size:12px;color:var(--text-muted)">
                                <?= esc($s['location_name'] ?? $s['location_custom'] ?? '—') ?>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Últimas sesiones dirigidas -->
        <div class="card-jp">
            <div class="card-jp-header">
                <span class="card-jp-title">
                    <i class="bi bi-calendar-check me-2" style="color:var(--accent)"></i>
                    Últimas sesiones dirigidas
                </span>
                <span style="font-size:12px;color:var(--text-muted)"><?= count($sessionsList) ?> registro(s)</span>
            </div>
            <?php if (!empty($sessionsList)): ?>
            <div class="table-responsive">
                <table class="table-jp">
                    <thead>
                        <tr>
                            <th>Sesión</th>
                            <th>Fecha</th>
                            <th>Horario</th>
                            <th>Sede</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($sessionsList as $s): ?>
                        <tr>
                            <td style="font-weight:600;color:var(--text-h)">
                                <a href="<?= base_url('clases/' . $s['id']) ?>" style="color:inherit;text-decoration:none">
                                    <?= esc($s['title'] ?? '—') ?>
                                </a>
                            </td>
                            <td style="white-space:nowrap;font-size:12px;color:var(--text-muted)">
                                <?= !empty($s['session_date']) ? date('d/m/Y', strtotime($s['session_date'])) : '—' ?>
                            </td>
                            <td style="white-space:nowrap;font-size:12px;color:var(--text-muted)">
                                <?= esc($formatTime($s['start_time'] ?? null)) ?> – <?= esc($formatTime($s['end_time'] ?? null)) ?>
                            </td>
                            <td style="font-size:12px;color:var(--text-muted)">
                                <?= esc($s['location_name'] ?? $s['location_custom'] ?? '—') ?>
                            </td>
                            <td>
                                <?php $st = $s['status'] ?? ''; ?>
                                <span class="badge-status <?= $st === 'completed' ? 'active' : ($st === 'cancelled' ? 'inactive' : 'active') ?>">
                                    <?= match($st) {
                                        'scheduled' => 'Programada',
                                        'completed' => 'Completada',
                                        'cancelled' => 'Cancelada',
                                        default     => ucfirst($st ?: '—'),
                                    } ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="card-jp-body">
                <p style="font-size:13px;color:var(--text-muted);margin:0">Sin sesiones registradas.</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Alumnos trabajados — colapsable -->
        <div class="card-jp">
            <div class="card-jp-header" style="cursor:pointer" onclick="document.getElementById('coachPlayersList').classList.toggle('d-none'); this.querySelector('.toggle-icon').classList.toggle('bi-chevron-down'); this.querySelector('.toggle-icon').classList.toggle('bi-chevron-up')">
                <span class="card-jp-title">
                    <i class="bi bi-people-fill me-2" style="color:var(--success)"></i>
                    Alumnos trabajados
                </span>
                <span style="display:flex;align-items:center;gap:10px">
                    <span style="font-size:12px;color:var(--text-muted)"><?= $playersCount ?> alumno(s)</span>
                    <i class="bi bi-chevron-down toggle-icon" style="font-size:14px;color:var(--text-muted)"></i>
                </span>
            </div>
            <div id="coachPlayersList" class="d-none">
                <?php if (!empty($playersList)): ?>
                <div class="table-responsive">
                    <table class="table-jp">
                        <thead>
                            <tr>
                                <th>Alumno</th>
                                <th>Email</th>
                                <th style="text-align:center">Clases asistidas</th>
                                <th>Estado</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($playersList as $p): ?>
                            <tr>
                                <td>
                                    <a href="<?= base_url('alumnos/' . $p['id']) ?>" style="color:inherit;text-decoration:none">
                                        <div class="td-user">
                                            <?= avatar_html($p['avatar'] ?? null, $p['name'], 'td-avatar') ?>
                                            <div class="td-name"><?= esc($p['name']) ?></div>
                                        </div>
                                    </a>
                                </td>
                                <td style="font-size:12px;color:var(--text-muted)"><?= esc($p['email'] ?? '—') ?></td>
                                <td style="text-align:center;font-weight:600;color:var(--text-h)"><?= (int)($p['classes_count'] ?? 0) ?></td>
                                <td>
                                    <span class="badge-status <?= esc($p['status'] ?? 'active') ?>">
                                        <?= match($p['status'] ?? 'active') {
                                            'active'   => 'Activo',
                                            'inactive' => 'Inactivo',
                                            default    => ucfirst($p['status'] ?? ''),
                                        } ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php else: ?>
                <div class="card-jp-body">
                    <p style="font-size:13px;color:var(--text-muted);margin:0">Sin alumnos atendidos todavía.</p>
                </div>
                <?php endif; ?>
            </div>
        </div>

    </div>
</div>

<?= $this->endSection() ?>
