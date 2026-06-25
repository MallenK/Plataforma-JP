<?= $this->extend('layouts/app') ?>
<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/tickets.css') ?>">
<?= $this->endSection() ?>
<?= $this->section('page_content') ?>

<?php
$byStatus   = $stats['by_status']    ?? [];
$byCategory = $stats['by_category']  ?? [];
$byPriority = $stats['by_priority']  ?? [];
$last30     = $stats['last_30_days'] ?? [];
$avgHours   = $stats['avg_hours']    ?? 0;
$total      = $stats['total']        ?? 0;

$open     = (int) ($byStatus['abierto']     ?? 0);
$progress = (int) ($byStatus['en_progreso'] ?? 0);
$resolved = (int) ($byStatus['resuelto']    ?? 0);
$closed   = (int) ($byStatus['cerrado']     ?? 0);
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="fw-bold mb-1" style="font-size:1.25rem">Dashboard de Tickets</h2>
        <p class="text-muted mb-0" style="font-size:13px">Resumen y métricas del sistema de soporte</p>
    </div>
    <a href="<?= base_url('tickets/admin') ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-list-ul me-1"></i>Ver todos los tickets
    </a>
</div>

<!-- KPIs -->
<div class="ticket-kpi-grid mb-4">
    <div class="ticket-kpi ticket-kpi--open">
        <div class="ticket-kpi-value"><?= $open ?></div>
        <div class="ticket-kpi-label">Abiertos</div>
        <i class="bi bi-circle-fill ticket-kpi-icon"></i>
    </div>
    <div class="ticket-kpi ticket-kpi--progress">
        <div class="ticket-kpi-value"><?= $progress ?></div>
        <div class="ticket-kpi-label">En progreso</div>
        <i class="bi bi-arrow-repeat ticket-kpi-icon"></i>
    </div>
    <div class="ticket-kpi ticket-kpi--resolved">
        <div class="ticket-kpi-value"><?= $resolved ?></div>
        <div class="ticket-kpi-label">Resueltos</div>
        <i class="bi bi-check-circle-fill ticket-kpi-icon"></i>
    </div>
    <div class="ticket-kpi ticket-kpi--closed">
        <div class="ticket-kpi-value"><?= $closed ?></div>
        <div class="ticket-kpi-label">Cerrados</div>
        <i class="bi bi-lock-fill ticket-kpi-icon"></i>
    </div>
    <div class="ticket-kpi ticket-kpi--total">
        <div class="ticket-kpi-value"><?= $total ?></div>
        <div class="ticket-kpi-label">Total</div>
        <i class="bi bi-ticket-perforated-fill ticket-kpi-icon"></i>
    </div>
    <div class="ticket-kpi ticket-kpi--time">
        <div class="ticket-kpi-value"><?= $avgHours ?>h</div>
        <div class="ticket-kpi-label">Tiempo medio resolución</div>
        <i class="bi bi-clock-history ticket-kpi-icon"></i>
    </div>
</div>

<div class="row g-4">

    <!-- Últimos 30 días (gráfico de barras) -->
    <div class="col-lg-7">
        <div class="ticket-chart-card">
            <div class="ticket-chart-title">Tickets creados — últimos 30 días</div>
            <?php if (empty($last30)): ?>
            <p class="text-muted text-center py-4" style="font-size:13px">Sin datos en este periodo</p>
            <?php else: ?>
            <div class="ticket-bar-chart" id="bar-chart">
                <?php
                $maxVal = max(array_column($last30, 'total'));
                foreach ($last30 as $row):
                    $pct = $maxVal > 0 ? round(($row['total'] / $maxVal) * 100) : 0;
                    $label = date('d/m', strtotime($row['day']));
                ?>
                <div class="ticket-bar-item">
                    <div class="ticket-bar-wrap" title="<?= $row['total'] ?> tickets el <?= $label ?>">
                        <div class="ticket-bar-fill" style="height:<?= $pct ?>%"></div>
                    </div>
                    <span class="ticket-bar-label"><?= $label ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Categorías + Prioridades -->
    <div class="col-lg-5">
        <div class="ticket-chart-card mb-3">
            <div class="ticket-chart-title">Por categoría</div>
            <?php foreach ($categories as $key => $label): ?>
            <?php $count = (int) ($byCategory[$key] ?? 0); ?>
            <?php $pct = $total > 0 ? round(($count / $total) * 100) : 0; ?>
            <div class="ticket-progress-row">
                <span class="ticket-progress-label"><?= esc($label) ?></span>
                <div class="ticket-progress-bar-wrap">
                    <div class="ticket-progress-bar-fill" style="width:<?= $pct ?>%"></div>
                </div>
                <span class="ticket-progress-count"><?= $count ?></span>
            </div>
            <?php endforeach; ?>
        </div>

        <div class="ticket-chart-card">
            <div class="ticket-chart-title">Por prioridad</div>
            <?php
            $priorityClasses = ['baja' => 'low', 'media' => 'medium', 'alta' => 'high', 'urgente' => 'urgent'];
            foreach ($priorities as $key => $label):
                $count = (int) ($byPriority[$key] ?? 0);
                $pct   = $total > 0 ? round(($count / $total) * 100) : 0;
                $cls   = $priorityClasses[$key] ?? '';
            ?>
            <div class="ticket-progress-row">
                <span class="ticket-progress-label">
                    <span class="ticket-priority ticket-priority--<?= $cls ?>"><?= esc($label) ?></span>
                </span>
                <div class="ticket-progress-bar-wrap">
                    <div class="ticket-progress-bar-fill ticket-bar--<?= $cls ?>" style="width:<?= $pct ?>%"></div>
                </div>
                <span class="ticket-progress-count"><?= $count ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

</div>

<!-- Accesos rápidos por estado -->
<div class="row g-3 mt-2">
    <?php foreach ($statuses as $key => $label): ?>
    <?php $count = (int) ($byStatus[$key] ?? 0); ?>
    <?php if ($count > 0): ?>
    <div class="col-auto">
        <a href="<?= base_url('tickets/admin?status=' . $key) ?>" class="btn btn-sm btn-outline-secondary">
            <?= esc($label) ?> <span class="badge bg-secondary ms-1"><?= $count ?></span>
        </a>
    </div>
    <?php endif; ?>
    <?php endforeach; ?>
</div>

<?= $this->endSection() ?>
