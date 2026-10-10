<?= $this->extend('layouts/app') ?>

<?php
$pageTitle    = 'Finanzas';
$pageSubtitle = 'Cuenta del alumno';
helper(['money', 'finhelp']);
$t     = $account['totals'];
$due   = (int) $t['due'];
$usable = array_filter($bonos, fn($b) => empty($b['voided_at']) && (int) $b['sessions_remaining'] > 0 && (empty($b['expires_at']) || $b['expires_at'] >= $today));
$left  = array_sum(array_map(fn($b) => (int) $b['sessions_remaining'], $usable));
$back  = '/finanzas/alumnos/' . (int) $player['id'];
$chargesById = [];
foreach ($account['charges'] as $c) { $chargesById[(int) $c['id']] = $c; }
$attLabel = ['present' => 'Presente', 'absent' => 'Ausencia justificada', 'unjustified' => 'Falta sin justificar', 'declined' => 'Avisó ausencia',
             'confirmed' => 'Confirmada', 'pending' => 'Pendiente'];
?>

<?= $this->section('page_content') ?>
<?= view('finanzas/_tabs', ['tab' => $tab, 'reviewCount' => $reviewCount]) ?>

<div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
    <div>
        <a href="<?= base_url('finanzas/alumnos') ?>" style="font-size:13px;font-weight:600;text-decoration:none">← Alumnos</a>
        <h2 style="margin:6px 0 0;font-size:22px;font-weight:800;color:var(--text-h)"><?= esc($player['name']) ?></h2>
        <div style="font-size:13px;color:var(--text-muted)"><?= $player['status'] === 'active' ? 'Activo' : 'De baja' ?> · <a href="<?= base_url('alumnos/' . (int) $player['id']) ?>">ficha del alumno</a> · <a href="<?= base_url('bonos') ?>">vender bono</a></div>
    </div>
</div>

<div class="row g-3 mb-3">
    <div class="col-6 col-lg"><div class="metric-card"><div class="metric-card-header"><span class="metric-label">Comprado<?= fin_help('comprado') ?></span></div><div class="metric-value" style="font-size:24px"><?= eur((int) $t['charged']) ?></div></div></div>
    <div class="col-6 col-lg"><div class="metric-card"><div class="metric-card-header"><span class="metric-label">Pagado<?= fin_help('pagado') ?></span></div><div class="metric-value" style="font-size:24px"><?= eur((int) $t['paid']) ?></div></div></div>
    <div class="col-6 col-lg"><div class="metric-card" style="<?= $due > 0 ? 'border-color:#fdba74;background:#fff7ed' : '' ?>"><div class="metric-card-header"><span class="metric-label"><?= $due < 0 ? 'A su favor' : 'Debe' ?><?= fin_help('debe') ?></span></div><div class="metric-value" style="font-size:24px;color:<?= $due > 0 ? '#9a3412' : '#047857' ?>"><?= eur(abs($due)) ?></div></div></div>
    <div class="col-6 col-lg"><div class="metric-card"><div class="metric-card-header"><span class="metric-label">Sesiones disponibles<?= fin_help('sesiones') ?></span></div><div class="metric-value" style="font-size:24px"><?= $left ?></div></div></div>
    <div class="col-12 col-lg"><div class="metric-card" style="<?= $debts ? 'border-color:#fdba74;background:#fff7ed' : '' ?>"><div class="metric-card-header"><span class="metric-label">Clases sin descontar<?= fin_help('sin_descontar') ?></span></div><div class="metric-value" style="font-size:24px"><?= count($debts) ?></div><?php if ($debts): ?><a href="<?= base_url('bonos/deudas') ?>" style="font-size:12px">Resolver</a><?php endif; ?></div></div>
</div>

