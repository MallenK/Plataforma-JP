<?= $this->extend('layouts/app') ?>

<?php
use App\Services\CoachRankingService as RK;

$pageTitle    = 'Rankings de entrenadores';
$pageSubtitle = 'Actividad, asistencia, alumnos y cierre de listas — datos reales de las clases';

$coaches = $report['coaches'];
$t       = $report['totals'];
$nf      = static fn($v, int $d = 0) => $v === null ? '—' : number_format((float) $v, $d, ',', '.');
$pct     = static fn($v) => $v === null ? '—' : number_format((float) $v, 1, ',', '') . ' %';
$days    = ['', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
$mShort  = ['', 'ene', 'feb', 'mar', 'abr', 'may', 'jun', 'jul', 'ago', 'sep', 'oct', 'nov', 'dic'];

$tabs = [
    'resumen'    => ['Resumen',    'bi-trophy'],
    'actividad'  => ['Actividad',  'bi-activity'],
    'asistencia' => ['Asistencia', 'bi-person-check'],
    'alumnos'    => ['Alumnos',    'bi-people'],
    'cierre'     => ['Cierre de listas', 'bi-clipboard-check'],
    'horarios'   => ['Horarios y tendencia', 'bi-calendar-heat'],
];
// Los filtros viajan en la URL para que cada pestaña conserve periodo y sede.
$filters = array_filter([
    'p'     => $period['key'] === 'rango' ? null : $period['key'],
    'desde' => $period['key'] === 'rango' ? $period['from'] : null,
    'hasta' => $period['key'] === 'rango' ? $period['to'] : null,
    'sede'  => $locId ?: null,
    'entrenador' => $coachId ?: null,
]);
$focus     = $report['focus'] ?? null;
$focusName = '';
foreach ($coachOpts as $o) {
    if ($coachId && $o['id'] === $coachId) { $focusName = $o['name']; }
}
$url = static fn(array $extra = []) => base_url('entrenadores/rankings') . '?' . http_build_query(array_merge($filters, $extra));

$presets = ['mes' => 'Este mes', 'mes_ant' => 'Mes anterior', '30' => '30 días', '90' => '90 días', 'anio' => 'Este año', 'todo' => 'Todo'];

// Podios del Resumen (cada uno con su condición para entrar, para no premiar muestras pequeñas).
$podiums = [
    ['Más clases impartidas', 'bi-calendar-check', RK::top($coaches, 'done', 3), fn($r) => $nf($r['done']) . ' clases', null],
    ['Más horas impartidas',  'bi-clock-history',  RK::top($coaches, 'hours', 3), fn($r) => $nf($r['hours'], 1) . ' h', null],
    ['Mejor asistencia de sus alumnos', 'bi-person-check', RK::top($coaches, 'att_rate', 3, true, fn($r) => $r['done'] >= RK::MIN_CLASSES),
        fn($r) => $pct($r['att_rate']), 'Mínimo ' . RK::MIN_CLASSES . ' clases'],
    ['Más alumnos distintos', 'bi-people', RK::top($coaches, 'students', 3), fn($r) => $nf($r['students']) . ' alumnos', null],
    ['Más alumnos fieles', 'bi-heart', RK::top($coaches, 'loyal_rate', 3, true, fn($r) => $r['students'] >= RK::MIN_CLASSES),
        fn($r) => $pct($r['loyal_rate']) . ' (' . $r['loyal'] . ')', 'Con ' . RK::FIDELITY_MIN . '+ asistencias · mín. ' . RK::MIN_CLASSES . ' alumnos'],
    ['Listas cerradas a tiempo', 'bi-clipboard-check', RK::top($coaches, 'on_time_rate', 3, true, fn($r) => $r['closed'] >= RK::MIN_CLASSES),
        fn($r) => $pct($r['on_time_rate']), 'En ' . RK::ON_TIME_HOURS . ' h · mín. ' . RK::MIN_CLASSES . ' clases'],
];
$watch = RK::top($coaches, 'unclosed', 5, true, fn($r) => $r['unclosed'] > 0);
$medal = ['#d4a017', '#8d99a6', '#b8733a'];
?>

<?= $this->section('page_content') ?>

<div class="page-header">
    <a href="<?= base_url('entrenadores') ?>" class="btn-jp btn-jp-secondary"><i class="bi bi-arrow-left"></i> Entrenadores</a>
</div>

<!-- Filtros: periodo + sede -->
<form method="get" class="card-jp mb-3" style="font-size:13px">
    <div class="card-jp-body py-3 d-flex flex-wrap gap-2 align-items-end">
        <input type="hidden" name="tab" value="<?= esc($tab, 'attr') ?>">
        <div class="d-flex flex-wrap gap-1" role="group" aria-label="Periodo">
            <?php foreach ($presets as $k => $label): ?>
            <a href="<?= $url(['p' => $k, 'tab' => $tab, 'desde' => null, 'hasta' => null]) ?>"
               class="btn-jp <?= $period['key'] === (string) $k ? 'btn-jp-primary' : 'btn-jp-secondary' ?>" style="padding:6px 12px"><?= $label ?></a>
            <?php endforeach; ?>
        </div>
        <div>
            <label for="rk-desde" class="form-label" style="margin-bottom:2px">Desde</label>
            <input type="date" id="rk-desde" name="desde" class="form-control-jp" style="width:auto" value="<?= esc($period['from'] === '2000-01-01' ? '' : $period['from']) ?>">
        </div>
        <div>
            <label for="rk-hasta" class="form-label" style="margin-bottom:2px">Hasta</label>
            <input type="date" id="rk-hasta" name="hasta" class="form-control-jp" style="width:auto" value="<?= esc($period['to']) ?>">
        </div>
        <div>
            <label for="rk-sede" class="form-label" style="margin-bottom:2px">Sede</label>
            <select id="rk-sede" name="sede" class="form-control-jp" style="width:auto;min-width:160px">
                <option value="">Todas</option>
                <?php foreach ($locations as $l): ?>
                <option value="<?= (int) $l['id'] ?>" <?= (int) $locId === (int) $l['id'] ? 'selected' : '' ?>><?= esc($l['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div>
            <label for="rk-coach" class="form-label" style="margin-bottom:2px">Entrenador</label>
            <select id="rk-coach" name="entrenador" class="form-control-jp" style="width:auto;min-width:180px">
                <option value="">Todos (ranking)</option>
                <?php foreach ($coachOpts as $o): ?>
                <option value="<?= (int) $o['id'] ?>" <?= (int) $coachId === $o['id'] ? 'selected' : '' ?>><?= esc($o['name']) ?><?= $o['status'] !== 'active' ? ' (de baja)' : '' ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <button type="submit" class="btn-jp btn-jp-secondary">Aplicar</button>
        <span style="margin-left:4px;color:var(--text-muted)">Periodo: <strong style="color:var(--text-h)"><?= esc($period['label']) ?></strong></span>
    </div>
</form>

<?php if ($coachId): ?>
<div class="alert-jp info mb-3" style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
    <i class="bi bi-person-badge"></i>
    <span>Mostrando solo a <strong><?= esc($focusName) ?></strong>.</span>
    <a href="<?= base_url('entrenadores/' . $coachId) ?>" style="font-weight:600">Ver su ficha</a>
    <a href="<?= $url(['entrenador' => null]) ?>" style="margin-left:auto;font-weight:600">Ver a todos</a>
</div>
<?php endif; ?>

<nav class="calendar-view-tabs mb-3" aria-label="Secciones de rankings" style="flex-wrap:wrap;gap:4px">
    <?php foreach ($tabs as $k => [$label, $icon]): ?>
    <a href="<?= $url(['tab' => $k]) ?>" class="calendar-view-tab <?= $tab === $k ? 'active' : '' ?>" style="text-decoration:none"
       <?= $tab === $k ? 'aria-current="page"' : '' ?>><i class="bi <?= $icon ?> me-1"></i><?= $label ?></a>
    <?php endforeach; ?>
</nav>

<?php if ($t['done'] + $t['cancelled'] + $t['unclosed'] + $t['upcoming'] === 0): ?>
<div class="card-jp"><div class="card-jp-body" style="color:var(--text-muted)">
    <i class="bi bi-info-circle me-1"></i>No hay clases con entrenador en este periodo<?= $locId ? ' y sede' : '' ?>. Prueba con otro periodo.
</div></div>
<?php else: ?>

<?php // ───────────────────────── RESUMEN ───────────────────────── ?>
<?php if ($tab === 'resumen'): ?>
<div class="row g-3 mb-3">
    <?php foreach ([
        ['Clases impartidas', $nf($t['done']), 'bi-calendar-check', 'clases cerradas en el periodo'],
        ['Horas impartidas', $nf($t['hours'], 1) . ' h', 'bi-clock-history', 'suma por entrenador'],
        ['Entrenadores activos', $nf($t['coaches']), 'bi-person-workspace', 'con alguna clase en el periodo'],
        ['Asistencia media', $pct($t['att_rate']), 'bi-person-check', 'presentes sobre apuntados'],
        ['Listas a tiempo', $pct($t['on_time_rate']), 'bi-clipboard-check', 'cerradas en ' . RK::ON_TIME_HOURS . ' h'],
        ['Clases sin cerrar', $nf($t['unclosed']), 'bi-exclamation-triangle', 'ya pasadas y aún «programadas»'],
    ] as [$label, $value, $icon, $hint]): ?>
    <div class="col-6 col-md-4 col-xl-2">
        <div class="card-jp h-100"><div class="card-jp-body">
            <div style="font-size:12px;color:var(--text-muted)"><i class="bi <?= $icon ?> me-1"></i><?= $label ?></div>
            <div style="font-size:24px;font-weight:800;color:var(--text-h);line-height:1.2"><?= $value ?></div>
            <div style="font-size:11px;color:var(--text-muted)"><?= $hint ?></div>
        </div></div>
    </div>
    <?php endforeach; ?>
</div>

<?php if ($focus && $focus['row']): ?>
<?php
$fr  = $focus['row'];
$pos = $focus['position'];
// [clave, etiqueta, formato, «más es mejor» (para colorear frente a la media)]
$cmp = [
    ['done',         'Clases impartidas',        fn($v) => $nf($v),       true],
    ['hours',        'Horas impartidas',         fn($v) => $nf($v, 1) . ' h', true],
    ['students',     'Alumnos distintos',        fn($v) => $nf($v),       true],
    ['att_rate',     'Asistencia de sus alumnos', fn($v) => $pct($v),     true],
    ['loyal_rate',   'Alumnos fieles',           fn($v) => $pct($v),      true],
    ['on_time_rate', 'Listas cerradas a tiempo', fn($v) => $pct($v),      true],
    ['unclosed',     'Clases sin cerrar',        fn($v) => $nf($v),       false],
];
?>
<div class="row g-3">
    <div class="col-12 col-xl-7">
        <div class="card-jp h-100">
            <div class="card-jp-header"><span class="card-jp-title"><i class="bi bi-person-badge me-2"></i><?= esc($focusName) ?> frente al equipo</span></div>
            <div class="table-responsive">
                <table class="table-jp" style="font-size:13px">
                    <thead><tr><th>Métrica</th><th style="text-align:right">Valor</th><th style="text-align:right">Puesto</th><th style="text-align:right">Media del equipo</th></tr></thead>
                    <tbody>
                    <?php foreach ($cmp as [$key, $label, $fmt, $moreIsBetter]):
                        $m = $pos[$key] ?? ['pos' => null, 'of' => 0, 'value' => null, 'avg' => null];
                        $good = null;
                        if ($m['value'] !== null && $m['avg'] !== null) {
                            $good = $moreIsBetter ? $m['value'] >= $m['avg'] : $m['value'] <= $m['avg'];
                        }
                    ?>
                    <tr>
                        <td><?= $label ?></td>
                        <td style="text-align:right;font-weight:700;<?= $good === null ? '' : ($good ? 'color:var(--success)' : 'color:#b45309') ?>"><?= $m['value'] === null ? '—' : esc($fmt($m['value'])) ?></td>
                        <td style="text-align:right"><?= $m['pos'] !== null ? '#' . $m['pos'] . ' de ' . $m['of'] : '<span style="color:var(--text-muted)">sin base</span>' ?></td>
                        <td style="text-align:right;color:var(--text-muted)"><?= $m['avg'] === null ? '—' : esc($fmt($m['avg'])) ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-jp-body" style="font-size:12px;color:var(--text-muted)">
                Verde = mejor que la media; naranja = peor. Los porcentajes solo puntúan con al menos <?= RK::MIN_CLASSES ?> clases (o alumnos).
                En «clases sin cerrar» menos es mejor.
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-5">
        <div class="card-jp h-100">
            <div class="card-jp-header"><span class="card-jp-title"><i class="bi bi-people me-2"></i>Alumnos que más entrena</span></div>
            <?php if (!$focus['students']): ?>
            <div class="card-jp-body"><p style="margin:0;color:var(--text-muted);font-size:13px">Sin asistencias registradas en el periodo.</p></div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table-jp" style="font-size:13px">
                    <thead><tr><th>Alumno</th><th style="text-align:right">Presente</th><th style="text-align:right">Ausencias</th><th style="text-align:right">Sin justif.</th><th style="text-align:right">Última</th></tr></thead>
                    <tbody>
                    <?php foreach ($focus['students'] as $st): ?>
                    <tr>
                        <td style="font-weight:600"><?= esc($st['name']) ?></td>
                        <td style="text-align:right;font-weight:700"><?= $st['present'] ?></td>
                        <td style="text-align:right"><?= $st['absent'] ?></td>
                        <td style="text-align:right"><?= $st['unjustified'] ?></td>
                        <td style="text-align:right;color:var(--text-muted)"><?= $st['last'] ? date('d/m/Y', strtotime($st['last'])) : '—' ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div class="card-jp-body" style="font-size:12px;color:var(--text-muted)">Top 10 por asistencias en el periodo. «Ausencias» incluye justificadas y avisos.</div>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php else: ?>
<div class="row g-3">
    <?php foreach ($podiums as [$title, $icon, $rows, $fmt, $note]): ?>
    <div class="col-12 col-md-6 col-xl-4">
        <div class="card-jp h-100">
            <div class="card-jp-header"><span class="card-jp-title"><i class="bi <?= $icon ?> me-2"></i><?= $title ?></span></div>
            <div class="card-jp-body">
                <?php if (!$rows): ?>
                <p style="margin:0;color:var(--text-muted);font-size:13px">Sin datos suficientes<?= $note ? ' (' . esc($note) . ')' : '' ?>.</p>
                <?php else: ?>
                <ol style="list-style:none;margin:0;padding:0;display:grid;gap:8px">
                    <?php foreach ($rows as $i => $r): ?>
                    <li style="display:flex;align-items:center;gap:10px">
                        <i class="bi bi-trophy-fill" style="color:<?= $medal[$i] ?>;font-size:18px;flex-shrink:0" aria-label="Puesto <?= $i + 1 ?>"></i>
                        <a href="<?= base_url('entrenadores/' . (int) $r['id']) ?>" style="font-weight:600;color:var(--text-h);text-decoration:none;flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= esc($r['name']) ?></a>
                        <span style="font-weight:800"><?= esc($fmt($r)) ?></span>
                    </li>
                    <?php endforeach; ?>
                </ol>
                <?php if ($note): ?><p style="margin:10px 0 0;font-size:11px;color:var(--text-muted)"><?= esc($note) ?></p><?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php endforeach; ?>
    <div class="col-12 col-md-6 col-xl-4">
        <div class="card-jp h-100" style="border-color:#fcd34d">
            <div class="card-jp-header"><span class="card-jp-title"><i class="bi bi-exclamation-triangle me-2" style="color:#b45309"></i>A vigilar: clases sin cerrar</span></div>
            <div class="card-jp-body">
                <?php if (!$watch): ?>
                <p style="margin:0;color:var(--text-muted);font-size:13px"><i class="bi bi-check-circle me-1" style="color:var(--success)"></i>Todas las clases pasadas están cerradas o canceladas.</p>
                <?php else: ?>
                <ol style="list-style:none;margin:0;padding:0;display:grid;gap:8px">
                    <?php foreach ($watch as $r): ?>
                    <li style="display:flex;gap:10px;align-items:center">
                        <a href="<?= base_url('entrenadores/' . (int) $r['id']) ?>" style="font-weight:600;color:var(--text-h);text-decoration:none;flex:1;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= esc($r['name']) ?></a>
                        <span style="font-weight:800;color:#b45309"><?= (int) $r['unclosed'] ?></span>
                    </li>
                    <?php endforeach; ?>
                </ol>
                <p style="margin:10px 0 0;font-size:11px;color:var(--text-muted)">Clases con fecha pasada que siguen en «programada»: falta pasar lista y cerrar.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>
<?php endif; // ficha individual / podios ?>

<?php // ───────────────────────── ACTIVIDAD ───────────────────────── ?>
<?php elseif ($tab === 'actividad'): ?>
<div class="card-jp">
    <div class="card-jp-header"><span class="card-jp-title">Actividad por entrenador</span></div>
    <div class="table-responsive">
        <table class="table-jp" style="font-size:13px" data-fin-list="rk-actividad" data-fin-page="25" data-fin-order='[[1,"desc"]]'>
            <thead><tr>
                <th>Entrenador</th><th style="text-align:right">Impartidas</th><th style="text-align:right">Horas</th>
                <th style="text-align:right">Días con clase</th><th style="text-align:right">Sedes</th>
                <th style="text-align:right">% dúo</th><th style="text-align:right">Canceladas</th>
                <th style="text-align:right">Sin cerrar</th><th style="text-align:right">Próximas</th>
            </tr></thead>
            <tbody>
            <?php foreach ($coaches as $r): ?>
            <tr>
                <td><a href="<?= base_url('entrenadores/' . (int) $r['id']) ?>" style="font-weight:600;color:var(--text-h);text-decoration:none"><?= esc($r['name']) ?></a>
                    <?php if ($r['status'] !== 'active'): ?><span style="font-size:11px;color:var(--text-muted)"> · de baja</span><?php endif; ?></td>
                <td style="text-align:right;font-weight:700" data-order="<?= (int) $r['done'] ?>"><?= $nf($r['done']) ?></td>
                <td style="text-align:right" data-order="<?= $r['hours'] ?>"><?= $nf($r['hours'], 1) ?></td>
                <td style="text-align:right"><?= $nf($r['days_worked']) ?></td>
                <td style="text-align:right"><?= $nf($r['locations']) ?></td>
                <td style="text-align:right" data-order="<?= (float) $r['duo_rate'] ?>"><?= $pct($r['duo_rate']) ?></td>
                <td style="text-align:right"><?= $nf($r['cancelled']) ?></td>
                <td style="text-align:right;<?= $r['unclosed'] ? 'color:#b45309;font-weight:700' : '' ?>"><?= $nf($r['unclosed']) ?></td>
                <td style="text-align:right"><?= $nf($r['upcoming']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-jp-body" style="font-size:12px;color:var(--text-muted)">
        «Impartida» = clase cerrada. Una clase con dos entrenadores cuenta para los dos. «Próximas» = programadas desde hoy.
        «% dúo» = clases de pareja sobre sus impartidas.
    </div>
</div>

<?php // ───────────────────────── ASISTENCIA ───────────────────────── ?>
<?php elseif ($tab === 'asistencia'): ?>
<div class="card-jp">
    <div class="card-jp-header"><span class="card-jp-title">Asistencia de los alumnos, por entrenador</span></div>
    <div class="table-responsive">
        <table class="table-jp" style="font-size:13px" data-fin-list="rk-asistencia" data-fin-page="25" data-fin-order='[[6,"desc"]]'>
            <thead><tr>
                <th>Entrenador</th><th style="text-align:right">Plazas</th><th style="text-align:right">Presentes</th>
                <th style="text-align:right">Ausencia justif.</th><th style="text-align:right">Avisó ausencia</th>
                <th style="text-align:right">Sin justificar</th><th style="text-align:right">% asistencia</th>
                <th style="text-align:right">% sin justificar</th>
            </tr></thead>
            <tbody>
            <?php foreach ($coaches as $r): ?>
            <tr>
                <td><a href="<?= base_url('entrenadores/' . (int) $r['id']) ?>" style="font-weight:600;color:var(--text-h);text-decoration:none"><?= esc($r['name']) ?></a></td>
                <td style="text-align:right"><?= $nf($r['att_base']) ?></td>
                <td style="text-align:right;font-weight:700"><?= $nf($r['present']) ?></td>
                <td style="text-align:right"><?= $nf($r['absent']) ?></td>
                <td style="text-align:right"><?= $nf($r['declined']) ?></td>
                <td style="text-align:right"><?= $nf($r['unjustified']) ?></td>
                <td style="text-align:right;font-weight:700" data-order="<?= (float) $r['att_rate'] ?>"><?= $pct($r['att_rate']) ?></td>
                <td style="text-align:right" data-order="<?= (float) $r['noshow_rate'] ?>"><?= $pct($r['noshow_rate']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-jp-body" style="font-size:12px;color:var(--text-muted)">
        Solo clases cerradas. «Plazas» = presentes + ausencias + avisos + faltas sin justificar (las plazas aún sin responder no cuentan).
        Con pocas clases el porcentaje engaña: en los podios del Resumen se exigen al menos <?= RK::MIN_CLASSES ?>.
    </div>
</div>

<?php // ───────────────────────── ALUMNOS ───────────────────────── ?>
<?php elseif ($tab === 'alumnos'): ?>
<div class="card-jp">
    <div class="card-jp-header"><span class="card-jp-title">Alumnos por entrenador</span></div>
    <div class="table-responsive">
        <table class="table-jp" style="font-size:13px" data-fin-list="rk-alumnos" data-fin-page="25" data-fin-order='[[1,"desc"]]'>
            <thead><tr>
                <th>Entrenador</th><th style="text-align:right">Alumnos distintos</th><th style="text-align:right">Nuevos</th>
                <th style="text-align:right">Fieles (<?= RK::FIDELITY_MIN ?>+)</th><th style="text-align:right">% fieles</th>
                <th style="text-align:right">Clases por alumno</th>
            </tr></thead>
            <tbody>
            <?php foreach ($coaches as $r): ?>
            <tr>
                <td><a href="<?= base_url('entrenadores/' . (int) $r['id']) ?>" style="font-weight:600;color:var(--text-h);text-decoration:none"><?= esc($r['name']) ?></a></td>
                <td style="text-align:right;font-weight:700"><?= $nf($r['students']) ?></td>
                <td style="text-align:right"><?= $nf($r['new_students']) ?></td>
                <td style="text-align:right"><?= $nf($r['loyal']) ?></td>
                <td style="text-align:right" data-order="<?= (float) $r['loyal_rate'] ?>"><?= $pct($r['loyal_rate']) ?></td>
                <td style="text-align:right" data-order="<?= (float) $r['per_student'] ?>"><?= $nf($r['per_student'], 1) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-jp-body" style="font-size:12px;color:var(--text-muted)">
        Cuenta alumnos con asistencia real. «Nuevos» = su primera asistencia con ese entrenador cae en el periodo.
        «Fiel» = <?= RK::FIDELITY_MIN ?> o más asistencias con él en el periodo.
    </div>
</div>

<?php // ───────────────────────── CIERRE ───────────────────────── ?>
<?php elseif ($tab === 'cierre'): ?>
<div class="card-jp">
    <div class="card-jp-header"><span class="card-jp-title">Cierre de listas</span></div>
    <div class="table-responsive">
        <table class="table-jp" style="font-size:13px" data-fin-list="rk-cierre" data-fin-page="25" data-fin-order='[[4,"desc"]]'>
            <thead><tr>
                <th>Entrenador</th><th style="text-align:right">Impartidas</th><th style="text-align:right">Con lista pasada</th>
                <th style="text-align:right">A tiempo (<?= RK::ON_TIME_HOURS ?> h)</th><th style="text-align:right">% a tiempo</th>
                <th style="text-align:right">Demora media</th><th style="text-align:right">Sin cerrar</th>
            </tr></thead>
            <tbody>
            <?php foreach ($coaches as $r): ?>
            <tr>
                <td><a href="<?= base_url('entrenadores/' . (int) $r['id']) ?>" style="font-weight:600;color:var(--text-h);text-decoration:none"><?= esc($r['name']) ?></a></td>
                <td style="text-align:right"><?= $nf($r['done']) ?></td>
                <td style="text-align:right"><?= $nf($r['closed']) ?></td>
                <td style="text-align:right"><?= $nf($r['on_time']) ?></td>
                <td style="text-align:right;font-weight:700" data-order="<?= (float) $r['on_time_rate'] ?>"><?= $pct($r['on_time_rate']) ?></td>
                <td style="text-align:right" data-order="<?= (float) $r['avg_close_hours'] ?>"><?= $r['avg_close_hours'] === null ? '—' : $nf($r['avg_close_hours'], 1) . ' h' ?></td>
                <td style="text-align:right;<?= $r['unclosed'] ? 'color:#b45309;font-weight:700' : '' ?>"><?= $nf($r['unclosed']) ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-jp-body" style="font-size:12px;color:var(--text-muted)">
        Demora = horas entre el fin de la clase y el momento en que se pasó lista (0 si se hizo antes de acabar).
        «Sin cerrar» = clases ya pasadas que siguen en «programada».
    </div>
</div>

<?php // ───────────────────────── HORARIOS Y TENDENCIA ───────────────────────── ?>
<?php elseif ($tab === 'horarios'): ?>
<?php $h = $report['heatmap']; $tr = $report['trend']; ?>
<div class="row g-3">
    <div class="col-12">
        <div class="card-jp">
            <div class="card-jp-header"><span class="card-jp-title"><i class="bi bi-grid-3x3-gap me-2"></i>Cuándo se da clase (día y hora de inicio)</span></div>
            <div class="card-jp-body" style="overflow-x:auto">
                <?php if (!$h['hours']): ?>
                <p style="margin:0;color:var(--text-muted);font-size:13px">Sin clases cerradas en el periodo.</p>
                <?php else: ?>
                <table style="border-collapse:separate;border-spacing:3px;font-size:12px">
                    <thead><tr><th></th><?php foreach ($h['hours'] as $hr): ?><th style="font-weight:600;color:var(--text-muted);text-align:center;min-width:34px"><?= $hr ?>h</th><?php endforeach; ?></tr></thead>
                    <tbody>
                    <?php for ($d = 1; $d <= 7; $d++): ?>
                    <tr>
                        <th style="font-weight:600;padding-right:8px;white-space:nowrap"><?= $days[$d] ?></th>
                        <?php foreach ($h['hours'] as $hr): $n = $h['cells'][$d][$hr] ?? 0; $lv = RK::heatLevel($n, $h['max']); ?>
                        <td title="<?= $days[$d] ?> <?= $hr ?>:00 — <?= $n ?> clases"
                            style="text-align:center;height:30px;border-radius:5px;<?= $lv ? 'background:color-mix(in srgb,var(--accent) ' . ($lv * 22 + 8) . '%,transparent);font-weight:700;' . ($lv >= 3 ? 'color:#fff' : '') : 'background:var(--bg-secondary,#f3f4f6);color:var(--text-muted)' ?>"><?= $n ?: '' ?></td>
                        <?php endforeach; ?>
                    </tr>
                    <?php endfor; ?>
                    </tbody>
                </table>
                <p style="margin:10px 0 0;font-size:11px;color:var(--text-muted)">Cada celda = clases impartidas en ese día y hora. Más oscuro = más clases (máx. <?= (int) $h['max'] ?>).</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12">
        <div class="card-jp">
            <div class="card-jp-header"><span class="card-jp-title"><i class="bi bi-graph-up me-2"></i>Clases impartidas por mes (últimos <?= RK::TREND_MONTHS ?> meses)</span></div>
            <div class="card-jp-body" style="overflow-x:auto">
                <?php if (!$tr['rows']): ?>
                <p style="margin:0;color:var(--text-muted);font-size:13px">Sin clases cerradas en estos meses.</p>
                <?php else: ?>
                <table style="border-collapse:separate;border-spacing:3px;font-size:12px">
                    <thead><tr><th></th>
                        <?php foreach ($tr['months'] as $m): ?><th style="font-weight:600;color:var(--text-muted);text-align:center;min-width:42px"><?= $mShort[(int) substr($m, 5, 2)] ?><br><span style="font-weight:400"><?= substr($m, 2, 2) ?></span></th><?php endforeach; ?>
                        <th style="text-align:right;padding-left:8px">Total</th></tr></thead>
                    <tbody>
                    <?php foreach ($tr['rows'] as $r): ?>
                    <tr>
                        <th style="font-weight:600;padding-right:8px;white-space:nowrap"><a href="<?= base_url('entrenadores/' . (int) $r['id']) ?>" style="color:var(--text-h);text-decoration:none"><?= esc($r['name']) ?></a></th>
                        <?php foreach ($tr['months'] as $m): $n = $r['by_month'][$m] ?? 0; $lv = RK::heatLevel($n, $tr['max']); ?>
                        <td title="<?= esc($r['name']) ?> · <?= $m ?> — <?= $n ?> clases"
                            style="text-align:center;height:28px;border-radius:5px;<?= $lv ? 'background:color-mix(in srgb,var(--accent) ' . ($lv * 22 + 8) . '%,transparent);font-weight:700;' . ($lv >= 3 ? 'color:#fff' : '') : 'background:var(--bg-secondary,#f3f4f6);color:var(--text-muted)' ?>"><?= $n ?: '' ?></td>
                        <?php endforeach; ?>
                        <td style="text-align:right;font-weight:800;padding-left:8px"><?= (int) $r['total'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <p style="margin:10px 0 0;font-size:11px;color:var(--text-muted)">Esta tabla no depende del periodo elegido arriba: siempre muestra los últimos <?= RK::TREND_MONTHS ?> meses (la sede sí se respeta).</p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-12 col-lg-6">
        <div class="card-jp h-100">
            <div class="card-jp-header"><span class="card-jp-title"><i class="bi bi-geo-alt me-2"></i>Por sede</span></div>
            <div class="table-responsive">
                <table class="table-jp" style="font-size:13px">
                    <thead><tr><th>Sede</th><th style="text-align:right">Clases</th><th style="text-align:right">De ellas dúo</th></tr></thead>
                    <tbody>
                    <?php foreach ($report['locations'] as $l): ?>
                    <tr><td><?= esc($l['name']) ?></td><td style="text-align:right;font-weight:700"><?= (int) $l['n'] ?></td><td style="text-align:right"><?= (int) $l['duo'] ?></td></tr>
                    <?php endforeach; ?>
                    <?php if (!$report['locations']): ?><tr><td colspan="3" style="color:var(--text-muted)">Sin clases cerradas.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<?php endif; // hay datos ?>

<p style="margin:16px 0 0;font-size:12px;color:var(--text-muted)">
    <i class="bi bi-shield-check me-1"></i>Solo administradores. Cifras calculadas en directo desde las clases y la asistencia registradas; sin importes.
    Las cuentas de prueba excluidas en Finanzas tampoco cuentan aquí.
</p>

<script src="<?= base_url('assets/js/fin-ui.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/fin-ui.js') ?: time() ?>"></script>
<?= $this->endSection() ?>
