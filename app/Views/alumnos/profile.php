<?= $this->extend('layouts/app') ?>

<?= $this->section('page_content') ?>

<?php
$canEdit = in_array(session('role'), ['admin', 'superadmin', 'player']);
$pos     = esc($profile['position'] ?? '—');
$level   = match($profile['level'] ?? '') {
    'beginner'     => 'Principiante',
    'intermediate' => 'Intermedio',
    'advanced'     => 'Avanzado',
    default        => '—',
};
?>

<div class="page-header">
    <div class="page-header-text">
        <h2>Mi ficha técnica</h2>
        <p>Datos deportivos del alumno</p>
    </div>
    <?php if ($canEdit): ?>
    <a href="/alumno?edit=1" class="btn-jp btn-jp-secondary">
        <i class="bi bi-pencil"></i> Editar ficha
    </a>
    <?php endif; ?>
</div>

<div class="row g-3">

    <!-- Stats rápidas -->
    <div class="col-6 col-md-3">
        <div class="metric-card">
            <div class="metric-card-header">
                <span class="metric-label">Posición</span>
                <div class="metric-icon blue"><i class="bi bi-geo-alt-fill"></i></div>
            </div>
            <div class="metric-value" style="font-size:20px"><?= $pos ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="metric-card">
            <div class="metric-card-header">
                <span class="metric-label">Nivel</span>
                <div class="metric-icon green"><i class="bi bi-bar-chart-fill"></i></div>
            </div>
            <div class="metric-value" style="font-size:20px"><?= $level ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="metric-card">
            <div class="metric-card-header">
                <span class="metric-label">Altura</span>
                <div class="metric-icon orange"><i class="bi bi-rulers"></i></div>
            </div>
            <div class="metric-value" style="font-size:20px">
                <?= $profile['height'] ? esc($profile['height']) . ' cm' : '—' ?>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="metric-card">
            <div class="metric-card-header">
                <span class="metric-label">Peso</span>
                <div class="metric-icon purple"><i class="bi bi-activity"></i></div>
            </div>
            <div class="metric-value" style="font-size:20px">
                <?= $profile['weight'] ? esc($profile['weight']) . ' kg' : '—' ?>
            </div>
        </div>
    </div>

    <!-- Detalle ficha -->
    <div class="col-12 col-lg-8">
        <div class="card-jp">
            <div class="card-jp-header">
                <span class="card-jp-title">
                    <i class="bi bi-person-badge-fill me-2" style="color:var(--accent)"></i>
                    Datos deportivos
                </span>
            </div>
            <div class="card-jp-body">
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <div class="form-group mb-0">
                            <label class="form-label">Fecha de nacimiento</label>
                            <input type="text" class="form-control-jp"
                                value="<?= esc($profile['birth_date'] ?? '—') ?>" readonly>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="form-group mb-0">
                            <label class="form-label">Posición</label>
                            <input type="text" class="form-control-jp" value="<?= $pos ?>" readonly>
                        </div>
                    </div>
                    <div class="col-12 col-md-3">
                        <div class="form-group mb-0">
                            <label class="form-label">Altura (cm)</label>
                            <input type="text" class="form-control-jp"
                                value="<?= esc($profile['height'] ?? '—') ?>" readonly>
                        </div>
                    </div>
                    <div class="col-12 col-md-3">
                        <div class="form-group mb-0">
                            <label class="form-label">Peso (kg)</label>
                            <input type="text" class="form-control-jp"
                                value="<?= esc($profile['weight'] ?? '—') ?>" readonly>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="form-group mb-0">
                            <label class="form-label">Nivel</label>
                            <input type="text" class="form-control-jp" value="<?= $level ?>" readonly>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Notas médicas -->
    <div class="col-12 col-lg-4">
        <div class="card-jp h-100">
            <div class="card-jp-header">
                <span class="card-jp-title">
                    <i class="bi bi-clipboard2-pulse-fill me-2" style="color:var(--danger)"></i>
                    Notas médicas
                </span>
            </div>
            <div class="card-jp-body">
                <?php if (!empty($profile['medical_notes'])): ?>
                    <p style="font-size:13.5px;color:var(--text-body);margin:0;line-height:1.6">
                        <?= nl2br(esc($profile['medical_notes'])) ?>
                    </p>
                <?php else: ?>
                    <p style="font-size:13px;color:var(--text-muted);margin:0">
                        Sin notas médicas registradas.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>

</div>

<?= $this->endSection() ?>
