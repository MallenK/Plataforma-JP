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

// Tipo de sesión (coach o staff)
$currentSessionType = $session['session_type'] ?? 'coach';

// Días de la semana para recurrencia
$weekDays = [1 => 'Lunes', 2 => 'Martes', 3 => 'Miércoles', 4 => 'Jueves', 5 => 'Viernes', 6 => 'Sábado', 7 => 'Domingo'];
$recDays  = [];
if ($isEdit && !empty($session['class_info']['recurrence_days'])) {
    $recDays = json_decode($session['class_info']['recurrence_days'], true) ?? [];
}
?>

<?= $this->section('page_content') ?>

<div class="page-header">
    <a href="<?= $isEdit ? '/clases/' . $session['id'] : '/clases' ?>" class="btn-jp btn-jp-secondary btn-jp-sm">
        <i class="bi bi-arrow-left me-1"></i><?= $isEdit ? 'Volver a sesión' : 'Clases' ?>
    </a>
</div>

<?php if ($flash = session()->getFlashdata('error')): ?>
    <div class="alert-jp error mb-3"><i class="bi bi-x-circle-fill me-2"></i><?= esc($flash) ?></div>
<?php endif; ?>

<form action="<?= $action ?>" method="POST" id="claseForm">
    <?= csrf_field() ?>

    <div class="row g-3">

        <!-- ── Columna principal ──────────────────────────── -->
        <div class="col-12">
        <div class="cf-grid">

            <div class="cf-span">
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

            </div><!-- /cf-span -->

            <div class="cf-stack">
            <!-- Tipo de responsable -->
            <div class="card-jp mb-3">
                <div class="card-jp-header">
                    <span class="card-jp-title" style="font-size:13px">
                        <i class="bi bi-person-badge-fill me-2" style="color:#7c3aed"></i>Tipo de responsable
                    </span>
                </div>
                <div class="card-jp-body">
                    <input type="hidden" name="session_type" id="session_type_input" value="<?= esc($currentSessionType) ?>">
                    <div class="d-flex gap-2">
                        <label style="display:flex;align-items:center;gap:7px;cursor:pointer;padding:9px 14px;border:2px solid var(--border);border-radius:var(--radius-sm);flex:1;transition:border-color .15s" id="lbl-stype-coach">
                            <input type="radio" name="_session_type_ui" value="coach"
                                   <?= $currentSessionType === 'coach' ? 'checked' : '' ?>
                                   onchange="switchResponsible('coach')"
                                   style="accent-color:var(--accent)">
                            <div>
                                <div style="font-weight:700;font-size:12px;color:var(--text-h)">Entrenamiento</div>
                                <div style="font-size:11px;color:var(--text-muted)">Con entrenador</div>
                            </div>
                        </label>
                        <label style="display:flex;align-items:center;gap:7px;cursor:pointer;padding:9px 14px;border:2px solid var(--border);border-radius:var(--radius-sm);flex:1;transition:border-color .15s" id="lbl-stype-staff">
                            <input type="radio" name="_session_type_ui" value="staff"
                                   <?= $currentSessionType === 'staff' ? 'checked' : '' ?>
                                   onchange="switchResponsible('staff')"
                                   style="accent-color:#7c3aed">
                            <div>
                                <div style="font-weight:700;font-size:12px;color:var(--text-h)">Sesión de staff</div>
                                <div style="font-size:11px;color:var(--text-muted)">Con personal</div>
                            </div>
                        </label>
                    </div>
                </div>
            </div>

            <!-- Responsable (entrenador o staff, según tipo) -->
            <div class="card-jp mb-3">
                <div class="card-jp-header">
                    <span class="card-jp-title" style="font-size:13px" id="responsible-card-title">
                        <i class="bi bi-person-workspace me-2" id="responsible-card-icon" style="color:#059669"></i>
                        <span id="responsible-card-label"><?= $currentSessionType === 'staff' ? 'Staff responsable' : 'Entrenadores' ?></span>
                    </span>
                </div>
                <div class="card-jp-body">
                    <input type="hidden" name="coach_ids_present" value="1">
                    <?php if ($isRec && ($seriesFutureCount ?? 0) > 1): ?>
                    <!-- Clase recurrente: preguntar el alcance del cambio de
                         responsable, igual que el modal de la ficha (TICKET-011) —
                         nunca aplicar a la serie entera sin que se elija a propósito. -->
                    <div class="mb-3" style="display:flex;flex-direction:column;gap:8px;padding:10px;background:var(--bg-app);border-radius:var(--radius-sm)">
                        <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer">
                            <input type="radio" name="coach_scope" value="single" checked>
                            Solo esta sesión
                        </label>
                        <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer">
                            <input type="radio" name="coach_scope" value="series">
                            Esta y las siguientes (<?= $seriesFutureCount ?> sesiones de la serie)
                        </label>
                    </div>
                    <?php else: ?>
                    <input type="hidden" name="coach_scope" value="single">
                    <?php endif; ?>
                    <!-- Buscador de entrenador / staff (la lista cambia con el tipo de responsable) -->
                    <div class="pp mb-2" id="coachPicker">
                        <input type="search" class="form-control-jp" aria-label="Buscar responsable"
                               placeholder="<?= $currentSessionType === 'staff' ? 'Buscar miembro del staff…' : 'Buscar entrenador…' ?>">
                        <ul class="pp-list" hidden></ul>
                    </div>
                    <div id="coachList" style="display:flex;flex-direction:column;gap:6px">
                        <?php foreach ($session['coaches'] ?? [] as $c): ?>
                        <div class="selected-person" id="coach-<?= $c['user_id'] ?>">
                            <input type="hidden" name="coach_ids[]" value="<?= $c['user_id'] ?>">
                            <div style="display:flex;align-items:center;justify-content:space-between;padding:7px 10px;background:var(--bg-app);border-radius:var(--radius-sm);font-size:13px">
                                <span><i class="bi bi-person-fill me-2" id="coach-icon-<?= $c['user_id'] ?>" style="color:<?= $currentSessionType === 'staff' ? '#7c3aed' : '#059669' ?>"></i><?= esc($c['name']) ?></span>
                                <button type="button" onclick="removeCoach(<?= $c['user_id'] ?>)" style="background:none;border:none;color:var(--danger);cursor:pointer;padding:0"><i class="bi bi-x-lg"></i></button>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div id="coachEmpty" style="font-size:12px;color:var(--text-muted);text-align:center;padding:8px <?= empty($session['coaches'] ?? []) ? '' : ';display:none' ?>">Sin responsable asignado</div>
                </div>
            </div>

            </div><!-- /cf-stack entrenadores -->

            <div class="cf-stack">
            <!-- Jugadores -->
            <div class="card-jp mb-3">
                <div class="card-jp-header">
                    <span class="card-jp-title" style="font-size:13px">
                        <i class="bi bi-people-fill me-2" style="color:var(--accent)"></i>Jugadores
                    </span>
                </div>
                <div class="card-jp-body">
                    <?php $canExterno = !$isEdit && !empty($externosAvailable); ?>
                    <?php if ($canExterno): ?>
                    <!-- ¿Alumno registrado o persona externa? (clase puntual de prueba / suelta) -->
                    <input type="hidden" name="externo_mode" id="externo_mode" value="<?= old('externo_mode') ? '1' : '0' ?>">
                    <div class="d-flex gap-2 mb-3" id="who-tabs" role="radiogroup" aria-label="Quién recibe la clase">
                        <label class="who-tab" id="lbl-who-reg">
                            <input type="radio" name="_who_ui" value="reg" checked onchange="switchWho('reg')">
                            <span><i class="bi bi-person-check-fill me-1"></i>Alumno registrado</span>
                        </label>
                        <label class="who-tab" id="lbl-who-ext">
                            <input type="radio" name="_who_ui" value="ext" onchange="switchWho('ext')">
                            <span><i class="bi bi-person-badge-fill me-1"></i>Externo (clase puntual)</span>
                        </label>
                    </div>
                    <?php endif; ?>

                    <div id="registered-block">
                    <!-- Buscador de alumnos -->
                    <div class="pp mb-2" id="playerPicker">
                        <input type="search" class="form-control-jp" aria-label="Buscar alumno" placeholder="Buscar alumno…">
                        <ul class="pp-list" hidden></ul>
                    </div>
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
                                <select name="player_coach_map[<?= $p['user_id'] ?>]" class="form-control-jp player-responsible-map" style="font-size:12px;padding:4px 8px">
                                    <option value="">Sin responsable asignado</option>
                                    <?php
                                    $responsibleOptions = $currentSessionType === 'staff' ? $staffOptions : $coachOptions;
                                    foreach ($responsibleOptions as $c): ?>
                                        <option value="<?= $c['id'] ?>" <?= $pCoachId == $c['id'] ? 'selected' : '' ?>><?= esc($c['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <div id="playerEmpty" style="font-size:12px;color:var(--text-muted);text-align:center;padding:8px <?= empty($session['players'] ?? []) ? '' : ';display:none' ?>">Sin jugadores asignados</div>
                    </div><!-- /registered-block -->

                    <?php if ($canExterno): ?>
                    <!-- Datos de la persona externa: solo se envían si el modo «Externo» está activo -->
                    <div id="externo-block" class="d-none">
                        <?php
                        // Personas que había al volver de un error de validación (varias) o, antes, una sola.
                        $exInitial = old('externos');
                        if (!is_array($exInitial)) {
                            $exInitial = old('externo') && is_array(old('externo')) ? [old('externo')] : [];
                        }
                        ?>
                        <div data-ex-root class="d-none" data-ex-prefix="externos" data-initial="<?= esc(json_encode(array_values($exInitial), JSON_UNESCAPED_UNICODE | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_TAG | JSON_HEX_AMP), 'attr') ?>">
                        <p style="font-size:12px;color:var(--text-muted);margin:0 0 10px">
                            Para personas que no son alumnas (prueba o clase suelta). Solo el nombre es obligatorio.
                            Rellena una persona y pulsa <b>Guardar persona</b>; repite para añadir más.
                        </p>
                        <div data-ex-list></div>
                        <div data-ex-hidden hidden></div>
                        <p class="ex-draft-title">Nueva persona</p>
                        <div class="row g-2">
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="ex-first">Nombre <span style="color:var(--danger)">*</span></label>
                                <input id="ex-first" type="text" data-ex="first_name" class="form-control-jp" maxlength="100">
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="ex-last">Apellidos</label>
                                <input id="ex-last" type="text" data-ex="last_name" class="form-control-jp" maxlength="100">
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="ex-birth">Fecha de nacimiento <span style="font-weight:400;color:var(--text-muted)">(opcional)</span></label>
                                <input id="ex-birth" type="date" data-ex="birth_date" class="form-control-jp" max="<?= date('Y-m-d') ?>">
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="ex-phone">Teléfono <span style="font-weight:400;color:var(--text-muted)">(opcional)</span></label>
                                <input id="ex-phone" type="tel" data-ex="phone" class="form-control-jp" maxlength="30">
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="ex-email">Email <span style="font-weight:400;color:var(--text-muted)">(opcional)</span></label>
                                <input id="ex-email" type="email" data-ex="email" class="form-control-jp" maxlength="150">
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="ex-gname">Madre / padre / tutor <span style="font-weight:400;color:var(--text-muted)">(si es menor)</span></label>
                                <input id="ex-gname" type="text" data-ex="guardian_name" class="form-control-jp" maxlength="150">
                            </div>
                            <div class="col-12 col-md-6">
                                <label class="form-label" for="ex-gphone">Teléfono del tutor</label>
                                <input id="ex-gphone" type="tel" data-ex="guardian_phone" class="form-control-jp" maxlength="30">
                            </div>
                            <div class="col-12">
                                <label class="form-label" for="ex-notes">Notas</label>
                                <textarea id="ex-notes" rows="2" data-ex="notes" class="form-control-jp"></textarea>
                            </div>
                            <?php if (!empty($externosCanMoney)): ?>
                            <!-- Dinero de la clase: solo con acceso total a Finanzas -->
                            <div class="col-12 col-md-4">
                                <label class="form-label" for="ex-price">Precio (€)</label>
                                <input id="ex-price" type="text" inputmode="decimal" data-ex="price" class="form-control-jp" placeholder="0 = gratis">
                            </div>
                            <div class="col-12 col-md-4">
                                <label class="form-label" for="ex-method">Medio de pago</label>
                                <select id="ex-method" data-ex="method_id" class="form-control-jp">
                                    <?php foreach ($externosMethods as $m): ?>
                                        <option value="<?= (int) $m['id'] ?>" <?= $m['code'] === 'efectivo' ? 'selected' : '' ?>><?= esc($m['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-12 col-md-4 d-flex align-items-end">
                                <label style="display:flex;gap:8px;align-items:center;font-size:13px;cursor:pointer;margin-bottom:10px">
                                    <input type="checkbox" data-ex="paid_now" value="1">
                                    <span>Cobrado ahora</span>
                                </label>
                            </div>
                            <?php endif; ?>
                            <div class="col-12">
                                <label style="display:flex;gap:8px;align-items:flex-start;font-size:13px;cursor:pointer">
                                    <input type="checkbox" data-ex="image_rights" value="1" style="margin-top:3px">
                                    <span>Autoriza el uso de su imagen (foto/vídeo) en las comunicaciones de la academia.</span>
                                </label>
                            </div>
                            <div class="col-12">
                                <button type="button" class="btn-jp btn-jp-secondary btn-jp-sm" data-ex-save><i class="bi bi-person-plus-fill me-1"></i>Guardar persona</button>
                                <p data-ex-error hidden></p>
                            </div>
                        </div>
                        </div><!-- /data-ex-root -->
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            </div><!-- /cf-stack jugadores -->

            <div class="cf-stack">
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
                    <!-- Formato de clase: individual o pareja -->
                    <div class="mb-3">
                        <label style="font-size:13px;font-weight:600;color:var(--text-h);display:block;margin-bottom:8px">
                            Formato de clase
                        </label>
                        <div class="d-flex gap-3">
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:10px 16px;border:2px solid var(--border);border-radius:var(--radius-sm);flex:1">
                                <input type="radio" name="class_format" value="individual"
                                       <?= ($isEdit ? ($session['class_info']['class_format'] ?? 'individual') : 'individual') === 'individual' ? 'checked' : '' ?>
                                       style="accent-color:var(--accent)">
                                <div>
                                    <div style="font-weight:700;color:var(--text-h)">Individual</div>
                                    <div style="font-size:12px;color:var(--text-muted)">1 alumno</div>
                                </div>
                            </label>
                            <label style="display:flex;align-items:center;gap:8px;cursor:pointer;padding:10px 16px;border:2px solid var(--border);border-radius:var(--radius-sm);flex:1">
                                <input type="radio" name="class_format" value="pareja"
                                       <?= ($isEdit ? ($session['class_info']['class_format'] ?? 'individual') : 'individual') === 'pareja' ? 'checked' : '' ?>
                                       style="accent-color:var(--accent)">
                                <div>
                                    <div style="font-weight:700;color:var(--text-h)">Pareja</div>
                                    <div style="font-size:12px;color:var(--text-muted)">2 alumnos</div>
                                </div>
                            </label>
                        </div>
                    </div>

                    <!-- Puntual -->
                    <div id="block-single">
                        <div class="row g-3">
                            <div class="col-12 col-md-4">
                                <label class="form-label">Fecha <span style="color:var(--danger)">*</span></label>
                                <input type="date" name="session_date" class="form-control-jp" required
                                       value="<?= $v('session_date', date('Y-m-d')) ?>">
                            </div>
                            <div class="col-6 col-md-4">
                                <label class="form-label">Hora inicio <span style="color:var(--danger)">*</span></label>
                                <input type="time" name="start_time" class="form-control-jp" required
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
                                               disabled
                                               <?= in_array($num, $recDays) ? 'checked' : '' ?>>
                                        <?= $name ?>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label">Hora inicio <span style="color:var(--danger)">*</span></label>
                                <input type="time" name="start_time" class="form-control-jp"
                                       value="<?= $v('start_time') ?>" disabled>
                            </div>
                            <div class="col-6 col-md-3">
                                <label class="form-label">Hora fin</label>
                                <input type="time" name="end_time" class="form-control-jp"
                                       value="<?= $v('end_time') ?>" disabled>
                            </div>
                        </div>

                        <!-- Configuración de la serie (TICKET-013): primera clase, nº de clases, fecha límite,
                             y "todas iguales" o clase a clase. Lo pinta series-builder.js -->
                        <div class="mt-3" id="series-builder"></div>
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
                            <label class="form-label">Hora inicio <span style="color:var(--danger)">*</span></label>
                            <input type="time" name="start_time" class="form-control-jp" required
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

            </div><!-- /cf-stack tipo -->

            <div class="cf-stack">
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
                            <select name="location_id" id="location_id" class="form-control-jp">
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
                    <!-- Aviso conflicto de instalación -->
                    <div id="location-conflict-warn" style="display:none;margin-top:10px;padding:10px 14px;background:#fef3c7;border:1px solid #fde68a;border-radius:8px;font-size:13px;color:#92400e">
                        <i class="bi bi-exclamation-triangle-fill me-2"></i>
                        <strong>Instalación ocupada:</strong> <span id="location-conflict-detail"></span>
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

            </div><!-- /cf-stack lugar -->
        </div><!-- /cf-grid -->

        <!-- ── Sidebar ──────────────────────────────────── -->
        <div class="cf-footer">

            <?php if (!$isEdit): ?>
            <!-- Cobertura de bono (TICKET-013): se rellena por JS al elegir alumnos + serie -->
            <div class="card-jp mb-3 d-none" id="bono-cov-card">
                <div class="card-jp-body">
                    <div id="bono-cov"></div>
                </div>
            </div>
            <?php endif; ?>

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

        </div><!-- /cf-footer -->
        </div><!-- /col-12 -->
    </div>
</form>

<?= $this->section('scripts') ?>
<script src="/assets/js/person-picker.js?v=<?= (int) @filemtime(FCPATH . 'assets/js/person-picker.js') ?>"></script>
<script src="/assets/js/externos-list.js?v=<?= (int) @filemtime(FCPATH . 'assets/js/externos-list.js') ?>"></script>
<?php if (!$isEdit): ?>
<script src="/assets/js/bono-coverage.js?v=<?= (int) @filemtime(FCPATH . 'assets/js/bono-coverage.js') ?>"></script>
<script src="/assets/js/series-builder.js?v=<?= (int) @filemtime(FCPATH . 'assets/js/series-builder.js') ?>"></script>
<?php endif; ?>
<?php
// Opciones para JS. Los nombres los controla el usuario (un alumno puede
// cambiar el suyo), así que se escapan <, >, ', ", & para que no puedan
// romper el bloque <script> aunque cambien las flags por defecto de PHP.
$jsonFlags = JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE;
$coachOptionsJs  = json_encode(array_map(fn($c) => ['id' => $c['id'], 'name' => $c['name']], $coachOptions), $jsonFlags);
$staffOptionsJs  = json_encode(array_map(fn($s) => ['id' => $s['id'], 'name' => $s['name']], $staffOptions), $jsonFlags);
$playerOptionsJs = json_encode(array_map(fn($p) => ['id' => $p['id'], 'name' => $p['name']], $playerOptions), $jsonFlags);
?>
<style>
.day-label { user-select: none; }
.day-label:has(.day-check:checked) {
    background: var(--accent-light);
    border-color: var(--accent);
    color: var(--accent);
}
/* Formulario en dos columnas: cada .cf-stack es una columna de tarjetas; .cf-span ocupa las dos */
.cf-grid { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 0 16px; align-items: start; }
.cf-span { grid-column: 1 / -1; }
.cf-stack, .cf-span { min-width: 0; }
@media (max-width: 991.98px) { .cf-grid { grid-template-columns: minmax(0, 1fr); } }
/* Crear / Cancelar siempre a mano al final, en una sola fila */
.cf-footer { position: sticky; bottom: 0; z-index: 20; padding-top: 4px; }
.cf-footer .card-jp-body { display: flex; gap: 8px; flex-wrap: wrap; }
.cf-footer .card-jp-body .w-100 { width: auto !important; flex: 1 1 160px; margin: 0 !important; }
.cf-footer .card-jp { box-shadow: 0 -6px 18px rgba(15, 23, 42, .08); }
/* (los estilos del buscador de personas los inyecta person-picker.js) */
/* Alumno registrado / Externo */
.who-tab { flex: 1; cursor: pointer; margin: 0; }
.who-tab input { position: absolute; opacity: 0; pointer-events: none; }
.who-tab span {
    display: block; text-align: center; padding: 9px 10px; font-size: 13px; font-weight: 600;
    border: 2px solid var(--border); border-radius: var(--radius-sm); color: var(--text-body);
}
.who-tab:has(input:checked) span { border-color: var(--accent); background: var(--accent-light); color: var(--accent); }
.who-tab:has(input:focus-visible) span { outline: 2px solid var(--accent); outline-offset: 2px; }
</style>
<script>
const coachOptions  = <?= $coachOptionsJs ?>;
const staffOptions  = <?= $staffOptionsJs ?>;
const playerOptions = <?= $playerOptionsJs ?>;
let currentSessionType = '<?= esc($currentSessionType, 'js') ?>';
function escHtml(s) {
    return String(s).replace(/[&<>"']/g, c => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
}
let coachPicker = null, playerPicker = null;

// ── Cambio tipo responsable ───────────────────────────────────
function switchResponsible(type) {
    currentSessionType = type;
    document.getElementById('session_type_input').value = type;

    const isStaff = type === 'staff';
    const lblCoach = document.getElementById('lbl-stype-coach');
    const lblStaff = document.getElementById('lbl-stype-staff');
    if (lblCoach) lblCoach.style.borderColor = isStaff ? 'var(--border)' : 'var(--accent)';
    if (lblStaff) lblStaff.style.borderColor = isStaff ? '#7c3aed'       : 'var(--border)';

    // El buscador de responsable cambia de lista (entrenadores / staff)
    if (typeof coachPicker !== 'undefined' && coachPicker) {
        coachPicker.setPlaceholder(isStaff ? 'Buscar miembro del staff…' : 'Buscar entrenador…');
        coachPicker.refresh();
    }

    // Update card label and icon
    const label = document.getElementById('responsible-card-label');
    const icon  = document.getElementById('responsible-card-icon');
    if (label) label.textContent = isStaff ? 'Staff responsable' : 'Entrenadores';
    if (icon)  icon.style.color  = isStaff ? '#7c3aed' : '#059669';

    // Update icon colors in already-added coaches
    document.querySelectorAll('[id^="coach-icon-"]').forEach(el => {
        el.style.color = isStaff ? '#7c3aed' : '#059669';
    });

    // Rebuild player_coach_map dropdowns
    document.querySelectorAll('.player-responsible-map').forEach(sel => {
        const selectedVal = sel.value;
        const options = isStaff ? staffOptions : coachOptions;
        const label = isStaff ? 'Sin staff asignado' : 'Sin entrenador asignado';
        sel.innerHTML = `<option value="">${escHtml(label)}</option>` +
            options.map(o => `<option value="${o.id}"${o.id == selectedVal ? ' selected' : ''}>${escHtml(o.name)}</option>`).join('');
    });
}

// Init visual state on load
(function() {
    const lblCoach = document.getElementById('lbl-stype-coach');
    const lblStaff = document.getElementById('lbl-stype-staff');
    if (currentSessionType === 'staff') {
        if (lblCoach) lblCoach.style.borderColor = 'var(--border)';
        if (lblStaff) lblStaff.style.borderColor = '#7c3aed';
    } else {
        if (lblCoach) lblCoach.style.borderColor = 'var(--accent)';
        if (lblStaff) lblStaff.style.borderColor = 'var(--border)';
    }
})();

// ── Tipo de clase toggle ──────────────────────────────────────
function setBlockDisabled(block, disabled) {
    block.querySelectorAll('input, select, textarea').forEach(el => {
        el.disabled = disabled;
    });
}

function toggleType(v) {
    // Una clase recurrente no admite externos: si ya hay personas externas, se pregunta antes de quitarlas.
    if (v === 'recurring' && typeof externosPendientes === 'function' && externosPendientes() > 0 && !toggleType._ok) {
        const n = externosPendientes();
        ExternosList.confirmDiscard({
            title: 'Una clase recurrente no admite personas externas',
            description: 'Has añadido ' + n + ' persona' + (n === 1 ? '' : 's') + ' externa' + (n === 1 ? '' : 's') + '. Si sigues, se quitarán de esta clase. Para una persona externa usa una clase puntual.',
            confirmLabel: 'Quitar y hacerla recurrente', cancelLabel: 'Mantener clase puntual'
        }).then(ok => {
            if (ok) { toggleType._ok = true; document.querySelector('#externo-block [data-ex-root]')?._exList?.reset(); const r = document.querySelector('[name="type"][value="recurring"]'); if (r) r.checked = true; toggleType('recurring'); toggleType._ok = false; }
        });
        const keep = document.querySelector('[name="type"][value="single"]'); if (keep) keep.checked = true;   // mientras decide, no cambia nada
        return;
    }
    if (typeof syncWhoTabs === 'function') syncWhoTabs(v);
    const single = document.getElementById('block-single');
    const recur  = document.getElementById('block-recurring');
    const lblS   = document.getElementById('lbl-single');
    const lblR   = document.getElementById('lbl-recurring');
    // Al EDITAR no existe el selector Puntual/Recurrente (ni sus bloques): sin esto lanzaba un error de JavaScript
    // que abortaba el resto del script y dejaba sin funcionar los buscadores de entrenador y alumno.
    if (!single || !recur || !lblS || !lblR) return;

    if (v === 'recurring') {
        single.classList.add('d-none');
        recur.classList.remove('d-none');
        lblR.style.borderColor = 'var(--accent)';
        lblS.style.borderColor = 'var(--border)';
        setBlockDisabled(single, true);
        setBlockDisabled(recur, false);
        if (seriesBuilder) seriesBuilder.syncDisabled();
    } else {
        recur.classList.add('d-none');
        single.classList.remove('d-none');
        lblS.style.borderColor = 'var(--accent)';
        lblR.style.borderColor = 'var(--border)';
        setBlockDisabled(recur, true);
        setBlockDisabled(single, false);
    }
}
// ── Cobertura de bono (TICKET-013) ────────────────────────────
// Cruza alumnos × fechas con su saldo y AVISA si se superará el bono.
// Solo informa: la clase se puede crear igualmente.
let bonoCov = null;
let seriesBuilder = null;

function recurDays() {
    return Array.from(document.querySelectorAll('#claseForm [name="recurrence_days[]"]:checked')).map(c => parseInt(c.value, 10));
}
function recurTimes() {
    const f = document.getElementById('claseForm');
    return {
        start: f.querySelector('#block-recurring [name="start_time"]')?.value || '',
        end:   f.querySelector('#block-recurring [name="end_time"]')?.value || ''
    };
}
<?php if (!$isEdit): ?>
// Configuración de la serie: primera clase, nº de clases, fecha límite, todas iguales o una a una
seriesBuilder = SeriesBuilder.attach({
    root:     document.getElementById('series-builder'),
    getDays:  recurDays,
    getTimes: recurTimes,
    onChange: function () { if (bonoCov) bonoCov.refresh(); }
});
bonoCov = BonoCoverage.attach({
    panel:     document.getElementById('bono-cov'),
    csrfName:  <?= json_encode(csrf_token()) ?>,
    csrfHash:  <?= json_encode(csrf_hash()) ?>,
    onChange:  function () {
        const hasPanel = document.getElementById('bono-cov').innerHTML.trim() !== '';
        document.getElementById('bono-cov-card').classList.toggle('d-none', !hasPanel);
    },
    collect: function () {
        const form = document.getElementById('claseForm');
        const players = form.querySelectorAll('[name="player_ids[]"]');
        if (!players.length) return null;
        const fd = new FormData();
        players.forEach(p => fd.append('player_ids[]', p.value));
        if ((form.querySelector('[name="type"]:checked')?.value || 'single') === 'recurring') {
            const days = recurDays();
            if (!days.length || seriesBuilder.validate()) return null;
            days.forEach(d => fd.append('recurrence_days[]', d));
            seriesBuilder.appendTo(fd);
        } else {
            const date = form.querySelector('#block-single [name="session_date"]')?.value;
            if (!date) return null;
            fd.append('session_date', date);
        }
        return fd;
    }
});
document.getElementById('claseForm').addEventListener('change', function (e) {
    const n = e.target && e.target.name;
    if (n === 'recurrence_days[]' || n === 'start_time' || n === 'end_time') { seriesBuilder.refresh(); bonoCov.refresh(); }
    if (n === 'session_date') bonoCov.refresh();
    if (n === 'type') { seriesBuilder.syncDisabled(); bonoCov.refresh(); }
});
<?php endif; ?>
function refreshBonoCov() { if (bonoCov) bonoCov.refresh(); }

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

function addCoach(opt) {
    const id = parseInt(opt.id);
    if (!id || addedCoaches.has(id)) { return; }

    // Solo se admite 1 responsable por sesión (ver ClasesService::syncCoaches,
    // que solo guarda el primer coach_ids[] recibido). Si dejábamos acumular
    // varias tarjetas aquí, el orden de los hidden inputs decidía en
    // silencio cuál se guardaba de verdad — normalmente el más antiguo,
    // ignorando el que se acababa de elegir. Añadir uno nuevo sustituye
    // siempre al anterior.
    addedCoaches.forEach(function (oldId) {
        document.getElementById('coach-' + oldId)?.remove();
    });
    addedCoaches.clear();

    const name = escHtml(opt.name);
    addedCoaches.add(id);

    const iconColor = currentSessionType === 'staff' ? '#7c3aed' : '#059669';
    const html = `<div class="selected-person" id="coach-${id}">
        <input type="hidden" name="coach_ids[]" value="${id}">
        <div style="display:flex;align-items:center;justify-content:space-between;padding:7px 10px;background:var(--bg-app);border-radius:var(--radius-sm);font-size:13px">
            <span><i class="bi bi-person-fill me-2" id="coach-icon-${id}" style="color:${iconColor}"></i>${name}</span>
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

function addPlayer(opt) {
    const id = parseInt(opt.id);
    if (!id || addedPlayers.has(id)) { return; }
    const name = escHtml(opt.name);
    addedPlayers.add(id);

    const responsibleOpts = (currentSessionType === 'staff' ? staffOptions : coachOptions)
        .map(c => `<option value="${c.id}">${escHtml(c.name)}</option>`).join('');
    const responsibleLabel = currentSessionType === 'staff' ? 'Sin staff asignado' : 'Sin entrenador asignado';

    const html = `<div class="selected-person" id="player-${id}">
        <input type="hidden" name="player_ids[]" value="${id}">
        <div style="padding:7px 10px;background:var(--bg-app);border-radius:var(--radius-sm);font-size:13px">
            <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:5px">
                <span><i class="bi bi-person-fill me-2" style="color:var(--accent)"></i>${name}</span>
                <button type="button" onclick="removePlayer(${id})" style="background:none;border:none;color:var(--danger);cursor:pointer;padding:0"><i class="bi bi-x-lg"></i></button>
            </div>
            <select name="player_coach_map[${id}]" class="form-control-jp player-responsible-map" style="font-size:12px;padding:4px 8px">
                <option value="">${responsibleLabel}</option>
                ${responsibleOpts}
            </select>
        </div>
    </div>`;
    document.getElementById('playerList').insertAdjacentHTML('beforeend', html);
    document.getElementById('playerEmpty').style.display = 'none';
    refreshBonoCov();
}

function removePlayer(id) {
    addedPlayers.delete(id);
    document.getElementById('player-' + id)?.remove();
    if (!addedPlayers.size) document.getElementById('playerEmpty').style.display = '';
    refreshBonoCov();
}

// ── Alumno registrado / Externo (clase puntual) ───────────────
// Cambio de pestaña Alumno registrado ⇄ Externo: nunca se descarta nada sin preguntar.
function externosPendientes() {
    const root = document.querySelector('#externo-block [data-ex-root]');
    return root && window.ExternosList ? ExternosList.pendingCount(root) : 0;
}
function switchWho(mode) {
    const cur = document.getElementById('externo_mode')?.value === '1' ? 'ext' : 'reg';
    if (mode === cur) return;
    const back = () => { const r = document.querySelector('#who-tabs input[value="' + cur + '"]'); if (r) r.checked = true; };
    if (mode === 'ext' && addedPlayers.size > 0) {
        const names = [...document.querySelectorAll('#playerList .selected-person > div span')].map(s => s.textContent.trim()).filter(Boolean);
        ExternosList.confirmDiscard({
            title: 'Ya has elegido alumnos registrados',
            description: (names.length ? names.join(', ') + '. ' : '') + 'Si pasas a «Externo» se quitarán de esta clase (una clase se crea o con alumnos registrados o con personas externas, no con ambos). ¿Quitarlos?',
            confirmLabel: 'Quitar alumnos y pasar a Externo', cancelLabel: 'Mantener alumnos'
        }).then(ok => { if (ok) { [...addedPlayers].forEach(id => removePlayer(id)); setWho('ext'); } });
        back();
        return;
    }
    if (mode === 'reg' && externosPendientes() > 0) {
        const n = externosPendientes();
        ExternosList.confirmDiscard({
            title: 'Ya has añadido personas externas',
            description: n + ' persona' + (n === 1 ? '' : 's') + ' externa' + (n === 1 ? '' : 's') + ' (guardada' + (n === 1 ? '' : 's') + ' o a medio escribir). Si pasas a «Alumno registrado» se quitarán de esta clase. ¿Quitarlas?',
            confirmLabel: 'Quitar personas y pasar a Alumno', cancelLabel: 'Mantener externas'
        }).then(ok => { if (ok) { document.querySelector('#externo-block [data-ex-root]')?._exList?.reset(); setWho('reg'); } });
        back();
        return;
    }
    setWho(mode);
}
function setWho(mode) {
    const hidden = document.getElementById('externo_mode');
    if (!hidden) return;                                   // edición o sin migrar: no hay modo externo
    const ext = mode === 'ext';
    hidden.value = ext ? '1' : '0';
    document.getElementById('registered-block')?.classList.toggle('d-none', ext);
    const block = document.getElementById('externo-block');
    if (block) {
        block.classList.toggle('d-none', !ext);
        // El bloque contiene el contenedor [data-ex-root]; ExternosList mira esta clase al enviar.
        block.querySelectorAll('[data-ex-root]').forEach(r => r.classList.toggle('d-none', !ext));
    }
    const radio = document.querySelector('#who-tabs input[value="' + mode + '"]');
    if (radio) radio.checked = true;
    refreshBonoCov();
}
// Una clase recurrente no admite externos (es para una sola clase puntual).
function syncWhoTabs(type) {
    const tabs = document.getElementById('who-tabs');
    if (!tabs) return;
    const recurring = type === 'recurring';
    tabs.classList.toggle('d-none', recurring);
    if (recurring) setWho('reg');
}

// ── Buscadores (sustituyen a los desplegables) ────────────────
coachPicker = PersonPicker.attach({
    root:       document.getElementById('coachPicker'),
    getOptions: () => currentSessionType === 'staff' ? staffOptions : coachOptions,
    isTaken:    id => addedCoaches.has(parseInt(id)),
    onPick:     addCoach
});
playerPicker = PersonPicker.attach({
    root:       document.getElementById('playerPicker'),
    getOptions: () => playerOptions,
    isTaken:    id => addedPlayers.has(parseInt(id)),
    onPick:     addPlayer
});
if (document.getElementById('externo_mode')?.value === '1') setWho('ext');

// ── Aviso conflicto instalación ───────────────────────────────
(function() {
    var EXCLUDE_ID = <?= $isEdit ? (int)$session['id'] : 'null' ?>;

    function checkLocationConflict() {
        var locId = document.getElementById('location_id')?.value;
        var date  = document.querySelector('[name="session_date"]')?.value;
        var start = document.querySelector('[name="start_time"]')?.value;
        var end   = document.querySelector('[name="end_time"]')?.value;
        var warn  = document.getElementById('location-conflict-warn');

        if (!locId || !date || !start || !end) { if (warn) warn.style.display = 'none'; return; }

        var url = '/clases/api/check-location?location_id=' + encodeURIComponent(locId)
                + '&date='  + encodeURIComponent(date)
                + '&start=' + encodeURIComponent(start)
                + '&end='   + encodeURIComponent(end)
                + (EXCLUDE_ID ? '&exclude=' + EXCLUDE_ID : '');

        fetch(url, { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!warn) return;
                var conflicts = data.conflicts ?? [];
                if (conflicts.length === 0) {
                    warn.style.display = 'none';
                } else {
                    var detail = conflicts.map(function(c) {
                        return '"' + c.title + '" (' + c.start_time.substring(0,5) + '–' + c.end_time.substring(0,5) + ')';
                    }).join(', ');
                    document.getElementById('location-conflict-detail').textContent = detail;
                    warn.style.display = '';
                }
            })
            .catch(function() {});
    }

    var triggers = ['location_id', 'session_date', 'start_time', 'end_time'];
    triggers.forEach(function(name) {
        var el = document.getElementById(name) || document.querySelector('[name="' + name + '"]');
        if (el) el.addEventListener('change', checkLocationConflict);
    });
    // Check on load if editing
    if (EXCLUDE_ID) checkLocationConflict();
})();
</script>

<script>
// ── Botón «<?= $btnLabel ?>» blando: dice qué falta y lleva al campo (JPGuard) ───────────
// Espejo de lo que exige el servidor (ClasesService::participantsError, horas, fecha).
(function () {
  // form-guard.js se carga al final de la página: se espera a que esté lista
  function initGuard() {
    if (!window.JPGuard) return;
    var form = document.getElementById('claseForm');
    var btn  = form && form.querySelector('.cf-footer button[type="submit"]');
    if (!btn) return;
    var IS_EDIT = <?= $isEdit ? 'true' : 'false' ?>;
    // Editar: el servidor solo valida los alumnos si se envían; una clase sin alumnos registrados (p. ej. solo con
    // personas externas) se puede editar. Solo se pide alumno si la clase ya los tenía y se han quitado todos.
    var INITIAL_PLAYERS = form.querySelectorAll('[name="player_ids[]"]').length;
    var on  = function (el) { return el && !el.disabled; };
    var one = function (sel) { return [].filter.call(form.querySelectorAll(sel), on)[0] || null; };
    var visibleDate = function () { var d = one('[name="session_date"]'); return d && (d._dateEs || d); };
    var externoOn = function () { var h = document.getElementById('externo_mode'); return !!h && h.value === '1'; };
    var externos = function () {
        var root = document.querySelector('#externo-block [data-ex-root]');
        return (root && window.ExternosList) ? ExternosList.attach(root).collect() : { people: [], error: '' };
    };
    var isStaff = function () { var s = document.getElementById('session_type_input'); return !!s && s.value === 'staff'; };
    var recurring = function () { var r = document.getElementById('block-recurring'); return !!r && !r.classList.contains('d-none') && !!one('#block-recurring [name="session_date"]'); };

    var guard = JPGuard.attach({
        root: form,
        buttons: [btn],
        hintAfter: btn,
        needsChange: IS_EDIT ? (function () { var base = JPGuard.snapshot(form); return function () { return JPGuard.snapshot(form) !== base; }; })() : null,
        rules: [
            { label: 'el título', ok: function () { return form.querySelector('[name="title"]').value.trim() !== ''; }, focus: function () { return form.querySelector('[name="title"]'); } },
            { label: 'la fecha', ok: function () { var d = visibleDate(); return !d || !!one('[name="session_date"]').value; }, focus: visibleDate },
            { label: 'al menos un día de la semana', ok: function () { return !recurring() || recurDays().length > 0; }, focus: function () { return form.querySelector('#block-recurring [name="recurrence_days[]"]'); } },
            { label: 'la configuración de la serie', ok: function () { return IS_EDIT || !recurring() || !seriesBuilder || !seriesBuilder.validate(); }, focus: function () { return document.querySelector('#series-builder input, #series-builder button'); } },
            { label: 'una hora de fin posterior a la de inicio', ok: function () { var s = one('[name="start_time"]'), e = one('[name="end_time"]'); return !s || !e || !e.value || e.value === '00:00' || e.value > s.value; }, focus: function () { return one('[name="end_time"]'); } },
            { label: function () { return isStaff() ? 'un responsable (staff)' : 'un entrenador'; }, ok: function () { return form.querySelectorAll('[name="coach_ids[]"]').length > 0; }, focus: function () { return document.querySelector('#coachPicker input[type="search"]'); } },
            { label: 'un alumno', ok: function () { var n = form.querySelectorAll('[name="player_ids[]"]').length; return IS_EDIT ? (INITIAL_PLAYERS === 0 || n > 0) : (externoOn() || n > 0); }, focus: function () { return document.querySelector('#playerPicker input[type="search"]'); } },
            { label: 'el nombre de la persona externa', ok: function () { if (!externoOn()) return true; var c = externos(); return c.people.length > 0 && !c.error; }, focus: function () { return document.getElementById('ex-first'); } }
        ]
    });
    // los valores que se cambian por código (fechas, tipo de clase) no siempre disparan eventos
    ['setWho', 'toggleType'].forEach(function (fn) {
        var orig = window[fn];
        if (typeof orig === 'function') window[fn] = function () { var r = orig.apply(this, arguments); setTimeout(function () { guard.refresh(); }, 0); return r; };
    });
  }
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', initGuard); else initGuard();
})();
</script>
<?= $this->endSection() ?>

<?= $this->endSection() ?>
