<?= $this->extend('layouts/app') ?>

<?php
$pageTitle    = 'Finanzas';
$pageSubtitle = 'Revisión: lo que hay que revisar a mano para que el histórico económico cuadre';
$th  = 'padding:8px 12px;color:var(--text-muted);font-weight:700;text-transform:uppercase;letter-spacing:.4px;border-bottom:1px solid var(--border);text-align:left;font-size:11px';
$td  = 'padding:8px 12px';
$eur = static fn(?int $c) => $c === null ? '—' : number_format($c / 100, 2, ',', '.') . ' €';
$sinceTxt = $since ? date('d/m/Y', strtotime($since)) : '—';
$unusedTotal = array_sum(array_column($unused, 'unused_cents'));

$cards = [
    ['#sin-cerrar', 'Sesiones sin cerrar',       count($unclosed),                     'bi-calendar-x-fill',          '#b45309', '#ffedd5'],
    ['#sin-bono',   'Clases sin descontar',      count($debts) + count($preControl),   'bi-receipt',                  '#b91c1c', '#fee2e2'],
    ['#precios',    'Bonos con precio estimado', count($estimated),                    'bi-question-circle-fill',     '#92400e', '#fef3c7'],
    ['#sin-usar',   'Caducados sin usar (aviso)', count($unused),                      'bi-hourglass-bottom',         '#6d28d9', '#ede9fe'],
];
?>

<?= $this->section('page_content') ?>

<?= view('finanzas/_tabs', ['tab' => $tab ?? 'revision']) ?>

<?php if (session()->getFlashdata('success')): ?>
<div class="alert-jp success mb-3"><i class="bi bi-check-circle-fill me-2"></i><?= esc(session()->getFlashdata('success')) ?></div>
<?php endif; ?>
<?php if (session()->getFlashdata('error')): ?>
<div class="alert-jp error mb-3"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif; ?>

<?php $cp = $closePreview ?? ['sessions' => 0, 'debts' => 0, 'prices' => 0]; ?>
<?php if ($cp['sessions'] + $cp['debts'] + $cp['prices'] > 0): ?>
<div class="card-jp mb-3" style="border:2px solid #bfdbfe">
    <div class="card-jp-body d-flex flex-wrap gap-3 align-items-center justify-content-between">
        <div style="font-size:13px;line-height:1.6;max-width:760px">
            <strong style="font-size:14px;color:var(--text-h)"><i class="bi bi-check2-all me-1" style="color:var(--accent)"></i>Cierre de revisión inicial</strong><br>
            Da por bueno lo anterior en un solo paso: se cierran las <strong><?= (int) $cp['sessions'] ?></strong> sesiones pasadas que ya tienen asistencia marcada,
            se dan por buenas las <strong><?= (int) $cp['debts'] ?></strong> clases dadas sin descontar (el saldo de los bonos no cambia)
            y se confirman los <strong><?= (int) $cp['prices'] ?></strong> precios estimados al precio de tarifa.
            Solo quedarán por revisar las sesiones que nadie cerró. Todo queda registrado.
        </div>
        <form action="<?= base_url('finanzas/revision/cierre-inicial') ?>" method="post" style="margin:0"
              data-ru-confirm="¿Dar por bueno lo anterior?"
              data-ru-confirm-desc="Se cierran <?= (int) $cp['sessions'] ?> sesiones, se aceptan <?= (int) $cp['debts'] ?> clases sin descontar y se confirman <?= (int) $cp['prices'] ?> precios. Queda en el registro de auditoría."
              data-ru-confirm-label="Cerrar revisión inicial">
            <?= csrf_field() ?>
            <button type="submit" class="btn-jp btn-jp-primary"><i class="bi bi-check2-all me-1"></i>Cerrar revisión inicial</button>
        </form>
    </div>
</div>
<?php endif; ?>

<div class="row g-3 mb-3">
    <?php foreach ($cards as [$href, $label, $n, $icon, $fg, $bg]): ?>
    <div class="col-6 col-lg-3">
        <a href="<?= $href ?>" class="metric-card" style="display:block;text-decoration:none;color:inherit;<?= $n > 0 ? 'border-color:' . $fg : '' ?>">
            <div class="metric-card-header">
                <span class="metric-label"><?= $label ?></span>
                <div class="metric-icon" style="background:<?= $bg ?>;color:<?= $fg ?>"><i class="bi <?= $icon ?>"></i></div>
            </div>
            <div class="metric-value"><?= (int) $n ?></div>
        </a>
    </div>
    <?php endforeach; ?>
</div>

