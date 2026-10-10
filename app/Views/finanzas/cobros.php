<?= $this->extend('layouts/app') ?>

<?php
$pageTitle    = 'Finanzas';
$pageSubtitle = 'Cobros: el dinero que entra, con su medio de pago';
helper(['money', 'finhelp']);
$total = array_sum(array_map(fn($p) => $p['voided_at'] ? 0 : (int) $p['amount_cents'], $payments));
?>

<?= $this->section('page_content') ?>
<?= view('finanzas/_tabs', ['tab' => $tab, 'reviewCount' => $reviewCount]) ?>

<div class="row g-3 mb-3">
    <div class="col-12 col-xl-5">
        <div class="card-jp" style="border:2px solid #bfdbfe">
            <div class="card-jp-header"><span class="card-jp-title"><i class="bi bi-cash-coin me-2" style="color:var(--accent)"></i>Registrar cobro</span></div>
            <form action="<?= base_url('finanzas/cobros') ?>" method="post" class="card-jp-body d-flex flex-column gap-3">
                <?= csrf_field() ?>
                <div>
                    <label class="form-label" for="pc-player">Alumno<?= fin_help('cobro_alumno') ?></label>
                    <select id="pc-player" name="player_id" class="form-control-jp" required>
                        <option value="">— Elige un alumno —</option>
                        <?php foreach ($players as $pl): ?>
                        <option value="<?= (int) $pl['id'] ?>" <?= $preselect === (int) $pl['id'] ? 'selected' : '' ?>><?= esc($pl['name']) ?><?= $pl['status'] !== 'active' ? ' (de baja)' : '' ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label" for="pc-amount">Importe (€)<?= fin_help('cobro_importe') ?></label>
                        <input id="pc-amount" name="amount" type="text" inputmode="decimal" class="form-control-jp" placeholder="0,00" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="pc-date">Fecha</label>
                        <input id="pc-date" name="paid_at" type="date" class="form-control-jp" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
                <fieldset style="border:0;margin:0;padding:0">
                    <legend class="form-label" style="font-size:inherit">Medio de pago<?= fin_help('cobro_medio') ?></legend>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($methods as $i => $m): if ($m['code'] === 'sin_especificar') continue; ?>
                        <label style="display:flex;align-items:center;gap:6px;border:1px solid var(--border-dark);border-radius:8px;padding:8px 12px;min-height:40px;font-size:13px;font-weight:600;cursor:pointer">
                            <input type="radio" name="method_id" value="<?= (int) $m['id'] ?>" <?= $i === 0 ? 'checked' : '' ?> required> <?= esc($m['name']) ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
                <div class="row g-2">
                    <div class="col-6">
                        <label class="form-label" for="pc-ref">Referencia<?= fin_help('cobro_ref') ?> <span style="font-weight:400;color:var(--text-muted)">(opcional)</span></label>
                        <input id="pc-ref" name="reference" type="text" class="form-control-jp" maxlength="120" placeholder="Nº Bizum, transferencia…">
                    </div>
                    <div class="col-6">
                        <label class="form-label" for="pc-note">Nota <span style="font-weight:400;color:var(--text-muted)">(opcional)</span></label>
                        <input id="pc-note" name="note" type="text" class="form-control-jp" maxlength="255" placeholder="Ej.: 1º de 2 plazos">
                    </div>
                </div>
                <p style="margin:0;font-size:12px;color:var(--text-muted)"><?= fin_help('cobro_reparto') ?>Se aplica a lo que el alumno debe, de lo más antiguo a lo más reciente. Si sobra, queda a su favor para su próximo bono. Para elegir qué bono paga, hazlo desde su cuenta.</p>
                <button type="submit" class="btn-jp btn-jp-primary">Guardar cobro</button>
            </form>
        </div>
    </div>
    <div class="col-12 col-xl-7">
        <div class="card-jp h-100">
            <div class="card-jp-header"><span class="card-jp-title">Quién debe ahora (<?= count($debtors) ?>)<?= fin_help('quien_debe') ?></span></div>
            <?php if (empty($debtors)): ?>
            <div class="card-jp-body"><p style="margin:0;color:var(--text-muted);font-size:13px"><i class="bi bi-check-circle-fill me-1" style="color:var(--success)"></i>Nadie debe nada.</p></div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table-jp" style="font-size:13px" data-fin-list="deudores" data-fin-page="10" data-fin-order='[[1,"desc"]]'>
                    <thead><tr><th>Alumno</th><th style="text-align:right">Debe</th><th class="no-sort no-label"></th></tr></thead>
                    <tbody>
                    <?php foreach ($debtors as $d): ?>
                    <tr>
                        <td style="font-weight:600"><a href="<?= base_url('finanzas/alumnos/' . (int) $d['id']) ?>" class="row-link-anchor"><?= esc($d['name']) ?></a></td>
                        <td data-order="<?= (int) $d['due'] ?>" style="text-align:right;font-weight:700;color:#9a3412"><?= eur((int) $d['due']) ?></td>
                        <td style="text-align:right"><a href="<?= base_url('finanzas/alumnos/' . (int) $d['id']) ?>#cobro" class="btn-jp btn-jp-secondary btn-jp-sm" style="text-decoration:none">Cobrar</a></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?= view('finanzas/_period', ['period' => $period, 'months' => $months]) ?>

