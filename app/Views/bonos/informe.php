<?= $this->extend('layouts/app') ?>

<?php
helper('avatar');
$pageTitle    = 'Informe de bonos';
$pageSubtitle = 'Saldo, importes y alertas por alumno';

use App\Services\BonoReportService as BRS;

$rows   = $report['rows']   ?? [];
$totals = $report['totals'] ?? [];
$alerts = $report['alerts'] ?? [];
$labels = BRS::alertLabels();
$eur    = fn($n) => number_format((float) $n, 2, ',', '.') . ' €';
$pctMulti = ($totals['players'] ?? 0) > 0 ? round(($totals['multi'] ?? 0) * 100 / $totals['players']) : 0;
?>

<?= $this->section('page_content') ?>

<div class="page-header">
    <a href="<?= base_url('bonos') ?>" class="btn-jp btn-jp-secondary"><i class="bi bi-arrow-left"></i> Volver a Bonos</a>
</div>

<!-- Resumen -->
<div class="row g-3 mb-3">
    <?php
    $kpis = [
        ['Alumnos con bono',       (int) ($totals['players'] ?? 0),           'bi-people-fill',               'blue',   ''],
        ['Con varios bonos',       (int) ($totals['multi'] ?? 0),             'bi-collection-fill',           'purple', $pctMulti . ' % de los que tienen bono'],
        ['Sesiones disponibles',   (int) ($totals['saldo'] ?? 0),             'bi-ticket-perforated-fill',    'green',  'entre todos los bonos con saldo'],
        ['Emitido',                $eur($totals['issued_eur'] ?? 0),          'bi-bag-check-fill',            'orange', 'suma de todos los bonos'],
        ['Pendiente de consumir',  $eur($totals['pending_eur'] ?? 0),         'bi-hourglass-split',           'blue',   'sesiones que quedan × precio/sesión'],
        ['Clases sin bono',        (int) ($totals['debts'] ?? 0),             'bi-receipt',                   'red',    'dadas y aún sin saldar'],
    ];
    foreach ($kpis as [$label, $value, $icon, $tone, $foot]): ?>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="metric-card">
            <div class="metric-card-header">
                <span class="metric-label"><?= esc($label) ?></span>
                <div class="metric-icon <?= $tone === 'purple' ? '' : $tone ?>" <?= $tone === 'purple' ? 'style="background:#8b5cf622;color:#8b5cf6"' : '' ?>><i class="bi <?= $icon ?>"></i></div>
            </div>
            <div class="metric-value" style="font-size:<?= is_string($value) ? '20px' : '28px' ?>"><?= is_string($value) ? esc($value) : (int) $value ?></div>
            <?php if ($foot): ?><div class="metric-footer"><span class="metric-footer-label"><?= esc($foot) ?></span></div><?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<!-- Alertas -->
<div class="card-jp mb-3">
    <div class="card-jp-header">
        <span class="card-jp-title"><i class="bi bi-bell-fill me-2" style="color:#f59e0b"></i>Alertas</span>
    </div>
    <div class="card-jp-body" style="display:flex;gap:8px;flex-wrap:wrap">
        <?php foreach ($labels as $key => [$text, $bg, $fg]): ?>
        <button type="button" class="informe-alert" data-alert="<?= esc($key, 'attr') ?>" title="Filtrar la tabla por esta alerta"
                style="border:1px solid <?= $fg ?>33;background:<?= $bg ?>;color:<?= $fg ?>;border-radius:999px;padding:6px 12px;font-size:12px;font-weight:700;cursor:pointer;<?= empty($alerts[$key]) ? 'opacity:.5' : '' ?>">
            <?= esc($text) ?> · <?= (int) ($alerts[$key] ?? 0) ?>
        </button>
        <?php endforeach; ?>
        <button type="button" class="informe-alert" data-alert="" style="border:1px solid var(--border);background:transparent;color:var(--text-muted);border-radius:999px;padding:6px 12px;font-size:12px;cursor:pointer">Ver todos</button>
    </div>
</div>