<!-- 1. Sesiones pasadas sin cerrar -->
<div class="card-jp mb-3" id="sin-cerrar">
    <div class="card-jp-header">
        <span class="card-jp-title" style="color:#b45309"><i class="bi bi-calendar-x-fill me-2"></i>Sesiones pasadas sin cerrar (<?= count($unclosed) ?>)</span>
    </div>
    <div class="card-jp-body" style="font-size:12.5px;color:var(--text-muted);padding-bottom:0">
        Ya pasó su fecha pero siguen como «programadas». Ábrelas y <strong>pasa lista y ciérralas</strong> si se dieron, o <strong>cancélalas</strong> si no se dieron.
    </div>
    <?php if (empty($unclosed)): ?>
    <div class="card-jp-body"><p style="color:var(--text-muted);font-size:13px;margin:0"><i class="bi bi-check-circle-fill me-1" style="color:var(--success)"></i>Todas las sesiones pasadas están cerradas.</p></div>
    <?php else: ?>
    <div class="table-responsive">
        <table style="width:100%;border-collapse:collapse;font-size:13px">
            <thead><tr>
                <th style="<?= $th ?>">Fecha</th><th style="<?= $th ?>">Clase</th><th style="<?= $th ?>">Entrenador</th><th style="<?= $th ?>">Estado</th>
            </tr></thead>
            <tbody>
            <?php foreach ($unclosed as $s): ?>
            <tr style="border-bottom:1px solid var(--border)">
                <td style="<?= $td ?>;white-space:nowrap;color:var(--text-muted)"><?= date('d/m/Y', strtotime($s['session_date'])) ?> · <?= substr($s['start_time'], 0, 5) ?></td>
                <td style="<?= $td ?>"><a href="<?= base_url('clases/' . (int) $s['id']) ?>" class="row-link-anchor" style="font-weight:600"><?= esc($s['title']) ?></a></td>
                <td style="<?= $td ?>"><?= $s['coach_names'] ? esc($s['coach_names']) : '<span style="color:var(--text-muted)">Sin asignar</span>' ?></td>
                <td style="<?= $td ?>">
                    <?php if (!empty($s['lista_pasada_at']) || (int) $s['marked'] > 0): ?>
                    <span style="font-size:11px;font-weight:700;color:#1d4ed8;background:#dbeafe;border-radius:10px;padding:2px 8px">Lista pasada, sin cerrar</span>
                    <?php else: ?>
                    <span style="font-size:11px;font-weight:700;color:#b45309;background:#ffedd5;border-radius:10px;padding:2px 8px">Sin pasar lista</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- 2. Clases dadas sin descontar -->
<div class="card-jp mb-3" id="sin-bono">
    <div class="card-jp-header">
        <span class="card-jp-title" style="color:#b91c1c"><i class="bi bi-receipt me-2"></i>Clases dadas sin descontar bono (<?= count($debts) + count($preControl) ?>)</span>
        <a href="<?= base_url('bonos/deudas') ?>" class="btn-jp btn-jp-primary btn-jp-sm" style="text-decoration:none">Revisarlas</a>
    </div>
    <div class="card-jp-body" style="font-size:13px">
        <div class="d-flex flex-wrap gap-4">
            <div><div style="font-size:22px;font-weight:800;color:var(--text-h)"><?= count($debts) ?></div><div style="color:var(--text-muted);font-size:12px">desde el <?= $sinceTxt ?> (control de bonos)</div></div>
            <div><div style="font-size:22px;font-weight:800;color:var(--text-h)"><?= count($preControl) ?></div><div style="color:var(--text-muted);font-size:12px">anteriores al <?= $sinceTxt ?></div></div>
        </div>
        <p style="color:var(--text-muted);font-size:12.5px;margin:10px 0 0">
            Cada una se salda con un bono, o se marca como <strong>ya pagada</strong> (se cobró de otra forma) o <strong>no cobrar</strong>. Nada se salda solo.
        </p>
    </div>
</div>

