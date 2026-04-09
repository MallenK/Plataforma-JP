<?= $this->extend('layouts/app') ?>

<?php
$pageTitle    = esc($alumno['name'] ?? 'Alumno');
$pageSubtitle = 'Perfil completo del alumno';

$name     = $alumno['name'] ?? '?';
$parts    = explode(' ', trim($name));
$initials = strtoupper(substr($parts[0], 0, 1));
if (count($parts) >= 2) {
    $initials .= strtoupper(substr($parts[1], 0, 1));
}

$levelLabel = match($alumno['level'] ?? '') {
    'beginner'     => 'Principiante',
    'intermediate' => 'Intermedio',
    'advanced'     => 'Avanzado',
    default        => '—',
};

$statusLabel = match($alumno['status'] ?? 'active') {
    'active'   => 'Activo',
    'inactive' => 'Inactivo',
    'banned'   => 'Bloqueado',
    default    => ucfirst($alumno['status'] ?? ''),
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
        <p>Perfil completo del alumno</p>
    </div>
    <?php if (in_array(session('role'), ['superadmin', 'admin'])): ?>
    <div class="d-flex gap-2">
        <a href="<?= base_url('alumnos') ?>" class="btn-jp btn-jp-secondary">
            <i class="bi bi-arrow-left"></i> Listado
        </a>
        <a href="<?= base_url('alumnos/' . $alumno['id'] . '/editar') ?>" class="btn-jp btn-jp-primary">
            <i class="bi bi-pencil"></i> Editar
        </a>
    </div>
    <?php endif; ?>
</div>

<div class="row g-3">

    <!-- ── Columna izquierda: identidad ─────────────────── -->
    <div class="col-12 col-lg-4 d-flex flex-column gap-3">

        <!-- Tarjeta de identidad -->
        <div class="card-jp">
            <div class="profile-header">
                <div class="profile-avatar-lg"><?= esc($initials) ?></div>
                <div>
                    <div class="profile-name"><?= esc($name) ?></div>
                    <div class="profile-email"><?= esc($alumno['email'] ?? '') ?></div>
                    <span class="badge-status <?= esc($alumno['status'] ?? 'active') ?> mt-2 d-inline-block">
                        <?= esc($statusLabel) ?>
                    </span>
                </div>
            </div>
            <div class="card-jp-body">
                <div class="d-flex flex-column gap-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <span style="font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px">ID</span>
                        <span style="font-size:13px;font-weight:600;color:var(--text-h)">#<?= esc($alumno['id']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span style="font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px">Miembro desde</span>
                        <span style="font-size:13px;font-weight:600;color:var(--text-h)">
                            <?= isset($alumno['created_at']) ? date('d M Y', strtotime($alumno['created_at'])) : '—' ?>
                        </span>
                    </div>
                    <?php if (!empty($alumno['birth_date'])): ?>
                    <div class="d-flex justify-content-between align-items-center">
                        <span style="font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px">Fecha nac.</span>
                        <span style="font-size:13px;font-weight:600;color:var(--text-h)">
                            <?= date('d M Y', strtotime($alumno['birth_date'])) ?>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Notas médicas -->
        <div class="card-jp">
            <div class="card-jp-header">
                <span class="card-jp-title">
                    <i class="bi bi-clipboard2-pulse-fill me-2" style="color:var(--danger)"></i>
                    Notas médicas
                </span>
            </div>
            <div class="card-jp-body">
                <?php if (!empty($alumno['medical_notes'])): ?>
                    <p style="font-size:13.5px;color:var(--text-body);margin:0;line-height:1.6">
                        <?= nl2br(esc($alumno['medical_notes'])) ?>
                    </p>
                <?php else: ?>
                    <p style="font-size:13px;color:var(--text-muted);margin:0">Sin notas médicas registradas.</p>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- ── Columna derecha: datos + historial ───────────── -->
    <div class="col-12 col-lg-8 d-flex flex-column gap-3">

        <!-- Stats rápidas -->
        <div class="row g-3">
            <div class="col-6 col-md-3">
                <div class="metric-card">
                    <div class="metric-card-header">
                        <span class="metric-label">Posición</span>
                        <div class="metric-icon blue"><i class="bi bi-geo-alt-fill"></i></div>
                    </div>
                    <div class="metric-value" style="font-size:18px"><?= esc($alumno['position'] ?? '—') ?></div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="metric-card">
                    <div class="metric-card-header">
                        <span class="metric-label">Nivel</span>
                        <div class="metric-icon green"><i class="bi bi-bar-chart-fill"></i></div>
                    </div>
                    <div class="metric-value" style="font-size:18px"><?= esc($levelLabel) ?></div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="metric-card">
                    <div class="metric-card-header">
                        <span class="metric-label">Altura</span>
                        <div class="metric-icon orange"><i class="bi bi-rulers"></i></div>
                    </div>
                    <div class="metric-value" style="font-size:18px">
                        <?= !empty($alumno['height']) ? esc($alumno['height']) . ' cm' : '—' ?>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="metric-card">
                    <div class="metric-card-header">
                        <span class="metric-label">Peso</span>
                        <div class="metric-icon purple"><i class="bi bi-activity"></i></div>
                    </div>
                    <div class="metric-value" style="font-size:18px">
                        <?= !empty($alumno['weight']) ? esc($alumno['weight']) . ' kg' : '—' ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Planes activos -->
        <div class="card-jp">
            <div class="card-jp-header">
                <span class="card-jp-title">
                    <i class="bi bi-ticket-perforated-fill me-2" style="color:var(--accent)"></i>
                    Planes / Bonos
                </span>
                <span style="font-size:12px;color:var(--text-muted)"><?= count($alumno['plans']) ?> registrado(s)</span>
            </div>
            <?php if (!empty($alumno['plans'])): ?>
            <div class="table-responsive">
                <table class="table-jp">
                    <thead>
                        <tr>
                            <th>Plan</th>
                            <th>Sesiones restantes</th>
                            <th>Vigencia</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($alumno['plans'] as $plan): ?>
                        <tr>
                            <td>
                                <div style="font-weight:600;color:var(--text-h)"><?= esc($plan['plan_name']) ?></div>
                                <?php if (!empty($plan['price'])): ?>
                                <div style="font-size:12px;color:var(--text-muted)"><?= number_format($plan['price'], 2) ?> €</div>
                                <?php endif; ?>
                            </td>
                            <td><?= esc($plan['sessions_remaining'] ?? '—') ?> / <?= esc($plan['sessions_count'] ?? '—') ?></td>
                            <td style="font-size:12px;color:var(--text-muted)">
                                <?= !empty($plan['start_date']) ? date('d/m/Y', strtotime($plan['start_date'])) : '—' ?>
                                <?php if (!empty($plan['end_date'])): ?>
                                → <?= date('d/m/Y', strtotime($plan['end_date'])) ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge-status <?= esc($plan['status'] ?? 'active') ?>">
                                    <?= match($plan['status'] ?? '') {
                                        'active'   => 'Activo',
                                        'expired'  => 'Expirado',
                                        'paused'   => 'Pausado',
                                        default    => ucfirst($plan['status'] ?? '—'),
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
                <p style="font-size:13px;color:var(--text-muted);margin:0">Sin planes asignados.</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Métricas recientes -->
        <div class="card-jp">
            <div class="card-jp-header">
                <span class="card-jp-title">
                    <i class="bi bi-graph-up-arrow me-2" style="color:var(--success)"></i>
                    Últimas métricas
                </span>
                <span style="font-size:12px;color:var(--text-muted)"><?= count($alumno['metrics']) ?> registro(s)</span>
            </div>
            <?php if (!empty($alumno['metrics'])): ?>
            <div class="table-responsive">
                <table class="table-jp">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Evaluación</th>
                            <th>Notas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($alumno['metrics'] as $metric): ?>
                        <tr>
                            <td style="white-space:nowrap;color:var(--text-muted);font-size:12px">
                                <?= !empty($metric['date']) ? date('d/m/Y', strtotime($metric['date'])) : '—' ?>
                            </td>
                            <td><?= esc($metric['evaluation'] ?? '—') ?></td>
                            <td style="font-size:12px;color:var(--text-muted)"><?= esc($metric['notes'] ?? '—') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="card-jp-body">
                <p style="font-size:13px;color:var(--text-muted);margin:0">Sin métricas registradas.</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Asistencia reciente -->
        <div class="card-jp">
            <div class="card-jp-header">
                <span class="card-jp-title">
                    <i class="bi bi-calendar-check-fill me-2" style="color:var(--accent)"></i>
                    Asistencia reciente
                </span>
                <span style="font-size:12px;color:var(--text-muted)"><?= count($alumno['attendance']) ?> registro(s)</span>
            </div>
            <?php if (!empty($alumno['attendance'])): ?>
            <div class="table-responsive">
                <table class="table-jp">
                    <thead>
                        <tr>
                            <th>Sesión</th>
                            <th>Fecha</th>
                            <th>Estado</th>
                            <th>Notas</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($alumno['attendance'] as $att): ?>
                        <tr>
                            <td style="font-weight:600;color:var(--text-h)"><?= esc($att['session_title'] ?? '—') ?></td>
                            <td style="white-space:nowrap;font-size:12px;color:var(--text-muted)">
                                <?= !empty($att['start_datetime']) ? date('d/m/Y H:i', strtotime($att['start_datetime'])) : '—' ?>
                            </td>
                            <td>
                                <span class="badge-status <?= ($att['status'] ?? '') === 'present' ? 'active' : 'inactive' ?>">
                                    <?= match($att['status'] ?? '') {
                                        'present' => 'Asistió',
                                        'absent'  => 'Faltó',
                                        'late'    => 'Tarde',
                                        default   => ucfirst($att['status'] ?? '—'),
                                    } ?>
                                </span>
                            </td>
                            <td style="font-size:12px;color:var(--text-muted)"><?= esc($att['notes'] ?? '—') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="card-jp-body">
                <p style="font-size:13px;color:var(--text-muted);margin:0">Sin registros de asistencia.</p>
            </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<?= console_debug('PlayerController::show #' . ($alumno['id'] ?? '?'), [
    'id'          => $alumno['id'] ?? null,
    'name'        => $alumno['name'] ?? null,
    'email'       => $alumno['email'] ?? null,
    'role'        => $alumno['role'] ?? null,
    'status'      => $alumno['status'] ?? null,
    'profile_id'  => $alumno['profile_id'] ?? null,
    'height'      => $alumno['height'] ?? null,
    'weight'      => $alumno['weight'] ?? null,
    'level'       => $alumno['level'] ?? null,
    'plans_count'      => count($alumno['plans'] ?? []),
    'metrics_count'    => count($alumno['metrics'] ?? []),
    'attendance_count' => count($alumno['attendance'] ?? []),
    'plans'      => $alumno['plans'] ?? [],
    'metrics'    => $alumno['metrics'] ?? [],
    'attendance' => $alumno['attendance'] ?? [],
]) ?>

<?= $this->endSection() ?>
