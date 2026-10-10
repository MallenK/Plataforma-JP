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
    ['desde' => $from, 'hasta' => $to, 'categoria' => $cat, 'orden' => $asc ? 'asc' : null, 'consultas' => $showViews ? '1' : null], $extra),
    fn($v) => $v !== null && $v !== ''));
$total   = array_sum($counts);
$alertUi = ['danger' => ['#991b1b', '#fef2f2', '#fecaca', 'bi-exclamation-octagon-fill'], 'warn' => ['#92400e', '#fffbeb', '#fde68a', 'bi-exclamation-triangle-fill'],
            'info' => ['#1e40af', '#eff6ff', '#bfdbfe', 'bi-info-circle-fill']];
$statusUi = ['Vigente' => ['#065f46', '#d1fae5'], 'Agotado' => ['#475569', '#f1f5f9'], 'Caducado' => ['#5b21b6', '#ede9fe'], 'Anulado' => ['#991b1b', '#fee2e2']];
$available = array_sum(array_map(fn($b) => $b['status'] === 'Vigente' ? $b['real'] : 0, $bonos));
$dt = static fn(string $at) => date('d/m/Y', strtotime($at)) . ' <span style="color:var(--text-muted)">' . date('H:i', strtotime($at)) . '</span>';
?>

<?= $this->section('page_content') ?>
<?= view('finanzas/_tabs', ['tab' => $tab, 'reviewCount' => $reviewCount]) ?>

