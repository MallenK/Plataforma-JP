<?= $this->extend('layouts/app') ?>

<?= $this->section('page_content') ?>

<div class="page-header mb-4">
    <h1 class="title">Dashboard</h1>
    <p class="subtitle">Resumen general de la plataforma</p>
</div>

<?php if(session('role') === 'admin'): ?>

<div 
    class="row g-4"
    id="stats-container"
    data-url="<?= route_to('dashboard_stats') ?>"
    data-csrf-name="<?= csrf_token() ?>"
    data-csrf-hash="<?= csrf_hash() ?>"
>

    <div class="col-12 col-md-6 col-xl-4">
        <div class="card-metric">
            <div class="card-metric-header">
                <span>Alumnos</span>
            </div>
            <div class="card-metric-body">
                <h2 id="alumnos-count">...</h2>
            </div>
        </div>
    </div>

    <div class="col-12 col-md-6 col-xl-4">
        <div class="card-metric">
            <div class="card-metric-header">
                <span>Entrenadores</span>
            </div>
            <div class="card-metric-body">
                <h2 id="entrenadores-count">...</h2>
            </div>
        </div>
    </div>

</div>

<?php else: ?>

<div class="empty-state">
    <div class="empty-state-box">
        <h5>Acceso restringido</h5>
        <p>No tienes permisos para ver estadísticas.</p>
    </div>
</div>

<?php endif; ?>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<?= view('dashboard/scripts') ?>
<?= $this->endSection() ?>