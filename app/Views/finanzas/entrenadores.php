<?= $this->extend('layouts/app') ?>

<?php
$pageTitle    = 'Finanzas';
$pageSubtitle = 'Entrenadores: clases impartidas, servicio prestado y gastos asignados';
helper(['money', 'finhelp']);
$roles = ['coach' => 'Entrenador', 'staff' => 'Staff', 'admin' => 'Admin', 'superadmin' => 'Superadmin'];
?>

<?= $this->section('page_content') ?>
<?= view('finanzas/_tabs', ['tab' => $tab, 'reviewCount' => $reviewCount]) ?>
<?= view('finanzas/_period', ['period' => $period, 'months' => $months]) ?>

<div class="card-jp">
    <?php if (empty($coaches)): ?><div class="card-jp-body"><p style="margin:0;color:var(--text-muted);font-size:13px">Sin datos en este periodo.</p></div><?php else: ?>
    <div class="table-responsive">
        <table class="table-jp" style="font-size:13px" data-fin-list="entrenadores" data-fin-order='[[2,"desc"]]'>
            <thead><tr><th>Nombre</th><th>Rol</th><th style="text-align:right">Clases dadas<?= fin_help('ent_clases') ?></th><th style="text-align:right">Asistencias</th><th style="text-align:right">Servicio prestado<?= fin_help('ent_servicio') ?></th><th style="text-align:right">Gastos asignados<?= fin_help('ent_gastos') ?></th><th class="no-sort no-label"></th></tr></thead>
            <tbody>
            <?php foreach ($coaches as $c): ?>
            <tr style="<?= $c['status'] !== 'active' ? 'opacity:.6' : '' ?>">
                <td style="font-weight:600"><?= esc($c['name']) ?><?= $c['status'] !== 'active' ? ' <span class="badge-status inactive" style="font-size:10px">De baja</span>' : '' ?></td>
                <td><?= $roles[$c['role']] ?? esc($c['role']) ?></td>
                <td style="text-align:right;font-weight:700"><?= (int) $c['sessions'] ?></td>
                <td style="text-align:right"><?= (int) $c['attended'] ?></td>
                <td style="text-align:right" data-order="<?= (int) $c['service_cents'] ?>"><?= eur((int) $c['service_cents']) ?></td>
                <td style="text-align:right"><?= (int) $c['expense_count'] ? eur((int) $c['expense_cents']) . ' <span style="color:var(--text-muted);font-size:11px">(' . (int) $c['expense_count'] . ')</span>' : '—' ?></td>
                <td style="text-align:right"><a href="<?= base_url('finanzas/gastos') ?>" class="btn-jp btn-jp-secondary btn-jp-sm" style="text-decoration:none">Asignar gasto</a></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
    <div class="card-jp-body" style="font-size:12px;color:var(--text-muted);padding-top:8px">
        Clases dadas = sesiones cerradas del periodo en las que figura como responsable. Servicio prestado = sesiones de bono consumidas en esas clases. De momento no se calcula un coste por sesión del entrenador: lo que se le pague se registra como gasto asignado a él.
    </div>
</div>

<?= $this->endSection() ?>
