<?= $this->extend('layouts/app') ?>

<?php
$pageTitle    = 'Finanzas';
$pageSubtitle = 'Control económico de la academia';
?>

<?= $this->section('page_content') ?>

<div class="page-header">
    <div class="page-header-text">
        <h2>Finanzas</h2>
        <p>Resumen económico y estado de pagos</p>
    </div>
    <div class="d-flex gap-2">
        <a href="#" class="btn-jp btn-jp-secondary">
            <i class="bi bi-download"></i> Exportar informe
        </a>
        <a href="#" class="btn-jp btn-jp-primary">
            <i class="bi bi-plus-lg"></i> Registrar pago
        </a>
    </div>
</div>

<!-- Métricas financieras -->
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="metric-card">
            <div class="metric-card-header">
                <span class="metric-label">Ingresos mes</span>
                <div class="metric-icon blue"><i class="bi bi-graph-up-arrow"></i></div>
            </div>
            <div class="metric-value">0€</div>
            <div class="metric-footer">
                <span class="badge-trend up"><i class="bi bi-arrow-up-short"></i>—</span>
                <span class="metric-footer-label">vs mes anterior</span>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="metric-card">
            <div class="metric-card-header">
                <span class="metric-label">Pagos pendientes</span>
                <div class="metric-icon orange"><i class="bi bi-hourglass-split"></i></div>
            </div>
            <div class="metric-value">0€</div>
            <div class="metric-footer"><span class="metric-footer-label">de 0 alumnos</span></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="metric-card">
            <div class="metric-card-header">
                <span class="metric-label">Tasa de cobro</span>
                <div class="metric-icon green"><i class="bi bi-check-circle-fill"></i></div>
            </div>
            <div class="metric-value">—%</div>
            <div class="metric-footer">
                <div class="metric-progress mt-0" style="flex:1"><div class="metric-progress-bar" style="width:0%;background:var(--success)"></div></div>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="metric-card">
            <div class="metric-card-header">
                <span class="metric-label">Meta mensual</span>
                <div class="metric-icon purple"><i class="bi bi-bullseye"></i></div>
            </div>
            <div class="metric-value">0€</div>
            <div class="metric-footer"><span class="metric-footer-label">objetivo fijado</span></div>
        </div>
    </div>
</div>

<div class="row g-3">

    <!-- Historial de pagos -->
    <div class="col-12 col-lg-8">
        <div class="card-jp">
            <div class="card-jp-header">
                <span class="card-jp-title">
                    <i class="bi bi-list-ul me-2" style="color:var(--accent)"></i>
                    Historial de pagos
                </span>
                <div class="d-flex gap-2">
                    <select class="form-control-jp" style="width:auto;min-width:120px">
                        <option>Todos</option>
                        <option>Pagados</option>
                        <option>Pendientes</option>
                        <option>Vencidos</option>
                    </select>
                </div>
            </div>
            <div class="empty-state">
                <i class="bi bi-receipt"></i>
                <h3>Sin movimientos</h3>
                <p>Los pagos registrados aparecerán en este listado.</p>
                <a href="#" class="btn-jp btn-jp-primary">
                    <i class="bi bi-plus-lg"></i> Registrar primer pago
                </a>
            </div>
        </div>
    </div>

    <!-- Panel lateral -->
    <div class="col-12 col-lg-4 d-flex flex-column gap-3">

        <!-- Resumen del mes -->
        <div class="card-jp" style="background:#0f172a;border-color:#1e293b">
            <div class="card-jp-header" style="border-color:#1e293b">
                <span class="card-jp-title" style="color:#f1f5f9">Progreso mensual</span>
            </div>
            <div class="card-jp-body">
                <div class="d-flex justify-content-between mb-2">
                    <span style="font-size:12px;color:#94a3b8">Recaudado</span>
                    <span style="font-size:13px;font-weight:700;color:#f1f5f9">0€ / 0€</span>
                </div>
                <div class="progress-jp mb-3">
                    <div class="progress-jp-bar" style="width:0%;background:var(--success)"></div>
                </div>
                <p style="font-size:12px;color:#64748b;margin:0">Sin datos de facturación disponibles aún.</p>
            </div>
        </div>

        <!-- Métodos de pago -->
        <div class="card-jp">
            <div class="card-jp-header">
                <span class="card-jp-title">Métodos de pago</span>
            </div>
            <div class="card-jp-body d-flex flex-column gap-3">
                <?php foreach ([
                    ['bi-cash-stack',        'Efectivo',        '0€', 'blue'],
                    ['bi-credit-card-fill',  'Tarjeta',         '0€', 'green'],
                    ['bi-bank',              'Transferencia',   '0€', 'orange'],
                ] as [$icon, $label, $amount, $color]): ?>
                <div class="d-flex align-items-center justify-content-between">
                    <div class="d-flex align-items-center gap-2">
                        <div class="list-item-icon <?= $color ?>"><i class="bi <?= $icon ?>"></i></div>
                        <span style="font-size:13.5px;font-weight:500;color:var(--text-h)"><?= $label ?></span>
                    </div>
                    <span style="font-size:13.5px;font-weight:700;color:var(--text-h)"><?= $amount ?></span>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

    </div>
</div>

<?= $this->endSection() ?>
