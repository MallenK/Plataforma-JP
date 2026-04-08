<?= $this->extend('layouts/app') ?>

<?php
$pageTitle    = 'Organizador';
$pageSubtitle = 'Calendario y planificación de clases';
?>

<?= $this->section('page_content') ?>

<div class="page-header">
    <div class="page-header-text">
        <h2>Organizador</h2>
        <p>Planifica y gestiona el calendario de entrenamiento</p>
    </div>
    <div class="d-flex gap-2">
        <div class="calendar-view-tabs">
            <button class="calendar-view-tab active">Semana</button>
            <button class="calendar-view-tab">Mes</button>
            <button class="calendar-view-tab">Día</button>
        </div>
        <?php if (in_array(session('role'), ['superadmin', 'admin', 'coach'])): ?>
        <a href="<?= base_url('clases') ?>" class="btn-jp btn-jp-primary">
            <i class="bi bi-plus-lg"></i> Nueva clase
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- Barra de calendario -->
<div class="card-jp mb-3">
    <div class="card-jp-body py-3 d-flex align-items-center justify-content-between flex-wrap gap-2">
        <div class="calendar-nav">
            <button id="cal-prev"><i class="bi bi-chevron-left"></i></button>
            <span class="calendar-nav-label" id="cal-range">—</span>
            <button id="cal-next"><i class="bi bi-chevron-right"></i></button>
        </div>
        <div class="d-flex gap-2">
            <button class="btn-jp btn-jp-secondary btn-jp-sm" id="cal-today">Hoy</button>
            <select class="form-control-jp" style="width:auto;min-width:160px">
                <option value="">Todos los entrenadores</option>
            </select>
        </div>
    </div>
</div>

<!-- Grid semanal -->
<div class="card-jp">
    <div class="card-jp-body p-0">
        <div id="calendar-grid" style="min-height:480px;display:flex;align-items:center;justify-content:center;flex-direction:column;gap:12px;color:var(--text-muted)">
            <i class="bi bi-calendar3" style="font-size:3rem;color:#cbd5e1"></i>
            <div style="font-size:15px;font-weight:600;color:var(--text-body)">Calendario en construcción</div>
            <div style="font-size:13px">El organizador con vista semanal se habilitará en la próxima fase.</div>
            <a href="<?= base_url('clases') ?>" class="btn-jp btn-jp-primary btn-jp-sm">
                <i class="bi bi-collection-play-fill"></i> Ver clases
            </a>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
// Semana actual
(function () {
    const now = new Date();
    const start = new Date(now);
    start.setDate(now.getDate() - now.getDay() + 1); // lunes
    const end = new Date(start);
    end.setDate(start.getDate() + 6); // domingo

    const fmt = { day: '2-digit', month: 'short' };
    document.getElementById('cal-range').textContent =
        start.toLocaleDateString('es-ES', fmt) + ' — ' + end.toLocaleDateString('es-ES', fmt) + ', ' + now.getFullYear();
})();
</script>
<?= $this->endSection() ?>
