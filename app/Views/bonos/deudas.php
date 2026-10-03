<?= $this->extend('layouts/app') ?>

<?php
$pageTitle    = 'Clases sin bono';
$pageSubtitle = 'Clases que el alumno ya dio sin tener bono';
$th = 'padding:8px 12px;color:var(--text-muted);font-weight:700;text-transform:uppercase;letter-spacing:.4px;border-bottom:1px solid var(--border);text-align:left;font-size:11px';
?>

<?= $this->section('page_content') ?>

<?php if (session()->getFlashdata('success')): ?>
<div class="alert-jp success mb-3"><i class="bi bi-check-circle-fill me-2"></i><?= esc(session()->getFlashdata('success')) ?></div>
<?php endif; ?>
<?php if (session()->getFlashdata('error')): ?>
<div class="alert-jp error mb-3"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif; ?>

<div class="page-header">
    <a href="<?= base_url('bonos') ?>" class="btn-jp btn-jp-secondary"><i class="bi bi-arrow-left"></i> Volver a Bonos</a>
</div>

<!-- Qué es esto -->
<div class="card-jp mb-3">
    <div class="card-jp-body" style="font-size:13px;line-height:1.6">
        <strong><i class="bi bi-info-circle me-1" style="color:var(--accent)"></i>¿Qué es esto?</strong>
        <p style="margin:8px 0 6px">Aquí aparecen las clases que un alumno <strong>ya ha dado sin tener bono</strong>. Cada una se llama «deuda de sesión».</p>
        <p style="margin:0 0 8px"><strong>Ejemplo:</strong> Ana viene a su clase del martes y no tiene bono. La clase queda apuntada aquí.
            Cuando le asignes un bono de 10 sesiones, el sistema descuenta <strong>1 sesión por cada clase pendiente</strong> (le quedan 9) y te avisa.</p>
        <ul style="margin:0;padding-left:18px">
            <li><strong>Ya está pagada:</strong> esa clase se cobró de otra forma (efectivo, Bizum…). No se descuenta ningún bono.</li>
            <li><strong>No cobrar:</strong> no se le va a cobrar (clase de prueba, regalo, error…).</li>
            <li>Siempre queda anotado quién lo hizo y cuándo. No se borra nada.</li>
            <li><strong>Clases anteriores al <?= $since ? date('d/m/Y', strtotime($since)) : '—' ?>:</strong> se dieron antes de empezar este control. No cuentan como deuda; solo se muestran para que lo sepas.</li>
        </ul>
    </div>
</div>

<!-- Deudas abiertas -->
<div class="card-jp mb-3">
    <div class="card-jp-header">
        <span class="card-jp-title" style="color:#b91c1c"><i class="bi bi-receipt me-2"></i>Clases sin bono pendientes (<?= count($debts) ?>)</span>
    </div>
    <?php if (empty($debts)): ?>
    <div class="card-jp-body"><p style="color:var(--text-muted);font-size:13px;margin:0"><i class="bi bi-check-circle-fill me-1" style="color:var(--success)"></i>No hay clases sin bono pendientes.</p></div>
    <?php else: ?>
    <div class="table-responsive">
        <table style="width:100%;border-collapse:collapse;font-size:13px">
            <thead><tr>
                <th style="<?= $th ?>">Alumno</th><th style="<?= $th ?>">Clase</th><th style="<?= $th ?>">Fecha</th><th style="<?= $th ?>">¿Qué hacemos con ella?</th>
            </tr></thead>
            <tbody>
            <?php foreach ($debts as $d): ?>
            <tr style="border-bottom:1px solid var(--border)">
                <td style="padding:8px 12px;font-weight:600"><?= esc($d['player_name']) ?></td>
                <td style="padding:8px 12px"><a href="<?= base_url('clases/' . (int) $d['session_id']) ?>" class="row-link-anchor"><?= esc($d['title']) ?></a></td>
                <td style="padding:8px 12px;color:var(--text-muted)"><?= date('d/m/Y', strtotime($d['session_date'])) ?></td>
                <td style="padding:8px 12px">
                    <form action="<?= base_url('bonos/deudas/' . (int) $d['csp_id'] . '/resolver') ?>" method="post" class="d-flex gap-1 flex-wrap">
                        <?= csrf_field() ?>
                        <input type="text" name="note" class="form-control-jp" placeholder="Nota (opcional)" style="width:150px;padding:4px 8px;font-size:12px" maxlength="120">
                        <button type="submit" name="resolution" value="external" class="btn-jp btn-jp-secondary btn-jp-sm" title="Se cobró de otra forma (efectivo, Bizum…)">Ya está pagada</button>
                        <button type="submit" name="resolution" value="waived" class="btn-jp btn-jp-secondary btn-jp-sm" title="No se le va a cobrar">No cobrar</button>
                    </form>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- No reflejadas (anteriores al control) -->
<div class="card-jp">
    <div class="card-jp-header">
        <span class="card-jp-title"><i class="bi bi-clock-history me-2" style="color:var(--text-muted)"></i>Clases anteriores al control (<?= count($unreflected) ?>)</span>
    </div>
    <?php if (empty($unreflected)): ?>
    <div class="card-jp-body"><p style="color:var(--text-muted);font-size:13px;margin:0">No hay clases anteriores sin descontar.</p></div>
    <?php else: ?>
    <div class="card-jp-body" style="font-size:12.5px;color:var(--text-muted);padding-bottom:0">
        Son clases que se dieron antes de empezar el control y no se descontaron de ningún bono. Solo es informativo: no se tocan ni se convierten en deuda.
    </div>
    <div class="table-responsive">
        <table style="width:100%;border-collapse:collapse;font-size:13px">
            <thead><tr>
                <th style="<?= $th ?>">Alumno</th><th style="<?= $th ?>">Clase</th><th style="<?= $th ?>">Fecha</th>
            </tr></thead>
            <tbody>
            <?php foreach ($unreflected as $d): ?>
            <tr style="border-bottom:1px solid var(--border)">
                <td style="padding:8px 12px;font-weight:600"><?= esc($d['player_name']) ?></td>
                <td style="padding:8px 12px"><a href="<?= base_url('clases/' . (int) $d['session_id']) ?>" class="row-link-anchor"><?= esc($d['title']) ?></a></td>
                <td style="padding:8px 12px;color:var(--text-muted)"><?= date('d/m/Y', strtotime($d['session_date'])) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
