<?= $this->extend('layouts/app') ?>

<?php
// Acceso total → Revisión dentro de Finanzas (con precios y dinero). Solo revisión → únicamente lo de las clases.
$finFull      = !empty($finFull);
$pageTitle    = $finFull ? 'Finanzas' : 'Revisión de clases';
$pageSubtitle = $finFull ? 'Revisión: lo que conviene mirar para que las cuentas cuadren' : 'Comprobar que las clases se dieron, se pasó lista y se cerraron';
$th  = 'padding:8px 12px;color:var(--text-muted);font-weight:700;text-transform:uppercase;letter-spacing:.4px;border-bottom:1px solid var(--border);text-align:left;font-size:11px';
$td  = 'padding:8px 12px';
$eur = static fn(?int $c) => $c === null ? '—' : number_format($c / 100, 2, ',', '.') . ' €';
$sinceTxt = $since ? date('d/m/Y', strtotime($since)) : '—';
$unusedTotal = array_sum(array_column($unused, 'unused_cents'));
$statusLabel = ['scheduled' => 'Programada', 'completed' => 'Cerrada', 'cancelled' => 'Cancelada'];

$bk = $buckets ?? ['sin_lista' => [], 'sin_cerrar' => [], 'sin_entrenador' => [], 'vacias' => []];
$nDebts = count($debts) + count($preControl);

// [ancla, título corto, nº, icono, color, fondo, qué hacer]
$groups = [
    'Clases' => [
        ['#sin-lista',      'Falta pasar lista',    count($bk['sin_lista']),       'bi-card-checklist',       '#b45309', '#ffedd5', 'Marcar quién vino'],
        ['#sin-cerrar',     'Falta cerrar la clase', count($bk['sin_cerrar']),     'bi-lock-fill',            '#1d4ed8', '#dbeafe', 'Pulsar «Cerrar clase»'],
        ['#sin-entrenador', 'Sin entrenador',       count($bk['sin_entrenador']),  'bi-person-x-fill',        '#b45309', '#ffedd5', 'Elegir quién la da'],
        ['#vacias',         'Clases vacías',        count($bk['vacias']),          'bi-trash3-fill',          '#b91c1c', '#fee2e2', 'Eliminarlas'],
    ],
    'Bonos y dinero' => [
        ['#sin-bono',       'Clases sin bono descontado', $nDebts,                 'bi-receipt',              '#b91c1c', '#fee2e2', 'Descontar del bono o dar por pagada'],
        ['#precios',        'Precio por confirmar', count($estimated),             'bi-question-circle-fill', '#92400e', '#fef3c7', 'Escribir lo que pagó'],
    ],
];
if (!$finFull) {
    // Sin acceso total: nada de precios ni dinero, solo si las clases cuadran con los bonos.
    $groups['Bonos'] = [$groups['Bonos y dinero'][0]];
    unset($groups['Bonos y dinero']);
}
$totalTasks = 0;
foreach ($groups as $g) { foreach ($g as $c) { $totalTasks += $c[2]; } }

// Cabecera de cada bloque: qué pasa y qué hago, en dos líneas.
$head = static function (string $id, string $icon, string $color, string $title, int $n, string $help, string $what, string $todo, string $extra = '') {
    ob_start(); ?>
    <div class="card-jp-header">
        <span class="card-jp-title" style="color:<?= $color ?>"><i class="bi <?= $icon ?> me-2"></i><?= $title ?> (<?= $n ?>)<?= fin_help($help) ?></span>
        <?= $extra ?>
    </div>
    <div class="card-jp-body" style="font-size:13px;line-height:1.6;padding-bottom:0">
        <div><strong style="color:var(--text-h)">¿Qué pasa?</strong> <span style="color:var(--text-muted)"><?= $what ?></span></div>
        <div><strong style="color:var(--text-h)">¿Qué hago?</strong> <span style="color:var(--text-muted)"><?= $todo ?></span></div>
    </div>
    <?php return ob_get_clean();
};
$allGood = static fn(string $msg) => '<div class="card-jp-body"><p style="color:var(--text-muted);font-size:13px;margin:0"><i class="bi bi-check-circle-fill me-1" style="color:var(--success)"></i>' . $msg . '</p></div>';
$dateCell = static fn(array $s) => '<td style="padding:8px 12px;white-space:nowrap;color:var(--text-muted)" data-order="' . esc($s['session_date'] . ' ' . $s['start_time']) . '">'
    . date('d/m/Y', strtotime($s['session_date'])) . ' · ' . substr($s['start_time'], 0, 5) . '</td>';
