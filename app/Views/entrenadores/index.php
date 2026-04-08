<?= $this->extend('layouts/app') ?>

<?php
$pageTitle    = 'Entrenadores';
$pageSubtitle = 'Gestión del equipo técnico';
?>

<?= $this->section('page_content') ?>

<div class="page-header">
    <div class="page-header-text">
        <h2>Entrenadores</h2>
        <p>Equipo técnico de la academia</p>
    </div>
    <div class="d-flex gap-2">
        <a href="#" class="btn-jp btn-jp-secondary">
            <i class="bi bi-download"></i> Exportar
        </a>
        <a href="#" class="btn-jp btn-jp-primary">
            <i class="bi bi-person-plus-fill"></i> Nuevo entrenador
        </a>
    </div>
</div>

<!-- Filtros -->
<div class="card-jp mb-3">
    <div class="card-jp-body py-3">
        <div class="search-bar">
            <div class="input-search">
                <i class="bi bi-search"></i>
                <input type="text" id="search-input" placeholder="Buscar entrenador...">
            </div>
            <select class="form-control-jp" style="width:auto;min-width:140px">
                <option value="">Todos los estados</option>
                <option>Activo</option>
                <option>Inactivo</option>
            </select>
        </div>
    </div>
</div>

<!-- Tabla -->
<div class="card-jp">
    <div class="card-jp-header">
        <span class="card-jp-title">
            <i class="bi bi-person-workspace me-2" style="color:var(--success)"></i>
            Equipo técnico
        </span>
        <span style="font-size:12px;color:var(--text-muted)">0 entrenadores</span>
    </div>

    <div class="empty-state">
        <i class="bi bi-person-workspace"></i>
        <h3>Sin entrenadores registrados</h3>
        <p>Añade el primer miembro del equipo técnico para empezar.</p>
        <a href="#" class="btn-jp btn-jp-primary">
            <i class="bi bi-person-plus-fill"></i> Añadir entrenador
        </a>
    </div>
</div>

<?= $this->endSection() ?>
