<?= $this->extend('layouts/app') ?>
<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/tickets.css') ?>">
<?= $this->endSection() ?>
<?= $this->section('page_content') ?>

<?php
$role = session('role');

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

<div class="d-flex justify-content-between align-items-center mb-3 flex-wrap gap-2">
    <div>
        <h2 class="fw-bold mb-1" style="font-size:1.25rem">Mis Tickets</h2>
        <p class="text-muted mb-0" style="font-size:13px">
            <span id="ticket-count"><?= count($tickets) ?></span> de <?= count($tickets) ?>
            ticket<?= count($tickets) !== 1 ? 's' : '' ?>
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if (in_array($role, ['admin', 'superadmin'], true)): ?>
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

<!-- ── Buscador + filtros ─────────────────────────────────── -->
<div class="ticket-toolbar" id="ticket-toolbar">
    <div class="ticket-toolbar-search">
        <i class="bi bi-search"></i>
        <input type="text" id="tf-search" autocomplete="off" spellcheck="false"
               placeholder="Buscar por número o título…">
        <button type="button" id="tf-search-clear" aria-label="Limpiar" hidden><i class="bi bi-x-lg"></i></button>
    </div>
    <select id="tf-status" class="ticket-toolbar-select">
        <option value="">Todos los estados</option>
        <?php foreach ($statuses as $k => $v): ?><option value="<?= $k ?>"><?= esc($v) ?></option><?php endforeach; ?>
    </select>
    <select id="tf-priority" class="ticket-toolbar-select">
        <option value="">Todas las prioridades</option>
        <?php foreach ($priorities as $k => $v): ?><option value="<?= $k ?>"><?= esc($v) ?></option><?php endforeach; ?>
    </select>
    <select id="tf-category" class="ticket-toolbar-select">
        <option value="">Todas las categorías</option>
        <?php foreach ($categories as $k => $v): ?><option value="<?= $k ?>"><?= esc($v) ?></option><?php endforeach; ?>
    </select>
    <button type="button" id="tf-reset" class="ticket-toolbar-reset" hidden>
        <i class="bi bi-arrow-counterclockwise me-1"></i>Quitar filtros
    </button>

    <div class="ticket-toolbar-export">
        <button type="button" class="btn btn-sm btn-outline-secondary dropdown-toggle"
                data-bs-toggle="dropdown" aria-expanded="false">
            <i class="bi bi-download me-1"></i>Exportar
        </button>
        <ul class="dropdown-menu dropdown-menu-end">
            <li><a class="dropdown-item" href="#" data-export="csv"><i class="bi bi-file-earmark-spreadsheet me-2"></i>Excel (CSV)</a></li>
            <li><a class="dropdown-item" href="#" data-export="pdf" target="_blank"><i class="bi bi-file-earmark-pdf me-2"></i>PDF (imprimir)</a></li>
        </ul>
    </div>
</div>

<div class="ticket-list" id="ticket-list">
    <?php foreach ($tickets as $t): ?>
    <?php
        $statusCls   = $statusColors[$t['status']]   ?? '';
        $priorityCls = $priorityColors[$t['priority']] ?? '';
        $catLabel    = $categories[$t['category']] ?? $t['category'];
        $haystack    = mb_strtolower($t['ticket_number'] . ' ' . $t['title'] . ' ' . $catLabel);
    ?>
    <a href="<?= base_url('tickets/' . $t['id']) ?>" class="ticket-card"
       data-status="<?= esc($t['status'], 'attr') ?>"
       data-priority="<?= esc($t['priority'], 'attr') ?>"
       data-category="<?= esc($t['category'], 'attr') ?>"
       data-text="<?= esc($haystack, 'attr') ?>">
        <div class="ticket-card-left">
            <div class="ticket-number"><?= esc($t['ticket_number']) ?></div>
            <div class="ticket-card-title"><?= esc($t['title']) ?></div>
            <div class="ticket-card-meta">
                <span class="ticket-category-badge"><?= esc($catLabel) ?></span>
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

<div class="ticket-noresults" id="ticket-noresults" hidden>
    <i class="bi bi-search"></i>
    <p>Ningún ticket coincide con la búsqueda o los filtros.</p>
</div>

<script>
(function () {
    const EXPORT_BASE = '<?= base_url('tickets/export') ?>';
    const list   = document.getElementById('ticket-list');
    const cards  = Array.from(list.querySelectorAll('.ticket-card'));
    const search = document.getElementById('tf-search');
    const clearB = document.getElementById('tf-search-clear');
    const selS   = document.getElementById('tf-status');
    const selP   = document.getElementById('tf-priority');
    const selC   = document.getElementById('tf-category');
    const resetB = document.getElementById('tf-reset');
    const countEl = document.getElementById('ticket-count');
    const noRes  = document.getElementById('ticket-noresults');

    function state() {
        return {
            search:   search.value.trim().toLowerCase(),
            status:   selS.value,
            priority: selP.value,
            category: selC.value,
        };
    }

    function apply() {
        const s = state();
        let visible = 0;
        cards.forEach(c => {
            const ok =
                (!s.search   || c.dataset.text.includes(s.search)) &&
                (!s.status   || c.dataset.status === s.status) &&
                (!s.priority || c.dataset.priority === s.priority) &&
                (!s.category || c.dataset.category === s.category);
            c.hidden = !ok;
            if (ok) visible++;
        });
        countEl.textContent = visible;
        noRes.hidden = visible > 0;
        list.hidden = visible === 0;

        const dirty = s.search || s.status || s.priority || s.category;
        clearB.hidden = !s.search;
        resetB.hidden = !dirty;

        // Actualiza los enlaces de exportar con los filtros actuales
        const qs = new URLSearchParams();
        if (s.search)   qs.set('search', search.value.trim());
        if (s.status)   qs.set('status', s.status);
        if (s.priority) qs.set('priority', s.priority);
        if (s.category) qs.set('category', s.category);
        document.querySelectorAll('[data-export]').forEach(a => {
            const u = new URLSearchParams(qs);
            u.set('format', a.dataset.export);
            a.href = EXPORT_BASE + '?' + u.toString();
        });
    }

    let t;
    search.addEventListener('input', () => { clearTimeout(t); t = setTimeout(apply, 120); });
    [selS, selP, selC].forEach(el => el.addEventListener('change', apply));
    clearB.addEventListener('click', () => { search.value = ''; apply(); search.focus(); });
    resetB.addEventListener('click', () => {
        search.value = ''; selS.value = ''; selP.value = ''; selC.value = ''; apply();
    });

    apply();
})();
</script>
<?php endif; ?>

<?= $this->endSection() ?>
