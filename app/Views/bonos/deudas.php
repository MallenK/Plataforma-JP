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
            Cuando le asignes un bono, <strong>no se descuenta nada solo</strong>: tú decides con qué bono saldar cada clase (1 sesión por clase). Si Ana tiene varios bonos, eliges con cuál.</p>
        <ul style="margin:0;padding-left:18px">
            <li><strong>Saldar con un bono:</strong> descuenta 1 sesión del bono que elijas, pide confirmación y queda registrado.</li>
            <li><strong>Ya está pagada:</strong> esa clase se cobró de otra forma (efectivo, Bizum…). No se descuenta ningún bono.</li>
            <li><strong>No cobrar:</strong> no se le va a cobrar (clase de prueba, regalo, error…).</li>
            <li>Siempre queda anotado quién lo hizo y cuándo. No se borra nada.</li>
            <li><strong>Clases anteriores al <?= $since ? date('d/m/Y', strtotime($since)) : '—' ?>:</strong> se dieron antes de empezar este control. Revísalas también y márcalas como pagadas o no cobradas para que el histórico cuadre.</li>
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
                    <?php if (!empty($d['usable_bonos'])): ?>
                    <div class="d-flex gap-1 flex-wrap mb-2">
                        <?php foreach ($d['usable_bonos'] as $ub): ?>
                        <form action="<?= base_url('bonos/deudas/' . (int) $d['csp_id'] . '/saldar') ?>" method="post" style="margin:0"
                              data-ru-confirm="¿Saldar esta clase con «<?= esc($ub['bono_name'] ?? 'este bono', 'attr') ?>»?"
                              data-ru-confirm-desc="Se descuenta 1 sesión de ese bono (le quedan <?= (int) $ub['sessions_remaining'] - 1 ?>). Queda registrado."
                              data-ru-confirm-label="Saldar">
                            <?= csrf_field() ?>
                            <input type="hidden" name="bono_id" value="<?= (int) $ub['id'] ?>">
                            <button type="submit" class="btn-jp btn-jp-primary btn-jp-sm">
                                <i class="bi bi-ticket-perforated-fill me-1"></i>Saldar con «<?= esc($ub['bono_name'] ?? 'Bono') ?>» (<?= (int) $ub['sessions_remaining'] ?>)
                            </button>
                        </form>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div style="font-size:11px;color:var(--text-muted);margin-bottom:6px">Sin bono con saldo: asígnale uno para poder saldarla.</div>
                    <?php endif; ?>
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
        Clases que se dieron antes de empezar el control y no se descontaron de ningún bono. No se convierten en deuda solas, pero hay que revisarlas: márcalas como <strong>ya pagada</strong> (se cobró de otra forma) o <strong>no cobrar</strong>. Queda registrado.
    </div>
    <div class="table-responsive">
        <table style="width:100%;border-collapse:collapse;font-size:13px">
            <thead><tr>
                <th style="<?= $th ?>">Alumno</th><th style="<?= $th ?>">Clase</th><th style="<?= $th ?>">Fecha</th><th style="<?= $th ?>">Revisión</th>
            </tr></thead>
            <tbody>
            <?php foreach ($unreflected as $d): ?>
            <tr style="border-bottom:1px solid var(--border)">
                <td style="padding:8px 12px;font-weight:600"><?= esc($d['player_name']) ?></td>
                <td style="padding:8px 12px"><a href="<?= base_url('clases/' . (int) $d['session_id']) ?>" class="row-link-anchor"><?= esc($d['title']) ?></a></td>
                <td style="padding:8px 12px;color:var(--text-muted)"><?= date('d/m/Y', strtotime($d['session_date'])) ?></td>
                <td style="padding:8px 12px">
                    <form action="<?= base_url('bonos/deudas/' . (int) $d['csp_id'] . '/resolver') ?>" method="post" class="d-flex gap-1 flex-wrap" style="margin:0">
                        <?= csrf_field() ?>
                        <input type="text" name="note" class="form-control-jp" placeholder="Nota (opcional)" style="width:150px;padding:4px 8px;font-size:12px" maxlength="120" aria-label="Nota">
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

<?= $this->endSection() ?>
