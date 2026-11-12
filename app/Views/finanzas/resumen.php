<?= $this->extend('layouts/app') ?>

<?php
$pageTitle    = 'Finanzas';
$pageSubtitle = 'Cómo va la academia: lo vendido, lo cobrado, lo que se ha dado y lo que se ha gastado';
helper(['money', 'finhelp']);
$s   = $summary;
$max = max([1, ...array_map(fn($m) => max($m['sold'], $m['service']), $monthly)]);
$kpi = static function (string $label, string $value, string $sub, string $style = '', string $help = '', string $valueStyle = '') {
    return '<div class="col-6 col-lg-3"><div class="metric-card" style="height:100%;' . $style . '">'
         . '<div class="metric-card-header"><span class="metric-label">' . $label . ($help ? fin_help($help) : '') . '</span></div>'
         . '<div class="metric-value" style="font-size:26px;' . $valueStyle . '">' . $value . '</div>'
         . '<div style="font-size:12px;color:var(--text-muted);margin-top:4px">' . $sub . '</div></div></div>';
};
$a = $s['attendance'];
?>

<?= $this->section('page_content') ?>
<?= view('finanzas/_tabs', ['tab' => $tab, 'reviewCount' => $reviewCount]) ?>
<?= view('finanzas/_period', ['period' => $period, 'months' => $months]) ?>

<?php $rv = (int) $review['unclosed'] + (int) $review['debts'] + (int) $review['pre_control'] + (int) $review['estimated']; ?>
<?php if ($rv > 0): ?>
<a href="<?= base_url('finanzas/revision') ?>" class="d-flex align-items-center gap-2 mb-3" style="text-decoration:none;padding:10px 14px;border-radius:10px;background:#fffbeb;border:1px solid #fde68a;color:#92400e;font-size:13px">
    <i class="bi bi-clipboard-check"></i><span><strong><?= $rv ?></strong> cosas pendientes de revisar: pueden cambiar las cifras de abajo</span><i class="bi bi-chevron-right ms-auto"></i>
</a>
<?php endif; ?>

<h2 class="fin-section">Dinero del periodo</h2>
<div class="row g-3 mb-4">
    <?= $kpi('Vendido', eur($s['sold']['cents']), $s['sold']['count'] . ' cargos' . ($s['sold']['discount'] ? ' · ' . eur($s['sold']['discount']) . ' en descuentos' : ''), '', 'vendido') ?>
    <?= $kpi('Cobrado', eur($s['paid']['cents']), $s['paid']['count'] . ' cobros', '', 'cobrado') ?>
    <?= $kpi('Gastos', eur($s['expenses']['cents']), $s['expenses']['count'] ? $s['expenses']['count'] . ' gastos' : '<a href="' . base_url('finanzas/gastos') . '">Registrar un gasto</a>', '', 'gastos') ?>
    <?= $kpi('Resultado (cobrado − gastos)', eur($s['result']), 'Dinero que queda · si se cobra todo lo pendiente del periodo: <strong>' . eur($s['result'] + $s['due_period']) . '</strong>', 'background:#0f172a;border-color:#0f172a;color:#e2e8f0', 'resultado', 'color:#f8fafc') ?>
</div>

<h2 class="fin-section">Pendiente y servicio</h2>
<div class="row g-3 mb-4">
    <?= $kpi('Pendiente del periodo (a hoy)', eur($s['due_period']), 'Total a hoy, con ventas posteriores: <strong>' . eur($s['due']) . '</strong> · <a href="' . base_url('finanzas/alumnos?ver=deben') . '">Ver quién debe</a>', '', 'pendiente') ?>
    <?= $kpi('Servicio prestado', eur($s['service']['cents']), $s['service']['count'] . ' sesiones consumidas', '', 'servicio') ?>
    <?= $kpi('Sesiones pagadas sin dar (hoy)', eur($s['prepaid']['cents']), $s['prepaid']['sessions'] . ' sesiones por dar', '', 'pagado_sin_dar') ?>
    <?= $kpi('Caducado sin usar', eur($s['expired']['cents']), $s['expired']['sessions'] . ' sesiones de ' . $s['expired']['count'] . ' bonos', '', 'caducado') ?>
</div>

