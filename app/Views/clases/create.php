<?= $this->extend('layouts/app') ?>

<?php
$isEdit   = !empty($session);
$action   = $isEdit ? '/clases/' . $session['id'] . '/editar' : '/clases/nueva';
$btnLabel = $isEdit ? 'Guardar cambios' : 'Crear sesión';
$isRec    = $isEdit && !empty($session['class_id']);

$v = function(string $key, $default = '') use ($session) {
    if (!empty($session[$key])) return esc($session[$key]);
    $old = old($key);
    return $old !== null ? esc($old) : esc($default);
};

// IDs ya asignados
$assignedCoachIds  = array_column($session['coaches'] ?? [], 'user_id');
$assignedPlayerIds = array_column($session['players'] ?? [], 'user_id');

// Días de la semana para recurrencia
$weekDays = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
$recDays  = [];
if ($isEdit && !empty($session['class_info']['recurrence_days'])) {
    $recDays = json_decode($session['class_info']['recurrence_days'], true) ?? [];
}
?>

<?= $this->section('page_content') ?>

<div class="page-header">
    <div class="page-header-text">
        <h2><?= $isEdit ? 'Editar sesión' : 'Nueva clase' ?></h2>
        <p>
            <a href="<?= $isEdit ? '/clases/' . $session['id'] : '/clases' ?>"
               style="color:var(--text-muted);text-decoration:none">
                <i class="bi bi-arrow-left me-1"></i>Volver
            </a>
        </p>
    </div>
</div>

<?php if ($flash = session()->getFlashdata('error')): ?>
    <div class="alert-jp error mb-3"><i class="bi bi-x-circle-fill me-2"></i><?= esc($flash) ?></div>
<?php endif; ?>