<!-- Tabla por alumno -->
<div class="card-jp">
    <div class="card-jp-header">
        <span class="card-jp-title"><i class="bi bi-person-lines-fill me-2" style="color:var(--accent)"></i>Por alumno (<?= count($rows) ?>)</span>
    </div>
    <?php if (empty($rows)): ?>
    <div class="card-jp-body"><p style="color:var(--text-muted);font-size:13px;margin:0">Todavía no hay bonos asignados a alumnos.</p></div>
    <?php else: ?>
    <div class="card-jp-body py-3" style="border-bottom:1px solid var(--border)">
        <div class="search-bar">
            <div class="input-search">
                <i class="bi bi-search"></i>
                <input type="text" id="informe-search" placeholder="Buscar por nombre o email del alumno..." autocomplete="off">
            </div>
        </div>
    </div>
    <div class="table-responsive">
        <table class="table-jp" id="informe-table" style="font-size:13px">
            <thead>
                <tr>
                    <th>Alumno</th>
                    <th title="Bonos con saldo y vigentes / bonos emitidos en total">Bonos</th>
                    <th>Saldo</th>
                    <th>Consumidas</th>
                    <th>Emitido</th>
                    <th>Pendiente</th>
                    <th>Clases prog.</th>
                    <th>Sin bono</th>
                    <th>Caduca</th>
                    <th>Alertas</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
            <tr data-name="<?= mb_strtolower(esc($r['name'])) ?>" data-email="<?= mb_strtolower(esc($r['email'])) ?>"
                data-alerts="<?= esc(implode(' ', $r['alerts']), 'attr') ?>">
                <td style="padding:10px 12px">
                    <a href="<?= base_url('alumnos/' . (int) $r['id']) ?>#bonos" class="row-link-anchor" style="font-weight:600"><?= esc($r['name']) ?></a>
                    <?php if (($r['status'] ?? 'active') !== 'active'): ?><span class="badge-status inactive" style="font-size:10px;margin-left:4px">De baja</span><?php endif; ?>
                    <div style="font-size:11px;color:var(--text-muted)"><?= esc($r['email']) ?></div>
                </td>
                <td data-order="<?= (int) $r['bonos_usable'] ?>" style="padding:10px 12px"><strong><?= (int) $r['bonos_usable'] ?></strong> <span style="color:var(--text-muted)">/ <?= (int) $r['bonos_total'] ?></span></td>
                <td data-order="<?= (int) $r['saldo'] ?>" style="padding:10px 12px;font-weight:700;color:<?= $r['saldo'] <= BRS::LOW_BALANCE && $r['bonos_usable'] > 0 ? 'var(--danger)' : 'var(--text-h)' ?>"><?= (int) $r['saldo'] ?></td>
                <td data-order="<?= (int) $r['consumed'] ?>" style="padding:10px 12px"><?= (int) $r['consumed'] ?></td>
                <td data-order="<?= (float) $r['issued_eur'] ?>" style="padding:10px 12px;white-space:nowrap"><?= $eur($r['issued_eur']) ?></td>
                <td data-order="<?= (float) $r['pending_eur'] ?>" style="padding:10px 12px;white-space:nowrap"><?= $eur($r['pending_eur']) ?></td>
                <td data-order="<?= (int) $r['scheduled'] ?>" style="padding:10px 12px"><?= (int) $r['scheduled'] ?></td>
                <td data-order="<?= (int) $r['debts'] ?>" style="padding:10px 12px;<?= $r['debts'] ? 'color:#b91c1c;font-weight:700' : 'color:var(--text-muted)' ?>"><?= (int) $r['debts'] ?></td>
                <td data-order="<?= esc($r['next_expiry'] ?? '9999-12-31') ?>" style="padding:10px 12px;white-space:nowrap;font-size:12px;color:var(--text-muted)"><?= $r['next_expiry'] ? date('d/m/Y', strtotime($r['next_expiry'])) : '—' ?></td>
                <td style="padding:10px 12px">
                    <?php foreach ($r['alerts'] as $a): [$text, $bg, $fg] = $labels[$a]; ?>
                    <span style="display:inline-block;margin:1px 2px 1px 0;padding:2px 8px;border-radius:10px;font-size:10px;font-weight:700;background:<?= $bg ?>;color:<?= $fg ?>"><?= esc($text) ?></span>
                    <?php endforeach; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-jp-body" style="font-size:11px;color:var(--text-muted);border-top:1px solid var(--border)">
        Los importes usan el precio actual de cada tipo de bono (el bono no guarda el precio pagado).
        «Pendiente» = sesiones que quedan × precio ÷ sesiones del bono, solo de bonos con saldo y vigentes.
        «Consumidas» incluye todos los bonos del alumno, también los agotados y caducados.
    </div>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
    var alertFilter = '';
    var dt = null;

    document.addEventListener('DOMContentLoaded', function () {
        if (!window.JPList || !document.getElementById('informe-table')) return;
        dt = JPList.init({
            table: '#informe-table', key: 'bonos-informe', search: '#informe-search', searchAttrs: ['name', 'email'],
        });
        // Filtro por alerta: se apoya en el filtro personalizado de DataTables.
        if (window.jQuery && jQuery.fn.dataTable) {
            jQuery.fn.dataTable.ext.search.push(function (settings, data, idx) {
                if (settings.nTable.id !== 'informe-table' || !alertFilter) return true;
                var tr = settings.aoData[idx] && settings.aoData[idx].nTr;
                return !!tr && (' ' + (tr.dataset.alerts || '') + ' ').indexOf(' ' + alertFilter + ' ') !== -1;
            });
        }
    });

    document.addEventListener('click', function (ev) {
        var b = ev.target.closest('.informe-alert');
        if (!b) return;
        alertFilter = b.dataset.alert || '';
        document.querySelectorAll('.informe-alert').forEach(function (x) { x.style.outline = x === b && alertFilter ? '2px solid currentColor' : ''; });
        if (dt && dt.draw) dt.draw();
    });
})();
</script>
<?= $this->endSection() ?>
