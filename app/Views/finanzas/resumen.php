<?= $this->extend('layouts/app') ?>

<?php
$pageTitle    = 'Finanzas';
$pageSubtitle = 'Cómo va la academia: lo vendido, lo cobrado, lo que se ha dado y lo que se ha gastado';
$s   = $summary;
$max = max(1, ...array_map(fn($m) => max($m['sold'], $m['service']), $monthly));
$kpi = static function (string $label, string $value, string $sub, string $style = '') {
    return '<div class="col-6 col-lg-3"><div class="metric-card" style="height:100%;' . $style . '">'
         . '<div class="metric-card-header"><span class="metric-label">' . $label . '</span></div>'
         . '<div class="metric-value" style="font-size:26px">' . $value . '</div>'
         . '<div style="font-size:12px;color:var(--text-muted);margin-top:4px">' . $sub . '</div></div></div>';
};
$a = $s['attendance'];
?>

<?= $this->section('page_content') ?>
<?= view('finanzas/_tabs', ['tab' => $tab, 'reviewCount' => $reviewCount]) ?>
<?= view('finanzas/_period', ['period' => $period, 'months' => $months]) ?>

<div class="row g-3 mb-3">
    <?= $kpi('Vendido', eur($s['sold']['cents']), $s['sold']['count'] . ' cargos' . ($s['sold']['discount'] ? ' · ' . eur($s['sold']['discount']) . ' en descuentos' : '')) ?>
    <?= $kpi('Cobrado', eur($s['paid']['cents']), $s['paid']['count'] . ' cobros') ?>
    <?= $kpi('Servicio prestado', eur($s['service']['cents']), $s['service']['count'] . ' sesiones consumidas') ?>
    <?= $kpi('Gastos', eur($s['expenses']['cents']), $s['expenses']['count'] ? $s['expenses']['count'] . ' gastos' : '<a href="' . base_url('finanzas/gastos') . '">Registrar un gasto</a>') ?>
</div>
<div class="row g-3 mb-3">
    <?= $kpi('Resultado (cobrado − gastos)', eur($s['result']), 'Dinero que queda en el periodo', 'background:#0f172a;border-color:#0f172a;color:#e2e8f0') ?>
    <?= $kpi('Pendiente de cobro (hoy)', eur($s['due']), '<a href="' . base_url('finanzas/alumnos?ver=deben') . '">Ver quién debe</a>') ?>
    <?= $kpi('Sesiones pagadas sin dar (hoy)', eur($s['prepaid']['cents']), $s['prepaid']['sessions'] . ' sesiones por dar') ?>
    <?= $kpi('Caducado sin usar', eur($s['expired']['cents']), $s['expired']['sessions'] . ' sesiones de ' . $s['expired']['count'] . ' bonos') ?>
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-xl-8">
        <div class="card-jp h-100">
            <div class="card-jp-header" style="flex-wrap:wrap;gap:8px">
                <span class="card-jp-title">Vendido y servicio prestado · últimos 12 meses</span>
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
                <div style="display:flex;justify-content:space-between"><span>Precio medio por sesión</span><strong style="color:var(--text-h)"><?= eur($s['avg_session_cents']) ?></strong></div>
                <?php $rv = (int) $review['unclosed'] + (int) $review['debts'] + (int) $review['pre_control'] + (int) $review['estimated']; ?>
                <div style="display:flex;justify-content:space-between;margin-top:8px"><span>Pendiente de revisar</span><a href="<?= base_url('finanzas/revision') ?>" style="font-weight:700"><?= $rv ?></a></div>
            </div>
        </div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-12 col-lg-6">
        <div class="card-jp h-100">
            <div class="card-jp-header"><span class="card-jp-title">Asistencia del periodo y su efecto en el bono</span></div>
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

<?= $this->endSection() ?>