$classCell = static fn(array $s) => '<td style="padding:8px 12px"><a href="' . base_url('clases/' . (int) $s['id']) . '" class="row-link-anchor" style="font-weight:600">' . esc($s['title']) . '</a></td>';
$noneTxt = '<span style="color:var(--text-muted)">—</span>';
$openBtn = static fn(array $s, string $label) => '<a href="' . base_url('clases/' . (int) $s['id']) . '" class="btn-jp btn-jp-primary btn-jp-sm" style="text-decoration:none;white-space:nowrap">' . $label . '</a>';
?>

<?= $this->section('page_content') ?>

<?= view('finanzas/_tabs', ['tab' => $tab ?? 'revision', 'reviewCount' => $reviewCount ?? 0]) ?>

<!-- Qué es esta pantalla -->
<div class="card-jp mb-3" style="border:2px solid <?= $totalTasks > 0 ? '#fde68a' : '#bbf7d0' ?>">
    <div class="card-jp-body" style="font-size:14px;line-height:1.6">
        <?php if ($totalTasks > 0): ?>
        <strong style="color:var(--text-h)"><i class="bi bi-clipboard-check me-1" style="color:#b45309"></i>Hay <?= (int) $totalTasks ?> cosa<?= $totalTasks === 1 ? '' : 's' ?> por revisar.</strong>
        <span style="color:var(--text-muted)">Aquí se juntan las clases<?= $finFull ? ' y los bonos' : '' ?> que han quedado a medias. <?= $finFull ? 'Hasta que se resuelvan, los números de Finanzas pueden no ser exactos.' : 'Hasta que se resuelvan, no se sabe con certeza si se dieron.' ?>
        Cada bloque te explica qué pasa y qué tienes que hacer; basta con ir de arriba abajo.</span>
        <?php else: ?>
        <strong style="color:var(--text-h)"><i class="bi bi-check-circle-fill me-1" style="color:var(--success)"></i>Todo en orden.</strong>
        <span style="color:var(--text-muted)">No hay clases <?= $finFull ? 'ni bonos ' : '' ?>pendientes de revisar.<?= $finFull ? ' Los números de Finanzas son fiables.' : '' ?></span>
        <?php endif; ?>
    </div>
</div>

<?php $cp = $closePreview ?? ['sessions' => 0, 'debts' => 0, 'prices' => 0]; ?>
<?php if ($finFull && $cp['sessions'] + $cp['debts'] + $cp['prices'] > 0): ?>
<div class="card-jp mb-3" style="border:2px solid #bfdbfe">
    <div class="card-jp-body d-flex flex-wrap gap-3 align-items-center justify-content-between">
        <div style="font-size:13px;line-height:1.6;max-width:760px">
            <strong style="font-size:14px;color:var(--text-h)"><i class="bi bi-check2-all me-1" style="color:var(--accent)"></i>Dar por bueno todo lo anterior, de una vez</strong><br>
            Si lo de antes de empezar a usar Finanzas ya está dado por válido, puedes cerrarlo todo con un clic:
            se cierran <strong><?= (int) $cp['sessions'] ?></strong> clases pasadas que ya tienen asistencia marcada,
            se dan por buenas <strong><?= (int) $cp['debts'] ?></strong> clases sin bono descontado (el saldo de los bonos no cambia)
            y se confirman <strong><?= (int) $cp['prices'] ?></strong> precios al precio de tarifa.
            Solo te quedará lo que de verdad nadie cerró. Todo queda registrado.
        </div>
        <form action="<?= base_url('finanzas/revision/cierre-inicial') ?>" method="post" style="margin:0"
              data-ru-confirm="¿Dar por bueno lo anterior?"
              data-ru-confirm-desc="Se cierran <?= (int) $cp['sessions'] ?> clases, se aceptan <?= (int) $cp['debts'] ?> clases sin bono descontado y se confirman <?= (int) $cp['prices'] ?> precios. Queda en el registro de auditoría."
              data-ru-confirm-label="Sí, dar por bueno">
            <?= csrf_field() ?>
            <button type="submit" class="btn-jp btn-jp-primary"><i class="bi bi-check2-all me-1"></i>Dar por bueno lo anterior</button>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Resumen por grupos -->
