<?= $this->extend('layouts/app') ?>

<?php
$pageTitle    = 'Bonos';
$pageSubtitle = 'Gestión de bonos y membresías';
?>

<?= $this->section('page_content') ?>

<div class="page-header">
    <div class="page-header-text">
        <h2>Bonos</h2>
        <p>Membresías y bonos de entrenamiento</p>
    </div>
    <?php if (in_array(session('role'), ['superadmin', 'admin'])): ?>
    <div class="d-flex gap-2">
        <a href="#" class="btn-jp btn-jp-secondary">
            <i class="bi bi-layout-text-sidebar-reverse"></i> Tipos de bono
        </a>
        <a href="#" class="btn-jp btn-jp-primary">
            <i class="bi bi-plus-lg"></i> Emitir bono
        </a>
    </div>
    <?php endif; ?>
</div>

<!-- Resumen -->
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="metric-card">
            <div class="metric-card-header">
                <span class="metric-label">Bonos activos</span>
                <div class="metric-icon blue"><i class="bi bi-ticket-perforated-fill"></i></div>
            </div>
            <div class="metric-value">0</div>
            <div class="metric-footer"><span class="badge-trend neutral">—</span></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="metric-card">
            <div class="metric-card-header">
                <span class="metric-label">Vendidos este mes</span>
                <div class="metric-icon green"><i class="bi bi-bag-check-fill"></i></div>
            </div>
            <div class="metric-value">0</div>
            <div class="metric-footer"><span class="badge-trend up"><i class="bi bi-arrow-up-short"></i>—</span></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="metric-card">
            <div class="metric-card-header">
                <span class="metric-label">Por vencer</span>
                <div class="metric-icon orange"><i class="bi bi-clock-fill"></i></div>
            </div>
            <div class="metric-value">0</div>
            <div class="metric-footer"><span class="metric-footer-label">próximos 7 días</span></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="metric-card">
            <div class="metric-card-header">
                <span class="metric-label">Ingresos bonos</span>
                <div class="metric-icon purple"><i class="bi bi-wallet2"></i></div>
            </div>
            <div class="metric-value">0€</div>
            <div class="metric-footer"><span class="metric-footer-label">este mes</span></div>
        </div>
    </div>
</div>

<!-- Tipos de bono -->
<div class="row g-3 mb-3">
    <div class="col-12 col-md-4">
        <div class="card-jp" style="border-top:3px solid var(--accent)">
            <div class="card-jp-body text-center py-4">
                <div class="metric-icon blue mx-auto mb-3"><i class="bi bi-1-circle-fill"></i></div>
                <div style="font-size:16px;font-weight:700;color:var(--text-h)">Bono Individual</div>
                <div style="font-size:13px;color:var(--text-muted);margin:6px 0 16px">1 sesión por clase</div>
                <div style="font-size:22px;font-weight:800;color:var(--text-h)">—€</div>
                <a href="#" class="btn-jp btn-jp-secondary btn-jp-sm mt-3">Configurar</a>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card-jp" style="border-top:3px solid var(--success)">
            <div class="card-jp-body text-center py-4">
                <div class="metric-icon green mx-auto mb-3"><i class="bi bi-collection-fill"></i></div>
                <div style="font-size:16px;font-weight:700;color:var(--text-h)">Bono 10 clases</div>
                <div style="font-size:13px;color:var(--text-muted);margin:6px 0 16px">10 sesiones, 3 meses</div>
                <div style="font-size:22px;font-weight:800;color:var(--text-h)">—€</div>
                <a href="#" class="btn-jp btn-jp-secondary btn-jp-sm mt-3">Configurar</a>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-4">
        <div class="card-jp" style="border-top:3px solid var(--warning)">
            <div class="card-jp-body text-center py-4">
                <div class="metric-icon orange mx-auto mb-3"><i class="bi bi-infinity"></i></div>
                <div style="font-size:16px;font-weight:700;color:var(--text-h)">Membresía mensual</div>
                <div style="font-size:13px;color:var(--text-muted);margin:6px 0 16px">Clases ilimitadas / mes</div>
                <div style="font-size:22px;font-weight:800;color:var(--text-h)">—€</div>
                <a href="#" class="btn-jp btn-jp-secondary btn-jp-sm mt-3">Configurar</a>
            </div>
        </div>
    </div>
</div>

<!-- Listado de bonos emitidos -->
<div class="card-jp">
    <div class="card-jp-header">
        <span class="card-jp-title">
            <i class="bi bi-ticket-perforated-fill me-2" style="color:var(--accent)"></i>
            Bonos emitidos
        </span>
        <div class="d-flex gap-2">
            <select class="form-control-jp" style="width:auto;min-width:140px">
                <option>Todos</option>
                <option>Activos</option>
                <option>Vencidos</option>
            </select>
        </div>
    </div>
    <div class="empty-state">
        <i class="bi bi-ticket-perforated"></i>
        <h3>Sin bonos emitidos</h3>
        <p>Cuando emitas bonos a alumnos aparecerán aquí.</p>
        <?php if (in_array(session('role'), ['superadmin', 'admin'])): ?>
        <a href="#" class="btn-jp btn-jp-primary">
            <i class="bi bi-plus-lg"></i> Emitir primer bono
        </a>
        <?php endif; ?>
    </div>
</div>

<?= $this->endSection() ?>