<div class="card-jp">
    <div class="card-jp-header"><span class="card-jp-title">Cobros del periodo (<?= count($payments) ?>) · <?= eur($total) ?></span></div>
    <?php if (empty($payments)): ?>
    <div class="card-jp-body"><p style="margin:0;color:var(--text-muted);font-size:13px">No hay cobros en este periodo.</p></div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table-jp" style="font-size:13px" data-fin-list="cobros" data-fin-order='[[0,"desc"]]'>
            <thead><tr><th>Fecha</th><th>Alumno</th><th>Medio</th><th>Referencia / nota</th><th style="text-align:right">Importe</th><th class="no-sort">Anular<?= fin_help('anular') ?></th></tr></thead>
            <tbody>
            <?php foreach ($payments as $p): ?>
            <tr style="<?= $p['voided_at'] ? 'opacity:.55' : '' ?>">
                <td style="white-space:nowrap;color:var(--text-muted)" data-order="<?= esc($p['paid_at']) . sprintf('%08d', (int) $p['id']) ?>"><?= date('d/m/Y', strtotime($p['paid_at'])) ?></td>
                <td style="font-weight:600"><a href="<?= base_url('finanzas/alumnos/' . (int) $p['player_id']) ?>" class="row-link-anchor"><?= esc($p['player_name'] ?? '—') ?></a></td>
                <td><?= esc($p['method_name'] ?? '—') ?></td>
                <td style="color:var(--text-body)"><?= esc(trim(($p['reference'] ?? '') . ' ' . ($p['note'] ?? ''))) ?: '—' ?>
                    <?php if ($p['voided_at']): ?><div style="font-size:11px;color:var(--danger);font-weight:600">Anulado: <?= esc($p['void_reason']) ?></div><?php endif; ?></td>
                <td data-order="<?= (int) $p['amount_cents'] ?>" style="text-align:right;font-weight:700;<?= $p['voided_at'] ? 'text-decoration:line-through' : '' ?>"><?= eur((int) $p['amount_cents']) ?></td>
                <td>
                    <?php if (!$p['voided_at']): ?>
                    <form action="<?= base_url('finanzas/cobros/' . (int) $p['id'] . '/anular') ?>" method="post" class="d-flex gap-1" style="margin:0"
                          data-ru-confirm="¿Anular este cobro de <?= eur((int) $p['amount_cents']) ?>?" data-ru-confirm-desc="No se borra: queda anulado con el motivo y lo que pagaba vuelve a estar pendiente." data-ru-confirm-label="Anular cobro">
                        <?= csrf_field() ?>
                        <input type="text" name="reason" class="form-control-jp" style="width:130px;padding:3px 6px;font-size:12px" placeholder="Motivo" required minlength="3" aria-label="Motivo de la anulación">
                        <button type="submit" class="btn-jp btn-jp-secondary btn-jp-sm" aria-label="Anular cobro"><i class="bi bi-x-octagon"></i></button>
                    </form>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