<?php foreach ($groups as $groupName => $cards): ?>
<div style="font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);margin:4px 0 8px"><?= $groupName ?></div>
<div class="row g-3 mb-3">
    <?php foreach ($cards as [$href, $label, $n, $icon, $fg, $bg, $todo]): ?>
    <div class="col-6 col-lg-3">
        <a href="<?= $href ?>" class="metric-card" style="display:block;text-decoration:none;color:inherit;<?= $n > 0 ? 'border-color:' . $fg : '' ?>">
            <div class="metric-card-header">
                <span class="metric-label"><?= $label ?></span>
                <div class="metric-icon" style="background:<?= $n > 0 ? $bg : '#dcfce7' ?>;color:<?= $n > 0 ? $fg : '#15803d' ?>"><i class="bi <?= $n > 0 ? $icon : 'bi-check-lg' ?>"></i></div>
            </div>
            <div class="metric-value"><?= (int) $n ?></div>
            <div style="font-size:12px;color:var(--text-muted);margin-top:2px"><?= $n > 0 ? esc($todo) : 'Nada pendiente' ?></div>
        </a>
    </div>
    <?php endforeach; ?>
</div>
<?php endforeach; ?>

<!-- ───────── CLASES ───────── -->

<!-- 1. Falta pasar lista -->
<div class="card-jp mb-3" id="sin-lista">
    <?= $head('sin-lista', 'bi-card-checklist', '#b45309', 'Clases dadas en las que falta pasar lista', count($bk['sin_lista']), 'rev_sin_lista',
        'La clase ya pasó, pero nadie marcó quién vino.',
        'Ábrela y marca la asistencia. Si en realidad no se dio, <strong>cancélala</strong>.') ?>
    <?php if (empty($bk['sin_lista'])): ?>
    <?= $allGood('Todas las clases pasadas tienen la lista pasada.') ?>
    <?php else: ?>
    <div class="table-responsive">
        <table style="width:100%;border-collapse:collapse;font-size:13px" data-fin-list="rev-sin-lista" data-fin-order='[[0,"asc"]]'>
            <thead><tr>
                <th style="<?= $th ?>">Fecha</th><th style="<?= $th ?>">Clase</th><th style="<?= $th ?>">Entrenador</th><th style="<?= $th ?>">Alumnos</th><th style="<?= $th ?>"></th>
            </tr></thead>
            <tbody>
            <?php foreach ($bk['sin_lista'] as $s): ?>
            <tr style="border-bottom:1px solid var(--border)">
                <?= $dateCell($s) ?>
                <?= $classCell($s) ?>
                <td style="<?= $td ?>"><?= $s['coach_names'] ? esc($s['coach_names']) : '<span style="color:var(--text-muted)">Sin asignar</span>' ?></td>
                <td style="<?= $td ?>"><?= (int) $s['players'] ?></td>
                <td style="<?= $td ?>;text-align:right"><?= $openBtn($s, 'Pasar lista') ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- 2. Falta cerrar la clase -->
