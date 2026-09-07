<?php
$catColors = ['bug' => '#dc2626', 'mejora' => '#2563eb', 'consulta' => '#0891b2', 'tecnico' => '#7c3aed', 'otro' => '#6b7280'];
$staColors = ['abierto' => '#2563eb', 'en_progreso' => '#d97706', 'resuelto' => '#059669', 'cerrado' => '#6b7280'];
$priColors = ['baja' => '#6b7280', 'media' => '#2563eb', 'alta' => '#d97706', 'urgente' => '#dc2626'];

$activeFilters = array_filter([
    'Estado'    => $statuses[$filters['status']]     ?? ($filters['status'] ?: null),
    'Prioridad' => $priorities[$filters['priority']] ?? ($filters['priority'] ?: null),
    'Categoría' => $categories[$filters['category']] ?? ($filters['category'] ?: null),
    'Búsqueda'  => $filters['search'] ?: null,
]);
?>
<!doctype html>
<html lang="es">
<head>
<meta charset="utf-8">
<title>Tickets — <?= date('d/m/Y') ?></title>
<style>
    * { box-sizing: border-box; }
    body { font: 12px/1.45 -apple-system, "Segoe UI", Roboto, Arial, sans-serif; color: #111827; margin: 28px; }
    h1 { font-size: 18px; margin: 0 0 2px; }
    .sub { color: #6b7280; font-size: 11px; margin-bottom: 4px; }
    .filters { color: #374151; font-size: 11px; margin-bottom: 14px; }
    .filters b { color: #111827; }
    table { width: 100%; border-collapse: collapse; }
    thead th {
        text-align: left; font-size: 10px; text-transform: uppercase; letter-spacing: .4px;
        color: #6b7280; border-bottom: 2px solid #e5e7eb; padding: 6px 8px;
    }
    tbody td { padding: 7px 8px; border-bottom: 1px solid #f1f5f9; vertical-align: top; }
    tbody tr:nth-child(even) { background: #fafafa; }
    .num { font-family: ui-monospace, Menlo, Consolas, monospace; font-size: 10.5px; color: #6b7280; white-space: nowrap; }
    .title { font-weight: 600; }
    .desc { color: #4b5563; font-size: 10.5px; margin-top: 2px; max-width: 340px; }
    .pill {
        display: inline-block; padding: 1px 7px; border-radius: 999px;
        font-size: 9.5px; font-weight: 700; color: #fff; white-space: nowrap;
    }
    .foot { margin-top: 16px; color: #9ca3af; font-size: 10px; }
    @media print {
        body { margin: 12mm; }
        thead { display: table-header-group; }
        tr { page-break-inside: avoid; }
        .no-print { display: none; }
    }
    .no-print { margin-bottom: 16px; }
    .btn { padding: 8px 16px; font-size: 13px; border: 1px solid #2563eb; background: #2563eb; color: #fff; border-radius: 8px; cursor: pointer; }
    .btn.sec { background: #fff; color: #2563eb; }
</style>
</head>
<body>

<div class="no-print">
    <button class="btn" onclick="window.print()">Imprimir / Guardar como PDF</button>
    <button class="btn sec" onclick="window.close()">Cerrar</button>
</div>

<h1>Tickets<?= $withUser ? '' : ' — mis tickets' ?></h1>
<div class="sub">Generado el <?= date('d/m/Y H:i') ?> · <?= count($tickets) ?> ticket<?= count($tickets) !== 1 ? 's' : '' ?></div>
<?php if ($activeFilters): ?>
<div class="filters">
    Filtros:
    <?php foreach ($activeFilters as $label => $val): ?>
        <b><?= esc($label) ?>:</b> <?= esc($val) ?><?php if ($label !== array_key_last($activeFilters)): ?> · <?php endif; ?>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<table>
    <thead>
        <tr>
            <th>Nº</th>
            <th>Ticket</th>
            <th>Categoría</th>
            <th>Prioridad</th>
            <th>Estado</th>
            <?php if ($withUser): ?><th>Usuario</th><?php endif; ?>
            <th>Resp.</th>
            <th>Creado</th>
        </tr>
    </thead>
    <tbody>
        <?php if (empty($tickets)): ?>
        <tr><td colspan="<?= $withUser ? 8 : 7 ?>" style="text-align:center;color:#9ca3af;padding:24px">Sin tickets.</td></tr>
        <?php endif; ?>
        <?php foreach ($tickets as $t): ?>
        <tr>
            <td class="num"><?= esc($t['ticket_number']) ?></td>
            <td>
                <div class="title"><?= esc($t['title']) ?></div>
                <?php if (!empty($t['description'])): ?>
                <div class="desc"><?= esc(mb_strimwidth(preg_replace('/\s+/', ' ', $t['description']), 0, 180, '…')) ?></div>
                <?php endif; ?>
            </td>
            <td><span class="pill" style="background:<?= $catColors[$t['category']] ?? '#6b7280' ?>"><?= esc($categories[$t['category']] ?? $t['category']) ?></span></td>
            <td><span class="pill" style="background:<?= $priColors[$t['priority']] ?? '#6b7280' ?>"><?= esc($priorities[$t['priority']] ?? $t['priority']) ?></span></td>
            <td><span class="pill" style="background:<?= $staColors[$t['status']] ?? '#6b7280' ?>"><?= esc($statuses[$t['status']] ?? $t['status']) ?></span></td>
            <?php if ($withUser): ?><td><?= esc($t['user_name'] ?? '') ?></td><?php endif; ?>
            <td style="text-align:center"><?= (int) ($t['reply_count'] ?? 0) ?></td>
            <td class="num"><?= $t['created_at'] ? date('d/m/Y', strtotime($t['created_at'])) : '' ?></td>
        </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<div class="foot">Tu Plataforma · Plataforma interna</div>

<?php if (!empty($autoPrint)): ?>
<script>window.addEventListener('load', () => setTimeout(() => window.print(), 300));</script>
<?php endif; ?>

</body>
</html>
