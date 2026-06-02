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

$attBorderColors = [
    'present'  => '#059669',
    'absent'   => '#dc2626',
    'confirmed'=> '#2563eb',
    'declined' => '#9ca3af',
    'pending'  => 'var(--border-color)',
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
?>

<?= $this->section('page_content') ?>

<div class="page-header">
    <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap">
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

<!-- ── Barra de navegación de semana + buscador ──────────────── -->
<div class="card-jp mb-4" style="padding:16px 20px">
    <div style="display:flex;align-items:center;gap:12px;flex-wrap:wrap;justify-content:space-between">
        <div style="display:flex;align-items:center;gap:8px">
            <a href="/pasar-lista?semana=<?= $weekOffset - 1 ?><?= $search ? '&buscar=' . urlencode($search) : '' ?>"
               class="btn-jp btn-jp-secondary btn-jp-sm" style="padding:6px 10px">
                <i class="bi bi-chevron-left"></i>
            </a>
            <span style="font-weight:700;font-size:1rem;min-width:180px;text-align:center">
                <?= $weekLabel ?>
                <?php if ($weekOffset === 0): ?>
                <span style="font-size:11px;font-weight:500;color:var(--text-muted);margin-left:4px">(esta semana)</span>
                <?php endif; ?>
            </span>
            <a href="/pasar-lista?semana=<?= $weekOffset + 1 ?><?= $search ? '&buscar=' . urlencode($search) : '' ?>"
               class="btn-jp btn-jp-secondary btn-jp-sm" style="padding:6px 10px">
                <i class="bi bi-chevron-right"></i>
            </a>
            <?php if ($weekOffset !== 0): ?>
            <a href="/pasar-lista" class="btn-jp btn-jp-secondary btn-jp-sm" style="font-size:12px">
                Hoy
            </a>
            <?php endif; ?>
        </div>

        <form method="GET" action="/pasar-lista" style="display:flex;gap:8px;align-items:center">
            <?php if ($weekOffset !== 0): ?>
            <input type="hidden" name="semana" value="<?= $weekOffset ?>">
            <?php endif; ?>
            <div style="position:relative">
                <i class="bi bi-search" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:13px"></i>
                <input type="text"
                       name="buscar"
                       value="<?= esc($search) ?>"
                       placeholder="Buscar alumno o entrenador…"
                       style="padding:7px 12px 7px 32px;border:1px solid var(--border-color);border-radius:8px;font-size:13px;background:var(--bg-card);color:var(--text-h);width:240px">
            </div>
            <button type="submit" class="btn-jp btn-jp-sm">Buscar</button>
            <?php if ($search): ?>
            <a href="/pasar-lista?semana=<?= $weekOffset ?>" class="btn-jp btn-jp-secondary btn-jp-sm">
                <i class="bi bi-x-lg"></i>
            </a>
            <?php endif; ?>
        </form>
    </div>
</div>

<!-- ── Días de la semana ──────────────────────────────────────── -->
<?php $dayIdx = 0; ?>
<?php foreach ($byDay as $date => $sessions): ?>
<?php
    $isToday   = ($date === $today);
    $isPast    = ($date < $today);
    $dayLabel  = $dayNames[$dayIdx] ?? '';
    $dateLabel = date('d/m', strtotime($date));
    $dayIdx++;

    if (empty($sessions)) continue;

    $dayPending = array_sum(array_map(fn($s) => empty($s['lista_pasada_at']) ? 1 : 0, $sessions));
?>

<div class="card-jp mb-3" id="day-<?= $date ?>" style="<?= $isToday ? 'border-left:4px solid var(--accent)' : '' ?>">
    <!-- Cabecera del día -->
    <div style="padding:14px 20px;display:flex;align-items:center;gap:12px;flex-wrap:wrap;border-bottom:1px solid var(--border-color);background:<?= $isToday ? 'var(--accent-light)' : 'var(--bg-app)' ?>;border-radius:var(--radius) var(--radius) 0 0">
        <div>
            <span style="font-weight:800;font-size:1.05rem;color:<?= $isToday ? 'var(--accent)' : 'var(--text-h)' ?>">
                <?= $dayLabel ?> <?= $dateLabel ?>
            </span>
            <?php if ($isToday): ?>
            <span style="font-size:11px;font-weight:600;color:var(--accent);margin-left:6px">HOY</span>
            <?php endif; ?>
        </div>
        <span style="font-size:12px;color:var(--text-muted)"><?= count($sessions) ?> sesión<?= count($sessions) !== 1 ? 'es' : '' ?></span>

        <?php if ($dayPending > 0): ?>
        <div style="display:flex;align-items:center;gap:8px;margin-left:auto;flex-wrap:wrap">
            <span style="background:#fef3c7;color:#92400e;border:1px solid #fde68a;border-radius:6px;padding:3px 10px;font-size:11px;font-weight:600">
                <i class="bi bi-hourglass-split me-1"></i><?= $dayPending ?> pendiente<?= $dayPending !== 1 ? 's' : '' ?>
            </span>
            <button type="button"
                    class="btn-jp btn-jp-sm btn-completar-dia"
                    data-date="<?= $date ?>"
                    data-label="<?= esc($dayLabel . ' ' . $dateLabel) ?>"
                    style="font-size:11px;background:#ede9fe;color:#5b21b6;border:1px solid #c4b5fd">
                <i class="bi bi-lightning-fill me-1"></i>Completar día
            </button>
        </div>
        <?php else: ?>
        <span style="background:#d1fae5;color:#065f46;border:1px solid #6ee7b7;border-radius:6px;padding:3px 10px;font-size:11px;font-weight:600;margin-left:auto">
            <i class="bi bi-check-all me-1"></i>Completado
        </span>
        <?php endif; ?>
    </div>

    <!-- Sesiones del día -->
    <?php foreach ($sessions as $sIdx => $s): ?>
    <?php
        $listaPasada   = !empty($s['lista_pasada_at']);
        $sessionStatus = $s['status'];
        $coachNames    = implode(', ', array_column($s['coaches'], 'name'));
    ?>
    <div class="session-block" style="border-bottom:1px solid var(--border-color)">
        <!-- Cabecera de sesión (toggle) -->
        <div onclick="toggleSession('s<?= $s['id'] ?>')"
             style="padding:12px 20px;cursor:pointer;display:flex;align-items:center;gap:12px;flex-wrap:wrap;user-select:none;transition:background .1s"
             onmouseover="this.style.background='var(--bg-app)'" onmouseout="this.style.background=''">
            <div style="flex:1;min-width:200px">
                <span style="font-weight:600;font-size:0.95rem"><?= esc($s['title']) ?></span>
                <span style="color:var(--text-muted);font-size:13px;margin-left:8px">
                    <?= substr($s['start_time'], 0, 5) ?>–<?= substr($s['end_time'], 0, 5) ?>
                </span>
                <?php if ($coachNames): ?>
                <span style="color:var(--text-muted);font-size:12px;margin-left:8px">
                    <i class="bi bi-person-badge me-1"></i><?= esc($coachNames) ?>
                </span>
                <?php endif; ?>
            </div>

            <?php if ($listaPasada): ?>
            <span style="background:#d1fae5;color:#065f46;border:1px solid #6ee7b7;border-radius:6px;padding:4px 12px;font-size:12px;font-weight:600;white-space:nowrap">
                <i class="bi bi-check-circle-fill me-1"></i>Lista pasada
                <span style="font-weight:400;margin-left:4px"><?= date('d/m H:i', strtotime($s['lista_pasada_at'])) ?></span>
                <?php if (!empty($s['lista_pasada_by_name'])): ?>
                <span style="font-weight:400"> · <?= esc($s['lista_pasada_by_name']) ?></span>
                <?php endif; ?>
            </span>
            <?php else: ?>
            <span class="session-pending-badge" style="background:#fef3c7;color:#92400e;border:1px solid #fde68a;border-radius:6px;padding:4px 12px;font-size:12px;font-weight:600;white-space:nowrap">
                <i class="bi bi-hourglass-split me-1"></i>Pendiente
            </span>
            <?php endif; ?>

            <?php if (!empty($s['players'])): ?>
            <div style="display:flex;gap:8px;font-size:12px">
                <span style="color:#059669;font-weight:600"><i class="bi bi-person-check-fill me-1"></i><?= $s['player_counts']['present'] ?></span>
                <span style="color:#dc2626;font-weight:600"><i class="bi bi-person-x-fill me-1"></i><?= $s['player_counts']['absent'] ?></span>
                <span style="color:#d97706;font-weight:600"><i class="bi bi-clock-fill me-1"></i><?= $s['player_counts']['pending'] ?></span>
            </div>
            <?php endif; ?>

            <i class="bi bi-chevron-down session-chevron-<?= $s['id'] ?>" style="transition:transform .2s;font-size:14px;color:var(--text-muted)"></i>
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

            <!-- Grid de alumnos -->
            <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:12px;padding:16px 20px">
            <?php foreach ($s['players'] as $p): ?>
            <?php
                $uid       = (int)$p['user_id'];
                $att       = $p['attendance'] ?? 'pending';
                $isAbsent  = ($att === 'absent');
                $isPresent = ($att === 'present');
                $deducted  = !empty($p['bono_deducted_at']);
                $formId    = 's' . $s['id'] . 'u' . $uid;
                $cardBorder = $attBorderColors[$att] ?? 'var(--border-color)';
            ?>
            <div id="card-<?= $formId ?>"
                 style="border:2px solid <?= $cardBorder ?>;border-radius:10px;padding:12px;background:var(--bg-card);display:flex;flex-direction:column;gap:8px;transition:border-color .15s">

                <!-- Nombre -->
                <div>
                    <div style="font-weight:700;font-size:0.9rem;color:var(--text-h)"><?= esc($p['name']) ?></div>
                    <?php if (!empty($p['student_note'])): ?>
                    <div style="font-size:11px;color:#d97706;margin-top:2px">
                        <i class="bi bi-chat-left-text-fill me-1"></i><?= esc($p['student_note']) ?>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Asistencia -->
                <select name="attendance[<?= $uid ?>]"
                        class="form-select-jp att-sel"
                        data-formid="<?= $formId ?>"
                        data-uid="<?= $uid ?>"
                        style="font-size:12px;width:100%">
                    <?php foreach ($attendanceOpts as $val => [$lbl, $col]): ?>
                    <option value="<?= $val ?>" <?= $att === $val ? 'selected' : '' ?>>
                        <?= $lbl ?>
                    </option>
                    <?php endforeach; ?>
                </select>

                <!-- Campos ausencia (solo si ausente) -->
                <div id="abs-<?= $formId ?>" style="display:<?= $isAbsent ? 'flex' : 'none' ?>;flex-direction:column;gap:6px">
                    <select name="absence_reason[<?= $uid ?>]" class="form-select-jp" style="font-size:11px;width:100%">
                        <option value="">— Motivo —</option>
                        <?php foreach ($absenceReasons as $r): ?>
                        <option value="<?= esc($r) ?>" <?= ($p['absence_reason'] ?? '') === $r ? 'selected' : '' ?>>
                            <?= esc($r) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <input type="text"
                           name="absence_notes[<?= $uid ?>]"
                           class="form-control-jp"
                           placeholder="Nota opcional…"
                           value="<?= esc($p['absence_notes'] ?? '') ?>"
                           style="font-size:11px;width:100%">
                </div>

                <!-- Bono -->
                <div style="margin-top:auto;padding-top:8px;border-top:1px solid var(--border-color);text-align:center">
                <?php if ($p['sessions_remaining'] !== null): ?>
                    <span class="bono-rem-<?= $formId ?>"
                          style="font-weight:800;font-size:1.1rem;display:block;color:<?= ($p['sessions_remaining'] <= 1) ? '#dc2626' : '#059669' ?>">
                        <?= $p['sessions_remaining'] ?>
                    </span>
                    <span style="font-size:10px;color:var(--text-muted);display:block"><?= esc($p['bono_name'] ?? '') ?></span>
                    <?php if ($deducted): ?>
                    <span style="font-size:10px;color:#059669;font-weight:600;display:block;margin-top:3px">
                        <i class="bi bi-check-circle-fill"></i> Descontado
                    </span>
                    <?php elseif ($isPresent): ?>
                    <button type="button"
                            class="btn-jp btn-jp-sm btn-deduct-week"
                            data-session="<?= $s['id'] ?>"
                            data-player="<?= $uid ?>"
                            data-formid="<?= $formId ?>"
                            style="margin-top:4px;background:#ede9fe;color:#5b21b6;border:1px solid #c4b5fd;font-size:10px;padding:2px 7px">
                        <i class="bi bi-dash-circle-fill me-1"></i>Descontar
                    </button>
                    <?php else: ?>
                    <span class="bono-hint-<?= $formId ?>" style="font-size:10px;color:var(--text-muted);display:block;margin-top:3px">Marcar presente</span>
                    <?php endif; ?>
                <?php else: ?>
                    <span style="font-size:11px;color:var(--text-muted)">Sin bono</span>
                <?php endif; ?>
                </div>
            </div>
            <?php endforeach; ?>
            </div>

            <div style="padding:12px 20px;display:flex;justify-content:flex-end;gap:10px;border-top:1px solid var(--border-color)">
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
    var CSRF_NAME  = '<?= csrf_token() ?>';
    var CSRF_HASH  = '<?= csrf_hash() ?>';

    // Toggle apertura de sesión
    window.toggleSession = function(id) {
        var el      = document.getElementById(id);
        var chevron = document.querySelector('.session-chevron-' + id.replace('s',''));
        if (!el) return;
        var open = el.style.display !== 'none';
        el.style.display = open ? 'none' : '';
        if (chevron) chevron.style.transform = open ? '' : 'rotate(180deg)';
    };

    // Auto-abrir sesiones pendientes de lista
    document.querySelectorAll('.session-block').forEach(function(block) {
        var badge = block.querySelector('.session-pending-badge');
        if (badge) {
            var toggle = block.querySelector('[onclick^="toggleSession"]');
            if (toggle) {
                var match = toggle.getAttribute('onclick').match(/'([^']+)'/);
                if (match) window.toggleSession(match[1]);
            }
        }
    });

    var attBorderColors = {
        present:   '#059669',
        absent:    '#dc2626',
        confirmed: '#2563eb',
        declined:  '#9ca3af',
        pending:   ''
    };

    // Toggle ausencia + color borde tarjeta
    document.querySelectorAll('.att-sel').forEach(function(sel) {
        sel.addEventListener('change', function() {
            var fid    = this.dataset.formid;
            var isAbs  = this.value === 'absent';
            var isPres = this.value === 'present';

            // Campos ausencia
            var absEl = document.getElementById('abs-' + fid);
            if (absEl) absEl.style.display = isAbs ? 'flex' : 'none';

            // Color borde tarjeta
            var card = document.getElementById('card-' + fid);
            if (card) {
                card.style.borderColor = attBorderColors[this.value] || 'var(--border-color)';
            }

            // Bono: mostrar/ocultar botón descontar
            var btn  = document.querySelector('.btn-deduct-week[data-formid="' + fid + '"]');
            var hint = document.querySelector('.bono-hint-' + fid);
            if (btn)  btn.style.display  = isPres ? '' : 'none';
            if (hint) hint.style.display = isPres ? 'none' : '';
        });
    });

    // Ocultar botón descontar si no está presente al cargar
    document.querySelectorAll('.btn-deduct-week').forEach(function(btn) {
        var fid = btn.dataset.formid;
        var sel = document.querySelector('.att-sel[data-formid="' + fid + '"]');
        if (sel && sel.value !== 'present') btn.style.display = 'none';
    });

    // Descontar bono AJAX
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
                        remEl.style.color = data.sessions_remaining <= 1 ? '#dc2626' : '#059669';
                    }
                    self.outerHTML = '<span style="font-size:10px;color:#059669;font-weight:600;display:block;margin-top:3px"><i class="bi bi-check-circle-fill"></i> Descontado</span>';
                } else {
                    alert(data.error || 'Error.');
                    self.disabled = false;
                    self.innerHTML = '<i class="bi bi-dash-circle-fill me-1"></i>Descontar';
                }
            })
            .catch(function() {
                alert('Error de red.');
                self.disabled = false;
                self.innerHTML = '<i class="bi bi-dash-circle-fill me-1"></i>Descontar';
            });
        });
    });

    // Completar día rápido
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
                    alert(data.error || 'Error al completar el día.');
                    self.disabled = false;
                    self.innerHTML = '<i class="bi bi-lightning-fill me-1"></i>Completar día';
                }
            })
            .catch(function() {
                alert('Error de red.');
                self.disabled = false;
                self.innerHTML = '<i class="bi bi-lightning-fill me-1"></i>Completar día';
            });
        });
    });
})();
</script>

<?= $this->endSection() ?>
