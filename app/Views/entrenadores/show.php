<?= $this->extend('layouts/app') ?>

<?php
$pageTitle    = esc($coach['name'] ?? 'Entrenador');
$pageSubtitle = 'Perfil del entrenador';

$name     = $coach['name'] ?? '?';
$parts    = explode(' ', trim($name));
$initials = strtoupper(substr($parts[0], 0, 1));
if (count($parts) >= 2) {
    $initials .= strtoupper(substr($parts[1], 0, 1));
}

$statusLabel = match($coach['status'] ?? 'active') {
    'active'   => 'Activo',
    'inactive' => 'Inactivo',
    'banned'   => 'Bloqueado',
    default    => ucfirst($coach['status'] ?? ''),
};

$sessionsCount   = count($coach['sessions']    ?? []);
$playersCount    = count($coach['players']     ?? []);
$evalsCount      = count($coach['evaluations'] ?? []);
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
        <a href="<?= base_url('entrenadores/' . $coach['id'] . '/editar') ?>" class="btn-jp btn-jp-primary">
            <i class="bi bi-pencil"></i> Editar
        </a>
    </div>
</div>

<div class="row g-3">

    <!-- ── Columna izquierda: identidad ─────────────────── -->
    <div class="col-12 col-lg-4 d-flex flex-column gap-3">

        <!-- Tarjeta de identidad -->
        <div class="card-jp">
            <div class="profile-header">
                <div class="profile-avatar-lg" style="background:var(--success)"><?= esc($initials) ?></div>
                <div>
                    <div class="profile-name"><?= esc($name) ?></div>
                    <div class="profile-email"><?= esc($coach['email'] ?? '') ?></div>
                    <span class="badge-status <?= esc($coach['status'] ?? 'active') ?> mt-2 d-inline-block">
                        <?= esc($statusLabel) ?>
                    </span>
                </div>
            </div>
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
                        <span style="font-size:13px;color:var(--text-muted)"><i class="bi bi-calendar3 me-2"></i>Sesiones dirigidas</span>
                        <span style="font-size:20px;font-weight:700;color:var(--text-h)"><?= $sessionsCount ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span style="font-size:13px;color:var(--text-muted)"><i class="bi bi-people-fill me-2"></i>Alumnos trabajados</span>
                        <span style="font-size:20px;font-weight:700;color:var(--text-h)"><?= $playersCount ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span style="font-size:13px;color:var(--text-muted)"><i class="bi bi-graph-up-arrow me-2"></i>Evaluaciones</span>
                        <span style="font-size:20px;font-weight:700;color:var(--text-h)"><?= $evalsCount ?></span>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- ── Columna derecha: historial ───────────────────── -->
    <div class="col-12 col-lg-8 d-flex flex-column gap-3">

        <!-- Sesiones dirigidas -->
        <div class="card-jp">
            <div class="card-jp-header">
                <span class="card-jp-title">
                    <i class="bi bi-calendar3 me-2" style="color:var(--accent)"></i>
                    Últimas sesiones dirigidas
                </span>
                <span style="font-size:12px;color:var(--text-muted)"><?= $sessionsCount ?> registro(s)</span>
            </div>
            <?php if (!empty($coach['sessions'])): ?>
            <div class="table-responsive">
                <table class="table-jp">
                    <thead>
                        <tr>
                            <th>Sesión</th>
                            <th>Sede</th>
                            <th>Fecha</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($coach['sessions'] as $s): ?>
                        <tr>
                            <td style="font-weight:600;color:var(--text-h)"><?= esc($s['title'] ?? '—') ?></td>
                            <td style="font-size:12px;color:var(--text-muted)"><?= esc($s['location_name'] ?? '—') ?></td>
                            <td style="white-space:nowrap;font-size:12px;color:var(--text-muted)">
                                <?= !empty($s['start_datetime']) ? date('d/m/Y H:i', strtotime($s['start_datetime'])) : '—' ?>
                            </td>
                            <td>
                                <span class="badge-status <?= ($s['status'] ?? '') === 'scheduled' ? 'active' : (($s['status'] ?? '') === 'completed' ? 'inactive' : 'active') ?>">
                                    <?= match($s['status'] ?? '') {
                                        'scheduled'  => 'Programada',
                                        'completed'  => 'Completada',
                                        'cancelled'  => 'Cancelada',
                                        'in_progress'=> 'En curso',
                                        default      => ucfirst($s['status'] ?? '—'),
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

        <!-- Alumnos trabajados -->
        <div class="card-jp">
            <div class="card-jp-header">
                <span class="card-jp-title">
                    <i class="bi bi-people-fill me-2" style="color:var(--success)"></i>
                    Alumnos trabajados
                </span>
                <span style="font-size:12px;color:var(--text-muted)"><?= $playersCount ?> alumno(s)</span>
            </div>
            <?php if (!empty($coach['players'])): ?>
            <div class="table-responsive">
                <table class="table-jp">
                    <thead>
                        <tr>
                            <th>Alumno</th>
                            <th>Email</th>
                            <th style="text-align:center">Evaluaciones</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($coach['players'] as $p): ?>
                        <tr>
                            <td>
                                <div class="td-user">
                                    <div class="td-avatar"><?= strtoupper(substr($p['name'], 0, 1)) ?></div>
                                    <div class="td-name"><?= esc($p['name']) ?></div>
                                </div>
                            </td>
                            <td style="font-size:12px;color:var(--text-muted)"><?= esc($p['email'] ?? '—') ?></td>
                            <td style="text-align:center;font-weight:600;color:var(--text-h)"><?= (int)($p['evals_count'] ?? 0) ?></td>
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
                <p style="font-size:13px;color:var(--text-muted);margin:0">Sin alumnos evaluados todavía.</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Últimas evaluaciones -->
        <div class="card-jp">
            <div class="card-jp-header">
                <span class="card-jp-title">
                    <i class="bi bi-graph-up-arrow me-2" style="color:var(--accent)"></i>
                    Últimas evaluaciones registradas
                </span>
                <span style="font-size:12px;color:var(--text-muted)"><?= $evalsCount ?> registro(s)</span>
            </div>
            <?php if (!empty($coach['evaluations'])): ?>
            <div class="table-responsive">
                <table class="table-jp">
                    <thead>
                        <tr>
                            <th>Alumno</th>
                            <th>Fecha</th>
                            <th>Evaluación</th>
                            <th>Notas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($coach['evaluations'] as $ev): ?>
                        <tr>
                            <td style="font-weight:600;color:var(--text-h)"><?= esc($ev['player_name'] ?? '—') ?></td>
                            <td style="white-space:nowrap;font-size:12px;color:var(--text-muted)">
                                <?= !empty($ev['date']) ? date('d/m/Y', strtotime($ev['date'])) : '—' ?>
                            </td>
                            <td><?= esc($ev['evaluation'] ?? '—') ?></td>
                            <td style="font-size:12px;color:var(--text-muted)"><?= esc($ev['notes'] ?? '—') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="card-jp-body">
                <p style="font-size:13px;color:var(--text-muted);margin:0">Sin evaluaciones registradas.</p>
            </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<?= console_debug('EntrenadoresController::show #' . ($coach['id'] ?? '?'), [
    'id'               => $coach['id'] ?? null,
    'name'             => $coach['name'] ?? null,
    'email'            => $coach['email'] ?? null,
    'status'           => $coach['status'] ?? null,
    'sessions_count'   => $sessionsCount,
    'players_count'    => $playersCount,
    'evaluations_count'=> $evalsCount,
    'sessions'         => $coach['sessions']    ?? [],
    'players'          => $coach['players']     ?? [],
    'evaluations'      => $coach['evaluations'] ?? [],
]) ?>

<?= $this->endSection() ?>