<?php
$closeAll = '';
if ($bk['sin_cerrar']) {
    ob_start(); ?>
    <form action="<?= base_url('finanzas/revision/cerrar-clases') ?>" method="post" style="margin:0"
          data-ru-confirm="¿Cerrar todas las clases?"
          data-ru-confirm-desc="Se cierran <?= count($bk['sin_cerrar']) ?> clases que ya tienen la lista pasada. Solo cambia su estado a «Cerrada»: no se descuenta ningún bono ni se avisa a nadie. Queda registrado."
          data-ru-confirm-label="Sí, cerrarlas todas">
        <?= csrf_field() ?>
        <button type="submit" class="btn-jp btn-jp-primary btn-jp-sm"><i class="bi bi-lock-fill me-1"></i>Cerrar las <?= count($bk['sin_cerrar']) ?> clases automáticamente</button>
    </form>
    <?php $closeAll = ob_get_clean();
}
?>
<div class="card-jp mb-3" id="sin-cerrar">
    <?= $head('sin-cerrar', 'bi-lock-fill', '#1d4ed8', 'Clases con lista pasada que faltan por cerrar', count($bk['sin_cerrar']), 'rev_sin_cerrar',
        'Ya se marcó quién vino, pero la clase sigue abierta y todavía no cuenta en Finanzas.',
        'Pulsa el botón para <strong>cerrarlas todas de golpe</strong>, o ábrelas una a una para comprobar la asistencia y pulsar «Cerrar clase». Las que tengan alumnos que deben bono pasarán a «Clases sin bono descontado».', $closeAll) ?>
    <?php if (empty($bk['sin_cerrar'])): ?>
    <?= $allGood('No hay clases pendientes de cerrar.') ?>
    <?php else: ?>
    <div class="table-responsive">
        <table style="width:100%;border-collapse:collapse;font-size:13px" data-fin-list="rev-sin-cerrar" data-fin-order='[[0,"asc"]]'>
            <thead><tr>
                <th style="<?= $th ?>">Fecha</th><th style="<?= $th ?>">Clase</th><th style="<?= $th ?>">Entrenador</th><th style="<?= $th ?>">Alumnos</th><th style="<?= $th ?>"></th>
            </tr></thead>
            <tbody>
            <?php foreach ($bk['sin_cerrar'] as $s): ?>
            <tr style="border-bottom:1px solid var(--border)">
                <?= $dateCell($s) ?>
                <?= $classCell($s) ?>
                <td style="<?= $td ?>"><?= $s['coach_names'] ? esc($s['coach_names']) : '<span style="color:var(--text-muted)">Sin asignar</span>' ?></td>
                <td style="<?= $td ?>"><?= (int) $s['players'] ?></td>
                <td style="<?= $td ?>;text-align:right"><?= $openBtn($s, 'Cerrar clase') ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- 3. Sin entrenador -->
<div class="card-jp mb-3" id="sin-entrenador">
    <?= $head('sin-entrenador', 'bi-person-x-fill', '#b45309', 'Clases sin entrenador', count($bk['sin_entrenador']), 'rev_sin_entrenador',
        'Tienen alumnos apuntados, pero ningún entrenador asignado. No se sabe quién las da ni a quién imputarlas.',
        'Ábrela y elige el <strong>entrenador</strong> (o el staff responsable).') ?>
    <?php if (empty($bk['sin_entrenador'])): ?>
    <?= $allGood('Todas las clases tienen entrenador.') ?>
    <?php else: ?>
    <div class="table-responsive">
        <table style="width:100%;border-collapse:collapse;font-size:13px" data-fin-list="rev-sin-entrenador" data-fin-order='[[0,"asc"]]'>
            <thead><tr>
                <th style="<?= $th ?>">Fecha</th><th style="<?= $th ?>">Clase</th><th style="<?= $th ?>">Alumnos</th><th style="<?= $th ?>">Estado</th><th style="<?= $th ?>"></th>
            </tr></thead>
            <tbody>
            <?php foreach ($bk['sin_entrenador'] as $s): ?>
            <tr style="border-bottom:1px solid var(--border)">
                <?= $dateCell($s) ?>
                <?= $classCell($s) ?>
                <td style="<?= $td ?>"><?= $s['player_names'] ? esc($s['player_names']) : $noneTxt ?></td>
                <td style="<?= $td ?>;color:var(--text-muted)"><?= esc($statusLabel[$s['status']] ?? $s['status']) ?></td>
                <td style="<?= $td ?>;text-align:right"><?= $openBtn($s, 'Asignar entrenador') ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- 4. Clases vacías -->
