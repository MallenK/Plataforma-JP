<?= $this->extend('layouts/app') ?>

<?php
$pageTitle    = 'Finanzas';
$pageSubtitle = 'Movimientos: todas las operaciones del periodo, una a una';
helper(['money', 'finhelp']);
$types  = \App\Services\FinanceReportService::MOVE_TYPES;
$colors = ['venta' => ['#1d4ed8', '#dbeafe'], 'cobro' => ['#065f46', '#d1fae5'], 'gasto' => ['#9a3412', '#ffedd5'],
           'servicio' => ['#0f766e', '#ccfbf1'], 'caducado' => ['#5b21b6', '#ede9fe']];
$qs = static fn(array $extra) => '?' . http_build_query(array_filter(array_merge([
    'mes' => $_GET['mes'] ?? null, 'desde' => $_GET['desde'] ?? null, 'hasta' => $_GET['hasta'] ?? null, 'tipo' => $_GET['tipo'] ?? null,
], $extra), fn($v) => $v !== null && $v !== ''));
?>

<?= $this->section('page_content') ?>
<?= view('finanzas/_tabs', ['tab' => $tab, 'reviewCount' => $reviewCount]) ?>
<?= view('finanzas/_period', ['period' => $period, 'months' => $months, 'extra' => ['tipo' => $type]]) ?>

<div class="row g-2 mb-3">
    <?php foreach ($types as $k => $label): $t = $mov['totals'][$k]; [$fg, $bg] = $colors[$k]; ?>
    <div class="col-6 col-md-4 col-xl">
        <a href="<?= $qs(['tipo' => $type === $k ? null : $k]) ?>" class="metric-card" style="display:block;text-decoration:none;color:inherit;<?= $type === $k ? 'border-color:' . $fg . ';box-shadow:0 0 0 2px ' . $bg : '' ?>">
            <div class="metric-card-header"><span class="metric-label"><?= $label ?><?= fin_help('mov_' . $k) ?></span>
                <span style="font-size:11px;font-weight:700;color:<?= $fg ?>;background:<?= $bg ?>;border-radius:8px;padding:1px 7px"><?= (int) $t['count'] ?></span></div>
            <div class="metric-value" style="font-size:20px"><?= eur(abs($t['cents'])) ?></div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<div class="card-jp">
    <div class="card-jp-header" style="flex-wrap:wrap;gap:8px">
        <span class="card-jp-title"><?= count($mov['rows']) ?> operaciones<?= $type ? ' · ' . esc($types[$type]) : '' ?></span>
        <div class="d-flex gap-2 flex-wrap">
            <?php if ($type): ?><a href="<?= $qs(['tipo' => null]) ?>" class="btn-jp btn-jp-secondary btn-jp-sm" style="text-decoration:none">Ver todas</a><?php endif; ?>
            <a href="<?= $qs(['export' => 'csv']) ?>" class="btn-jp btn-jp-secondary btn-jp-sm" style="text-decoration:none"><i class="bi bi-download me-1"></i>Exportar CSV</a><?= fin_help('export') ?>
        </div>
    </div>
    <?php if (empty($mov['rows'])): ?>
    <div class="card-jp-body"><p style="margin:0;color:var(--text-muted);font-size:13px">No hay operaciones en este periodo.</p></div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table-jp" style="font-size:13px" data-fin-list="movimientos" data-fin-order='[[0,"desc"]]'>
            <thead><tr><th>Fecha</th><th>Tipo</th><th>Alumno / quién</th><th>Detalle</th><th style="text-align:right">Importe</th></tr></thead>
            <tbody>
            <?php foreach ($mov['rows'] as $r): [$fg, $bg] = $colors[$r['type']]; ?>
            <tr style="<?= $r['voided'] ? 'opacity:.55' : '' ?>">
                <td style="white-space:nowrap;color:var(--text-muted)" data-order="<?= esc($r['date']) ?>"><?= date('d/m/Y', strtotime($r['date'])) ?></td>
                <td><span style="font-size:11px;font-weight:700;color:<?= $fg ?>;background:<?= $bg ?>;border-radius:10px;padding:2px 8px;white-space:nowrap"><?= esc($types[$r['type']]) ?></span></td>
                <td style="font-weight:600">
                    <?php if (!empty($r['player_id'])): ?><a href="<?= base_url('finanzas/alumnos/' . (int) $r['player_id']) ?>" class="row-link-anchor"><?= esc($r['who'] ?? '—') ?></a>
                    <?php else: ?><?= esc($r['who'] ?? '—') ?><?php endif; ?>
                </td>
                <td style="color:var(--text-body)">
                    <?= esc($r['detail']) ?>
                    <?php if (!empty($r['session_id'])): ?> · <a href="<?= base_url('clases/' . (int) $r['session_id']) ?>">clase</a><?php endif; ?>
                    <?php if ($r['voided']): ?><div style="font-size:11px;color:var(--danger);font-weight:600">Anulado<?= $r['void_reason'] ? ': ' . esc($r['void_reason']) : '' ?></div><?php endif; ?>
                </td>
                <td data-order="<?= (int) $r['cents'] ?>" style="text-align:right;white-space:nowrap;font-weight:700;color:<?= $r['cents'] < 0 ? '#9a3412' : 'var(--text-h)' ?>;<?= $r['voided'] ? 'text-decoration:line-through' : '' ?>"><?= $r['cents'] < 0 ? '−' : '' ?><?= eur(abs($r['cents'])) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
