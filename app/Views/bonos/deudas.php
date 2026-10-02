<?= $this->extend('layouts/app') ?>

<?php
$pageTitle    = 'Deudas de sesión';
$pageSubtitle = 'Clases dadas sin bono y sesiones anteriores al control';
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
        <strong><i class="bi bi-info-circle me-1" style="color:var(--accent)"></i>Cómo funciona</strong>
        <ul style="margin:8px 0 0;padding-left:18px">
            <li><strong>Deuda de sesión:</strong> el alumno asistió a una clase y no se le descontó bono (porque no tenía). Queda anotada.</li>
            <li><strong>Se salda sola</strong> cuando se le emite un bono nuevo: se descuenta primero lo más antiguo y se avisa a los administradores.</li>
            <li>Si no va a haber bono, puedes <strong>resolverla</strong>: <em>pagada fuera de bono</em> o <em>condonada</em>. Siempre queda registrado quién y cuándo; nada se borra.</li>
            <li><strong>Anteriores al control:</strong> sesiones dadas antes del <?= $since ? date('d/m/Y', strtotime($since)) : '—' ?> (cuando empezó este control). No se tratan como deuda, solo se muestran para que sepas que no están bien reflejadas.</li>
        </ul>
    </div>
</div>

<!-- Deudas abiertas -->
<div class="card-jp mb-3">
    <div class="card-jp-header">
        <span class="card-jp-title" style="color:#b91c1c"><i class="bi bi-receipt me-2"></i>Deudas abiertas (<?= count($debts) ?>)</span>
    </div>
    <?php if (empty($debts)): ?>
    <div class="card-jp-body"><p style="color:var(--text-muted);font-size:13px;margin:0"><i class="bi bi-check-circle-fill me-1" style="color:var(--success)"></i>No hay deudas abiertas.</p></div>
    <?php else: ?>
    <div class="table-responsive">
        <table style="width:100%;border-collapse:collapse;font-size:13px">
            <thead><tr>
                <th style="<?= $th ?>">Alumno</th><th style="<?= $th ?>">Clase</th><th style="<?= $th ?>">Fecha</th><th style="<?= $th ?>">Resolver sin bono</th>
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
                        <button type="submit" name="resolution" value="external" class="btn-jp btn-jp-secondary btn-jp-sm">Pagada fuera</button>
                        <button type="submit" name="resolution" value="waived" class="btn-jp btn-jp-secondary btn-jp-sm">Condonar</button>
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
        <span class="card-jp-title"><i class="bi bi-clock-history me-2" style="color:var(--text-muted)"></i>Anteriores al control: no reflejadas (<?= count($unreflected) ?>)</span>
    </div>
    <?php if (empty($unreflected)): ?>
    <div class="card-jp-body"><p style="color:var(--text-muted);font-size:13px;margin:0">No hay sesiones anteriores sin descontar.</p></div>
    <?php else: ?>
    <div class="card-jp-body" style="font-size:12.5px;color:var(--text-muted);padding-bottom:0">
        Clases dadas con asistencia que consume bono pero sin descuento. Es informativo: no se modifican ni se convierten en deuda.
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