<div class="row g-3">
    <div class="col-12 col-xl-8 d-flex flex-column gap-3">

        <!-- Bonos -->
        <div class="card-jp">
            <div class="card-jp-header"><span class="card-jp-title">Bonos</span></div>
            <?php if (empty($bonos)): ?><div class="card-jp-body"><p style="margin:0;color:var(--text-muted);font-size:13px">Sin bonos.</p></div><?php else: ?>
            <div class="table-responsive">
                <table class="table-jp" style="font-size:13px" data-fin-list="alumno-bonos" data-fin-page="10">
                    <thead><tr><th>Bono</th><th style="text-align:right">Precio</th><th>Vigencia</th><th style="text-align:right">Usadas</th><th style="text-align:right">Quedan</th><th>Estado</th></tr></thead>
                    <tbody>
                    <?php foreach ($bonos as $b):
                        $rem = (int) $b['sessions_remaining']; $exp = !empty($b['expires_at']) && $b['expires_at'] < $today;
                        [$lbl, $fg, $bg] = !empty($b['voided_at']) ? ['Anulado', '#475569', '#f1f5f9']
                            : ($exp && $rem > 0 ? ['Caducado · ' . $rem . ' sin usar', '#5b21b6', '#ede9fe']
                            : ($rem === 0 ? ['Agotado', '#475569', '#f1f5f9'] : ['Con saldo', '#065f46', '#d1fae5'])); ?>
                    <tr style="<?= !empty($b['voided_at']) ? 'opacity:.55' : '' ?>">
                        <td style="font-weight:600"><a href="<?= base_url('bonos/' . (int) $b['id']) ?>" class="row-link-anchor"><?= esc($b['bono_name'] ?? 'Bono') ?></a></td>
                        <td style="text-align:right"><?= eur($b['price_cents'] !== null ? (int) $b['price_cents'] : null) ?><?= (int) ($b['price_list_cents'] ?? 0) > (int) ($b['price_cents'] ?? 0) ? '<div style="font-size:11px;color:var(--text-muted)">tarifa ' . eur((int) $b['price_list_cents']) . '</div>' : '' ?></td>
                        <td style="white-space:nowrap"><?= date('d/m', strtotime($b['start_date'])) ?> – <?= $b['expires_at'] ? date('d/m/Y', strtotime($b['expires_at'])) : 'sin fecha' ?></td>
                        <td style="text-align:right"><?= (int) $b['sessions_total'] - $rem ?></td>
                        <td style="text-align:right;font-weight:700"><?= $rem ?></td>
                        <td><span style="font-size:11px;font-weight:700;color:<?= $fg ?>;background:<?= $bg ?>;border-radius:10px;padding:2px 8px;white-space:nowrap"><?= $lbl ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>

        <!-- Estado de cuenta -->
        <div class="card-jp">
            <div class="card-jp-header" style="flex-wrap:wrap;gap:6px"><span class="card-jp-title">Estado de cuenta<?= fin_help('estado_cuenta') ?></span><span style="font-size:12px;color:var(--text-muted)">Nada se borra: lo erróneo se anula y queda a la vista</span></div>
            <?php if (empty($account['lines'])): ?><div class="card-jp-body"><p style="margin:0;color:var(--text-muted);font-size:13px">Sin movimientos.</p></div><?php else: ?>
            <div class="table-responsive">
                <table class="table-jp" style="font-size:13px" data-fin-list="alumno-cuenta" data-fin-page="10">
                    <thead><tr><th>Fecha</th><th>Concepto</th><th style="text-align:right">Cargo</th><th style="text-align:right">Pago</th><th style="text-align:right">Saldo</th><th class="no-sort">Anular<?= fin_help('anular') ?></th></tr></thead>
                    <tbody>
                    <?php foreach ($account['lines'] as $l): $isCharge = $l['kind'] === 'charge'; ?>
                    <tr style="<?= $l['voided'] ? 'opacity:.55' : '' ?>">
                        <td style="white-space:nowrap;color:var(--text-muted)"><?= date('d/m/Y', strtotime($l['date'])) ?></td>
                        <td><?= esc($l['concept']) ?>
                            <?php if ($isCharge && !empty($l['discount'])): ?><div style="font-size:11px;color:var(--text-muted)">Descuento <?= eur((int) $l['discount']) ?> · <?= esc($l['discount_reason'] ?? '') ?></div><?php endif; ?>
                            <?php if (!$isCharge && !empty($l['note'])): ?><div style="font-size:11px;color:var(--text-muted)"><?= esc($l['note']) ?></div><?php endif; ?>
                            <?php if ($l['voided']): ?><div style="font-size:11px;color:var(--danger);font-weight:600">Anulado<?= $l['void_reason'] ? ': ' . esc($l['void_reason']) : '' ?></div><?php endif; ?>
                        </td>
                        <td style="text-align:right;<?= $l['voided'] ? 'text-decoration:line-through' : '' ?>"><?= $isCharge ? eur((int) $l['amount']) : '' ?></td>
                        <td style="text-align:right;<?= $l['voided'] ? 'text-decoration:line-through' : '' ?>"><?= !$isCharge ? eur((int) $l['amount']) : '' ?></td>
                        <td style="text-align:right;font-weight:700"><?= $l['balance'] < 0 ? eur(-$l['balance']) . ' a favor' : eur($l['balance']) ?></td>
                        <td>
                            <?php if (!$l['voided']): ?>
                            <form action="<?= base_url(($isCharge ? 'finanzas/cargos/' : 'finanzas/cobros/') . (int) $l['id'] . '/anular') ?>" method="post" class="d-flex gap-1" style="margin:0"
                                  data-ru-confirm="¿Anular este <?= $isCharge ? 'cargo' : 'pago' ?>?" data-ru-confirm-desc="No se borra: queda anulado con el motivo." data-ru-confirm-label="Anular">
                                <?= csrf_field() ?>
                                <input type="hidden" name="back" value="<?= esc($back, 'attr') ?>">
                                <input type="text" name="reason" class="form-control-jp" style="width:110px;padding:3px 6px;font-size:12px" placeholder="Motivo" required minlength="3" aria-label="Motivo de la anulación">
                                <button type="submit" class="btn-jp btn-jp-secondary btn-jp-sm" aria-label="Anular"><i class="bi bi-x-octagon"></i></button>
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

        <!-- Clases -->
        <div class="card-jp">
            <div class="card-jp-header"><span class="card-jp-title">Clases y sesiones consumidas</span></div>
            <div class="table-responsive">
                <table class="table-jp" style="font-size:13px" data-fin-list="alumno-clases" data-fin-page="10" data-fin-order='[[0,"desc"]]'>
                    <thead><tr><th>Fecha</th><th>Asistencia</th><th>Bono</th><th style="text-align:right">Valor</th></tr></thead>
                    <tbody>
                    <?php foreach ($classes as $c):
                        $state = $c['status'] === 'cancelled' ? 'Cancelada' : ($c['status'] === 'scheduled' && $c['session_date'] < $today ? 'Sesión sin cerrar' : ($attLabel[$c['attendance']] ?? $c['attendance'])); ?>
                    <tr>
                        <td style="white-space:nowrap" data-order="<?= esc($c['session_date'] . ' ' . $c['start_time']) ?>"><a href="<?= base_url('clases/' . (int) $c['id']) ?>" class="row-link-anchor"><?= date('d/m/Y', strtotime($c['session_date'])) ?> · <?= substr($c['start_time'], 0, 5) ?></a></td>
                        <td><?= esc($state) ?></td>
                        <td><?php if ($c['bono_deducted_at']): ?><?= esc($c['bono_name'] ?? 'Bono') ?>
                            <?php elseif ($c['bono_resolution']): ?><span style="color:var(--text-muted)"><?= esc(\App\Services\BonoControlService::resolutionLabel($c['bono_resolution'])) ?></span>
                            <?php else: ?><span style="color:var(--text-muted)">—</span><?php endif; ?></td>
                        <td style="text-align:right"><?= $c['bono_deducted_at'] && $c['unit_cents'] !== null ? eur((int) round((float) $c['unit_cents'])) : '—' ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <div class="col-12 col-xl-4 d-flex flex-column gap-3">
        <!-- Registrar cobro -->
        <div class="card-jp" id="cobro" style="border:2px solid #bfdbfe">
            <div class="card-jp-header"><span class="card-jp-title"><i class="bi bi-cash-coin me-2" style="color:var(--accent)"></i>Registrar cobro</span></div>
            <form action="<?= base_url('finanzas/cobros') ?>" method="post" class="card-jp-body d-flex flex-column gap-3">
                <?= csrf_field() ?>
                <input type="hidden" name="player_id" value="<?= (int) $player['id'] ?>">
                <input type="hidden" name="back" value="alumno">
                <div>
                    <label class="form-label" for="ac-amount">Importe (€)<?= fin_help('cobro_importe') ?></label>
                    <input id="ac-amount" name="amount" type="text" inputmode="decimal" class="form-control-jp" value="<?= $due > 0 ? eur_input($due) : '' ?>" placeholder="0,00" required style="font-size:18px;font-weight:700">
                </div>
                <fieldset style="border:0;margin:0;padding:0">
                    <legend class="form-label" style="font-size:inherit">Medio de pago<?= fin_help('cobro_medio') ?></legend>
                    <div class="d-flex flex-wrap gap-2">
                        <?php foreach ($methods as $i => $m): if ($m['code'] === 'sin_especificar') continue; ?>
                        <label style="display:flex;align-items:center;gap:6px;border:1px solid var(--border-dark);border-radius:8px;padding:8px 10px;min-height:40px;font-size:13px;font-weight:600;cursor:pointer">
                            <input type="radio" name="method_id" value="<?= (int) $m['id'] ?>" <?= $i === 0 ? 'checked' : '' ?> required> <?= esc($m['name']) ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </fieldset>
                <?php if ($pending): ?>
                <div>
                    <label class="form-label" for="ac-charge">Paga<?= fin_help('cobro_reparto') ?></label>
                    <select id="ac-charge" name="charge_id" class="form-control-jp">
                        <option value="">Lo más antiguo primero</option>
                        <?php foreach ($pending as $cid => $cents): $ch = $chargesById[$cid] ?? null; ?>
                        <option value="<?= (int) $cid ?>"><?= esc($ch['concept'] ?? 'Cargo') ?> · pendiente <?= eur((int) $cents) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php endif; ?>
                <div class="row g-2">
                    <div class="col-6"><label class="form-label" for="ac-date">Fecha</label><input id="ac-date" name="paid_at" type="date" class="form-control-jp" value="<?= $today ?>" max="<?= $today ?>"></div>
                    <div class="col-6"><label class="form-label" for="ac-ref">Referencia</label><input id="ac-ref" name="reference" type="text" class="form-control-jp" maxlength="120"></div>
                </div>
                <div><label class="form-label" for="ac-note">Nota <span style="font-weight:400;color:var(--text-muted)">(opcional)</span></label><input id="ac-note" name="note" type="text" class="form-control-jp" maxlength="255" placeholder="Ej.: 1º de 2 plazos"></div>
                <button type="submit" class="btn-jp btn-jp-primary">Guardar cobro</button>
            </form>
        </div>

        <!-- Cargo manual -->
        <div class="card-jp">
            <div class="card-jp-header"><span class="card-jp-title">Añadir cargo<?= fin_help('cargo_manual') ?></span></div>
            <form action="<?= base_url('finanzas/alumnos/' . (int) $player['id'] . '/cargo') ?>" method="post" class="card-jp-body d-flex flex-column gap-2">
                <?= csrf_field() ?>
                <p style="margin:0 0 4px;font-size:12px;color:var(--text-muted)">Para una sesión suelta, material u otro concepto. Los bonos se cargan solos al venderlos.</p>
                <div><label class="form-label" for="mc-concept">Concepto</label><input id="mc-concept" name="concept" type="text" class="form-control-jp" maxlength="160" required placeholder="Ej.: sesión suelta 12/10"></div>
                <div class="row g-2">
                    <div class="col-6"><label class="form-label" for="mc-amount">Importe (€)</label><input id="mc-amount" name="amount" type="text" inputmode="decimal" class="form-control-jp" required></div>
                    <div class="col-6"><label class="form-label" for="mc-cat">Categoría</label>
                        <select id="mc-cat" name="category_id" class="form-control-jp"><?php foreach ($incomeCategories as $ic): ?><option value="<?= (int) $ic['id'] ?>"><?= esc($ic['name']) ?></option><?php endforeach; ?></select></div>
                </div>
                <div class="row g-2">
                    <div class="col-6"><label class="form-label" for="mc-disc">Descuento<?= fin_help('descuento') ?></label><input id="mc-disc" name="discount" type="text" class="form-control-jp" placeholder="10 o 10%"></div>
                    <div class="col-6"><label class="form-label" for="mc-reason">Motivo</label><input id="mc-reason" name="discount_reason" type="text" class="form-control-jp" maxlength="120" placeholder="Hermanos…"></div>
                </div>
                <button type="submit" class="btn-jp btn-jp-secondary">Añadir cargo</button>
            </form>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
