<?= $this->extend('layouts/app') ?>

<?php
$pageTitle    = 'Clases';
$pageSubtitle = 'Gestión de sesiones de entrenamiento';
?>

<?= $this->section('page_content') ?>

<div class="page-header">
    <div class="page-header-text">
        <h2>Clases</h2>
        <p>Sesiones de entrenamiento programadas</p>
    </div>
    <?php if (in_array(session('role'), ['superadmin', 'admin', 'coach'])): ?>
    <a href="#" class="btn-jp btn-jp-primary">
        <i class="bi bi-plus-lg"></i> Nueva clase
    </a>
    <?php endif; ?>
</div>

<!-- Métricas rápidas -->
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="metric-card">
            <div class="metric-card-header">
                <span class="metric-label">Esta semana</span>
                <div class="metric-icon blue"><i class="bi bi-calendar-week"></i></div>
            </div>
            <div class="metric-value">0</div>
            <div class="metric-footer"><span class="metric-footer-label">clases programadas</span></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="metric-card">
            <div class="metric-card-header">
                <span class="metric-label">Este mes</span>
                <div class="metric-icon green"><i class="bi bi-calendar-month"></i></div>
            </div>
            <div class="metric-value">0</div>
            <div class="metric-footer"><span class="metric-footer-label">total de clases</span></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="metric-card">
            <div class="metric-card-header">
                <span class="metric-label">Alumnos activos</span>
                <div class="metric-icon orange"><i class="bi bi-people-fill"></i></div>
            </div>
            <div class="metric-value">0</div>
            <div class="metric-footer"><span class="metric-footer-label">inscritos en clases</span></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="metric-card">
            <div class="metric-card-header">
                <span class="metric-label">Asistencia media</span>
                <div class="metric-icon purple"><i class="bi bi-bar-chart-fill"></i></div>
            </div>
            <div class="metric-value">—%</div>
            <div class="metric-footer"><span class="metric-footer-label">últimas 4 semanas</span></div>
        </div>
    </div>
</div>

<!-- Tabla de clases -->
<div class="card-jp">
    <div class="card-jp-header">
        <span class="card-jp-title">
            <i class="bi bi-collection-play-fill me-2" style="color:var(--accent)"></i>
            Próximas clases
        </span>
        <div class="search-bar">
            <div class="input-search" style="max-width:200px">
                <i class="bi bi-search"></i>
                <input type="text" placeholder="Buscar...">
            </div>
        </div>
    </div>

    <div class="empty-state">
        <i class="bi bi-collection-play"></i>
        <h3>Sin clases programadas</h3>
        <p>Crea la primera sesión de entrenamiento para comenzar a organizar el calendario.</p>
        <?php if (in_array(session('role'), ['superadmin', 'admin', 'coach'])): ?>
        <a href="#" class="btn-jp btn-jp-primary">
            <i class="bi bi-plus-lg"></i> Crear primera clase
        </a>
        <?php endif; ?>
    </div>
</div>

<?= $this->endSection() ?>
