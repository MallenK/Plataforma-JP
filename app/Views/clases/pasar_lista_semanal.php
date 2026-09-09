<?= $this->extend('layouts/app') ?>

<?php
helper('attendance');

// Una sesión está "por pasar" si aún no tiene lista y no está cerrada.
$plSessionPending = fn(array $s): bool =>
    empty($s['lista_pasada_at']) && ($s['status'] ?? '') !== 'completed';

$weekData   = $weekData ?? [];
$byDay      = $weekData['by_day'] ?? [];
$weekStart  = $weekData['week_start'] ?? date('Y-m-d');
$weekEnd    = $weekData['week_end']   ?? date('Y-m-d');
$weekOffset = (int)($weekData['week_offset'] ?? 0);
$search     = $search ?? '';

$dayNames = ['Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
$today    = date('Y-m-d');

$totalSessions = 0;
$pendingLista  = 0;
foreach ($byDay as $sessions) {
    foreach ($sessions as $s) {
        $totalSessions++;
        if ($plSessionPending($s)) $pendingLista++;
    }
}

$weekLabel = date('d/m', strtotime($weekStart)) . ' – ' . date('d/m/Y', strtotime($weekEnd));

$defaultDay = null;
foreach ($byDay as $date => $sessions) {
    if (!empty($sessions)) {
        if ($defaultDay === null) $defaultDay = $date;
        if ($date === $today)    { $defaultDay = $today; break; }
    }
}
$defaultDay = $defaultDay ?? $today;

$qs = fn(int $off) => '/pasar-lista?semana=' . $off . ($search ? '&buscar=' . urlencode($search) : '');
?>

<?= $this->section('page_content') ?>

<link rel="stylesheet" href="<?= base_url('assets/css/pasar-lista.css') ?>?v=<?= @filemtime(FCPATH . 'assets/css/pasar-lista.css') ?: time() ?>">

<div class="pl-wrap">

    <a href="/clases" class="pl-crumb"><i class="bi bi-arrow-left"></i>Clases</a>

    <div class="pl-head">
        <div>
            <h2>Pasar lista</h2>
            <p class="pl-sub">
                Semana <?= esc($weekLabel) ?><?= $weekOffset === 0 ? ' · esta semana' : '' ?>
            </p>
        </div>
        <div class="pl-head-aside">
            <?php if ($pendingLista > 0): ?>
            <span class="pl-tag is-pending">
                <i class="bi bi-hourglass-split"></i>
                <?= $pendingLista ?> sesión<?= $pendingLista !== 1 ? 'es' : '' ?> por pasar
            </span>
            <?php elseif ($totalSessions > 0): ?>
            <span class="pl-tag is-done"><i class="bi bi-check-circle-fill"></i>Semana al día</span>
            <?php endif; ?>

            <details class="pl-help">
                <summary>
                    <i class="bi bi-question-circle"></i>Estados
                    <i class="bi bi-chevron-down pl-help-chev"></i>
                </summary>
                <div class="pl-help-body">
                    <p>Despliega una sesión para ver el detalle, o entra en ella para registrar la asistencia.</p>
                    <ul>
                        <li><b>Por pasar</b> — aún sin registrar.</li>
                        <li><b>Lista pasada · sin cerrar</b> — asistencia guardada, falta cerrar la sesión.</li>
                        <li><b>Cerrada</b> — finalizada (se puede reabrir).</li>
                    </ul>
                </div>
            </details>
        </div>
    </div>

    <?php if ($flash = session()->getFlashdata('success')): ?>
    <div class="alert-jp success mb-3"><i class="bi bi-check-circle-fill me-2"></i><?= esc($flash) ?></div>
    <?php endif; ?>

    <!-- ── Barra de control ──────────────────────────────────── -->
    <div class="pl-toolbar">
        <div class="pl-toolbar-row">
            <div class="pl-weeknav">
                <a href="<?= $qs($weekOffset - 1) ?>" class="btn-jp btn-jp-secondary btn-jp-sm" style="padding:5px 9px" aria-label="Semana anterior">
                    <i class="bi bi-chevron-left"></i>
                </a>
                <span class="pl-weeklabel">
                    <?= esc($weekLabel) ?>
                    <?php if ($weekOffset === 0): ?><small>(esta semana)</small><?php endif; ?>
                </span>
                <a href="<?= $qs($weekOffset + 1) ?>" class="btn-jp btn-jp-secondary btn-jp-sm" style="padding:5px 9px" aria-label="Semana siguiente">
                    <i class="bi bi-chevron-right"></i>
                </a>
                <?php if ($weekOffset !== 0): ?>
                <a href="/pasar-lista" class="btn-jp btn-jp-secondary btn-jp-sm">Hoy</a>
                <?php endif; ?>
            </div>

            <div class="pl-toggle" role="tablist" aria-label="Vista">
                <button id="btn-view-week" class="is-active" onclick="setView('week')">
                    <i class="bi bi-calendar-week me-1"></i>Semana
                </button>
                <button id="btn-view-day" onclick="setView('day')">
                    <i class="bi bi-calendar-day me-1"></i>Día
                </button>
            </div>
        </div>

        <div class="pl-toolbar-row">
            <div class="pl-filter" id="statusFilter">
                <button class="pl-chip is-active" data-filter="all"><i class="bi bi-list-ul me-1"></i>Todas</button>
                <button class="pl-chip" data-filter="pending"><i class="bi bi-hourglass-split me-1"></i>Por pasar</button>
                <button class="pl-chip" data-filter="done"><i class="bi bi-check2-all me-1"></i>Pasadas</button>
            </div>

            <form method="GET" action="/pasar-lista" class="pl-search-form" style="display:flex;gap:6px;align-items:center">
                <?php if ($weekOffset !== 0): ?><input type="hidden" name="semana" value="<?= $weekOffset ?>"><?php endif; ?>
                <label class="pl-search">
                    <i class="bi bi-search"></i>
                    <input type="text" name="buscar" value="<?= esc($search) ?>" placeholder="Buscar alumno o entrenador…">
                </label>
                <button type="submit" class="btn-jp btn-jp-sm">Buscar</button>
                <?php if ($search): ?>
                <a href="/pasar-lista?semana=<?= $weekOffset ?>" class="btn-jp btn-jp-secondary btn-jp-sm" aria-label="Quitar búsqueda"><i class="bi bi-x-lg"></i></a>
                <?php endif; ?>
            </form>
        </div>

        <div class="pl-daypills" id="dayPills" hidden>
            <?php $dpIdx = 0; foreach ($byDay as $date => $sessions): ?>
            <?php $dpLabel = $dayNames[$dpIdx++] ?? ''; $hasSessions = !empty($sessions); ?>
            <button class="pl-daypill <?= $date === $today ? 'is-today' : '' ?>"
                    data-date="<?= $date ?>" onclick="showDay('<?= $date ?>')"
                    <?= !$hasSessions ? 'disabled' : '' ?>>
                <?= mb_substr($dpLabel, 0, 3) ?>
                <span><?= date('d/m', strtotime($date)) ?></span>
            </button>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- ── Días ──────────────────────────────────────────────── -->
    <?php $dayIdx = 0; ?>
    <?php foreach ($byDay as $date => $sessions): ?>
    <?php
        $isToday   = ($date === $today);
        $dayLabel  = $dayNames[$dayIdx] ?? '';
        $dateLabel = date('d/m', strtotime($date));
        $dayIdx++;
        if (empty($sessions)) continue;
        $dayPending = array_sum(array_map(fn($s) => $plSessionPending($s) ? 1 : 0, $sessions));
    ?>
    <section class="pl-day-section <?= $isToday ? 'is-today' : '' ?>" data-date="<?= $date ?>" data-pending="<?= $dayPending ?>">
        <div class="pl-day <?= $isToday ? 'is-today' : '' ?>">
            <div class="pl-day-title">
                <h3><?= esc($dayLabel) ?> <?= $dateLabel ?></h3>
                <?php if ($isToday): ?><span class="pl-tag is-today">HOY</span><?php endif; ?>
                <span class="pl-day-count"><?= count($sessions) ?> sesión<?= count($sessions) !== 1 ? 'es' : '' ?></span>
                <span class="pl-day-meta">
                    <?php if ($dayPending > 0): ?>
                    <span class="pl-tag is-pending"><i class="bi bi-hourglass-split"></i><?= $dayPending ?> por pasar</span>
                    <?php else: ?>
                    <span class="pl-tag is-done"><i class="bi bi-check-all"></i>Al día</span>
                    <?php endif; ?>
                </span>
            </div>
        </div>

        <?php foreach ($sessions as $s): ?>
        <?php
            $listaPasada = !empty($s['lista_pasada_at']);
            $sessionDone = !$plSessionPending($s);   // lista pasada o sesión cerrada
            $coachNames  = implode(', ', array_column($s['coaches'] ?? [], 'name'));
            $pc          = $s['player_counts'] ?? ['present' => 0, 'absent' => 0, 'unjustified' => 0, 'pending' => 0];
            // Colapsadas por defecto: la vista semanal es un listado desplegable.
            $openInit    = '0';
        ?>
        <article class="pl-session" data-open="<?= $openInit ?>" data-pending="<?= $sessionDone ? '0' : '1' ?>">
            <div class="pl-session-head" onclick="toggleSession(this)" role="button" tabindex="0" aria-expanded="<?= $openInit === '1' ? 'true' : 'false' ?>">
                <div class="pl-session-title">
                    <strong><?= esc($s['title']) ?></strong>
                    <span class="pl-meta">
                        <i class="bi bi-clock"></i><?= substr($s['start_time'], 0, 5) ?>–<?= substr($s['end_time'], 0, 5) ?>
                        <?php if ($coachNames): ?>
                        &nbsp;·&nbsp;<i class="bi bi-person-badge"></i><?= esc($coachNames) ?>
                        <?php endif; ?>
                    </span>
                </div>

                <?php if (($s['status'] ?? '') === 'completed'): ?>
                <span class="pl-tag is-done">
                    <i class="bi bi-lock-fill"></i>Cerrada
                    <?php if (!empty($s['lista_pasada_at'])): ?>
                    <span style="font-weight:500">· <?= date('d/m H:i', strtotime($s['lista_pasada_at'])) ?><?= !empty($s['lista_pasada_by_name']) ? ' · ' . esc($s['lista_pasada_by_name']) : '' ?></span>
                    <?php endif; ?>
                </span>
                <?php elseif ($listaPasada): ?>
                <span class="pl-tag is-done" style="background:#fef9c3;color:#854d0e">
                    <i class="bi bi-clipboard2-check-fill"></i>Lista pasada · sin cerrar
                </span>
                <?php else: ?>
                <span class="pl-tag is-pending"><i class="bi bi-hourglass-split"></i>Por pasar</span>
                <?php endif; ?>

                <?php if (!empty($s['players'])): ?>
                <span class="pl-session-counts">
                    <span class="pl-c-present" title="Presentes"><i class="bi bi-person-check-fill"></i><?= (int)($pc['present'] ?? 0) ?></span>
                    <span class="pl-c-absent" title="Ausentes / no justificadas"><i class="bi bi-person-x-fill"></i><?= (int)($pc['absent'] ?? 0) + (int)($pc['unjustified'] ?? 0) ?></span>
                    <span class="pl-c-pending" title="Pendientes"><i class="bi bi-hourglass-split"></i><?= (int)($pc['pending'] ?? 0) ?></span>
                </span>
                <?php endif; ?>

                <i class="bi bi-chevron-down pl-chevron"></i>
            </div>

            <div class="pl-session-body">
                <?php if (empty($s['players'])): ?>
                <div class="pl-empty"><i class="bi bi-people me-1"></i>Sin alumnos asignados a esta sesión.</div>
                <?php else: ?>
                <div class="pl-roster">
                    <?php foreach ($s['players'] as $p): ?>
                    <?php $att = $p['attendance'] ?? 'pending'; $m = attendance_meta($att); ?>
                    <div class="pl-player">
                        <div>
                            <span class="pl-player-name"><?= esc($p['name']) ?></span>
                            <?php if (!empty($p['student_note'])): ?>
                            <span class="pl-player-note"><i class="bi bi-chat-left-text-fill me-1"></i><?= esc($p['student_note']) ?></span>
                            <?php endif; ?>
                        </div>
                        <span class="pl-badge" style="background:<?= $m['bg'] ?>;color:<?= $m['fg'] ?>">
                            <i class="bi bi-<?= $m['icon'] ?>"></i><?= esc($m['label']) ?>
                        </span>
                        <?php if (in_array($att, ['absent', 'unjustified'], true) && !empty($p['absence_reason'])): ?>
                        <span class="pl-player-reason"><?= esc($p['absence_reason']) ?></span>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="pl-session-foot">
                    <a href="/clases/<?= $s['id'] ?>/lista" class="btn-jp btn-jp-sm btn-jp-primary">
                        <i class="bi bi-clipboard2-check-fill me-1"></i><?= $sessionDone ? 'Revisar asistencia' : 'Pasar lista' ?>
                    </a>
                </div>
                <?php endif; ?>
            </div>
        </article>
        <?php endforeach; ?>
    </section>
    <?php endforeach; ?>

    <?php if ($totalSessions === 0): ?>
    <div class="pl-session" style="padding:40px;text-align:center;color:var(--text-muted)">
        <i class="bi bi-calendar-x" style="font-size:2.2rem;display:block;margin-bottom:12px"></i>
        <?= $search ? 'No se encontraron clases con "' . esc($search) . '" esta semana.' : 'No hay clases esta semana.' ?>
    </div>
    <?php endif; ?>

</div>

<script>
// Ayuda desplegable: cerrar al pulsar fuera o con Escape.
(function () {
    var help = document.querySelector('details.pl-help');
    if (!help) return;
    document.addEventListener('click', function(e) {
        if (help.open && !help.contains(e.target)) help.open = false;
    });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape' && help.open) help.open = false;
    });
})();