<?php
$deletableEmpty = array_filter($bk['vacias'], static fn($s) => !empty($s['deletable']));
$deleteAll = '';
if ($deletableEmpty) {
    ob_start(); ?>
    <form action="<?= base_url('finanzas/revision/eliminar-vacias') ?>" method="post" style="margin:0"
          data-ru-confirm="¿Eliminar las clases vacías?"
          data-ru-confirm-desc="Se eliminan <?= count($deletableEmpty) ?> clases que no tienen ningún alumno. Queda registrado en el historial de cambios."
          data-ru-confirm-label="Sí, eliminarlas">
        <?= csrf_field() ?>
        <button type="submit" class="btn-jp btn-jp-sm" style="background:#b91c1c;color:#fff;border-color:#b91c1c"><i class="bi bi-trash3 me-1"></i>Eliminar las <?= count($deletableEmpty) ?> vacías</button>
    </form>
    <?php $deleteAll = ob_get_clean();
}
?>
<div class="card-jp mb-3" id="vacias">
    <?= $head('vacias', 'bi-trash3-fill', '#b91c1c', 'Clases vacías (sin ningún alumno)', count($bk['vacias']), 'rev_vacias',
        'No tienen ningún alumno apuntado. Casi seguro son un error, una prueba o lo que quedó al quitar a un alumno. No cuentan en Finanzas.',
        'Elimínalas. Si alguna es útil, ábrela y apunta a un alumno.', $deleteAll) ?>
    <?php if (empty($bk['vacias'])): ?>
    <?= $allGood('No hay clases vacías.') ?>
    <?php else: ?>
    <div class="table-responsive">
        <table style="width:100%;border-collapse:collapse;font-size:13px" data-fin-list="rev-vacias" data-fin-order='[[0,"asc"]]'>
            <thead><tr>
                <th style="<?= $th ?>">Fecha</th><th style="<?= $th ?>">Clase</th><th style="<?= $th ?>">Entrenador</th><th style="<?= $th ?>">Estado</th><th style="<?= $th ?>"></th>
            </tr></thead>
            <tbody>
            <?php foreach ($bk['vacias'] as $s): ?>
            <tr style="border-bottom:1px solid var(--border)">
                <?= $dateCell($s) ?>
                <?= $classCell($s) ?>
                <td style="<?= $td ?>"><?= $s['coach_names'] ? esc($s['coach_names']) : $noneTxt ?></td>
                <td style="<?= $td ?>;color:var(--text-muted)"><?= esc($statusLabel[$s['status']] ?? $s['status']) ?></td>
                <td style="<?= $td ?>;text-align:right">
                    <?php if (!empty($s['deletable'])): ?>
                    <form action="<?= base_url('finanzas/revision/eliminar-vacias') ?>" method="post" style="margin:0"
                          data-ru-confirm="¿Eliminar esta clase?"
                          data-ru-confirm-desc="«<?= esc($s['title'], 'attr') ?>» no tiene ningún alumno. Queda registrado en el historial de cambios."
                          data-ru-confirm-label="Sí, eliminar">
                        <?= csrf_field() ?>
                        <input type="hidden" name="ids[]" value="<?= (int) $s['id'] ?>">
                        <button type="submit" class="btn-jp btn-jp-sm" style="color:#b91c1c;border-color:#fecaca"><i class="bi bi-trash3 me-1"></i>Eliminar</button>
                    </form>
                    <?php else: ?>
                    <span style="font-size:12px;color:var(--text-muted)">Tiene archivos: <a href="<?= base_url('clases/' . (int) $s['id']) ?>">ábrela y cancélala</a></span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- ───────── BONOS Y DINERO ───────── -->

<!-- 5. Clases sin bono descontado -->
<div class="card-jp mb-3" id="sin-bono">
    <?= $head('sin-bono', 'bi-receipt', '#b91c1c', 'Clases dadas sin descontar del bono', $nDebts, 'rev_sin_bono',
        'El alumno vino a clase, pero no se le ha descontado ninguna sesión de su bono. Esa clase está dada y no cobrada.',
        'Para cada una: <strong>descuéntala de un bono</strong>, márcala como <strong>ya pagada</strong> (se cobró de otra forma) o como <strong>no cobrar</strong>. Nada se resuelve solo.',
        '<a href="' . base_url('bonos/deudas') . '" class="btn-jp btn-jp-primary btn-jp-sm" style="text-decoration:none">Revisarlas una a una</a>') ?>
    <div class="card-jp-body" style="font-size:13px">
        <div class="d-flex flex-wrap gap-4">
            <div><div style="font-size:22px;font-weight:800;color:var(--text-h)"><?= count($debts) ?></div><div style="color:var(--text-muted);font-size:12px">desde el <?= $sinceTxt ?> (cuando empezó el control de bonos)</div></div>
            <div><div style="font-size:22px;font-weight:800;color:var(--text-h)"><?= count($preControl) ?></div><div style="color:var(--text-muted);font-size:12px">de antes del <?= $sinceTxt ?></div></div>
        </div>
    </div>
</div>