<form action="<?= $action ?>" method="POST" id="claseForm">
    <?= csrf_field() ?>

    <div class="row g-3">

        <!-- ── Columna principal ──────────────────────────── -->
        <div class="col-12 col-lg-8">

            <!-- Información básica -->
            <div class="card-jp mb-3">
                <div class="card-jp-header">
                    <span class="card-jp-title">
                        <i class="bi bi-collection-play-fill me-2" style="color:var(--accent)"></i>
                        Información de la clase
                    </span>
                </div>
                <div class="card-jp-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Título <span style="color:var(--danger)">*</span></label>
                            <input type="text" name="title" class="form-control-jp" required
                                   value="<?= $v('title') ?>"
                                   placeholder="Ej: Entrenamiento individual – Control y pase">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Objetivo del entrenamiento</label>
                            <input type="text" name="focus" class="form-control-jp"
                                   value="<?= $v('focus') ?>"
                                   placeholder="Ej: Mejora del primer toque, trabajo de presión alta…">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Descripción / notas generales</label>
                            <textarea name="description" class="form-control-jp" rows="2"
                                      placeholder="Información adicional sobre la sesión…"><?= $v('description') ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Tipo: puntual vs recurrente (solo en creación) -->
            <?php if (!$isEdit): ?>
            <div class="card-jp mb-3">
                <div class="card-jp-header">
                    <span class="card-jp-title">
                        <i class="bi bi-calendar3 me-2" style="color:#7c3aed"></i>
                        Tipo de clase
                    </span>
                </div>
                <div class="card-jp-body">
                    <div class="d-flex gap-3 mb-3">
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:12px 18px;border:2px solid var(--border);border-radius:var(--radius-sm);flex:1;transition:border-color .15s" id="lbl-single">
                            <input type="radio" name="type" value="single" checked onchange="toggleType(this.value)" style="accent-color:var(--accent)">
                            <div>
                                <div style="font-weight:700;color:var(--text-h)">Sesión puntual</div>
                                <div style="font-size:12px;color:var(--text-muted)">Un único día y horario</div>
                            </div>
                        </label>
                        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:12px 18px;border:2px solid var(--border);border-radius:var(--radius-sm);flex:1;transition:border-color .15s" id="lbl-recurring">
                            <input type="radio" name="type" value="recurring" onchange="toggleType(this.value)" style="accent-color:var(--accent)">
                            <div>
                                <div style="font-weight:700;color:var(--text-h)">Clase recurrente</div>
                                <div style="font-size:12px;color:var(--text-muted)">Varios días a la semana</div>
                            </div>
                        </label>
                    </div>

                    <!-- Puntual -->
                    <div id="block-single">
                        <div class="row g-3">
                            <div class="col-12 col-md-4">
                                <label class="form-label">Fecha <span style="color:var(--danger)">*</span></label>
                                <input type="date" name="session_date" class="form-control-jp"
                                       value="<?= $v('session_date', date('Y-m-d')) ?>">
                            </div>
                            <div class="col-6 col-md-4">
                                <label class="form-label">Hora inicio <span style="color:var(--danger)">*</span></label>
                                <input type="time" name="start_time" class="form-control-jp"
                                       value="<?= $v('start_time') ?>">
                            </div>
                            <div class="col-6 col-md-4">
                                <label class="form-label">Hora fin</label>
                                <input type="time" name="end_time" class="form-control-jp"
                                       value="<?= $v('end_time') ?>">
                            </div>
                        </div>
                    </div>

                    <!-- Recurrente -->
                    <div id="block-recurring" class="d-none">
                        <div class="row g-3">
                            <div class="col-12">
                                <label class="form-label">Días de la semana <span style="color:var(--danger)">*</span></label>
                                <div class="d-flex gap-2 flex-wrap">
                                    <?php foreach ($weekDays as $num => $name): ?>
                                    <label style="display:flex;align-items:center;gap:5px;cursor:pointer;padding:6px 12px;border:1px solid var(--border);border-radius:20px;font-size:12.5px;font-weight:600;color:var(--text-body);transition:all .15s" class="day-label">
                                        <input type="checkbox" name="recurrence_days[]" value="<?= $num ?>"
                                               style="accent-color:var(--accent);display:none" class="day-check"
                                               <?= in_array($num, $recDays) ? 'checked' : '' ?>>
                                        <?= $name ?>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label">Hora inicio <span style="color:var(--danger)">*</span></label>
                                <input type="time" name="start_time" class="form-control-jp"
                                       value="<?= $v('start_time') ?>">
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label">Hora fin</label>
                                <input type="time" name="end_time" class="form-control-jp"
                                       value="<?= $v('end_time') ?>">
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label">Desde <span style="color:var(--danger)">*</span></label>
                                <input type="date" name="recurrence_start" class="form-control-jp"
                                       value="<?= $v('recurrence_start', date('Y-m-d')) ?>">
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label">Hasta <span style="color:var(--danger)">*</span></label>
                                <input type="date" name="recurrence_end" class="form-control-jp"
                                       value="<?= $v('recurrence_end', date('Y-m-d', strtotime('+1 month'))) ?>">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php else: ?>
            <!-- En edición, mostrar fecha/hora directamente -->
            <div class="card-jp mb-3">
                <div class="card-jp-header">
                    <span class="card-jp-title">
                        <i class="bi bi-calendar3 me-2" style="color:var(--accent)"></i>
                        Fecha y horario
                    </span>
                </div>
                <div class="card-jp-body">
                    <input type="hidden" name="type" value="single">
                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <label class="form-label">Fecha <span style="color:var(--danger)">*</span></label>
                            <input type="date" name="session_date" class="form-control-jp" required
                                   value="<?= $v('session_date') ?>">
                        </div>
                        <div class="col-6 col-md-4">
                            <label class="form-label">Hora inicio</label>
                            <input type="time" name="start_time" class="form-control-jp"
                                   value="<?= $v('start_time') ?>">
                        </div>
                        <div class="col-6 col-md-4">
                            <label class="form-label">Hora fin</label>
                            <input type="time" name="end_time" class="form-control-jp"
                                   value="<?= $v('end_time') ?>">
                        </div>
                    </div>
                    <?php if ($isRec): ?>
                    <div class="mt-2" style="font-size:12px;color:var(--text-muted)">
                        <i class="bi bi-info-circle me-1"></i>
                        Esta sesión pertenece a la clase recurrente
                        <strong><?= esc($session['class_info']['title'] ?? '') ?></strong>.
                        Solo se editará esta sesión.
                    </div>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Lugar -->
            <div class="card-jp mb-3">
                <div class="card-jp-header">
                    <span class="card-jp-title">
                        <i class="bi bi-geo-alt-fill me-2" style="color:#059669"></i>
                        Lugar
                    </span>
                </div>
                <div class="card-jp-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label">Instalación (de la lista)</label>
                            <select name="location_id" class="form-control-jp">
                                <option value="">— Seleccionar instalación —</option>
                                <?php foreach ($locationOptions as $loc): ?>
                                    <option value="<?= $loc['id'] ?>"
                                        <?= ($v('location_id') == $loc['id']) ? 'selected' : '' ?>>
                                        <?= esc($loc['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">O lugar personalizado</label>
                            <input type="text" name="location_custom" class="form-control-jp"
                                   value="<?= $v('location_custom') ?>"
                                   placeholder="Ej: Estadio Municipal, Campo 3">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Observaciones previas (planificación) -->
            <div class="card-jp mb-3">
                <div class="card-jp-header">
                    <span class="card-jp-title">
                        <i class="bi bi-clipboard-fill me-2" style="color:#7c3aed"></i>
                        Planificación previa
                    </span>
                </div>
                <div class="card-jp-body">
                    <label class="form-label">Notas de planificación / objetivos</label>
                    <textarea name="pre_notes" class="form-control-jp" rows="4"
                              placeholder="Describe qué se trabajará en la sesión, ejercicios planificados, puntos de mejora…"><?= $v('pre_notes') ?></textarea>
                </div>
            </div>

        </div><!-- /col-8 -->

        <!-- ── Sidebar ──────────────────────────────────── -->
        <div class="col-12 col-lg-4">

            <!-- Acción -->
            <div class="card-jp mb-3">
                <div class="card-jp-body">
                    <button type="submit" class="btn-jp btn-jp-primary w-100 mb-2">
                        <i class="bi bi-check-lg me-1"></i><?= $btnLabel ?>
                    </button>
                    <a href="<?= $isEdit ? '/clases/' . $session['id'] : '/clases' ?>"
                       class="btn-jp btn-jp-secondary w-100" style="text-align:center;display:block">
                        Cancelar
                    </a>
                </div>
            </div>

            <!-- Entrenadores -->
            <div class="card-jp mb-3">
                <div class="card-jp-header">
                    <span class="card-jp-title" style="font-size:13px">
                        <i class="bi bi-person-workspace me-2" style="color:#059669"></i>Entrenadores
                    </span>
                </div>
                <div class="card-jp-body">
                    <select id="coachSelect" class="form-control-jp mb-2" onchange="addCoach(this)">
                        <option value="">Añadir entrenador…</option>
                        <?php foreach ($coachOptions as $c): ?>
                            <option value="<?= $c['id'] ?>" data-name="<?= esc($c['name']) ?>">
                                <?= esc($c['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div id="coachList" style="display:flex;flex-direction:column;gap:6px">
                        <?php foreach ($session['coaches'] ?? [] as $c): ?>
                        <div class="selected-person" id="coach-<?= $c['user_id'] ?>">
                            <input type="hidden" name="coach_ids[]" value="<?= $c['user_id'] ?>">
                            <div style="display:flex;align-items:center;justify-content:space-between;padding:7px 10px;background:var(--bg-app);border-radius:var(--radius-sm);font-size:13px">
                                <span><i class="bi bi-person-fill me-2" style="color:#059669"></i><?= esc($c['name']) ?></span>
                                <button type="button" onclick="removeCoach(<?= $c['user_id'] ?>)" style="background:none;border:none;color:var(--danger);cursor:pointer;padding:0"><i class="bi bi-x-lg"></i></button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div id="coachEmpty" style="font-size:12px;color:var(--text-muted);text-align:center;padding:8px <?= empty($session['coaches'] ?? []) ? '' : ';display:none' ?>">Sin entrenadores asignados</div>
                </div>
            </div>

            <!-- Jugadores -->
            <div class="card-jp">
                <div class="card-jp-header">
                    <span class="card-jp-title" style="font-size:13px">
                        <i class="bi bi-people-fill me-2" style="color:var(--accent)"></i>Jugadores
                    </span>
                </div>
                <div class="card-jp-body">
                    <select id="playerSelect" class="form-control-jp mb-2" onchange="addPlayer(this)">
                        <option value="">Añadir jugador…</option>
                        <?php foreach ($playerOptions as $p): ?>
                            <option value="<?= $p['id'] ?>" data-name="<?= esc($p['name']) ?>">
                                <?= esc($p['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div id="playerList" style="display:flex;flex-direction:column;gap:6px">
                        <?php foreach ($session['players'] ?? [] as $p):
                            $pCoachId = $p['coach_id'] ?? ''; ?>
                        <div class="selected-person" id="player-<?= $p['user_id'] ?>">
                            <input type="hidden" name="player_ids[]" value="<?= $p['user_id'] ?>">
                            <div style="padding:7px 10px;background:var(--bg-app);border-radius:var(--radius-sm);font-size:13px">
                                <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:5px">
                                    <span><i class="bi bi-person-fill me-2" style="color:var(--accent)"></i><?= esc($p['name']) ?></span>
                                    <button type="button" onclick="removePlayer(<?= $p['user_id'] ?>)" style="background:none;border:none;color:var(--danger);cursor:pointer;padding:0"><i class="bi bi-x-lg"></i></button>
                                </div>
                                <select name="player_coach_map[<?= $p['user_id'] ?>]" class="form-control-jp" style="font-size:12px;padding:4px 8px">
                                    <option value="">Sin entrenador asignado</option>
                                    <?php foreach ($coachOptions as $c): ?>
                                        <option value="<?= $c['id'] ?>" <?= $pCoachId == $c['id'] ? 'selected' : '' ?>><?= esc($c['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div id="playerEmpty" style="font-size:12px;color:var(--text-muted);text-align:center;padding:8px <?= empty($session['players'] ?? []) ? '' : ';display:none' ?>">Sin jugadores asignados</div>
                </div>
            </div>

        </div><!-- /sidebar -->
    </div>
</form>

<?= $this->section('scripts') ?>
<?php
// Opciones para JS
$coachOptionsJs  = json_encode(array_map(fn($c) => ['id' => $c['id'], 'name' => $c['name']], $coachOptions));
$playerOptionsJs = json_encode(array_map(fn($p) => ['id' => $p['id'], 'name' => $p['name']], $playerOptions));
?>
<style>
.day-label { user-select: none; }
.day-label:has(.day-check:checked) {
    background: var(--accent-light);
    border-color: var(--accent);
    color: var(--accent);
}
</style>
<script>
const coachOptions  = <?= $coachOptionsJs ?>;
const playerOptions = <?= $playerOptionsJs ?>;

// ── Tipo de clase toggle ──────────────────────────────────────
function toggleType(v) {
    const single = document.getElementById('block-single');
    const recur  = document.getElementById('block-recurring');
    const lblS   = document.getElementById('lbl-single');
    const lblR   = document.getElementById('lbl-recurring');

    if (v === 'recurring') {
        single.classList.add('d-none');
        recur.classList.remove('d-none');
        lblR.style.borderColor = 'var(--accent)';
        lblS.style.borderColor = 'var(--border)';
    } else {
        recur.classList.add('d-none');
        single.classList.remove('d-none');
        lblS.style.borderColor = 'var(--accent)';
        lblR.style.borderColor = 'var(--border)';
    }
}
// Init
toggleType(document.querySelector('[name="type"]:checked')?.value || 'single');

// ── Días de semana estilo toggle ──────────────────────────────
document.querySelectorAll('.day-check').forEach(cb => {
    cb.addEventListener('change', () => {
        cb.closest('.day-label').style.background = cb.checked ? 'var(--accent-light)' : '';
        cb.closest('.day-label').style.borderColor = cb.checked ? 'var(--accent)' : 'var(--border)';
        cb.closest('.day-label').style.color = cb.checked ? 'var(--accent)' : 'var(--text-body)';
    });
    // Init
    if (cb.checked) {
        cb.closest('.day-label').style.background = 'var(--accent-light)';
        cb.closest('.day-label').style.borderColor = 'var(--accent)';
        cb.closest('.day-label').style.color = 'var(--accent)';
    }
});

// ── Gestión de entrenadores ───────────────────────────────────
const addedCoaches = new Set([<?= implode(',', array_map(fn($c) => $c['user_id'], $session['coaches'] ?? [])) ?>]);

function addCoach(sel) {
    const id = parseInt(sel.value);
    if (!id || addedCoaches.has(id)) { sel.value = ''; return; }
    const name = sel.options[sel.selectedIndex].dataset.name;
    addedCoaches.add(id);
    sel.value = '';

    const html = `<div class="selected-person" id="coach-${id}">
        <input type="hidden" name="coach_ids[]" value="${id}">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:7px 10px;background:var(--bg-app);border-radius:var(--radius-sm);font-size:13px">
            <span><i class="bi bi-person-fill me-2" style="color:#059669"></i>${name}</span>
            <button type="button" onclick="removeCoach(${id})" style="background:none;border:none;color:var(--danger);cursor:pointer;padding:0"><i class="bi bi-x-lg"></i></button>
        </div>
    </div>`;
    document.getElementById('coachList').insertAdjacentHTML('beforeend', html);
    document.getElementById('coachEmpty').style.display = 'none';
}

function removeCoach(id) {
    addedCoaches.delete(id);
    document.getElementById('coach-' + id)?.remove();
    if (!addedCoaches.size) document.getElementById('coachEmpty').style.display = '';
}

// ── Gestión de jugadores ──────────────────────────────────────
const addedPlayers = new Set([<?= implode(',', array_map(fn($p) => $p['user_id'], $session['players'] ?? [])) ?>]);

function addPlayer(sel) {
    const id = parseInt(sel.value);
    if (!id || addedPlayers.has(id)) { sel.value = ''; return; }
    const name = sel.options[sel.selectedIndex].dataset.name;
    addedPlayers.add(id);
    sel.value = '';

    const coachOpts = coachOptions.map(c =>
        `<option value="${c.id}">${c.name}</option>`
    ).join('');

    const html = `<div class="selected-person" id="player-${id}">
        <input type="hidden" name="player_ids[]" value="${id}">
        <div style="padding:7px 10px;background:var(--bg-app);border-radius:var(--radius-sm);font-size:13px">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:5px">
                <span><i class="bi bi-person-fill me-2" style="color:var(--accent)"></i>${name}</span>
                <button type="button" onclick="removePlayer(${id})" style="background:none;border:none;color:var(--danger);cursor:pointer;padding:0"><i class="bi bi-x-lg"></i></button>
            </div>
            <select name="player_coach_map[${id}]" class="form-control-jp" style="font-size:12px;padding:4px 8px">
                <option value="">Sin entrenador asignado</option>
                ${coachOpts}
            </select>
        </div>
    </div>`;
    document.getElementById('playerList').insertAdjacentHTML('beforeend', html);
    document.getElementById('playerEmpty').style.display = 'none';
}

function removePlayer(id) {
    addedPlayers.delete(id);
    document.getElementById('player-' + id)?.remove();
    if (!addedPlayers.size) document.getElementById('playerEmpty').style.display = '';
}
</script>
<?= $this->endSection() ?>

<?= $this->endSection() ?>
