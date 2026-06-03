<?= $this->extend('layouts/app') ?>

<?php
$weekData   = $weekData ?? [];
$byDay      = $weekData['by_day'] ?? [];
$weekStart  = $weekData['week_start'] ?? date('Y-m-d');
$weekEnd    = $weekData['week_end']   ?? date('Y-m-d');
$weekOffset = (int)($weekData['week_offset'] ?? 0);

$dayNames = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
$today    = date('Y-m-d');

$attendanceOpts = [
    'pending'  => ['Pendiente',  '#d97706'],
    'present'  => ['Presente',   '#059669'],
    'absent'   => ['Ausente',    '#dc2626'],
    'confirmed'=> ['Confirmado', '#2563eb'],
    'declined' => ['Declinado',  '#6b7280'],
];

$statusIcons = [
    'present'  => 'check-circle-fill',
    'absent'   => 'x-circle-fill',
    'confirmed'=> 'check-circle',
    'declined' => 'dash-circle',
    'pending'  => 'hourglass-split',
];

$totalSessions = 0;
$pendingLista  = 0;
foreach ($byDay as $sessions) {
    foreach ($sessions as $s) {
        $totalSessions++;
        if (empty($s['lista_pasada_at'])) $pendingLista++;
    }
}

$weekLabel = date('d/m', strtotime($weekStart)) . ' – ' . date('d/m/Y', strtotime($weekEnd));

// Default day for day-view: today if has sessions, else first day with sessions
$defaultDay = null;
foreach ($byDay as $date => $sessions) {
    if (!empty($sessions)) {
        if ($defaultDay === null) $defaultDay = $date;
        if ($date === $today)    { $defaultDay = $today; break; }
    }
}
$defaultDay = $defaultDay ?? $today;
?>

<?= $this->section('page_content') ?>

<style>
/* ── Barra de control ────────────────────────────────────────── */
.fb            { padding:14px 18px; display:flex; flex-direction:column; gap:10px; }
.fb-row        { display:flex; align-items:center; gap:10px; flex-wrap:wrap; justify-content:space-between; }
.fb-week-nav   { display:flex; align-items:center; gap:6px; }
.fb-week-label { font-weight:700; font-size:.95rem; min-width:170px; text-align:center; }

.view-toggle { display:flex; border:1px solid var(--border-color); border-radius:8px; overflow:hidden; }
.vt-btn { padding:5px 14px; font-size:12px; font-weight:600; border:none; background:transparent; cursor:pointer; color:var(--text-muted); transition:all .12s; white-space:nowrap; }
.vt-btn.active { background:var(--accent); color:white; }
.vt-btn:hover:not(.active) { background:var(--bg-app); color:var(--text-h); }

.status-filter { display:flex; gap:6px; flex-wrap:wrap; }
.sf-btn { padding:5px 12px; font-size:12px; font-weight:600; border:1.5px solid var(--border-color); border-radius:20px; background:var(--bg-card); cursor:pointer; color:var(--text-muted); transition:all .12s; }
.sf-btn.active { border-color:var(--accent); color:var(--accent); background:var(--accent-light,#ede9fe); }
.sf-btn:hover:not(.active) { border-color:var(--accent); color:var(--accent); }

.fb-search-wrap   { position:relative; }
.fb-search-wrap i { position:absolute; left:10px; top:50%; transform:translateY(-50%); color:var(--text-muted); font-size:13px; pointer-events:none; }
.fb-search-inp    { padding:6px 12px 6px 30px; border:1px solid var(--border-color); border-radius:8px; font-size:13px; background:var(--bg-card); color:var(--text-h); width:210px; }

/* ── Pills de día ────────────────────────────────────────────── */
.day-pills { display:flex; gap:6px; flex-wrap:wrap; padding-top:10px; border-top:1px solid var(--border-color); margin-top:2px; }
.dp { padding:6px 12px 4px; border-radius:8px; border:2px solid var(--border-color); background:var(--bg-card); cursor:pointer; font-size:11px; font-weight:700; text-align:center; line-height:1.4; transition:all .12s; color:var(--text-h); min-width:52px; }
.dp:hover:not(.active) { border-color:var(--accent); color:var(--accent); }
.dp.active { background:var(--accent); border-color:var(--accent); color:white; }
.dp.is-today { border-color:var(--accent); }
.dp.is-today:not(.active) { color:var(--accent); }
.dp-sub   { font-size:9px; font-weight:500; display:block; }
.dp-today { font-size:9px; font-weight:800; display:block; }

/* ── Cabecera de día ─────────────────────────────────────────── */
.day-header { padding:12px 18px; display:flex; align-items:center; gap:10px; flex-wrap:wrap; border-bottom:1px solid var(--border-color); border-radius:var(--radius) var(--radius) 0 0; }
.dh-name { font-weight:800; font-size:1rem; }
.dh-count { font-size:11px; color:var(--text-muted); }
.dh-meta { margin-left:auto; display:flex; align-items:center; gap:8px; flex-wrap:wrap; }

/* ── Tarjetas de jugador — Opción C ──────────────────────────── */
.player-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(185px,1fr)); gap:12px; padding:16px 18px; }

