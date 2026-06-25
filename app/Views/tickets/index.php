<?= $this->extend('layouts/app') ?>
<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/tickets.css') ?>">
<?= $this->endSection() ?>
<?= $this->section('page_content') ?>

<?php
$userId   = session('id');
$role     = session('role');
$csrfName = csrf_token();
$csrfHash = csrf_hash();

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
        <h2 class="fw-bold mb-1" style="font-size:1.25rem">Mis Tickets</h2>
        <p class="text-muted mb-0" style="font-size:13px">
            <?= count($tickets) ?> ticket<?= count($tickets) !== 1 ? 's' : '' ?> registrado<?= count($tickets) !== 1 ? 's' : '' ?>
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if ($role === 'superadmin'): ?>
        <a href="<?= base_url('tickets/admin/dashboard') ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-bar-chart-fill me-1"></i>Dashboard
        </a>
        <a href="<?= base_url('tickets/admin') ?>" class="btn btn-sm btn-outline-secondary">
            <i class="bi bi-list-ul me-1"></i>Todos los tickets
        </a>
        <?php endif; ?>
        <a href="<?= base_url('tickets/create') ?>" class="btn btn-sm btn-primary">
            <i class="bi bi-plus-lg me-1"></i>Nuevo ticket
        </a>
    </div>
</div>

<?php if (empty($tickets)): ?>
<div class="ticket-empty">
    <i class="bi bi-ticket-perforated ticket-empty-icon"></i>
    <p class="ticket-empty-title">Sin tickets todavía</p>
    <p class="ticket-empty-sub">Usa el botón de arriba para reportar un problema o enviar una sugerencia.</p>
    <a href="<?= base_url('tickets/create') ?>" class="btn btn-primary mt-2">
        <i class="bi bi-plus-lg me-1"></i>Crear mi primer ticket
    </a>
</div>

<?php else: ?>
<div class="ticket-list">
    <?php foreach ($tickets as $t): ?>
    <?php
        $statusCls   = $statusColors[$t['status']]   ?? '';
        $priorityCls = $priorityColors[$t['priority']] ?? '';
    ?>
    <a href="<?= base_url('tickets/' . $t['id']) ?>" class="ticket-card">
        <div class="ticket-card-left">
            <div class="ticket-number"><?= esc($t['ticket_number']) ?></div>
            <div class="ticket-card-title"><?= esc($t['title']) ?></div>
            <div class="ticket-card-meta">
                <span class="ticket-category-badge">
                    <?= esc($categories[$t['category']] ?? $t['category']) ?>
                </span>
                <span class="ticket-meta-sep">·</span>
                <i class="bi bi-chat-left-text" style="font-size:11px"></i>
                <?= (int) $t['reply_count'] ?> respuesta<?= (int)$t['reply_count'] !== 1 ? 's' : '' ?>
                <span class="ticket-meta-sep">·</span>
                <?= date('d/m/Y', strtotime($t['created_at'])) ?>
            </div>
        </div>
        <div class="ticket-card-right">
            <span class="ticket-priority <?= $priorityCls ?>">
                <?= esc($priorities[$t['priority']] ?? $t['priority']) ?>
            </span>
            <span class="ticket-status <?= $statusCls ?>">
                <?= esc($statuses[$t['status']] ?? $t['status']) ?>
            </span>
        </div>
    </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?= $this->endSection() ?>