(function () {
    var DEFAULT_DAY = '<?= $defaultDay ?>';

    window.toggleSession = function(head) {
        var card = head.closest('.pl-session');
        if (!card) return;
        var open = card.dataset.open === '1';
        card.dataset.open = open ? '0' : '1';
        head.setAttribute('aria-expanded', open ? 'false' : 'true');
    };

    document.querySelectorAll('.pl-session-head').forEach(function(h) {
        h.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); window.toggleSession(h); }
        });
    });

    var currentView = 'week';
    var currentDay  = DEFAULT_DAY;

    window.setView = function(mode) {
        currentView = mode;
        document.getElementById('btn-view-week').classList.toggle('is-active', mode === 'week');
        document.getElementById('btn-view-day').classList.toggle('is-active',  mode === 'day');
        document.getElementById('dayPills').hidden = mode !== 'day';

        if (mode === 'week') {
            document.querySelectorAll('.pl-day-section').forEach(function(d) { d.hidden = false; });
            applyStatusFilter();
        } else {
            window.showDay(currentDay);
        }
    };

    window.showDay = function(date) {
        currentDay = date;
        document.querySelectorAll('.pl-day-section').forEach(function(d) {
            d.hidden = d.dataset.date !== date;
        });
        document.querySelectorAll('.pl-daypill').forEach(function(p) {
            p.classList.toggle('is-active', p.dataset.date === date);
        });
        applyStatusFilter();
    };

    function applyStatusFilter() {
        var active = document.querySelector('.pl-chip.is-active');
        var filter = active ? active.dataset.filter : 'all';
        document.querySelectorAll('.pl-day-section').forEach(function(day) {
            if (currentView === 'day' && day.dataset.date !== currentDay) return;
            var visible = 0;
            day.querySelectorAll('.pl-session').forEach(function(card) {
                var isPending = card.dataset.pending === '1';
                var show = filter === 'all'
                    || (filter === 'pending' && isPending)
                    || (filter === 'done'    && !isPending);
                card.hidden = !show;
                if (show) visible++;
            });
            if (currentView === 'week') day.hidden = visible === 0;
        });
    }

    document.querySelectorAll('.pl-chip').forEach(function(btn) {
        btn.addEventListener('click', function() {
            document.querySelectorAll('.pl-chip').forEach(function(b) { b.classList.remove('is-active'); });
            this.classList.add('is-active');
            applyStatusFilter();
        });
    });
})();
</script>

<?= $this->endSection() ?>