<?php if ($finFull): // precios y dinero: solo con acceso total a Finanzas ?>
<!-- 6. Bonos con precio por confirmar -->
<?php
$priceAll = '';
if ($estimated) {
    ob_start(); ?>
    <form action="<?= base_url('finanzas/revision/confirmar-precios') ?>" method="post" style="margin:0"
          data-ru-confirm="¿Confirmar todos los precios?"
          data-ru-confirm-desc="Se dan por buenos los <?= count($estimated) ?> precios al precio de tarifa (el estimado que ya figura). Si alguno se pagó distinto, podrás corregirlo después desde la ficha del bono. Queda registrado."
          data-ru-confirm-label="Sí, confirmarlos todos">
        <?= csrf_field() ?>
        <button type="submit" class="btn-jp btn-jp-primary btn-jp-sm"><i class="bi bi-check2-all me-1"></i>Confirmar los <?= count($estimated) ?> al precio de tarifa</button>
    </form>
    <?php $priceAll = ob_get_clean();
}
?>
<div class="card-jp mb-3" id="precios">
    <?= $head('precios', 'bi-question-circle-fill', '#92400e', 'Bonos con el precio por confirmar', count($estimated), 'rev_precios',
        'De estos bonos no se guardó lo que pagó el alumno. Se ha puesto el precio de tarifa como <strong>estimado</strong>.',
        'Si los alumnos pagaron la tarifa normal, pulsa el botón para <strong>confirmarlos todos de golpe</strong>. Si alguno pagó otra cantidad (descuento, gratis = 0), escríbela en su fila y pulsa «Confirmar».', $priceAll) ?>
    <?php if (empty($estimated)): ?>
    <?= $allGood('Todos los precios están confirmados.') ?>
    <?php else: ?>
    <div class="table-responsive">
        <table style="width:100%;border-collapse:collapse;font-size:13px" data-fin-list="rev-precios">
            <thead><tr>
                <th style="<?= $th ?>">Alumno</th><th style="<?= $th ?>">Bono</th><th style="<?= $th ?>">Emitido</th><th style="<?= $th ?>">Precio estimado</th><th style="<?= $th ?>">Lo que pagó</th>
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
                               aria-label="Lo que pagó el alumno, en euros" required>
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

<!-- 7. Caducados con clases sin usar (informativo) -->
<div class="card-jp mb-3" id="sin-usar">
    <?= $head('sin-usar', 'bi-hourglass-bottom', '#6d28d9', 'Bonos caducados con clases sin usar (solo informativo)', count($unused), 'rev_caducados',
        'El bono caducó y al alumno le quedaban clases. Ese dinero se da por ganado.',
        '<strong>Nada, no es una tarea.</strong> Si quieres darle más tiempo al alumno, amplía la caducidad desde la ficha del bono.',
        $unusedTotal > 0 ? '<span style="font-size:13px;font-weight:700;color:var(--text-h)">' . $eur($unusedTotal) . '</span>' : '') ?>
    <?php if (empty($unused)): ?>
    <?= $allGood('No hay bonos caducados con clases sin usar.') ?>
    <?php else: ?>
    <div class="table-responsive">
        <table style="width:100%;border-collapse:collapse;font-size:13px" data-fin-list="rev-caducados" data-fin-order='[[2,"desc"]]'>
            <thead><tr>
                <th style="<?= $th ?>">Alumno</th><th style="<?= $th ?>">Bono</th><th style="<?= $th ?>">Caducó</th><th style="<?= $th ?>">Clases sin usar</th><th style="<?= $th ?>">Importe</th>
            </tr></thead>
            <tbody>
            <?php foreach ($unused as $b): ?>
            <tr style="border-bottom:1px solid var(--border)">
                <td style="<?= $td ?>;font-weight:600"><?= $b['player_name'] ? esc($b['player_name']) : '<span style="color:var(--text-muted)">Sin asignar</span>' ?></td>
                <td style="<?= $td ?>"><a href="<?= base_url('bonos/' . (int) $b['id']) ?>" class="row-link-anchor"><?= esc($b['bono_name'] ?? 'Bono') ?></a></td>
                <td style="<?= $td ?>;color:var(--text-muted);white-space:nowrap" data-order="<?= esc($b['expires_at']) ?>"><?= date('d/m/Y', strtotime($b['expires_at'])) ?></td>
                <td style="<?= $td ?>"><?= (int) $b['sessions_remaining'] ?> de <?= (int) $b['sessions_total'] ?></td>
                <td style="<?= $td ?>;white-space:nowrap"><?= $eur((int) $b['unused_cents']) ?><?php if (!empty($b['price_estimated'])): ?> <span title="Precio estimado" style="font-size:10px;font-weight:700;color:#92400e;background:#fef3c7;border-radius:6px;padding:1px 5px">est.</span><?php endif; ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>
<?php endif; // $finFull ?>

<?= $this->endSection() ?>