.pc { border-radius:12px; overflow:hidden; display:flex; flex-direction:column; background:var(--bg-card); border:1px solid var(--border-color); transition:border-color .15s, box-shadow .15s; }
.pc:hover { box-shadow:0 2px 10px rgba(0,0,0,.09); }
.pc.att-present   { border:2px solid #059669; }
.pc.att-absent    { border:2px solid #dc2626; }
.pc.att-confirmed { border:2px solid #2563eb; }
.pc.att-declined  { border:2px solid #9ca3af; }

/* Nombre */
.pc-top  { padding:10px 12px 8px; }
.pc-name { font-weight:700; font-size:.88rem; color:var(--text-h); display:block; }
.pc-note { font-size:10px; color:#d97706; display:block; margin-top:2px; }

/* Zona de estado (color dominante) */
.pc-status { padding:14px 12px 10px; display:flex; flex-direction:column; align-items:center; gap:8px; }
.att-zone-present   { background:#d1fae5; }
.att-zone-absent    { background:#fee2e2; }
.att-zone-confirmed { background:#dbeafe; }
.att-zone-declined  { background:#f3f4f6; }
.att-zone-pending   { background:var(--bg-app); }

.pc-status-label { display:flex; align-items:center; gap:5px; font-weight:700; font-size:.82rem; }
.att-zone-present   .pc-status-label { color:#065f46; }
.att-zone-absent    .pc-status-label { color:#991b1b; }
.att-zone-confirmed .pc-status-label { color:#1e40af; }
.att-zone-declined  .pc-status-label { color:#6b7280; }
.att-zone-pending   .pc-status-label { color:#92400e; }

/* Botones P / A */
.pc-actions { display:flex; gap:5px; }
.pc-act-btn { border:2px solid; border-radius:7px; padding:3px 14px; font-size:13px; font-weight:700; cursor:pointer; transition:all .12s; background:transparent; line-height:1.5; }
.pc-act-btn.pc-present { border-color:#059669; color:#059669; }
.pc-act-btn.pc-present:hover, .pc-act-btn.pc-present.active { background:#059669; color:white; }
.pc-act-btn.pc-absent  { border-color:#dc2626; color:#dc2626; }
.pc-act-btn.pc-absent:hover,  .pc-act-btn.pc-absent.active  { background:#dc2626; color:white; }

/* Campos de ausencia */
.pc-abs { padding:8px 12px; background:#fff7ed; border-top:1px solid #fed7aa; display:flex; flex-direction:column; gap:4px; }

/* Bono footer */
.pc-bono         { padding:8px 12px; border-top:1px solid var(--border-color); display:flex; align-items:center; gap:6px; flex-wrap:wrap; margin-top:auto; }
.pc-bono-count   { font-weight:800; font-size:1rem; }
.pc-bono-count.ok  { color:#059669; }
.pc-bono-count.low { color:#dc2626; }
.pc-bono-name    { font-size:10px; color:var(--text-muted); flex:1; min-width:0; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; }
.pc-bono-ok      { font-size:10px; color:#059669; font-weight:600; margin-left:auto; }
.pc-bono-hint    { font-size:10px; color:var(--text-muted); }
.pc-bono-empty   { font-size:11px; color:#d97706; font-weight:600; }
.pc-deduct-btn   { background:#ede9fe !important; color:#5b21b6 !important; border:1px solid #c4b5fd !important; font-size:10px !important; padding:2px 8px !important; margin-left:auto; }

/* ── Responsive ──────────────────────────────────────────────── */
@media (max-width:600px) {
    .player-grid    { grid-template-columns:1fr 1fr; gap:8px; padding:10px 12px; }
    .fb-search-inp  { width:150px; }
    .fb-week-label  { min-width:130px; font-size:.85rem; }
}
</style>

<!-- Header con back button -->
<div class="page-header">
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
        <a href="/clases" class="btn-jp btn-jp-secondary btn-jp-sm">
            <i class="bi bi-arrow-left me-1"></i>Clases
        </a>
        <?php if ($pendingLista > 0): ?>
        <span style="background:#fef3c7;color:#92400e;border:1px solid #fde68a;border-radius:8px;padding:6px 14px;font-size:13px;font-weight:600">
            <i class="bi bi-exclamation-triangle-fill me-1"></i>
            <?= $pendingLista ?> sesión<?= $pendingLista !== 1 ? 'es' : '' ?> pendiente<?= $pendingLista !== 1 ? 's' : '' ?>
        </span>
        <?php elseif ($totalSessions > 0): ?>
        <span style="background:#d1fae5;color:#065f46;border:1px solid #6ee7b7;border-radius:8px;padding:6px 14px;font-size:13px;font-weight:600">
            <i class="bi bi-check-circle-fill me-1"></i>Semana completada
        </span>
        <?php endif; ?>
    </div>
</div>

<?php if ($flash = session()->getFlashdata('success')): ?>
<div class="alert-jp success mb-3"><i class="bi bi-check-circle-fill me-2"></i><?= esc($flash) ?></div>
<?php endif; ?>

<!-- ── Barra de control unificada ────────────────────────────── -->
<div class="card-jp mb-4 fb">

    <!-- Fila 1: Navegación semana + toggle vista -->
    <div class="fb-row">
        <div class="fb-week-nav">
            <a href="/pasar-lista?semana=<?= $weekOffset - 1 ?><?= $search ? '&buscar=' . urlencode($search) : '' ?>"
               class="btn-jp btn-jp-secondary btn-jp-sm" style="padding:5px 9px">
                <i class="bi bi-chevron-left"></i>
            </a>
            <span class="fb-week-label">
                <?= $weekLabel ?>
                <?php if ($weekOffset === 0): ?>
                <span style="font-size:11px;font-weight:500;color:var(--text-muted);margin-left:4px">(esta semana)</span>
                <?php endif; ?>
            </span>
            <a href="/pasar-lista?semana=<?= $weekOffset + 1 ?><?= $search ? '&buscar=' . urlencode($search) : '' ?>"
               class="btn-jp btn-jp-secondary btn-jp-sm" style="padding:5px 9px">
                <i class="bi bi-chevron-right"></i>
            </a>
            <?php if ($weekOffset !== 0): ?>
            <a href="/pasar-lista" class="btn-jp btn-jp-secondary btn-jp-sm" style="font-size:12px">Hoy</a>
            <?php endif; ?>
        </div>

        <div class="view-toggle">
            <button id="btn-view-week" class="vt-btn active" onclick="setView('week')">
                <i class="bi bi-calendar-week me-1"></i>Semana
            </button>
            <button id="btn-view-day" class="vt-btn" onclick="setView('day')">
                <i class="bi bi-calendar-day me-1"></i>Día
            </button>
        </div>
    </div>

    <!-- Fila 2: Filtro estado + búsqueda -->
    <div class="fb-row">
        <div class="status-filter" id="statusFilter">
            <button class="sf-btn active" data-filter="all">
                <i class="bi bi-list-ul me-1"></i>Todas
            </button>
            <button class="sf-btn" data-filter="pending">
                <i class="bi bi-hourglass-split me-1"></i>Pendientes
            </button>
            <button class="sf-btn" data-filter="done">
                <i class="bi bi-check2-all me-1"></i>Completadas
            </button>
        </div>

        <form method="GET" action="/pasar-lista" style="display:flex;gap:6px;align-items:center">
            <?php if ($weekOffset !== 0): ?>
            <input type="hidden" name="semana" value="<?= $weekOffset ?>">
            <?php endif; ?>
            <div class="fb-search-wrap">
                <i class="bi bi-search"></i>
                <input type="text" name="buscar" value="<?= esc($search) ?>"
                       placeholder="Buscar alumno o entrenador…" class="fb-search-inp">
            </div>
            <button type="submit" class="btn-jp btn-jp-sm">Buscar</button>
            <?php if ($search): ?>
            <a href="/pasar-lista?semana=<?= $weekOffset ?>" class="btn-jp btn-jp-secondary btn-jp-sm">
                <i class="bi bi-x-lg"></i>
            </a>
            <?php endif; ?>
        </form>
    </div>

    <!-- Fila 3: Pills de día (solo visible en vista Día) -->
    <div class="day-pills" id="dayPills" style="display:none">
        <?php $dpIdx = 0; foreach ($byDay as $date => $sessions): ?>
        <?php $dpLabel = $dayNames[$dpIdx++] ?? ''; $hasSessions = !empty($sessions); ?>
        <button class="dp <?= $date === $today ? 'is-today' : '' ?>"
                data-date="<?= $date ?>"
                onclick="showDay('<?= $date ?>')"
                <?= !$hasSessions ? 'style="opacity:.4;cursor:default"' : '' ?>>
            <?= mb_substr($dpLabel, 0, 3) ?>
            <span class="dp-sub"><?= date('d/m', strtotime($date)) ?></span>
            <?php if ($date === $today): ?><span class="dp-today">HOY</span><?php endif; ?>
        </button>
        <?php endforeach; ?>
    </div>
</div>

<!-- ── Días de la semana ──────────────────────────────────────── -->
<?php $dayIdx = 0; ?>
<?php foreach ($byDay as $date => $sessions): ?>
<?php
    $isToday   = ($date === $today);
    $dayLabel  = $dayNames[$dayIdx] ?? '';
    $dateLabel = date('d/m', strtotime($date));
    $dayIdx++;
    if (empty($sessions)) continue;
    $dayPending = array_sum(array_map(fn($s) => empty($s['lista_pasada_at']) ? 1 : 0, $sessions));
?>

<div class="card-jp mb-3 day-section"
     id="day-<?= $date ?>"
     data-date="<?= $date ?>"
     data-pending="<?= $dayPending ?>"
     style="<?= $isToday ? 'border-left:4px solid var(--accent)' : '' ?>">

    <!-- Cabecera del día -->
    <div class="day-header" style="background:<?= $isToday ? 'var(--accent-light,#ede9fe)' : 'var(--bg-app)' ?>">
        <span class="dh-name" style="color:<?= $isToday ? 'var(--accent)' : 'var(--text-h)' ?>">
            <?= $dayLabel ?> <?= $dateLabel ?>
        </span>
        <?php if ($isToday): ?>
        <span style="background:var(--accent);color:white;border-radius:5px;padding:1px 7px;font-size:10px;font-weight:800">HOY</span>
        <?php endif; ?>
        <span class="dh-count"><?= count($sessions) ?> sesión<?= count($sessions) !== 1 ? 'es' : '' ?></span>
        <div class="dh-meta">
            <?php if ($dayPending > 0): ?>
            <span style="background:#fef3c7;color:#92400e;border:1px solid #fde68a;border-radius:6px;padding:3px 10px;font-size:11px;font-weight:600">
                <i class="bi bi-hourglass-split me-1"></i><?= $dayPending ?> pendiente<?= $dayPending !== 1 ? 's' : '' ?>
            </span>
            <button type="button" class="btn-jp btn-jp-sm btn-completar-dia"
                    data-date="<?= $date ?>" data-label="<?= esc($dayLabel . ' ' . $dateLabel) ?>"
                    style="font-size:11px;background:#ede9fe;color:#5b21b6;border:1px solid #c4b5fd">
                <i class="bi bi-lightning-fill me-1"></i>Completar día
            </button>
            <?php else: ?>
            <span style="background:#d1fae5;color:#065f46;border:1px solid #6ee7b7;border-radius:6px;padding:3px 10px;font-size:11px;font-weight:600">
                <i class="bi bi-check-all me-1"></i>Completado
            </span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Sesiones del día -->
    <?php foreach ($sessions as $sIdx => $s): ?>
    <?php
        $listaPasada = !empty($s['lista_pasada_at']);
        $coachNames  = implode(', ', array_column($s['coaches'], 'name'));
    ?>
    <div class="session-block"
         data-pending="<?= $listaPasada ? '0' : '1' ?>"
         style="border-bottom:1px solid var(--border-color)">

        <!-- Toggle cabecera sesión -->
        <div onclick="toggleSession('s<?= $s['id'] ?>')"
             style="padding:11px 18px;cursor:pointer;display:flex;align-items:center;gap:10px;flex-wrap:wrap;user-select:none;transition:background .1s"
             onmouseover="this.style.background='var(--bg-app)'" onmouseout="this.style.background=''">

            <div style="flex:1;min-width:180px">
                <span style="font-weight:600;font-size:.92rem"><?= esc($s['title']) ?></span>
                <span style="color:var(--text-muted);font-size:12px;margin-left:7px">
                    <?= substr($s['start_time'], 0, 5) ?>–<?= substr($s['end_time'], 0, 5) ?>
                </span>
                <?php if ($coachNames): ?>
                <span style="color:var(--text-muted);font-size:11px;margin-left:7px">
                    <i class="bi bi-person-badge me-1"></i><?= esc($coachNames) ?>
                </span>
                <?php endif; ?>
            </div>

            <?php if ($listaPasada): ?>
            <span style="background:#d1fae5;color:#065f46;border:1px solid #6ee7b7;border-radius:6px;padding:3px 10px;font-size:11px;font-weight:600;white-space:nowrap">
                <i class="bi bi-check-circle-fill me-1"></i>Lista pasada
                <span style="font-weight:400;margin-left:3px"><?= date('d/m H:i', strtotime($s['lista_pasada_at'])) ?></span>
                <?php if (!empty($s['lista_pasada_by_name'])): ?>
                <span style="font-weight:400"> · <?= esc($s['lista_pasada_by_name']) ?></span>
                <?php endif; ?>
            </span>
            <?php else: ?>
            <span class="session-pending-badge" style="background:#fef3c7;color:#92400e;border:1px solid #fde68a;border-radius:6px;padding:3px 10px;font-size:11px;font-weight:600;white-space:nowrap">
                <i class="bi bi-hourglass-split me-1"></i>Pendiente
            </span>
            <?php endif; ?>

            <?php if (!empty($s['players'])): ?>
            <div style="display:flex;gap:6px;font-size:12px">
                <span style="color:#059669;font-weight:600"><i class="bi bi-person-check-fill me-1"></i><?= $s['player_counts']['present'] ?></span>
                <span style="color:#dc2626;font-weight:600"><i class="bi bi-person-x-fill me-1"></i><?= $s['player_counts']['absent'] ?></span>
                <span style="color:#d97706;font-weight:600"><i class="bi bi-clock-fill me-1"></i><?= $s['player_counts']['pending'] ?></span>
            </div>
            <?php endif; ?>

            <i class="bi bi-chevron-down session-chevron-<?= $s['id'] ?>" style="transition:transform .2s;font-size:13px;color:var(--text-muted)"></i>
        </div>

        <!-- Formulario asistencia (colapsable) -->
        <div id="s<?= $s['id'] ?>" style="display:none">
        <?php if (empty($s['players'])): ?>
        <div style="padding:20px 24px;color:var(--text-muted);font-size:13px">
            <i class="bi bi-people me-1"></i>Sin alumnos asignados a esta sesión.
        </div>
        <?php else: ?>
        <form action="/clases/<?= $s['id'] ?>/lista-guardar" method="POST" style="margin:0">
            <?= csrf_field() ?>
            <input type="hidden" name="semana" value="<?= $weekOffset ?>">
            <?php if ($search): ?><input type="hidden" name="buscar" value="<?= esc($search) ?>"><?php endif; ?>

            <!-- Grid de jugadores — Opción C -->
            <div class="player-grid">
            <?php foreach ($s['players'] as $p): ?>
            <?php
                $uid       = (int)$p['user_id'];
                $att       = $p['attendance'] ?? 'pending';
                $isAbsent  = ($att === 'absent');
                $isPresent = ($att === 'present');
                $deducted  = !empty($p['bono_deducted_at']);
                $formId    = 's' . $s['id'] . 'u' . $uid;
                $icon      = $statusIcons[$att] ?? 'hourglass-split';
                $bonoRem   = $p['sessions_remaining'];
            ?>
            <div id="card-<?= $formId ?>" class="pc att-<?= $att ?>">

                <!-- Nombre -->
                <div class="pc-top">
                    <span class="pc-name"><?= esc($p['name']) ?></span>
                    <?php if (!empty($p['student_note'])): ?>
                    <span class="pc-note"><i class="bi bi-chat-left-text-fill me-1"></i><?= esc($p['student_note']) ?></span>
                    <?php endif; ?>
                </div>

                <!-- Zona de estado (color dominante) -->
                <div class="pc-status att-zone-<?= $att ?>">
                    <!-- Select oculto para el form -->
                    <select name="attendance[<?= $uid ?>]"
                            class="att-sel"
                            data-formid="<?= $formId ?>"
                            data-uid="<?= $uid ?>"
                            style="display:none">
                        <?php foreach ($attendanceOpts as $val => [$lbl, $col]): ?>
                        <option value="<?= $val ?>" <?= $att === $val ? 'selected' : '' ?>><?= $lbl ?></option>
                        <?php endforeach; ?>
                    </select>

                    <!-- Etiqueta de estado -->
                    <div class="pc-status-label">
                        <i class="bi bi-<?= $icon ?>"></i>
                        <span><?= $attendanceOpts[$att][0] ?></span>
                    </div>

                    <!-- Botones rápidos ✓ / ✗ -->
                    <div class="pc-actions">
                        <button type="button"
                                class="pc-act-btn pc-present <?= $att === 'present' ? 'active' : '' ?>"
                                data-formid="<?= $formId ?>" data-val="present">
                            <i class="bi bi-check-lg"></i>
                        </button>
                        <button type="button"
                                class="pc-act-btn pc-absent <?= $att === 'absent' ? 'active' : '' ?>"
                                data-formid="<?= $formId ?>" data-val="absent">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>

                <!-- Campos de ausencia (visible solo si ausente) -->
                <div id="abs-<?= $formId ?>" class="pc-abs" style="display:<?= $isAbsent ? 'flex' : 'none' ?>;flex-direction:column;gap:4px">
                    <select name="absence_reason[<?= $uid ?>]" class="form-select-jp" style="font-size:11px;width:100%">
                        <option value="">— Motivo —</option>
                        <?php foreach ($absenceReasons as $r): ?>
                        <option value="<?= esc($r) ?>" <?= ($p['absence_reason'] ?? '') === $r ? 'selected' : '' ?>><?= esc($r) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text"
                           name="absence_notes[<?= $uid ?>]"
                           class="form-control-jp"
                           placeholder="Nota opcional…"
                           value="<?= esc($p['absence_notes'] ?? '') ?>"
                           style="font-size:11px;width:100%">
                </div>

                <!-- Bono footer -->
                <div class="pc-bono">
                    <?php if ($bonoRem !== null): ?>
                    <span class="bono-rem-<?= $formId ?> pc-bono-count <?= $bonoRem <= 1 ? 'low' : 'ok' ?>">
                        <?= $bonoRem ?>
                    </span>
                    <span class="pc-bono-name"><?= esc($p['bono_name'] ?? '') ?></span>
                    <?php if ($deducted): ?>
                    <span class="pc-bono-ok">
                        <i class="bi bi-check-circle-fill"></i> Descontado
                    </span>
                    <?php elseif ($isPresent): ?>
                    <button type="button"
                            class="btn-jp btn-jp-sm btn-deduct-week pc-deduct-btn"
                            data-session="<?= $s['id'] ?>"
                            data-player="<?= $uid ?>"
                            data-formid="<?= $formId ?>">
                        <i class="bi bi-dash-circle-fill me-1"></i>Descontar
                    </button>
                    <?php else: ?>
                    <span class="bono-hint-<?= $formId ?> pc-bono-hint">Marcar presente</span>
                    <?php endif; ?>
                    <?php else: ?>
                    <span class="pc-bono-empty" title="Este jugador no tiene bono activo">
                        <i class="bi bi-exclamation-triangle-fill me-1" style="color:#d97706"></i>Sin bono
                    </span>
                    <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            </div>

            <div style="padding:10px 18px;display:flex;justify-content:flex-end;border-top:1px solid var(--border-color)">
                <button type="submit" class="btn-jp btn-jp-sm" style="background:#ede9fe;color:#5b21b6;border:1px solid #c4b5fd">
                    <i class="bi bi-clipboard2-check-fill me-1"></i>Guardar y marcar lista pasada
                </button>
            </div>
        </form>
        <?php endif; ?>
        </div>
    </div>
    <?php endforeach; ?>
</div>

<?php endforeach; ?>

<?php if ($totalSessions === 0): ?>
<div class="card-jp" style="padding:40px;text-align:center;color:var(--text-muted)">
    <i class="bi bi-calendar-x" style="font-size:2.5rem;display:block;margin-bottom:12px"></i>
    <?php if ($search): ?>
    No se encontraron clases con "<?= esc($search) ?>" esta semana.
    <?php else: ?>
    No hay clases esta semana.
    <?php endif; ?>
</div>
<?php endif; ?>

<script>
(function () {
    var CSRF_NAME   = '<?= csrf_token() ?>';
    var CSRF_HASH   = '<?= csrf_hash() ?>';
    var DEFAULT_DAY = '<?= $defaultDay ?>';

    // ── Toggle sesión ────────────────────────────────────────────
    window.toggleSession = function(id) {
        var el      = document.getElementById(id);
        var chevron = document.querySelector('.session-chevron-' + id.replace('s',''));
        if (!el) return;
        var open = el.style.display !== 'none';
        el.style.display = open ? 'none' : '';
        if (chevron) chevron.style.transform = open ? '' : 'rotate(180deg)';
    };

    // Auto-abrir sesiones pendientes al cargar
    document.querySelectorAll('.session-block').forEach(function(block) {
        if (block.querySelector('.session-pending-badge')) {
            var toggle = block.querySelector('[onclick^="toggleSession"]');
            if (toggle) {
                var match = toggle.getAttribute('onclick').match(/'([^']+)'/);
                if (match) window.toggleSession(match[1]);
            }
        }
    });

    // ── Vista Semana / Día ───────────────────────────────────────
    var currentView = 'week';
    var currentDay  = DEFAULT_DAY;

    window.setView = function(mode) {
        currentView = mode;
        document.getElementById('btn-view-week').classList.toggle('active', mode === 'week');
        document.getElementById('btn-view-day').classList.toggle('active',  mode === 'day');
        document.getElementById('dayPills').style.display = mode === 'day' ? 'flex' : 'none';

        if (mode === 'week') {
            document.querySelectorAll('.day-section').forEach(function(d) { d.style.display = ''; });
            applyStatusFilter();
        } else {
            window.showDay(currentDay);
        }
    };

    window.showDay = function(date) {
        currentDay = date;
        document.querySelectorAll('.day-section').forEach(function(d) {
            d.style.display = d.dataset.date === date ? '' : 'none';
        });
        // Auto-expandir todas las sesiones del día seleccionado
        var dayEl = document.querySelector('.day-section[data-date="' + date + '"]');
        if (dayEl) {
            dayEl.querySelectorAll('.session-block').forEach(function(block) {
                var toggle = block.querySelector('[onclick^="toggleSession"]');
                if (toggle) {
                    var match = toggle.getAttribute('onclick').match(/'([^']+)'/);
                    if (match) {
                        var el = document.getElementById(match[1]);
                        if (el && el.style.display === 'none') window.toggleSession(match[1]);
                    }
                }
            });
        }
        // Actualizar pill activa
        document.querySelectorAll('.dp').forEach(function(p) {
            p.classList.toggle('active', p.dataset.date === date);
        });
    };

    // ── Filtro de estado ─────────────────────────────────────────
    function applyStatusFilter() {
        var active = document.querySelector('.sf-btn.active');
        var filter = active ? active.dataset.filter : 'all';

        document.querySelectorAll('.day-section').forEach(function(day) {
            if (currentView === 'day' && day.dataset.date !== currentDay) return;
            day.querySelectorAll('.session-block').forEach(function(block) {
                var isPending = block.dataset.pending === '1';
                var show = filter === 'all'
                    || (filter === 'pending' && isPending)
                    || (filter === 'done'    && !isPending);
                block.style.display = show ? '' : 'none';
            });
        });
    }

    document.querySelectorAll('.sf-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.sf-btn').forEach(function(b) { b.classList.remove('active'); });
            this.classList.add('active');
            applyStatusFilter();
        });
    });

    // ── Botones rápidos P / A en tarjeta ─────────────────────────
    var statusConfig = {
        present:   ['Presente',   'check-circle-fill'],
        absent:    ['Ausente',    'x-circle-fill'],
        pending:   ['Pendiente',  'hourglass-split'],
        confirmed: ['Confirmado', 'check-circle'],
        declined:  ['Declinado',  'dash-circle'],
    };

    document.querySelectorAll('.pc-act-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var fid  = this.dataset.formid;
            var val  = this.dataset.val;
            var card = document.getElementById('card-' + fid);
            if (!card) return;

            // Actualizar select oculto
            var sel = card.querySelector('.att-sel');
            if (sel) sel.value = val;

            // Color de zona de estado
            var zone = card.querySelector('.pc-status');
            if (zone) zone.className = 'pc-status att-zone-' + val;

            // Etiqueta + icono
            var labelEl = card.querySelector('.pc-status-label span');
            var iconEl  = card.querySelector('.pc-status-label i');
            if (labelEl && statusConfig[val]) labelEl.textContent = statusConfig[val][0];
            if (iconEl  && statusConfig[val]) iconEl.className = 'bi bi-' + statusConfig[val][1];

            // Border de tarjeta
            card.className = card.className.replace(/att-\w+/, 'att-' + val);

            // Botón activo
            card.querySelectorAll('.pc-act-btn').forEach(function(b) { b.classList.remove('active'); });
            this.classList.add('active');

            // Campos ausencia
            var absEl = document.getElementById('abs-' + fid);
            if (absEl) absEl.style.display = (val === 'absent') ? 'flex' : 'none';

            // Bono: mostrar/ocultar
            var deductBtn = card.querySelector('.btn-deduct-week');
            var hint      = card.querySelector('.pc-bono-hint');
            if (deductBtn) deductBtn.style.display = (val === 'present') ? '' : 'none';
            if (hint)      hint.style.display      = (val === 'present') ? 'none' : '';
        });
    });

    // Ocultar botón descontar si no está presente al cargar
    document.querySelectorAll('.btn-deduct-week').forEach(function(btn) {
        var fid = btn.dataset.formid;
        var sel = document.querySelector('.att-sel[data-formid="' + fid + '"]');
        if (sel && sel.value !== 'present') btn.style.display = 'none';
    });

    // ── Descontar bono AJAX ──────────────────────────────────────
    document.querySelectorAll('.btn-deduct-week').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var sessionId = this.dataset.session;
            var playerId  = this.dataset.player;
            var fid       = this.dataset.formid;
            var self      = this;

            if (!confirm('¿Descontar 1 sesión del bono de este jugador?')) return;
            self.disabled = true;
            self.textContent = '…';

            fetch('/clases/' + sessionId + '/jugadores/' + playerId + '/descontar-bono', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                body: JSON.stringify({ [CSRF_NAME]: CSRF_HASH })
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    var remEl = document.querySelector('.bono-rem-' + fid);
                    if (remEl) {
                        remEl.textContent = data.sessions_remaining;
                        remEl.classList.remove('ok', 'low');
                        remEl.classList.add(data.sessions_remaining <= 1 ? 'low' : 'ok');
                    }
                    self.outerHTML = '<span class="pc-bono-ok"><i class="bi bi-check-circle-fill"></i> Descontado</span>';
                } else {
                    showAlert(data.error || 'Error.');
                    self.disabled = false;
                    self.innerHTML = '<i class="bi bi-dash-circle-fill me-1"></i>Descontar';
                }
            })
            .catch(function() {
                showAlert('Error de red.');
                self.disabled = false;
                self.innerHTML = '<i class="bi bi-dash-circle-fill me-1"></i>Descontar';
            });
        });
    });

    // ── Completar día rápido ─────────────────────────────────────
    document.querySelectorAll('.btn-completar-dia').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var date  = this.dataset.date;
            var label = this.dataset.label;
            var self  = this;

            if (!confirm('¿Marcar todos los alumnos como Presente y completar todas las sesiones pendientes de ' + label + '?\n\nEsta acción no descuenta bonos.')) return;
            self.disabled = true;
            self.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Guardando…';

            var body = new URLSearchParams();
            body.append('date', date);
            body.append(CSRF_NAME, CSRF_HASH);

            fetch('/pasar-lista/completar-dia', {
                method: 'POST',
                headers: { 'Content-Type': 'application/x-www-form-urlencoded', 'X-Requested-With': 'XMLHttpRequest' },
                body: body.toString()
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    window.location.reload();
                } else {
                    showAlert(data.error || 'Error al completar el día.');
                    self.disabled = false;
                    self.innerHTML = '<i class="bi bi-lightning-fill me-1"></i>Completar día';
                }
            })
            .catch(function() {
                showAlert('Error de red.');
                self.disabled = false;
                self.innerHTML = '<i class="bi bi-lightning-fill me-1"></i>Completar día';
            });
        });
    });
})();
</script>

<?= $this->endSection() ?>
