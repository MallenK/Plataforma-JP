<?= $this->extend('layouts/app') ?>

<?php
$pageTitle    = 'Configuración';
$pageSubtitle = 'Ajustes de la plataforma';
?>

<?= $this->section('page_content') ?>

<div class="page-header">
    <div class="page-header-text">
        <h2>Configuración</h2>
        <p>Ajustes generales de la plataforma JP Preparation</p>
    </div>
</div>

<div class="row g-3">

    <!-- Sección izquierda: categorías de config -->
    <div class="col-12 col-lg-3">
        <div class="card-jp">
            <div class="card-jp-body p-2">
                <ul class="sidebar-nav mb-0">
                    <li class="sidebar-nav-item">
                        <a href="#general" class="sidebar-nav-link active" style="color:var(--text-h)">
                            <i class="bi bi-sliders"></i> General
                        </a>
                    </li>
                    <li class="sidebar-nav-item">
                        <a href="#usuarios" class="sidebar-nav-link" style="color:var(--text-h)">
                            <i class="bi bi-people-fill"></i> Usuarios
                        </a>
                    </li>
                    <li class="sidebar-nav-item">
                        <a href="#notificaciones" class="sidebar-nav-link" style="color:var(--text-h)">
                            <i class="bi bi-bell-fill"></i> Notificaciones
                        </a>
                    </li>
                    <li class="sidebar-nav-item">
                        <a href="#pagos" class="sidebar-nav-link" style="color:var(--text-h)">
                            <i class="bi bi-credit-card-fill"></i> Pagos
                        </a>
                    </li>
                    <li class="sidebar-nav-item">
                        <a href="#seguridad" class="sidebar-nav-link" style="color:var(--text-h)">
                            <i class="bi bi-shield-lock-fill"></i> Seguridad
                        </a>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Sección derecha: formulario -->
    <div class="col-12 col-lg-9">

        <!-- General -->
        <div id="general" class="card-jp mb-3">
            <div class="card-jp-header">
                <span class="card-jp-title"><i class="bi bi-sliders me-2" style="color:var(--accent)"></i>General</span>
            </div>
            <div class="card-jp-body">
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <div class="form-group">
                            <label class="form-label">Nombre de la academia</label>
                            <input type="text" class="form-control-jp" value="JP Preparation" disabled>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="form-group">
                            <label class="form-label">Email de contacto</label>
                            <input type="email" class="form-control-jp" placeholder="contacto@jpprep.com" disabled>
                        </div>
                    </div>
                    <div class="col-12">
                        <div class="alert-jp warning">
                            <i class="bi bi-info-circle-fill"></i>
                            La configuración completa estará disponible en la siguiente fase.
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Notificaciones -->
        <div id="notificaciones" class="card-jp mb-3">
            <div class="card-jp-header">
                <span class="card-jp-title"><i class="bi bi-bell-fill me-2" style="color:var(--warning)"></i>Notificaciones</span>
            </div>
            <div class="card-jp-body">
                <div class="d-flex flex-column gap-3">
                    <?php foreach ([
                        ['Notificaciones por email', 'Recibe avisos de nuevos alumnos y pagos'],
                        ['Recordatorios de clases', 'Envío automático 24h antes de cada clase'],
                        ['Alertas de pago pendiente', 'Avisar cuando un bono está próximo a vencer'],
                    ] as [$label, $desc]): ?>
                    <div class="d-flex align-items-center justify-content-between">
                        <div>
                            <div style="font-size:13.5px;font-weight:600;color:var(--text-h)"><?= $label ?></div>
                            <div style="font-size:12px;color:var(--text-muted)"><?= $desc ?></div>
                        </div>
                        <div class="form-check form-switch mb-0">
                            <input class="form-check-input" type="checkbox" disabled>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>

    </div>

</div>

<?= $this->endSection() ?>