<?php $x = $s['externos'] ?? null; ?>
<?php if ($x && ($x['sold']['count'] || $x['paid']['count'] || $x['due'] || $x['unpriced'])): ?>
<!-- Personas externas: ya están sumadas en las cifras de arriba; aquí se ve cuánto aportan -->
<div class="card-jp mb-3" style="border-left:3px solid #0ea5e9">
    <div class="card-jp-body" style="font-size:13px">
        <div style="font-weight:700;color:var(--text-h);margin-bottom:6px"><i class="bi bi-person-badge-fill me-1" style="color:#0ea5e9"></i>Incluye personas externas
            <span style="font-weight:400;color:var(--text-muted)">· clase de prueba o suelta, sin cuenta de alumno (ya sumadas arriba)</span></div>
        <div class="d-flex flex-wrap gap-4">
            <span>Vendido: <b><?= eur($x['sold']['cents']) ?></b> <small style="color:var(--text-muted)">(<?= (int) $x['sold']['count'] ?> clases)</small></span>
            <span>Cobrado: <b><?= eur($x['paid']['cents']) ?></b> <small style="color:var(--text-muted)">(<?= (int) $x['paid']['count'] ?> cobros)</small></span>
            <span>Servicio prestado: <b><?= eur($x['service']['cents']) ?></b> <small style="color:var(--text-muted)">(<?= (int) $x['service']['count'] ?> clases)</small></span>
            <span>Pendiente de cobro: <b style="color:<?= $x['due'] > 0 ? '#dc2626' : 'inherit' ?>"><?= eur($x['due']) ?></b></span>
        </div>
        <?php if ($x['unpriced'] > 0): ?>
            <div style="margin-top:8px;color:#b45309"><i class="bi bi-exclamation-triangle-fill me-1"></i>
                <?= (int) $x['unpriced'] ?> clase<?= $x['unpriced'] > 1 ? 's' : '' ?> de externos <b>sin precio fijado</b>: no cuentan en el dinero hasta que se ponga (ábrela y fija el precio en «Externos»).</div>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div class="row g-3 mb-3">
    <div class="col-12 col-xl-8">
        <div class="card-jp h-100">
            <div class="card-jp-header" style="flex-wrap:wrap;gap:8px">
                <span class="card-jp-title">Vendido y servicio prestado · últimos 12 meses<?= fin_help('servicio', 'Barras azules: lo vendido cada mes. Barras verdes: el valor de las clases dadas ese mes. Pasa el ratón por una barra o abre «Ver como tabla» para ver las cifras.') ?></span>
                <span style="display:flex;gap:14px;font-size:12px;color:var(--text-body)">
                    <span><span aria-hidden="true" style="display:inline-block;width:11px;height:11px;border-radius:3px;background:#2563eb;vertical-align:-1px"></span> Vendido</span>
                    <span><span aria-hidden="true" style="display:inline-block;width:11px;height:11px;border-radius:3px;background:#0d9488;vertical-align:-1px"></span> Servicio prestado</span>
                </span>
            </div>
            <div class="card-jp-body">
                <div role="img" aria-label="Gráfico de barras: vendido y servicio prestado por mes. Los valores están en la tabla de debajo."
                     style="height:220px;display:grid;grid-template-columns:repeat(<?= count($monthly) ?>,minmax(0,1fr));gap:6px;align-items:end;border-bottom:1px solid var(--border-dark)">
                    <?php foreach ($monthly as $m): ?>
                    <div style="display:flex;gap:2px;align-items:flex-end;justify-content:center;height:100%" title="<?= esc($m['label']) ?> · vendido <?= eur($m['sold']) ?> · servicio <?= eur($m['service']) ?>">
                        <div style="width:min(16px,40%);height:<?= max($m['sold'] ? 2 : 0, round($m['sold'] / $max * 200)) ?>px;background:#2563eb;border-radius:4px 4px 0 0"></div>
                        <div style="width:min(16px,40%);height:<?= max($m['service'] ? 2 : 0, round($m['service'] / $max * 200)) ?>px;background:#0d9488;border-radius:4px 4px 0 0"></div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div style="display:grid;grid-template-columns:repeat(<?= count($monthly) ?>,minmax(0,1fr));gap:6px;font-size:11px;color:var(--text-muted);text-align:center;margin-top:6px">
                    <?php foreach ($monthly as $m): ?><span><?= esc($m['label']) ?></span><?php endforeach; ?>
                </div>
                <details style="margin-top:12px">
                    <summary style="cursor:pointer;font-size:12px;font-weight:600;color:var(--accent-dark)">Ver como tabla</summary>
                    <div class="table-responsive" style="margin-top:8px">
                        <table class="table-jp" style="font-size:12px">
                            <thead><tr><th>Mes</th><th style="text-align:right">Vendido</th><th style="text-align:right">Cobrado</th><th style="text-align:right">Servicio</th><th style="text-align:right">Gastos</th></tr></thead>
                            <tbody>
                            <?php foreach (array_reverse($monthly) as $m): ?>
                            <tr><td><?= esc($m['label']) ?></td><td style="text-align:right"><?= eur($m['sold']) ?></td><td style="text-align:right"><?= eur($m['paid']) ?></td><td style="text-align:right"><?= eur($m['service']) ?></td><td style="text-align:right"><?= eur($m['expenses']) ?></td></tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </details>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-4 d-flex flex-column gap-3">
        <div class="card-jp">
            <div class="card-jp-header"><span class="card-jp-title">Cobrado por medio de pago</span></div>
            <div class="card-jp-body" style="font-size:13px">
                <?php if (empty($s['paid']['by_method'])): ?>
                <p style="margin:0;color:var(--text-muted)">Sin cobros en el periodo.</p>
                <?php else: foreach ($s['paid']['by_method'] as $r): ?>
                <div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid var(--border)"><span><?= esc($r['name']) ?></span><strong style="color:var(--text-h)"><?= eur((int) $r['v']) ?></strong></div>
                <?php endforeach; endif; ?>
            </div>
        </div>
        <div class="card-jp">
            <div class="card-jp-header"><span class="card-jp-title">Gastos por categoría</span></div>
            <div class="card-jp-body" style="font-size:13px">
                <?php if (empty($s['expenses']['by_category'])): ?>
                <p style="margin:0;color:var(--text-muted)">Sin gastos en el periodo. <a href="<?= base_url('finanzas/gastos') ?>">Registrar</a></p>
                <?php else: foreach ($s['expenses']['by_category'] as $r): ?>
                <div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid var(--border)"><span><?= esc($r['name']) ?></span><strong style="color:var(--text-h)"><?= eur((int) $r['v']) ?></strong></div>
                <?php endforeach; endif; ?>
            </div>
        </div>
        <div class="card-jp">
            <div class="card-jp-body" style="font-size:13px">
                <div style="display:flex;justify-content:space-between"><span>Precio medio por sesión<?= fin_help('precio_medio') ?></span><strong style="color:var(--text-h)"><?= eur($s['avg_session_cents']) ?></strong></div>
            </div>
        </div>
    </div>
