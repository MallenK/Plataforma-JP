<?= $this->extend('layouts/app') ?>

<?php
$pageTitle    = 'Finanzas';
$pageSubtitle = 'Análisis: qué se vende, cómo se usan las clases y dónde';
$a   = $an['attendance'];
$cat = ['prebenjamin' => 'Prebenjamín', 'benjamin' => 'Benjamín', 'alevin' => 'Alevín', 'infantil' => 'Infantil', 'cadete' => 'Cadete',
        'juvenil' => 'Juvenil', 'junior' => 'Júnior', 'senior' => 'Sénior', 'veterano' => 'Veterano'];
$soldTotal = array_sum(array_map(fn($r) => (int) $r['v'], $an['by_type']));
?>

<?= $this->section('page_content') ?>
<?= view('finanzas/_tabs', ['tab' => $tab, 'reviewCount' => $reviewCount]) ?>
<?= view('finanzas/_period', ['period' => $period, 'months' => $months]) ?>

<div class="row g-3">
    <div class="col-12 col-xl-7">
        <div class="card-jp h-100">
            <div class="card-jp-header"><span class="card-jp-title">Ventas por tipo de bono · <?= eur($soldTotal) ?></span></div>
            <div class="table-responsive">
                <table class="table-jp" style="font-size:13px">
                    <thead><tr><th>Tipo</th><th style="text-align:right">Vendidos</th><th style="text-align:right">Importe</th><th style="text-align:right">Descuentos</th><th style="text-align:right">€ / sesión</th><th style="text-align:right">% ventas</th></tr></thead>
                    <tbody>
                    <?php if (empty($an['by_type'])): ?><tr><td colspan="6" style="color:var(--text-muted)">Sin ventas en el periodo.</td></tr><?php endif; ?>
                    <?php foreach ($an['by_type'] as $r): $per = (int) $r['sessions'] > 0 && (int) $r['n'] > 0 ? (int) round((int) $r['v'] / (int) $r['n'] / (int) $r['sessions']) : null; ?>
                    <tr>
                        <td style="font-weight:600"><?= esc($r['name']) ?></td>
                        <td style="text-align:right"><?= (int) $r['n'] ?></td>
                        <td style="text-align:right;font-weight:700"><?= eur((int) $r['v']) ?></td>
                        <td style="text-align:right;color:var(--text-muted)"><?= (int) $r['d'] ? eur((int) $r['d']) : '—' ?></td>
                        <td style="text-align:right"><?= $per !== null ? eur($per) : '—' ?></td>
                        <td style="text-align:right"><?= $soldTotal ? number_format((int) $r['v'] / $soldTotal * 100, 1, ',', '') . ' %' : '—' ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-5">
        <div class="card-jp h-100">
            <div class="card-jp-header"><span class="card-jp-title">Asistencia del periodo</span></div>
            <div class="table-responsive">
                <table class="table-jp" style="font-size:13px">
                    <tbody>
                        <tr><td>Presente</td><td style="text-align:right;font-weight:700"><?= (int) ($a['present'] ?? 0) ?></td></tr>
                        <tr><td>Ausencia justificada</td><td style="text-align:right;font-weight:700"><?= (int) ($a['absent'] ?? 0) ?></td></tr>
                        <tr><td>Falta sin justificar</td><td style="text-align:right;font-weight:700"><?= (int) ($a['unjustified'] ?? 0) ?></td></tr>
                        <tr><td>Avisó ausencia</td><td style="text-align:right;font-weight:700"><?= (int) ($a['declined'] ?? 0) ?></td></tr>
                        <tr><td>· con menos de <?= (int) $a['notice_hours'] ?> h</td><td style="text-align:right"><?= (int) ($a['late_notice'] ?? 0) ?></td></tr>
                        <tr><td>· con <?= (int) $a['notice_hours'] ?> h o más</td><td style="text-align:right"><?= (int) ($a['early_notice'] ?? 0) ?></td></tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-4">
        <div class="card-jp h-100">
            <div class="card-jp-header"><span class="card-jp-title">Individual y DUO</span></div>
            <div class="table-responsive"><table class="table-jp" style="font-size:13px">
                <thead><tr><th>Formato</th><th style="text-align:right">Clases</th><th style="text-align:right">Asistencias</th></tr></thead>
                <tbody>
                <?php foreach ($an['by_format'] as $r): ?>
                <tr><td><?= $r['f'] === 'pareja' ? 'DUO (pareja)' : 'Individual' ?></td><td style="text-align:right;font-weight:700"><?= (int) $r['sessions'] ?></td><td style="text-align:right"><?= (int) $r['present'] ?></td></tr>
                <?php endforeach; ?>
                <?php if (empty($an['by_format'])): ?><tr><td colspan="3" style="color:var(--text-muted)">Sin clases cerradas.</td></tr><?php endif; ?>
                </tbody>
            </table></div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-4">
        <div class="card-jp h-100">
            <div class="card-jp-header"><span class="card-jp-title">Por sede</span></div>
            <div class="table-responsive"><table class="table-jp" style="font-size:13px">
                <thead><tr><th>Sede</th><th style="text-align:right">Clases</th></tr></thead>
                <tbody>
                <?php foreach ($an['by_location'] as $r): ?><tr><td><?= esc($r['name']) ?></td><td style="text-align:right;font-weight:700"><?= (int) $r['sessions'] ?></td></tr><?php endforeach; ?>
                <?php if (empty($an['by_location'])): ?><tr><td colspan="2" style="color:var(--text-muted)">Sin clases cerradas.</td></tr><?php endif; ?>
                </tbody>
            </table></div>
        </div>
    </div>
    <div class="col-12 col-xl-4">
        <div class="card-jp h-100">
            <div class="card-jp-header"><span class="card-jp-title">Por categoría de edad</span></div>
            <div class="table-responsive"><table class="table-jp" style="font-size:13px">
                <thead><tr><th>Categoría</th><th style="text-align:right">Alumnos</th><th style="text-align:right">Asistencias</th></tr></thead>
                <tbody>
                <?php foreach ($an['by_category'] as $r): ?><tr><td><?= esc($cat[$r['cat']] ?? ucfirst($r['cat'])) ?></td><td style="text-align:right"><?= (int) $r['players'] ?></td><td style="text-align:right;font-weight:700"><?= (int) $r['attended'] ?></td></tr><?php endforeach; ?>
                <?php if (empty($an['by_category'])): ?><tr><td colspan="3" style="color:var(--text-muted)">Sin asistencias.</td></tr><?php endif; ?>
                </tbody>
            </table></div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
