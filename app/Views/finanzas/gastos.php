<?= $this->extend('layouts/app') ?>

<?php
$pageTitle    = 'Finanzas';
$pageSubtitle = 'Gastos: material, reparaciones, alquiler, entrenadores… con su ticket o factura';
$total = array_sum(array_map(fn($e) => $e['voided_at'] ? 0 : (int) $e['amount_cents'], $expenses));
?>

<?= $this->section('page_content') ?>
<?= view('finanzas/_tabs', ['tab' => $tab, 'reviewCount' => $reviewCount]) ?>

<div class="card-jp mb-3" style="border:2px solid #fed7aa">
    <div class="card-jp-header"><span class="card-jp-title"><i class="bi bi-receipt-cutoff me-2" style="color:#c2410c"></i>Registrar gasto</span></div>
    <form action="<?= base_url('finanzas/gastos') ?>" method="post" enctype="multipart/form-data" class="card-jp-body">
        <?= csrf_field() ?>
        <div class="row g-3">
            <div class="col-6 col-md-3">
                <label class="form-label" for="ex-amount">Importe (€)</label>
                <input id="ex-amount" name="amount" type="text" inputmode="decimal" class="form-control-jp" placeholder="0,00" required>
            </div>
            <div class="col-6 col-md-3">
                <label class="form-label" for="ex-date">Fecha</label>
                <input id="ex-date" name="spent_at" type="date" class="form-control-jp" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" required>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label" for="ex-cat">Categoría</label>
                <select id="ex-cat" name="category_id" class="form-control-jp" required>
                    <option value="">— Elige —</option>
                    <?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>"><?= esc($c['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-3">
                <label class="form-label" for="ex-method">Pagado con</label>
                <select id="ex-method" name="method_id" class="form-control-jp">
                    <option value="">—</option>
                    <?php foreach ($methods as $m): if ($m['code'] === 'sin_especificar') continue; ?><option value="<?= (int) $m['id'] ?>"><?= esc($m['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label" for="ex-desc">Descripción</label>
                <input id="ex-desc" name="description" type="text" class="form-control-jp" maxlength="255" placeholder="Ej.: 20 balones talla 4">
            </div>
            <div class="col-12 col-md-4">
                <label class="form-label" for="ex-supplier">Proveedor <span style="font-weight:400;color:var(--text-muted)">(opcional)</span></label>
                <input id="ex-supplier" name="supplier" type="text" class="form-control-jp" maxlength="120">
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="ex-staff">Entrenador</label>
                <select id="ex-staff" name="staff_id" class="form-control-jp">
                    <option value="">—</option>
                    <?php foreach ($staff as $s): ?><option value="<?= (int) $s['id'] ?>"><?= esc($s['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-6 col-md-2">
                <label class="form-label" for="ex-loc">Sede</label>
                <select id="ex-loc" name="location_id" class="form-control-jp">
                    <option value="">—</option>
                    <?php foreach ($locations as $l): ?><option value="<?= (int) $l['id'] ?>"><?= esc($l['name']) ?></option><?php endforeach; ?>
                </select>
            </div>
            <div class="col-12 col-md-8">
                <label class="form-label" for="ex-file">Ticket o factura <span style="font-weight:400;color:var(--text-muted)">(PDF o foto, máx. 10 MB)</span></label>
                <input id="ex-file" name="attachment" type="file" class="form-control-jp" accept=".pdf,.jpg,.jpeg,.png,.webp,.heic,image/*,application/pdf">
            </div>
            <div class="col-12 col-md-4 d-flex align-items-end">
                <button type="submit" class="btn-jp btn-jp-primary w-100">Guardar gasto</button>
            </div>
        </div>
    </form>
</div>

<?= view('finanzas/_period', ['period' => $period, 'months' => $months, 'extra' => ['categoria' => $categoryId]]) ?>

<div class="card-jp">
    <div class="card-jp-header" style="flex-wrap:wrap;gap:8px">
        <span class="card-jp-title">Gastos del periodo (<?= count($expenses) ?>) · <?= eur($total) ?></span>
        <form method="get" class="d-flex gap-2" style="margin:0">
            <?php foreach (['mes', 'desde', 'hasta'] as $k): if (!empty($_GET[$k])): ?><input type="hidden" name="<?= $k ?>" value="<?= esc($_GET[$k], 'attr') ?>"><?php endif; endforeach; ?>
            <select name="categoria" class="form-control-jp" style="width:auto" onchange="this.form.submit()" aria-label="Filtrar por categoría">
                <option value="">Todas las categorías</option>
                <?php foreach ($categories as $c): ?><option value="<?= (int) $c['id'] ?>" <?= $categoryId === (int) $c['id'] ? 'selected' : '' ?>><?= esc($c['name']) ?></option><?php endforeach; ?>
            </select>
        </form>
    </div>
    <?php if (empty($expenses)): ?>
    <div class="card-jp-body"><p style="margin:0;color:var(--text-muted);font-size:13px">No hay gastos en este periodo.</p></div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table-jp" style="font-size:13px">
            <thead><tr><th>Fecha</th><th>Categoría</th><th>Descripción</th><th>Asignado a</th><th style="text-align:right">Importe</th><th>Adjunto</th><th>Anular</th></tr></thead>
            <tbody>
            <?php foreach ($expenses as $e): ?>
            <tr style="<?= $e['voided_at'] ? 'opacity:.55' : '' ?>">
                <td style="white-space:nowrap;color:var(--text-muted)"><?= date('d/m/Y', strtotime($e['spent_at'])) ?></td>
                <td><?= esc($e['category_name'] ?? '—') ?></td>
                <td><?= esc($e['description'] ?? '—') ?><?= $e['supplier'] ? '<div style="font-size:11px;color:var(--text-muted)">' . esc($e['supplier']) . ($e['method_name'] ? ' · ' . esc($e['method_name']) : '') . '</div>' : '' ?>
                    <?php if ($e['voided_at']): ?><div style="font-size:11px;color:var(--danger);font-weight:600">Anulado: <?= esc($e['void_reason']) ?></div><?php endif; ?></td>
                <td><?= esc(trim(($e['staff_name'] ?? '') . ($e['location_name'] ? ' · ' . $e['location_name'] : ''), ' ·')) ?: '—' ?></td>
                <td style="text-align:right;font-weight:700;<?= $e['voided_at'] ? 'text-decoration:line-through' : '' ?>"><?= eur((int) $e['amount_cents']) ?></td>
                <td><?php if (!empty($e['attachment_path'])): ?><a href="<?= base_url('finanzas/gastos/' . (int) $e['id'] . '/adjunto') ?>" aria-label="Descargar adjunto"><i class="bi bi-paperclip"></i> Ver</a><?php else: ?>—<?php endif; ?></td>
                <td>
                    <?php if (!$e['voided_at']): ?>
                    <form action="<?= base_url('finanzas/gastos/' . (int) $e['id'] . '/anular') ?>" method="post" class="d-flex gap-1" style="margin:0"
                          data-ru-confirm="¿Anular este gasto?" data-ru-confirm-desc="No se borra: queda anulado con el motivo." data-ru-confirm-label="Anular gasto">
                        <?= csrf_field() ?>
                        <input type="text" name="reason" class="form-control-jp" style="width:120px;padding:3px 6px;font-size:12px" placeholder="Motivo" required minlength="3" aria-label="Motivo de la anulación">
                        <button type="submit" class="btn-jp btn-jp-secondary btn-jp-sm" aria-label="Anular gasto"><i class="bi bi-x-octagon"></i></button>
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