</div>

<details class="fin-more mb-3">
    <summary>Asistencia y qué significa cada cifra</summary>
    <div style="margin-top:12px">
<div class="row g-3 mb-3">
    <div class="col-12 col-lg-6">
        <div class="card-jp h-100">
            <div class="card-jp-header"><span class="card-jp-title">Asistencia del periodo y su efecto en el bono<?= fin_help('asistencia') ?></span></div>
            <div class="table-responsive">
                <table class="table-jp" style="font-size:13px">
                    <thead><tr><th>Estado</th><th style="text-align:right">Veces</th><th>¿Consume sesión?</th></tr></thead>
                    <tbody>
                        <tr><td>Presente</td><td style="text-align:right;font-weight:700"><?= (int) ($a['present'] ?? 0) ?></td><td>Sí</td></tr>
                        <tr><td>Falta sin justificar</td><td style="text-align:right;font-weight:700"><?= (int) ($a['unjustified'] ?? 0) ?></td><td>Sí · automático</td></tr>
                        <tr><td>Avisó con menos de <?= (int) $a['notice_hours'] ?> h</td><td style="text-align:right;font-weight:700"><?= (int) ($a['late_notice'] ?? 0) ?></td><td>Sí · automático</td></tr>
                        <tr><td>Avisó con <?= (int) $a['notice_hours'] ?> h o más</td><td style="text-align:right;font-weight:700"><?= (int) ($a['early_notice'] ?? 0) ?></td><td>No</td></tr>
                        <tr><td>Ausencia justificada</td><td style="text-align:right;font-weight:700"><?= (int) ($a['absent'] ?? 0) ?></td><td>No</td></tr>
                        <tr><td>Avisó ausencia (total)</td><td style="text-align:right;font-weight:700"><?= (int) ($a['declined'] ?? 0) ?></td><td style="color:var(--text-muted)">según antelación</td></tr>
                    </tbody>
                </table>
            </div>
            <div class="card-jp-body" style="font-size:12px;color:var(--text-muted);padding-top:8px">Entrenador, admin y superadmin pueden deshacer cualquier descuento automático desde Pasar lista.</div>
        </div>
    </div>
    <div class="col-12 col-lg-6">
        <div class="card-jp h-100">
            <div class="card-jp-header"><span class="card-jp-title">Qué significa cada cifra</span></div>
            <div class="card-jp-body" style="font-size:12.5px;line-height:1.55">
                <p style="margin:0 0 6px"><strong>Vendido:</strong> bonos y cargos del periodo, con su descuento.</p>
                <p style="margin:0 0 6px"><strong>Cobrado:</strong> dinero que ha entrado, por cualquier medio.</p>
                <p style="margin:0 0 6px"><strong>Servicio prestado:</strong> sesiones consumidas × lo que costó cada sesión de su bono.</p>
                <p style="margin:0 0 6px"><strong>Resultado:</strong> cobrado menos gastos del periodo.</p>
                <p style="margin:0 0 6px"><strong>Pendiente de cobro:</strong> vendido y aún sin pagar (a hoy).</p>
                <p style="margin:0 0 6px"><strong>Sesiones pagadas sin dar:</strong> saldo vivo de los bonos; lo que la academia aún debe dar.</p>
                <p style="margin:0"><strong>Caducado sin usar:</strong> saldo de bonos que vencieron en el periodo; se da por ganado y se señala. Todas las operaciones, una a una, en <a href="<?= base_url('finanzas/movimientos') ?>">Movimientos</a>.</p>
            </div>
        </div>
    </div>
</div>

    </div>
</details>

<?= $this->endSection() ?>
