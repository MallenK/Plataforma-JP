<?= $this->extend('layouts/app') ?>

<?php
$pageTitle    = 'Finanzas';
$pageSubtitle = 'Alumnos: lo que ha comprado, pagado y debe cada uno';
$tot = ['charged' => 0, 'paid' => 0, 'due' => 0];
foreach ($rows as $r) { $tot['charged'] += (int) $r['charged']; $tot['paid'] += (int) $r['paid']; $tot['due'] += max(0, (int) $r['due']); }
?>

<?= $this->section('page_content') ?>
<?= view('finanzas/_tabs', ['tab' => $tab, 'reviewCount' => $reviewCount]) ?>

<form method="get" class="d-flex flex-wrap gap-2 align-items-end mb-3">
    <div>
        <label class="form-label" for="fa-q" style="margin-bottom:2px">Buscar alumno</label>
        <input id="fa-q" name="q" type="search" class="form-control-jp" value="<?= esc($q) ?>" placeholder="Nombre…" style="min-width:220px">
    </div>
    <div>
        <label class="form-label" for="fa-ver" style="margin-bottom:2px">Mostrar</label>
        <select id="fa-ver" name="ver" class="form-control-jp" style="width:auto">
            <option value="">Todos</option>
            <option value="deben" <?= $only === 'deben' ? 'selected' : '' ?>>Solo los que deben</option>
            <option value="favor" <?= $only === 'favor' ? 'selected' : '' ?>>Con saldo a favor</option>
        </select>
    </div>
    <button type="submit" class="btn-jp btn-jp-secondary">Filtrar</button>
    <span style="margin-left:auto;font-size:13px;color:var(--text-muted)">Comprado <strong style="color:var(--text-h)"><?= eur($tot['charged']) ?></strong> · Pagado <strong style="color:var(--text-h)"><?= eur($tot['paid']) ?></strong> · Deben <strong style="color:#9a3412"><?= eur($tot['due']) ?></strong></span>
</form>

<div class="card-jp">
    <div class="table-responsive">
        <table class="table-jp" style="font-size:13px">
            <thead><tr><th>Alumno</th><th style="text-align:right">Comprado</th><th style="text-align:right">Pagado</th><th style="text-align:right">Debe</th><th style="text-align:right">Sesiones disponibles</th><th></th></tr></thead>
            <tbody>
            <?php if (empty($rows)): ?>
            <tr><td colspan="6" style="color:var(--text-muted)">No hay alumnos con ese filtro.</td></tr>
            <?php endif; ?>
            <?php foreach ($rows as $r): $due = (int) $r['due']; ?>
            <tr class="row-link" data-href="<?= base_url('finanzas/alumnos/' . (int) $r['id']) ?>" style="cursor:pointer">
                <td style="font-weight:600"><a href="<?= base_url('finanzas/alumnos/' . (int) $r['id']) ?>" class="row-link-anchor"><?= esc($r['name']) ?></a><?= $r['status'] !== 'active' ? ' <span class="badge-status inactive" style="font-size:10px">De baja</span>' : '' ?></td>
                <td style="text-align:right"><?= eur((int) $r['charged']) ?></td>
                <td style="text-align:right"><?= eur((int) $r['paid']) ?></td>
                <td style="text-align:right;font-weight:700;color:<?= $due > 0 ? '#9a3412' : ($due < 0 ? '#047857' : 'var(--text-muted)') ?>"><?= $due < 0 ? eur(-$due) . ' a favor' : eur($due) ?></td>
                <td style="text-align:right"><?= (int) $r['sessions_left'] ?></td>
                <td style="text-align:right"><a href="<?= base_url('finanzas/alumnos/' . (int) $r['id']) ?>" class="btn-jp btn-jp-secondary btn-jp-sm" style="text-decoration:none">Cuenta</a></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<?= $this->endSection() ?>
