<?= $this->extend('layouts/app') ?>

<?php
$pageTitle    = 'Documentación';
$pageSubtitle = 'Recursos y material formativo';
?>

<?= $this->section('page_content') ?>

<div class="page-header">
    <div class="page-header-text">
        <h2>Documentación</h2>
        <p>Material formativo y recursos de la academia</p>
    </div>
    <?php if (in_array(session('role'), ['superadmin', 'admin'])): ?>
    <a href="#" class="btn-jp btn-jp-primary">
        <i class="bi bi-plus-lg"></i> Subir documento
    </a>
    <?php endif; ?>
</div>

<!-- Buscador -->
<div class="card-jp mb-3">
    <div class="card-jp-body py-3">
        <div class="search-bar">
            <div class="input-search" style="max-width:400px">
                <i class="bi bi-search"></i>
                <input type="text" placeholder="Buscar documento...">
            </div>
            <select class="form-control-jp" style="width:auto;min-width:160px">
                <option value="">Todas las categorías</option>
                <option>Reglamentos</option>
                <option>Formación técnica</option>
                <option>Nutrición</option>
                <option>Psicología deportiva</option>
            </select>
        </div>
    </div>
</div>

<!-- Categorías -->
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="card-jp text-center" style="cursor:pointer">
            <div class="card-jp-body py-4">
                <div class="metric-icon blue mx-auto mb-2"><i class="bi bi-file-earmark-text-fill"></i></div>
                <div style="font-size:13px;font-weight:600;color:var(--text-h)">Reglamentos</div>
                <div style="font-size:12px;color:var(--text-muted)">0 documentos</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card-jp text-center" style="cursor:pointer">
            <div class="card-jp-body py-4">
                <div class="metric-icon green mx-auto mb-2"><i class="bi bi-camera-video-fill"></i></div>
                <div style="font-size:13px;font-weight:600;color:var(--text-h)">Vídeos técnicos</div>
                <div style="font-size:12px;color:var(--text-muted)">0 documentos</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card-jp text-center" style="cursor:pointer">
            <div class="card-jp-body py-4">
                <div class="metric-icon orange mx-auto mb-2"><i class="bi bi-clipboard2-pulse-fill"></i></div>
                <div style="font-size:13px;font-weight:600;color:var(--text-h)">Nutrición</div>
                <div style="font-size:12px;color:var(--text-muted)">0 documentos</div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="card-jp text-center" style="cursor:pointer">
            <div class="card-jp-body py-4">
                <div class="metric-icon purple mx-auto mb-2"><i class="bi bi-brain"></i></div>
                <div style="font-size:13px;font-weight:600;color:var(--text-h)">Psicología</div>
                <div style="font-size:12px;color:var(--text-muted)">0 documentos</div>
            </div>
        </div>
    </div>
</div>

<!-- Listado -->
<div class="card-jp">
    <div class="card-jp-header">
        <span class="card-jp-title">
            <i class="bi bi-folder2-open me-2" style="color:var(--accent)"></i>
            Todos los documentos
        </span>
    </div>
    <div class="empty-state">
        <i class="bi bi-folder2-open"></i>
        <h3>Sin documentos</h3>
        <p>Todavía no hay material disponible. Los documentos aparecerán aquí cuando se añadan.</p>
        <?php if (in_array(session('role'), ['superadmin', 'admin'])): ?>
        <a href="#" class="btn-jp btn-jp-primary">
            <i class="bi bi-plus-lg"></i> Subir primer documento
        </a>
        <?php endif; ?>
    </div>
</div>

<?= $this->endSection() ?>
