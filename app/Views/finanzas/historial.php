<?= $this->extend('layouts/app') ?>

<?php
$pageTitle    = 'Finanzas';
$pageSubtitle = 'Historial completo del alumno';
helper(['money', 'finhelp']);
$cats   = \App\Services\StudentHistoryService::CATEGORIES;
$colors = ['economico' => ['#065f46', '#d1fae5'], 'bonos' => ['#1d4ed8', '#dbeafe'], 'clases' => ['#0f766e', '#ccfbf1'],
           'acceso' => ['#475569', '#e2e8f0'], 'comunicacion' => ['#7c2d12', '#ffedd5'], 'seguimiento' => ['#6d28d9', '#ede9fe'],
           'auditoria' => ['#92400e', '#fef3c7']];
$qs = static fn(array $extra) => '?' . http_build_query(array_filter(array_merge(
    ['desde' => $from, 'hasta' => $to, 'categoria' => $cat], $extra), fn($v) => $v !== null && $v !== ''));
$total = array_sum($counts);
?>

<?= $this->section('page_content') ?>
<?= view('finanzas/_tabs', ['tab' => $tab, 'reviewCount' => $reviewCount]) ?>

<style>
@media print {
    .sidebar, .topbar, .sidebar-overlay, .fin-tabs, .fin-noprint, .jp-files-toolbar, .dt-search, .dt-paging, .dt-length, .dt-info, .fin-help { display: none !important; }
    .main-wrap { margin: 0 !important; }
    .page-body { padding: 0 !important; }
    .card-jp { box-shadow: none !important; border: 0 !important; }
    body { background: #fff !important; }
    tr { break-inside: avoid; }
    a { color: inherit !important; text-decoration: none !important; }
}
</style>

<div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-3">
    <div>
        <a href="<?= base_url('finanzas/alumnos/' . (int) $player['id']) ?>" class="fin-back fin-noprint"><i class="bi bi-arrow-left" aria-hidden="true"></i>Volver a la cuenta</a>
        <h2 style="margin:6px 0 0;font-size:22px;font-weight:800;color:var(--text-h)">Historial completo · <?= esc($player['name']) ?></h2>
        <div style="font-size:13px;color:var(--text-body)">Todo lo que ha pasado con este alumno en la plataforma, de lo más reciente a lo más antiguo.<?= fin_help('historial') ?></div>
    </div>
    <div class="d-flex flex-wrap gap-2 fin-noprint">
        <a href="<?= $qs(['export' => 'csv']) ?>" class="btn-jp btn-jp-primary btn-jp-sm" style="text-decoration:none"><i class="bi bi-file-earmark-spreadsheet me-1" aria-hidden="true"></i>Descargar Excel (CSV)</a>
        <button type="button" class="btn-jp btn-jp-secondary btn-jp-sm" id="hist-print"><i class="bi bi-printer me-1" aria-hidden="true"></i>Imprimir / guardar PDF</button>
    </div>
</div>

<form method="get" class="d-flex flex-wrap gap-2 align-items-end mb-3 fin-noprint" style="font-size:13px">
    <div>
        <label class="form-label" for="h-cat" style="margin-bottom:2px">Categoría</label>
        <select id="h-cat" name="categoria" class="form-control-jp" style="width:auto">
            <option value="">Todas (<?= $total ?>)</option>
            <?php foreach ($cats as $k => $label): ?><option value="<?= $k ?>" <?= $cat === $k ? 'selected' : '' ?>><?= $label ?> (<?= (int) $counts[$k] ?>)</option><?php endforeach; ?>
        </select>
    </div>
    <div><label class="form-label" for="h-desde" style="margin-bottom:2px">Desde</label><input type="date" id="h-desde" name="desde" class="form-control-jp" style="width:auto" value="<?= esc($from ?? '') ?>"></div>
    <div><label class="form-label" for="h-hasta" style="margin-bottom:2px">Hasta</label><input type="date" id="h-hasta" name="hasta" class="form-control-jp" style="width:auto" value="<?= esc($to ?? '') ?>"></div>
    <button type="submit" class="btn-jp btn-jp-secondary">Filtrar</button>
    <?php if ($from || $to || $cat): ?><a href="<?= base_url('finanzas/alumnos/' . (int) $player['id'] . '/historial') ?>" class="btn-jp btn-jp-secondary" style="text-decoration:none">Quitar filtros</a><?php endif; ?>
</form>

<div class="d-flex flex-wrap gap-2 mb-3 fin-noprint">
    <?php foreach ($cats as $k => $label): if (!$counts[$k]) continue; [$fg, $bg] = $colors[$k]; ?>
    <a href="<?= $qs(['categoria' => $cat === $k ? null : $k]) ?>" style="text-decoration:none;font-size:12px;font-weight:700;color:<?= $fg ?>;background:<?= $bg ?>;border-radius:14px;padding:4px 10px;<?= $cat === $k ? 'box-shadow:0 0 0 2px ' . $fg : '' ?>"><?= $label ?> · <?= (int) $counts[$k] ?></a>
    <?php endforeach; ?>
</div>

<div class="card-jp">
    <?php if (empty($rows)): ?>
    <div class="card-jp-body"><p style="margin:0;color:var(--text-muted);font-size:13px">No hay movimientos con estos filtros.</p></div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table-jp" style="font-size:13px" data-fin-list="historial" data-fin-page="50" data-fin-order='[[0,"desc"]]' id="hist-table">
            <thead><tr><th>Fecha</th><th>Categoría</th><th>Evento</th><th>Detalle</th><th style="text-align:right">Importe</th><th>Hecho por</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): [$fg, $bg] = $colors[$r['cat']] ?? ['#475569', '#e2e8f0']; ?>
            <tr>
                <td style="white-space:nowrap;color:var(--text-body)" data-order="<?= esc($r['at']) ?>"><?= date('d/m/Y', strtotime($r['at'])) ?> <span style="color:var(--text-muted)"><?= date('H:i', strtotime($r['at'])) ?></span></td>
                <td><span style="font-size:11px;font-weight:700;color:<?= $fg ?>;background:<?= $bg ?>;border-radius:10px;padding:2px 8px;white-space:nowrap"><?= esc($cats[$r['cat']] ?? $r['cat']) ?></span></td>
                <td style="font-weight:600;color:var(--text-h)"><?= esc($r['event']) ?></td>
                <td style="color:var(--text-body)"><?= !empty($r['link']) ? '<a href="' . base_url($r['link']) . '" class="row-link-anchor">' . esc($r['detail']) . '</a>' : esc($r['detail']) ?></td>
                <td style="text-align:right;white-space:nowrap;font-weight:700" data-order="<?= $r['cents'] ?? 0 ?>"><?= $r['cents'] === null ? '' : ($r['cents'] < 0 ? '−' : '') . eur(abs($r['cents'])) ?></td>
                <td style="color:var(--text-body)"><?= esc($r['who'] ?? '—') ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<script>
// Imprimir el historial COMPLETO (no solo la página visible de la tabla).
document.getElementById('hist-print').addEventListener('click', function () {
    var t = window.jQuery && jQuery.fn.dataTable && jQuery.fn.dataTable.isDataTable('#hist-table') ? jQuery('#hist-table').DataTable() : null;
    if (t) { t.search('').page.len(-1).draw(); }
    window.print();
    if (t) { t.page.len(50).draw(); }
});
</script>

<?= $this->endSection() ?>
