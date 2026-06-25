<?= $this->extend('layouts/app') ?>
<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/tickets.css') ?>">
<?= $this->endSection() ?>
<?= $this->section('page_content') ?>

<?php
$csrfName = csrf_token();
$csrfHash = csrf_hash();
$totalPages = $perPage > 0 ? (int) ceil($total / $perPage) : 1;

$statusColors = [
    'abierto'     => 'ticket-status--open',
    'en_progreso' => 'ticket-status--progress',
    'resuelto'    => 'ticket-status--resolved',
    'cerrado'     => 'ticket-status--closed',
];
$priorityColors = [
    'baja'    => 'ticket-priority--low',
    'media'   => 'ticket-priority--medium',
    'alta'    => 'ticket-priority--high',
    'urgente' => 'ticket-priority--urgent',
];
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="fw-bold mb-1" style="font-size:1.25rem">Gestión de Tickets</h2>
        <p class="text-muted mb-0" style="font-size:13px"><?= $total ?> ticket<?= $total !== 1 ? 's' : '' ?> en total</p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <a href="<?= base_url('tickets/admin/dashboard') ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-bar-chart-fill me-1"></i>Dashboard
        </a>
        <a href="<?= base_url('tickets') ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-person me-1"></i>Mis tickets
        </a>
    </div>
</div>

<!-- Filtros -->
<form method="GET" action="<?= base_url('tickets/admin') ?>" class="ticket-filters mb-4">
    <div class="row g-2 align-items-end">
        <div class="col-sm-4 col-lg-3">
            <input type="text" name="search" class="form-control form-control-sm"
                   placeholder="Buscar por título, número o usuario..."
                   value="<?= esc($filters['search']) ?>">
        </div>
        <div class="col-sm-2 col-lg-2">
            <select name="status" class="form-select form-select-sm">
                <option value="">Todos los estados</option>
                <?php foreach ($statuses as $key => $label): ?>
                <option value="<?= $key ?>" <?= $filters['status'] === $key ? 'selected' : '' ?>><?= esc($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-sm-2 col-lg-2">
            <select name="priority" class="form-select form-select-sm">
                <option value="">Todas las prioridades</option>
                <?php foreach ($priorities as $key => $label): ?>
                <option value="<?= $key ?>" <?= $filters['priority'] === $key ? 'selected' : '' ?>><?= esc($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-sm-2 col-lg-2">
            <select name="category" class="form-select form-select-sm">
                <option value="">Todas las categorías</option>
                <?php foreach ($categories as $key => $label): ?>
                <option value="<?= $key ?>" <?= $filters['category'] === $key ? 'selected' : '' ?>><?= esc($label) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-sm-2 col-lg-1">
            <button type="submit" class="btn btn-sm btn-primary w-100">
                <i class="bi bi-search"></i>
            </button>
        </div>
        <?php if (array_filter($filters)): ?>
        <div class="col-auto">
            <a href="<?= base_url('tickets/admin') ?>" class="btn btn-sm btn-outline-secondary">
                <i class="bi bi-x-lg me-1"></i>Limpiar
            </a>
        </div>
        <?php endif; ?>
    </div>
</form>

<?php if (empty($tickets)): ?>
<div class="ticket-empty">
    <i class="bi bi-inbox ticket-empty-icon"></i>
    <p class="ticket-empty-title">No hay tickets que mostrar</p>
    <p class="ticket-empty-sub">Prueba a cambiar los filtros.</p>
</div>

<?php else: ?>
<div class="ticket-admin-table-wrap">
    <table class="ticket-admin-table">
        <thead>
            <tr>
                <th>Nº Ticket</th>
                <th>Título</th>
                <th>Solicitante</th>
                <th>Categoría</th>
                <th>Prioridad</th>
                <th>Estado</th>
                <th>Resp.</th>
                <th>Creado</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tickets as $t): ?>
            <?php
                $statusCls   = $statusColors[$t['status']]    ?? '';
                $priorityCls = $priorityColors[$t['priority']] ?? '';
            ?>
            <tr class="ticket-admin-row">
                <td class="ticket-number-cell">
                    <span class="ticket-number"><?= esc($t['ticket_number']) ?></span>
                </td>
                <td class="ticket-title-cell">
                    <span class="ticket-admin-title"><?= esc($t['title']) ?></span>
                </td>
                <td><?= esc($t['user_name']) ?></td>
                <td>
                    <span class="ticket-category-badge">
                        <?= esc($categories[$t['category']] ?? $t['category']) ?>
                    </span>
                </td>
                <td>
                    <span class="ticket-priority <?= $priorityCls ?>">
                        <?= esc($priorities[$t['priority']] ?? $t['priority']) ?>
                    </span>
                </td>
                <td>
                    <span class="ticket-status <?= $statusCls ?>">
                        <?= esc($statuses[$t['status']] ?? $t['status']) ?>
                    </span>
                </td>
                <td class="text-center"><?= (int) $t['reply_count'] ?></td>
                <td class="ticket-date-cell"><?= date('d/m/Y', strtotime($t['created_at'])) ?></td>
                <td>
                    <a href="<?= base_url('tickets/' . $t['id']) ?>" class="btn btn-xs btn-outline-secondary">
                        Ver
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Paginación -->
<?php if ($totalPages > 1): ?>
<nav class="d-flex justify-content-center mt-4">
    <ul class="pagination pagination-sm mb-0">
        <?php for ($p = 1; $p <= $totalPages; $p++): ?>
        <li class="page-item <?= $p === $page ? 'active' : '' ?>">
            <a class="page-link" href="<?= base_url('tickets/admin') ?>?page=<?= $p ?>
                <?= $filters['status']   ? '&status='   . urlencode($filters['status'])   : '' ?>
                <?= $filters['priority'] ? '&priority=' . urlencode($filters['priority']) : '' ?>
                <?= $filters['category'] ? '&category=' . urlencode($filters['category']) : '' ?>
                <?= $filters['search']   ? '&search='   . urlencode($filters['search'])   : '' ?>
            "><?= $p ?></a>
        </li>
        <?php endfor; ?>
    </ul>
</nav>
<?php endif; ?>
<?php endif; ?>

<?= $this->endSection() ?>