<style>
.hist-sec { font-size: 16px; font-weight: 800; color: var(--text-h); margin: 22px 0 10px; display: flex; align-items: center; gap: 6px; }
.hist-chip { font-size: 11px; font-weight: 700; border-radius: 10px; padding: 2px 8px; white-space: nowrap; }
.hist-alert { display: flex; gap: 10px; align-items: flex-start; border: 1px solid; border-radius: 10px; padding: 10px 12px; font-size: 13px; margin-bottom: 8px; }
.hist-alert i { font-size: 16px; line-height: 1.2; }
.hist-bono { margin-bottom: 12px; }
.hist-bono > summary { list-style: none; cursor: pointer; padding: 14px 16px; }
.hist-bono > summary::-webkit-details-marker { display: none; }
.hist-bono > summary .hist-caret { transition: transform .15s; color: var(--text-muted); }
.hist-bono[open] > summary .hist-caret { transform: rotate(90deg); }
.hist-bar { height: 8px; border-radius: 4px; background: #e2e8f0; overflow: hidden; margin-top: 8px; max-width: 360px; }
.hist-bar > span { display: block; height: 100%; background: #1d4ed8; border-radius: 4px; }
.hist-steps td, .hist-steps th { vertical-align: top; }
.hist-delta-neg { color: #b91c1c; font-weight: 700; }
.hist-delta-pos { color: #047857; font-weight: 700; }
.hist-saldo { font-size: 12px; color: var(--text-body); white-space: nowrap; }
#hist-table td.hist-detail { min-width: 280px; }
@media print {
    .sidebar, .topbar, .sidebar-overlay, .fin-tabs, .fin-noprint, .jp-files-toolbar, .dt-search, .dt-paging, .dt-length, .dt-info, .fin-help { display: none !important; }
    .main-wrap { margin: 0 !important; }
    .page-body { padding: 0 !important; }
    .card-jp { box-shadow: none !important; }
    body { background: #fff !important; }
    tr, .hist-alert { break-inside: avoid; }
    a { color: inherit !important; text-decoration: none !important; }
}
</style>

<div class="d-flex flex-wrap justify-content-between align-items-end gap-2 mb-2">
    <div>
        <a href="<?= base_url('finanzas/alumnos/' . (int) $player['id']) ?>" class="fin-back fin-noprint"><i class="bi bi-arrow-left" aria-hidden="true"></i>Volver a la cuenta</a>
        <h2 style="margin:6px 0 0;font-size:22px;font-weight:800;color:var(--text-h)">Historial completo · <?= esc($player['name']) ?></h2>
        <div style="font-size:13px;color:var(--text-body)">
            <?= count($bonos) ?> <?= count($bonos) === 1 ? 'bono' : 'bonos' ?> · <strong><?= $available ?></strong> <?= $available === 1 ? 'sesión disponible' : 'sesiones disponibles' ?>
            · <?= count($upcoming) ?> <?= count($upcoming) === 1 ? 'clase programada' : 'clases programadas' ?>
            · <strong style="color:<?= $debt > 0 ? '#9a3412' : '#047857' ?>"><?= esc(\App\Services\StudentHistoryService::balanceText((int) $debt)) ?></strong>
        </div>
    </div>
    <div class="d-flex flex-wrap gap-2 fin-noprint">
        <a href="<?= $qs(['export' => 'csv']) ?>" class="btn-jp btn-jp-primary btn-jp-sm" style="text-decoration:none"><i class="bi bi-file-earmark-spreadsheet me-1" aria-hidden="true"></i>Descargar Excel (CSV)</a>
        <button type="button" class="btn-jp btn-jp-secondary btn-jp-sm" id="hist-print"><i class="bi bi-printer me-1" aria-hidden="true"></i>Imprimir / guardar PDF</button>
    </div>
</div>

<!-- 1 · Qué revisar -->
<div class="hist-sec">Qué revisar<?= fin_help('hist_revisar') ?></div>
<?php if (empty($alerts)): ?>
<div class="hist-alert" style="color:#065f46;background:#ecfdf5;border-color:#a7f3d0"><i class="bi bi-check-circle-fill" aria-hidden="true"></i><div><strong>Todo cuadra.</strong> Los saldos de sus bonos coinciden con sus movimientos y no hay nada pendiente.</div></div>
<?php else: foreach ($alerts as $a): [$fg, $bg, $bd, $ic] = $alertUi[$a['level']]; ?>
<div class="hist-alert" style="color:<?= $fg ?>;background:<?= $bg ?>;border-color:<?= $bd ?>">
    <i class="bi <?= $ic ?>" aria-hidden="true"></i>
    <div><strong><?= esc($a['title']) ?>.</strong> <?= esc($a['text']) ?>
        <?php if (!empty($a['link'])): ?> <a href="<?= base_url($a['link']) ?>" class="fin-noprint" style="color:<?= $fg ?>;font-weight:700">Ver</a><?php endif; ?></div>
</div>
<?php endforeach; endif; ?>

<!-- 2 · Bonos, paso a paso -->
<div class="hist-sec">Sus bonos, paso a paso<?= fin_help('hist_bonos') ?></div>
<?php if (empty($bonos)): ?>
<div class="card-jp"><div class="card-jp-body"><p style="margin:0;color:var(--text-muted);font-size:13px">No tiene bonos.</p></div></div>
<?php endif; ?>
<?php foreach ($bonos as $i => $b): [$sfg, $sbg] = $statusUi[$b['status']] ?? ['#475569', '#f1f5f9'];
    $usedPct = $b['total'] > 0 ? max(0, min(100, round(($b['total'] - $b['real']) / $b['total'] * 100))) : 0;
    $pending = $b['charge_cents'] !== null ? $b['charge_cents'] - $b['paid_cents'] : null; ?>
<details class="card-jp hist-bono" <?= $b['status'] === 'Vigente' || $b['diff'] !== 0 || $i === 0 ? 'open' : '' ?>>
    <summary>
        <div class="d-flex flex-wrap align-items-center gap-2">
            <i class="bi bi-chevron-right hist-caret" aria-hidden="true"></i>
            <span style="font-size:15px;font-weight:800;color:var(--text-h)"><?= esc($b['label']) ?></span>
            <span class="hist-chip" style="color:<?= $sfg ?>;background:<?= $sbg ?>"><?= $b['status'] ?></span>
            <?php if ($b['diff'] !== 0): ?><span class="hist-chip" style="color:#991b1b;background:#fee2e2"><i class="bi bi-exclamation-octagon-fill" aria-hidden="true"></i> No cuadra</span><?php endif; ?>
            <span style="margin-left:auto;font-size:14px;color:var(--text-h)">Quedan <strong style="font-size:18px"><?= $b['real'] ?></strong> de <?= $b['total'] ?></span>
        </div>
        <div style="font-size:12.5px;color:var(--text-body);margin:6px 0 0 22px">
            Comprado el <?= date('d/m/Y', strtotime($b['created_at'])) ?><?= $b['created_by'] ? ' por ' . esc($b['created_by']) : '' ?>
            · válido <?= $b['start_date'] ? date('d/m/Y', strtotime($b['start_date'])) : '—' ?> – <?= $b['expires_at'] ? date('d/m/Y', strtotime($b['expires_at'])) : 'sin fecha' ?>
            <?php if ($b['price_cents'] !== null): ?> · <?= eur($b['price_cents']) ?><?= $b['price_estimated'] ? ' (estimado)' : '' ?><?php endif; ?>
            <?php if ($pending !== null): ?> · <?= $pending > 0 ? '<strong style="color:#9a3412">pendiente de pagar ' . eur($pending) . '</strong>' : 'pagado' . ($b['methods'] ? ' (' . esc(implode(', ', $b['methods'])) . ')' : '') ?><?php endif; ?>
            <div class="hist-bar" role="img" aria-label="Usadas <?= $b['total'] - $b['real'] ?> de <?= $b['total'] ?>"><span style="width:<?= $usedPct ?>%"></span></div>
        </div>
    </summary>
    <div class="table-responsive">
        <table class="table-jp hist-steps" style="font-size:13px">
            <thead><tr><th style="width:130px">Fecha</th><th>Qué pasó</th><th style="text-align:right;width:80px">Sesiones</th><th style="text-align:right;width:80px">Quedan<?= fin_help('hist_saldo') ?></th><th style="width:170px">Quién</th></tr></thead>
            <tbody>
            <?php foreach ($b['events'] as $e): ?>
            <tr>
                <td style="white-space:nowrap"><?= $dt($e['at']) ?></td>
                <td><strong style="color:var(--text-h)"><?= esc($e['title']) ?></strong>
                    <div style="color:var(--text-body)"><?= !empty($e['session_id']) ? '<a href="' . base_url('clases/' . (int) $e['session_id']) . '" class="row-link-anchor">' . esc($e['text']) . '</a>' : esc($e['text']) ?></div></td>
                <td style="text-align:right" class="<?= $e['delta'] < 0 ? 'hist-delta-neg' : ($e['delta'] > 0 ? 'hist-delta-pos' : '') ?>"><?= $e['delta'] > 0 ? '+' . $e['delta'] : ($e['delta'] < 0 ? '−' . abs($e['delta']) : '—') ?></td>
                <td style="text-align:right;font-weight:700"><?= (int) $e['saldo'] ?></td>
                <td style="color:var(--text-body)"><?= esc($e['who'] ?? '—') ?></td>
            </tr>
            <?php endforeach; ?>
            <tr style="background:<?= $b['diff'] !== 0 ? '#fef2f2' : '#f0fdf4' ?>">
                <td colspan="3" style="font-weight:700;color:<?= $b['diff'] !== 0 ? '#991b1b' : '#065f46' ?>">
                    <?php if ($b['diff'] === 0): ?><i class="bi bi-check-circle-fill" aria-hidden="true"></i> Saldo real hoy: coincide con los movimientos
                    <?php elseif ($b['diff'] > 0): ?><i class="bi bi-exclamation-octagon-fill" aria-hidden="true"></i> Saldo real hoy: <?= $b['real'] ?>. <?= $b['diff'] === 1 ? 'Falta 1 sesión' : 'Faltan ' . $b['diff'] . ' sesiones' ?> por explicar (según los movimientos deberían quedar <?= $b['expected'] ?>)
                    <?php else: ?><i class="bi bi-exclamation-octagon-fill" aria-hidden="true"></i> Saldo real hoy: <?= $b['real'] ?>. Hay <?= -$b['diff'] ?> de más que nada explica (según los movimientos deberían quedar <?= $b['expected'] ?>)
                    <?php endif; ?>
                </td>
                <td style="text-align:right;font-weight:800;font-size:15px"><?= $b['real'] ?></td>
                <td></td>
            </tr>
            </tbody>
        </table>
    </div>
    <?php if ($b['planned'] || $b['leftover'] > 0): ?>
    <div class="card-jp-body" style="font-size:12.5px;color:var(--text-body);padding-top:8px">
        <?php if ($b['planned']): ?><i class="bi bi-calendar-event" aria-hidden="true"></i> Próximas clases que saldrán de este bono:
            <?= implode(', ', array_map(fn($u) => date('d/m', strtotime($u['at'])), $b['planned'])) ?>.<?php endif; ?>
        <?php if ($b['leftover'] > 0 && $b['status'] === 'Vigente'): ?> <strong>Le sobrará<?= $b['leftover'] > 1 ? 'n ' . $b['leftover'] . ' sesiones' : ' 1 sesión' ?></strong> si no se programan más clases antes del <?= date('d/m/Y', strtotime($b['expires_at'])) ?>.<?php endif; ?>
    </div>
    <?php endif; ?>
</details>
<?php endforeach; ?>

<!-- 3 · Próximas clases -->
<div class="hist-sec">Próximas clases<?= fin_help('hist_proximas') ?></div>
<div class="card-jp">
    <?php if (empty($upcoming)): ?>
    <div class="card-jp-body"><p style="margin:0;color:var(--text-muted);font-size:13px">No tiene clases programadas.</p></div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table-jp" style="font-size:13px">
            <thead><tr><th style="width:130px">Fecha</th><th>Clase</th><th>Entrenador</th><th>Bono</th></tr></thead>
            <tbody>
            <?php foreach ($upcoming as $u): ?>
            <tr>
                <td style="white-space:nowrap"><?= $dt($u['at']) ?></td>
                <td><a href="<?= base_url($u['link']) ?>" class="row-link-anchor"><?= esc($u['title']) ?></a></td>
                <td style="color:var(--text-body)"><?= esc($u['coach'] ?? '—') ?></td>
                <td style="<?= !$u['bono_id'] && !$u['declined'] ? 'color:#92400e;font-weight:700' : 'color:var(--text-body)' ?>"><?= esc(ucfirst($u['from'])) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- 4 · Todo lo que ha pasado -->
<div class="hist-sec">Todo lo que ha pasado<?= fin_help('historial') ?></div>
<form method="get" class="d-flex flex-wrap gap-2 align-items-end mb-2 fin-noprint" style="font-size:13px">
    <div>
        <label class="form-label" for="h-cat" style="margin-bottom:2px">Categoría</label>
        <select id="h-cat" name="categoria" class="form-control-jp" style="width:auto">
            <option value="">Todas (<?= $total ?>)</option>
            <?php foreach ($cats as $k => $label): ?><option value="<?= $k ?>" <?= $cat === $k ? 'selected' : '' ?>><?= $label ?> (<?= (int) $counts[$k] ?>)</option><?php endforeach; ?>
        </select>
    </div>
    <div><label class="form-label" for="h-desde" style="margin-bottom:2px">Desde</label><input type="date" id="h-desde" name="desde" class="form-control-jp" style="width:auto" value="<?= esc($from ?? '') ?>"></div>
    <div><label class="form-label" for="h-hasta" style="margin-bottom:2px">Hasta</label><input type="date" id="h-hasta" name="hasta" class="form-control-jp" style="width:auto" value="<?= esc($to ?? '') ?>"></div>
    <div>
        <label class="form-label" for="h-orden" style="margin-bottom:2px">Orden</label>
        <select id="h-orden" name="orden" class="form-control-jp" style="width:auto">
            <option value="">Más reciente primero</option>
            <option value="asc" <?= $asc ? 'selected' : '' ?>>Más antiguo primero</option>
        </select>
    </div>
    <label style="display:flex;align-items:center;gap:6px;padding-bottom:8px"><input type="checkbox" name="consultas" value="1" <?= $showViews ? 'checked' : '' ?>> Mostrar quién ha consultado este historial</label>
    <button type="submit" class="btn-jp btn-jp-secondary">Aplicar</button>
    <?php if ($from || $to || $cat || $asc || $showViews): ?><a href="<?= base_url('finanzas/alumnos/' . (int) $player['id'] . '/historial') ?>" class="btn-jp btn-jp-secondary" style="text-decoration:none">Quitar filtros</a><?php endif; ?>
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
        <table class="table-jp" style="font-size:13px" data-fin-list="historial" data-fin-page="50" data-fin-order='[[0,"<?= $asc ? 'asc' : 'desc' ?>"]]' id="hist-table">
            <thead><tr><th>Fecha</th><th>Categoría</th><th>Qué pasó</th><th>Detalle</th><th style="text-align:right">Importe</th><th>Saldo después<?= fin_help('hist_saldo_col') ?></th><th>Quién</th></tr></thead>
            <tbody>
            <?php foreach ($rows as $r): [$fg, $bg] = $colors[$r['cat']] ?? ['#475569', '#e2e8f0']; ?>
            <tr>
                <td style="white-space:nowrap;color:var(--text-body)" data-order="<?= esc($r['at']) ?>"><?= $dt($r['at']) ?></td>
                <td><span class="hist-chip" style="color:<?= $fg ?>;background:<?= $bg ?>"><?= esc($cats[$r['cat']] ?? $r['cat']) ?></span></td>
                <td style="font-weight:600;color:var(--text-h)"><?= esc($r['event']) ?></td>
                <td class="hist-detail" style="color:var(--text-body)"><?= !empty($r['link']) ? '<a href="' . base_url($r['link']) . '" class="row-link-anchor">' . esc($r['detail']) . '</a>' : esc($r['detail']) ?></td>
                <td style="text-align:right;white-space:nowrap;font-weight:700" data-order="<?= $r['cents'] ?? 0 ?>"><?= $r['cents'] === null ? '' : ($r['cents'] < 0 ? '−' : '') . eur(abs($r['cents'])) ?></td>
                <td class="hist-saldo"><?= esc($r['saldo'] ?? '') ?></td>
                <td style="color:var(--text-body)"><?= esc($r['who'] ?? '—') ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<script>
// Imprimir TODO: bonos desplegados y el historial completo (no solo la página visible de la tabla).
document.getElementById('hist-print').addEventListener('click', function () {
    var closed = Array.prototype.filter.call(document.querySelectorAll('details.hist-bono'), function (d) { return !d.open; });
    closed.forEach(function (d) { d.open = true; });
    var t = window.jQuery && jQuery.fn.dataTable && jQuery.fn.dataTable.isDataTable('#hist-table') ? jQuery('#hist-table').DataTable() : null;
    if (t) { t.search('').page.len(-1).draw(); }
    window.print();
    if (t) { t.page.len(50).draw(); }
    closed.forEach(function (d) { d.open = false; });
});
</script>

<?= $this->endSection() ?>
