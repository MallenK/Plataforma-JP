<?= $this->extend('layouts/app') ?>

<?php
$pageTitle    = 'Torneos';
$pageSubtitle = 'Calendario de competiciones';
?>

<?= $this->section('page_content') ?>

<div class="page-header">
    <div class="page-header-text">
        <h2>Torneos</h2>
        <p>Gestión de competiciones y eventos</p>
    </div>
    <?php if (in_array(session('role'), ['superadmin', 'admin'])): ?>
    <a href="#" class="btn-jp btn-jp-primary">
        <i class="bi bi-plus-lg"></i> Nuevo torneo
    </a>
    <?php endif; ?>
</div>

<!-- Tabs próximos / pasados -->
<div class="d-flex gap-2 mb-3">
    <button class="btn-jp btn-jp-primary btn-jp-sm tab-btn active" data-tab="proximos">Próximos</button>
    <button class="btn-jp btn-jp-secondary btn-jp-sm tab-btn" data-tab="pasados">Pasados</button>
    <button class="btn-jp btn-jp-secondary btn-jp-sm tab-btn" data-tab="todos">Todos</button>
</div>

<!-- Contenido -->
<div class="card-jp">
    <div class="card-jp-header">
        <span class="card-jp-title">
            <i class="bi bi-trophy-fill me-2" style="color:var(--warning)"></i>
            Competiciones
        </span>
        <div class="input-search" style="max-width:200px">
            <i class="bi bi-search"></i>
            <input type="text" placeholder="Buscar torneo...">
        </div>
    </div>

    <div class="empty-state">
        <i class="bi bi-trophy"></i>
        <h3>Sin torneos programados</h3>
        <p>No hay competiciones registradas para este período.</p>
        <?php if (in_array(session('role'), ['superadmin', 'admin'])): ?>
        <a href="#" class="btn-jp btn-jp-primary">
            <i class="bi bi-plus-lg"></i> Crear primer torneo
        </a>
        <?php endif; ?>
    </div>
</div>

<?= $this->endSection() ?>
