<?= $this->extend('layouts/app') ?>

<?php
helper('attendance');
$groups   = attendance_groups();
$players  = $session['players'] ?? [];
$isClosed = ($session['status'] === 'completed');
$listaSaved = !empty($session['lista_pasada_at']);
?>

<?= $this->section('page_content') ?>

<link rel="stylesheet" href="<?= base_url('assets/css/pasar-lista.css') ?>?v=<?= @filemtime(FCPATH . 'assets/css/pasar-lista.css') ?: time() ?>">

<div class="pl-wrap">

    <a href="/pasar-lista" class="pl-crumb"><i class="bi bi-arrow-left"></i>Pasar lista</a>

    <?php if (session()->getFlashdata('success')): ?>
    <div class="alert-jp success mb-3"><i class="bi bi-check-circle-fill me-2"></i><?= esc(session()->getFlashdata('success')) ?></div>
    <?php endif; ?>
    <?php if (session()->getFlashdata('error')): ?>
    <div class="alert-jp error mb-3"><i class="bi bi-x-circle-fill me-2"></i><?= esc(session()->getFlashdata('error')) ?></div>
    <?php endif; ?>

    <div class="pl-head">
        <div>
            <h2><?= esc($session['title']) ?></h2>
            <p class="pl-sub">
                <?= date('d/m/Y', strtotime($session['session_date'])) ?>
                · <?= substr($session['start_time'], 0, 5) ?>–<?= substr($session['end_time'], 0, 5) ?>
                · <?= count($players) ?> alumno<?= count($players) !== 1 ? 's' : '' ?>
            </p>
        </div>
        <div class="pl-head-aside">
            <?php if ($isClosed): ?>
            <span class="pl-tag is-done"><i class="bi bi-lock-fill"></i>Sesión cerrada<span class="pl-tag-note"> · se puede reabrir</span></span>
            <?php elseif ($listaSaved): ?>
            <span class="pl-tag is-done"><i class="bi bi-clipboard2-check-fill"></i>Lista guardada · pendiente de cerrar</span>
            <?php else: ?>
            <span class="pl-tag is-pending"><i class="bi bi-hourglass-split"></i>Por pasar</span>
            <?php endif; ?>

            <?php if (!$isClosed && !empty($players)): ?>
            <details class="pl-help">
                <summary>
                    <i class="bi bi-question-circle"></i>Cómo funciona
                    <i class="bi bi-chevron-down pl-help-chev"></i>
                </summary>
                <div class="pl-help-body">
                    <p>Marca la asistencia y pulsa <b>Guardar y cerrar</b> cuando la clase haya terminado. Si aún no se ha impartido, usa <b>Guardar sin cerrar</b>. Una sesión cerrada siempre se puede <b>reabrir</b> para corregir.</p>
                    <ul>
                        <li><b>Ausente</b> — falta avisada o justificada.</li>
                        <li><b>No justificado</b> — no se presentó y no avisó. Permite descontar el bono como falta.</li>
                        <li><b>Descontar bono</b> — es <b>manual</b>: solo se descuenta si pulsas el botón. Disponible con el alumno <b>Presente</b>, <b>Confirmado</b> o <b>No justificado</b> (no hace falta guardar antes: al descontar se registra también esa asistencia). Consume 1 sesión del bono activo y queda registrado.</li>
                        <li><b>Devolver bono</b> — deshace un descuento. También se devuelve solo si cambias la asistencia a Ausente, Avisó o Pendiente.</li>
                    </ul>
                </div>
            </details>
            <?php endif; ?>
        </div>
    </div>

    <?php if (empty($players)): ?>
    <div class="card-jp" style="padding:32px;text-align:center;color:var(--text-muted)">
        <i class="bi bi-people" style="font-size:2rem;display:block;margin-bottom:8px"></i>
        No hay alumnos asignados a esta sesión.
    </div>
    <?php else: ?>

    <?php if ($isClosed): ?>
    <div class="pl-note">
        <i class="bi bi-lock-fill"></i>
        <span>Sesión cerrada: la asistencia está bloqueada. Pulsa <b>Reabrir sesión</b> para volver a editarla; no se pierde nada de lo registrado.</span>
    </div>
    <?php endif; ?>

    <div class="card-jp">
        <div class="card-jp-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
            <span class="card-jp-title"><i class="bi bi-people-fill me-2" style="color:var(--accent)"></i>Asistencia</span>
            <div class="pl-statline" id="lista-stats">
                <span><b id="cnt-present" style="color:#059669">0</b> presentes</span>
                <span><b id="cnt-absent" style="color:#dc2626">0</b> ausentes</span>
                <span><b id="cnt-unjustified" style="color:#b91c1c">0</b> no justif.</span>
                <span><b id="cnt-pending" style="color:#d97706">0</b> pendientes</span>
            </div>
        </div>

        <form id="form-lista" action="/clases/<?= $session['id'] ?>/lista" method="POST">
            <?= csrf_field() ?>

            <?php if (!$isClosed && count($players) > 1): ?>
            <div class="pl-bulk">
                <span>Marcar todos como:</span>
                <?php foreach ($groups['asistencia'] as $val => $label): ?>
                <button type="button" class="btn-jp btn-jp-sm btn-jp-secondary" data-bulk="<?= $val ?>"><?= esc($label) ?></button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="pl-scroll">
            <table class="table-jp" style="min-width:720px">
                <thead>
                    <tr>
                        <th style="width:26%">Alumno</th>
                        <th style="width:19%">Asistencia</th>
                        <th style="width:17%">Razón ausencia</th>
                        <th style="width:20%">Nota adicional</th>
                        <th style="width:18%;text-align:center">Bono</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($players as $p): ?>
                <?php
                    $uid       = (int)$p['user_id'];
                    $att       = $p['attendance'] ?? 'pending';
                    $reason    = $p['absence_reason'] ?? '';
                    $notes     = $p['absence_notes'] ?? '';
                    $bono      = $p['active_bono'] ?? null;
                    $deducted  = !empty($p['bono_deducted_at']);
                    $absLike   = \App\Services\ClasesService::attendanceIsAbsence($att);
                    $canDeduct = \App\Services\ClasesService::attendanceConsumesBono($att);
                    $remaining = $bono ? (int)$bono['sessions_remaining'] : null;
                ?>
                <tr data-uid="<?= $uid ?>">
                    <td>
                        <div style="font-weight:600"><?= esc($p['name']) ?></div>
                        <div style="font-size:12px;color:var(--text-muted)"><?= esc($p['email'] ?? '') ?></div>
                    </td>
                    <td>
                        <select name="attendance[<?= $uid ?>]" class="form-select-jp att-select" data-uid="<?= $uid ?>"
                                data-state="<?= esc($att) ?>" data-saved="<?= esc($att) ?>" style="width:100%" <?= $isClosed ? 'disabled' : '' ?>>
                            <optgroup label="Asistencia">
                                <?php foreach ($groups['asistencia'] as $val => $label): ?>
                                <option value="<?= $val ?>" <?= $att === $val ? 'selected' : '' ?>><?= esc($label) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                            <optgroup label="Convocatoria">
                                <?php foreach ($groups['convocatoria'] as $val => $label): ?>
                                <option value="<?= $val ?>" <?= $att === $val ? 'selected' : '' ?>><?= esc($label) ?></option>
                                <?php endforeach; ?>
                            </optgroup>
                        </select>
                    </td>
                    <td class="absence-col-<?= $uid ?>" style="<?= !$absLike ? 'opacity:.35;pointer-events:none' : '' ?>">
                        <select name="absence_reason[<?= $uid ?>]" class="form-select-jp" style="width:100%" <?= $isClosed ? 'disabled' : '' ?>>
                            <option value="">— Seleccionar —</option>
                            <?php foreach (($absenceReasons ?? []) as $r): ?>
                            <option value="<?= esc($r) ?>" <?= $reason === $r ? 'selected' : '' ?>><?= esc($r) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </td>
                    <td class="notes-col-<?= $uid ?>" style="<?= !$absLike ? 'opacity:.35;pointer-events:none' : '' ?>">
                        <input type="text" name="absence_notes[<?= $uid ?>]" class="form-control-jp"
                               placeholder="Nota opcional…" value="<?= esc($notes) ?>" style="width:100%" <?= $isClosed ? 'disabled' : '' ?>>
                    </td>
                    <td style="text-align:center">
                        <div class="bono-cell-<?= $uid ?>" style="display:flex;flex-direction:column;align-items:center;gap:3px">
                            <?php if ($bono): ?>
                            <span class="bono-remaining-<?= $uid ?> pl-bono-num <?= $remaining <= 1 ? 'is-low' : 'is-ok' ?>"><?= $remaining ?></span>
                            <span style="font-size:11px;color:var(--text-muted)"><?= esc($bono['bono_name'] ?? '') ?></span>
                            <?php elseif (!$deducted): ?>
                            <span class="pl-bono-empty" style="font-size:12px;color:var(--text-muted)">Sin bono activo</span>
                            <?php endif; ?>

                            <span class="pl-bono-action" data-uid="<?= $uid ?>">
                            <?php if ($deducted): ?>
                                <span class="pl-bono-done" title="Bono descontado el <?= date('d/m/Y \a \l\a\s H:i', strtotime($p['bono_deducted_at'])) ?>"><i class="bi bi-check-circle-fill me-1"></i>Descontado</span>
                                <?php if (!$isClosed): ?>
                                <button type="button" class="btn-jp btn-jp-sm pl-refund"
                                        data-session="<?= $session['id'] ?>" data-player="<?= $uid ?>">
                                    <i class="bi bi-arrow-counterclockwise me-1"></i>Devolver bono
                                </button>
                                <span class="pl-row-hint" <?= $canDeduct ? 'hidden' : '' ?>>Al guardar se le devolverá el bono (asistencia sin clase).</span>
                                <?php endif; ?>
                            <?php elseif ($bono && !$isClosed): ?>
                                <button type="button" class="btn-jp btn-jp-sm btn-deduct pl-deduct"
                                        data-session="<?= $session['id'] ?>" data-player="<?= $uid ?>"
                                        style="<?= $canDeduct ? '' : 'display:none' ?>">
                                    <i class="bi bi-dash-circle-fill me-1"></i>Descontar bono
                                </button>
                                <span class="pl-deduct-hint" style="font-size:11px;color:var(--text-muted);<?= $canDeduct ? 'display:none' : '' ?>">Marcar presente / no justif.</span>
                            <?php endif; ?>
                            </span>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            </div>

            <div class="pl-session-foot" style="justify-content:space-between">
                <a href="/clases/<?= $session['id'] ?>" class="btn-jp btn-jp-secondary btn-jp-sm">
                    <i class="bi bi-calendar-event me-1"></i>Ver sesión
                </a>
                <div style="display:flex;gap:8px;flex-wrap:wrap;align-items:center">
                    <?php if ($isClosed): ?>
                    <span style="font-size:12px;color:var(--text-muted)"><i class="bi bi-lock-fill me-1"></i>Cerrada · asistencia bloqueada</span>
                    <button type="submit" form="form-reabrir" class="btn-jp btn-jp-sm btn-jp-secondary">
                        <i class="bi bi-unlock-fill me-1"></i>Reabrir sesión
                    </button>
                    <?php elseif ($session['status'] === 'scheduled'): ?>
                    <button type="submit" name="cerrar" value="0" class="btn-jp btn-jp-sm btn-jp-secondary">
                        <i class="bi bi-floppy-fill me-1"></i>Guardar sin cerrar
                    </button>
                    <button type="submit" name="cerrar" value="1" id="btn-guardar-cerrar" class="btn-jp btn-jp-sm btn-jp-primary">
                        <i class="bi bi-check2-circle me-1"></i>Guardar y cerrar
                    </button>
                    <?php else: ?>
                    <button type="submit" name="cerrar" value="0" class="btn-jp btn-jp-sm btn-jp-primary">
                        <i class="bi bi-floppy-fill me-1"></i>Guardar asistencia
                    </button>
                    <?php endif; ?>
                </div>
            </div>
        </form>

        <?php if ($isClosed): ?>
        <form id="form-reabrir" action="/clases/<?= $session['id'] ?>/reabrir" method="POST" hidden><?= csrf_field() ?></form>
        <?php endif; ?>
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
    var form = document.getElementById('form-lista');
    if (!form) return;
    var dirty = false;

    // Estados que permiten descontar bono — misma lista que el servidor
    // (ClasesService::BONO_CONSUMING_ATTENDANCE), sin duplicar a mano.
    var CONSUMES_BONO = <?= json_encode(\App\Services\ClasesService::BONO_CONSUMING_ATTENDANCE) ?>;
    function canDeductFor(v) { return CONSUMES_BONO.indexOf(v) !== -1; }

    // Diálogo de confirmación de la plataforma (radix-ui.js). Última red de
    // seguridad al diálogo nativo solo si el componente no estuviera cargado.
    function askConfirm(opts) {
        if (window.RadixUI && typeof RadixUI.confirm === 'function') {
            return RadixUI.confirm(opts);
        }
        var txt = (opts.title || '') + (opts.description ? '\n\n' + opts.description : '');
        return Promise.resolve(window.confirm(txt));
    }

    // Hay cambios sin guardar si algún selector difiere de su valor guardado.
    function recomputeDirty() {
        dirty = false;
        document.querySelectorAll('.att-select').forEach(function(s) {
            if (s.value !== s.dataset.saved) dirty = true;
        });
    }

    function updateCounts() {
        var cnt = { present: 0, absent: 0, unjustified: 0, pending: 0 };
        document.querySelectorAll('.att-select').forEach(function(s) {
            if (cnt[s.value] !== undefined) cnt[s.value]++;
            else cnt.pending++;
        });
        [['cnt-present','present'],['cnt-absent','absent'],['cnt-unjustified','unjustified'],['cnt-pending','pending']]
            .forEach(function(p){ var el = document.getElementById(p[0]); if (el) el.textContent = cnt[p[1]]; });
    }

    function syncRow(sel) {
        var uid   = sel.dataset.uid;
        var absLike = (sel.value === 'absent' || sel.value === 'unjustified');
        var absCol = document.querySelector('.absence-col-' + uid);
        var notCol = document.querySelector('.notes-col-' + uid);
        [absCol, notCol].forEach(function(c) {
            if (!c) return;
            c.style.opacity = absLike ? '1' : '0.35';
            c.style.pointerEvents = absLike ? '' : 'none';
        });
        var canDeduct = canDeductFor(sel.value);
        var act = document.querySelector('.pl-bono-action[data-uid="' + uid + '"]');
        if (act) {
            var dBtn = act.querySelector('.pl-deduct');
            var hint = act.querySelector('.pl-deduct-hint');
            var rowHint = act.querySelector('.pl-row-hint');
            if (dBtn) dBtn.style.display = canDeduct ? '' : 'none';
            if (hint) hint.style.display = canDeduct ? 'none' : '';
            if (rowHint) rowHint.hidden = canDeduct;   // aviso "se devolverá el bono"
        }
        sel.dataset.state = sel.value;
    }

    document.querySelectorAll('.att-select').forEach(function(sel) {
        syncRow(sel);
        sel.addEventListener('change', function() { recomputeDirty(); syncRow(sel); updateCounts(); });
    });
    updateCounts();

    // Acciones masivas: fijan el estado de TODOS los alumnos a la vez.
    document.querySelectorAll('[data-bulk]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var target = this.dataset.bulk;
            var changed = false;
            document.querySelectorAll('.att-select').forEach(function(sel) {
                if (sel.value !== target) { sel.value = target; syncRow(sel); changed = true; }
            });
            if (changed) { recomputeDirty(); updateCounts(); }
        });
    });

    // Aviso al salir con cambios sin guardar
    form.addEventListener('submit', function() { dirty = false; });
    window.addEventListener('beforeunload', function(e) {
        if (dirty) { e.preventDefault(); e.returnValue = ''; }
    });

    // ── Bono: descontar / devolver (delegación: los botones se re-generan) ──
    var CSRF_NAME = <?= json_encode(csrf_token()) ?>;

    function setRemaining(playerId, n) {
        var cell = document.querySelector('.bono-cell-' + playerId);
        if (!cell || n === null || n === undefined) return;
        var remEl = cell.querySelector('.bono-remaining-' + playerId);
        if (!remEl) {
            // el bono estaba agotado (sin contador en pantalla) y ahora tiene
            // saldo tras la devolución: creamos el contador.
            var empty = cell.querySelector('.pl-bono-empty');
            remEl = document.createElement('span');
            remEl.className = 'bono-remaining-' + playerId + ' pl-bono-num';
            cell.insertBefore(remEl, empty || cell.firstChild);
            if (empty) empty.remove();
        }
        remEl.textContent = n;
        remEl.classList.toggle('is-low', n <= 1);
        remEl.classList.toggle('is-ok',  n > 1);
    }

    function renderDeducted(uid, sessionId) {
        var act = document.querySelector('.pl-bono-action[data-uid="' + uid + '"]');
        if (!act) return;
        var sel = document.querySelector('.att-select[data-uid="' + uid + '"]');
        var can = sel && canDeductFor(sel.value);
        act.innerHTML =
            '<span class="pl-bono-done" title="Bono descontado ahora"><i class="bi bi-check-circle-fill me-1"></i>Descontado</span>' +
            '<button type="button" class="btn-jp btn-jp-sm pl-refund" data-session="' + sessionId + '" data-player="' + uid + '">' +
            '<i class="bi bi-arrow-counterclockwise me-1"></i>Devolver bono</button>' +
            '<span class="pl-row-hint"' + (can ? ' hidden' : '') + '>Al guardar se le devolverá el bono (asistencia sin clase).</span>';
    }

    function renderDeductable(uid, sessionId) {
        var act = document.querySelector('.pl-bono-action[data-uid="' + uid + '"]');
        if (!act) return;
        var sel = document.querySelector('.att-select[data-uid="' + uid + '"]');
        var can = sel && canDeductFor(sel.value);
        act.innerHTML =
            '<button type="button" class="btn-jp btn-jp-sm btn-deduct pl-deduct" data-session="' + sessionId + '" data-player="' + uid + '"' +
            ' style="' + (can ? '' : 'display:none') + '"><i class="bi bi-dash-circle-fill me-1"></i>Descontar bono</button>' +
            '<span class="pl-deduct-hint" style="font-size:11px;color:var(--text-muted);' + (can ? 'display:none' : '') + '">Marcar presente / no justif.</span>';
    }

    function bonoRequest(url, btn, labelBusy, labelIdle, onOk, extraBody) {
        var body = extraBody ? Object.assign({}, extraBody) : {};
        body[CSRF_NAME] = '<?= csrf_hash() ?>';
        btn.disabled = true;
        var prev = btn.innerHTML;
        btn.innerHTML = labelBusy;
        fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': '<?= csrf_hash() ?>' },
            body: JSON.stringify(body)
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (data.success) { onOk(data); }
            else {
                if (!(window.handleApiError && window.handleApiError(data, 'clases.bono'))) {
                    showAlert(data.error || 'No se pudo completar la operación.');
                }
                btn.disabled = false;
                btn.innerHTML = labelIdle || prev;
            }
        })
        .catch(function() {
            showAlert('Error de red. Inténtalo de nuevo.');
            btn.disabled = false;
            btn.innerHTML = labelIdle || prev;
        });
    }

    form.addEventListener('click', function(ev) {
        var dBtn = ev.target.closest('.pl-deduct');
        var rBtn = ev.target.closest('.pl-refund');

        if (dBtn) {
            var sid = dBtn.dataset.session, pid = dBtn.dataset.player;
            var sel = document.querySelector('.att-select[data-uid="' + pid + '"]');
            var isUnj = sel && sel.value === 'unjustified';
            askConfirm({
                title: isUnj ? '¿Descontar bono por falta no justificada?' : '¿Descontar 1 sesión del bono?',
                description: isUnj
                    ? 'Se consume 1 sesión del bono activo del alumno y la falta queda registrada. Podrás devolverla si te equivocas.'
                    : 'Se consume 1 sesión del bono activo del alumno. Podrás devolverla si te equivocas.',
                confirmLabel: 'Descontar'
            }).then(function(ok) {
                if (!ok) return;
                // Enviamos la asistencia elegida: descontar bono también la
                // registra (no hace falta "Guardar" antes).
                bonoRequest('/clases/' + sid + '/jugadores/' + pid + '/descontar-bono', dBtn, 'Descontando…', null, function(data) {
                    setRemaining(pid, data.sessions_remaining);
                    renderDeducted(pid, sid);
                    if (sel) { sel.dataset.saved = sel.value; recomputeDirty(); }
                }, { attendance: sel ? sel.value : '' });
            });
        } else if (rBtn) {
            var sid2 = rBtn.dataset.session, pid2 = rBtn.dataset.player;
            askConfirm({
                title: '¿Devolver el bono?',
                description: 'Se añade 1 sesión de vuelta al bono del alumno y se deshace el descuento de esta clase.',
                confirmLabel: 'Devolver bono'
            }).then(function(ok) {
                if (!ok) return;
                bonoRequest('/clases/' + sid2 + '/jugadores/' + pid2 + '/devolver-bono', rBtn, 'Devolviendo…', null, function(data) {
                    setRemaining(pid2, data.sessions_remaining);
                    renderDeductable(pid2, sid2);
                    if (window.showAlert) showAlert('Bono devuelto.', 'success');
                });
            });
        }
    });

    // "Guardar y cerrar": avisa si algo se queda a medias. Cerrar ya no es
    // irreversible (existe "Reabrir sesión"), por eso avisa sin obligar.
    var btnGuardarCerrar = document.getElementById('btn-guardar-cerrar');
    if (btnGuardarCerrar) {
        btnGuardarCerrar.addEventListener('click', function(e) {
            var pending = 0;
            document.querySelectorAll('.att-select').forEach(function(s) {
                if (s.value === 'pending') pending++;
            });
            var sinBono = 0;
            document.querySelectorAll('.pl-deduct').forEach(function(b) {
                if (b.style.display !== 'none') sinBono++;   // presente con bono, sin descontar
            });

            var avisos = [];
            if (pending > 0) avisos.push(pending + (pending > 1 ? ' alumnos sin marcar' : ' alumno sin marcar'));
            if (sinBono > 0) avisos.push(sinBono + (sinBono > 1 ? ' presentes con bono sin descontar' : ' presente con bono sin descontar'));
            if (!avisos.length) return;   // nada que avisar → deja enviar el formulario

            e.preventDefault();
            askConfirm({
                title: 'Cerrar la sesión',
                description: 'Vas a cerrar con ' + avisos.join(' y ') + '. Podrás reabrirla para corregir.',
                confirmLabel: 'Cerrar sesión'
            }).then(function(ok) {
                if (!ok) return;
                var h = form.querySelector('input[type="hidden"][name="cerrar"]');
                if (!h) { h = document.createElement('input'); h.type = 'hidden'; h.name = 'cerrar'; form.appendChild(h); }
                h.value = '1';
                dirty = false;
                form.submit();
            });
        });
    }
})();
</script>

<?= $this->endSection() ?>