<!-- 3. Bonos con precio estimado -->
<div class="card-jp mb-3" id="precios">
    <div class="card-jp-header">
        <span class="card-jp-title" style="color:#92400e"><i class="bi bi-question-circle-fill me-2"></i>Bonos con precio estimado (<?= count($estimated) ?>)</span>
    </div>
    <div class="card-jp-body" style="font-size:12.5px;color:var(--text-muted);padding-bottom:0">
        Antes de la v1.33.0 no se guardaba lo que se pagó por cada bono: se ha puesto el precio del tipo de bono como <strong>estimado</strong>.
        Escribe el importe real que pagó el alumno (con descuento, gratis = 0) y pulsa <strong>Confirmar</strong>. Queda registrado.
    </div>
    <?php if (empty($estimated)): ?>
    <div class="card-jp-body"><p style="color:var(--text-muted);font-size:13px;margin:0"><i class="bi bi-check-circle-fill me-1" style="color:var(--success)"></i>Todos los precios están confirmados.</p></div>
    <?php else: ?>
    <div class="table-responsive">
        <table style="width:100%;border-collapse:collapse;font-size:13px">
            <thead><tr>
                <th style="<?= $th ?>">Alumno</th><th style="<?= $th ?>">Bono</th><th style="<?= $th ?>">Emitido</th><th style="<?= $th ?>">Precio estimado</th><th style="<?= $th ?>">Precio real pagado</th>
            </tr></thead>
            <tbody>
            <?php foreach ($estimated as $b): ?>
            <tr style="border-bottom:1px solid var(--border)">
                <td style="<?= $td ?>;font-weight:600"><?= $b['player_name'] ? esc($b['player_name']) : '<span style="color:var(--text-muted)">Sin asignar</span>' ?></td>
                <td style="<?= $td ?>"><a href="<?= base_url('bonos/' . (int) $b['id']) ?>" class="row-link-anchor"><?= esc($b['bono_name'] ?? 'Bono') ?></a> <span style="color:var(--text-muted);font-size:11px">#<?= (int) $b['id'] ?></span></td>
                <td style="<?= $td ?>;color:var(--text-muted);white-space:nowrap"><?= date('d/m/Y', strtotime($b['created_at'])) ?></td>
                <td style="<?= $td ?>;white-space:nowrap"><?= $eur($b['price_cents'] !== null ? (int) $b['price_cents'] : null) ?></td>
                <td style="<?= $td ?>">
                    <form action="<?= base_url('bonos/' . (int) $b['id'] . '/precio') ?>" method="post" class="d-flex gap-1 align-items-center" style="margin:0">
                        <?= csrf_field() ?>
                        <input type="text" name="price" inputmode="decimal" class="form-control-jp" style="width:96px;padding:4px 8px;font-size:13px"
                               value="<?= $b['price_cents'] !== null ? number_format((int) $b['price_cents'] / 100, 2, ',', '') : '' ?>"
                               aria-label="Precio real pagado en euros" required>
                        <span style="color:var(--text-muted)">€</span>
                        <button type="submit" class="btn-jp btn-jp-primary btn-jp-sm">Confirmar</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- 4. Caducados con sesiones sin usar -->
<div class="card-jp mb-3" id="sin-usar">
    <div class="card-jp-header">
        <span class="card-jp-title" style="color:#6d28d9"><i class="bi bi-hourglass-bottom me-2"></i>Bonos caducados con sesiones sin usar (<?= count($unused) ?>)</span>
        <?php if ($unusedTotal > 0): ?><span style="font-size:13px;font-weight:700;color:var(--text-h)"><?= $eur($unusedTotal) ?></span><?php endif; ?>
    </div>
    <div class="card-jp-body" style="font-size:12.5px;color:var(--text-muted);padding-bottom:0">
        <strong>Solo informativo, no es una tarea.</strong> El bono caducó y le quedaban sesiones: ese importe <strong>se da por ganado</strong> y se señala aquí como <strong>«sin usar»</strong>.
        Si se le quiere dar más tiempo al alumno, se amplía la caducidad desde la ficha del bono.
    </div>
    <?php if (empty($unused)): ?>
    <div class="card-jp-body"><p style="color:var(--text-muted);font-size:13px;margin:0"><i class="bi bi-check-circle-fill me-1" style="color:var(--success)"></i>No hay bonos caducados con sesiones sin usar.</p></div>
    <?php else: ?>
    <div class="table-responsive">
        <table style="width:100%;border-collapse:collapse;font-size:13px">
            <thead><tr>
                <th style="<?= $th ?>">Alumno</th><th style="<?= $th ?>">Bono</th><th style="<?= $th ?>">Caducó</th><th style="<?= $th ?>">Sin usar</th><th style="<?= $th ?>">Importe</th>
            </tr></thead>
            <tbody>
            <?php foreach ($unused as $b): ?>
            <tr style="border-bottom:1px solid var(--border)">
                <td style="<?= $td ?>;font-weight:600"><?= $b['player_name'] ? esc($b['player_name']) : '<span style="color:var(--text-muted)">Sin asignar</span>' ?></td>
                <td style="<?= $td ?>"><a href="<?= base_url('bonos/' . (int) $b['id']) ?>" class="row-link-anchor"><?= esc($b['bono_name'] ?? 'Bono') ?></a></td>
                <td style="<?= $td ?>;color:var(--text-muted);white-space:nowrap"><?= date('d/m/Y', strtotime($b['expires_at'])) ?></td>
                <td style="<?= $td ?>"><?= (int) $b['sessions_remaining'] ?> de <?= (int) $b['sessions_total'] ?></td>
                <td style="<?= $td ?>;white-space:nowrap"><?= $eur((int) $b['unused_cents']) ?><?php if (!empty($b['price_estimated'])): ?> <span title="Precio estimado" style="font-size:10px;font-weight:700;color:#92400e;background:#fef3c7;border-radius:6px;padding:1px 5px">est.</span><?php endif; ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
