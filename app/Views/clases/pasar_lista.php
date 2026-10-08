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
                        <li><b>Descontar bono</b> — es <b>manual</b>: solo se descuenta si pulsas el botón. Disponible con el alumno <b>Presente</b>, <b>Confirmado</b> o <b>No justificado</b> (no hace falta guardar antes: al descontar se registra también esa asistencia). Si el alumno tiene <b>varios bonos</b>, hay un botón por bono y eliges de cuál se descuenta (no hay ninguno por defecto). Queda registrado y puedes devolverlo o <b>cambiarlo de bono</b>.</li>
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

            <div class="pl-bulk">
                <?php if (!$isClosed && count($players) > 1): ?>
                <span>Marcar todos como:</span>
                <?php foreach ($groups['asistencia'] as $val => $label): ?>
                <button type="button" class="btn-jp btn-jp-sm btn-jp-secondary" data-bulk="<?= $val ?>"><?= esc($label) ?></button>
                <?php endforeach; ?>
                <?php endif; ?>
                <div class="pl-bulk-tools">
                    <div class="input-search"><i class="bi bi-search"></i><input type="text" id="pl-search" placeholder="Buscar alumno…" aria-label="Buscar alumno" autocomplete="off"></div>
                    <?= view('partials/list_view_toggle', ['key' => 'pasar-lista']) ?>
                </div>
            </div>

            <div class="pl-scroll">
            <table class="table-jp" id="pl-table" style="min-width:720px">
                <thead>
                    <tr>
                        <th style="width:<?= $canBonos ? 26 : 32 ?>%">Alumno</th>
                        <th style="width:<?= $canBonos ? 19 : 24 ?>%">Asistencia</th>
                        <th style="width:<?= $canBonos ? 17 : 21 ?>%">Razón ausencia</th>
                        <th style="width:<?= $canBonos ? 20 : 23 ?>%">Nota adicional</th>
                        <?php if ($canBonos): ?>
                        <th style="width:18%;text-align:center">Bono</th>
                        <?php endif; ?>
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
                    $usableBonos = $p['usable_bonos'] ?? [];
                    $dedBono     = $p['deducted_bono'] ?? null;
                ?>
                <tr data-uid="<?= $uid ?>" data-name="<?= esc($p['name'] ?? '', 'attr') ?>" data-email="<?= esc($p['email'] ?? '', 'attr') ?>">
                    <td>
                        <div style="font-weight:600"><?php if (in_array(session('role'), ['superadmin', 'admin', 'coach'], true)): ?><a href="<?= base_url('alumnos/' . $uid) ?>" class="row-link-anchor" title="Ver perfil del alumno"><?= esc($p['name']) ?></a><?php else: ?><?= esc($p['name']) ?><?php endif; ?></div>
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
                    <?php if ($canBonos): ?>
                    <td style="text-align:center">
                        <div class="bono-cell-<?= $uid ?>" style="display:flex;flex-direction:column;align-items:center;gap:3px">
                            <?php foreach ($usableBonos as $ub): $ubRem = (int)$ub['sessions_remaining']; ?>
                            <div class="pl-bono-line" data-bono="<?= (int)$ub['id'] ?>" data-name="<?= esc($ub['bono_name'] ?? '', 'attr') ?>"
                                 data-exp="<?= !empty($ub['expires_at']) ? date('d/m/Y', strtotime($ub['expires_at'])) : '' ?>" data-rem="<?= $ubRem ?>"
                                 style="display:flex;align-items:center;gap:6px">
                                <span class="bono-remaining-<?= $uid ?> pl-bono-num <?= $ubRem <= 1 ? 'is-low' : 'is-ok' ?>"><?= $ubRem ?></span>
                                <span class="pl-bono-name" style="font-size:11px;color:var(--text-muted)"><?= esc($ub['bono_name'] ?? '') ?><?= !empty($ub['expires_at']) ? ' · caduca ' . date('d/m/Y', strtotime($ub['expires_at'])) : '' ?></span>
                            </div>
                            <?php endforeach; ?>
                            <?php if (!$usableBonos && !$deducted): ?>
                            <span class="pl-bono-empty" style="font-size:12px;color:var(--text-muted);text-align:center" title="Si viene sin bono, la clase queda apuntada en Bonos > Clases sin bono. No se descuenta sola: se salda a mano con el bono que elijas.">Sin bono con saldo<br><span style="font-size:11px;color:#b91c1c">Si viene, quedará apuntada como clase sin bono</span></span>
                            <?php endif; ?>

                            <?php /* Los botones los pinta renderAction() (JS) a partir de estos datos */ ?>
                            <span class="pl-bono-action" data-uid="<?= $uid ?>" data-session="<?= (int)$session['id'] ?>"
                                  data-state="<?= $deducted ? 'deducted' : 'open' ?>" data-closed="<?= $isClosed ? '1' : '0' ?>"
                                  data-from="<?= (int)($dedBono['id'] ?? 0) ?>" data-from-name="<?= esc($dedBono['bono_name'] ?? '', 'attr') ?>"
                                  data-done-title="<?= $deducted ? esc('Bono descontado el ' . date('d/m/Y \a \l\a\s H:i', strtotime($p['bono_deducted_at'])), 'attr') : '' ?>"
                                  style="display:flex;flex-direction:column;align-items:center;gap:4px;width:100%"></span>
                        </div>
                    </td>
                    <?php endif; ?>
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

    // Con DataTables las filas fuera de la página / del filtro salen del DOM:
    // TODO acceso a filas pasa por aquí (no por document.querySelector).
    var plTable = document.getElementById('pl-table');
    var plDt = null;
    function plRows() {
        return plDt ? plDt.rows().nodes().toArray()
                    : Array.prototype.slice.call(plTable.querySelectorAll('tbody tr'));
    }
    function plAll(selector) {
        var out = [];
        plRows().forEach(function(tr) {
            Array.prototype.forEach.call(tr.querySelectorAll(selector), function(e) { out.push(e); });
        });
        return out;
    }
    function plRow(uid) {
        var rows = plRows();
        for (var i = 0; i < rows.length; i++) if (rows[i].dataset.uid === String(uid)) return rows[i];
        return null;
    }
    function plOne(uid, selector) {
        var tr = plRow(uid);
        return tr ? tr.querySelector(selector) : null;
    }
    // Los campos de filas fuera del DOM no se envían con el formulario:
    // se copian como hidden justo antes de enviar.
    function plSyncOffscreen() {
        Array.prototype.forEach.call(form.querySelectorAll('input[data-pl-off]'), function(e) { e.remove(); });
        plRows().forEach(function(tr) {
            if (form.contains(tr)) return;
            Array.prototype.forEach.call(tr.querySelectorAll('select[name],input[name],textarea[name]'), function(f) {
                if (f.disabled) return;
                var h = document.createElement('input');
                h.type = 'hidden'; h.name = f.name; h.value = f.value;
                h.setAttribute('data-pl-off', '1');
                form.appendChild(h);
            });
        });
    }

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
        plAll('.att-select').forEach(function(s) {
            if (s.value !== s.dataset.saved) dirty = true;
        });
    }

    function updateCounts() {
        var cnt = { present: 0, absent: 0, unjustified: 0, pending: 0 };
        plAll('.att-select').forEach(function(s) {
            if (cnt[s.value] !== undefined) cnt[s.value]++;
            else cnt.pending++;
        });
        [['cnt-present','present'],['cnt-absent','absent'],['cnt-unjustified','unjustified'],['cnt-pending','pending']]
            .forEach(function(p){ var el = document.getElementById(p[0]); if (el) el.textContent = cnt[p[1]]; });
    }

    function syncRow(sel) {
        var uid   = sel.dataset.uid;
        var absLike = (sel.value === 'absent' || sel.value === 'unjustified');
        var absCol = plOne(uid, '.absence-col-' + uid);
        var notCol = plOne(uid, '.notes-col-' + uid);
        [absCol, notCol].forEach(function(c) {
            if (!c) return;
            c.style.opacity = absLike ? '1' : '0.35';
            c.style.pointerEvents = absLike ? '' : 'none';
        });
        var canDeduct = canDeductFor(sel.value);
        var act = plOne(uid, '.pl-bono-action[data-uid="' + uid + '"]');
        if (act) {
            var dBtn = act.querySelector('.pl-deduct');
            var hint = act.querySelector('.pl-deduct-hint');
            var rowHint = act.querySelector('.pl-row-hint');
            if (dBtn) dBtn.style.display = canDeduct ? '' : 'none';
            if (hint) hint.style.display = canDeduct ? 'none' : '';
            if (rowHint) rowHint.hidden = canDeduct;   // aviso "se devolverá el bono"
        }
        sel.dataset.state = sel.value;
        renderAction(uid);
    }

    plAll('.att-select').forEach(function(sel) {
        syncRow(sel);
        sel.addEventListener('change', function() { recomputeDirty(); syncRow(sel); updateCounts(); });
    });
    updateCounts();

    // Buscador + paginación + vista lista/cuadrícula (DataTables). Los scripts
    // globales cargan después de este bloque → se espera al DOMContentLoaded.
    document.addEventListener('DOMContentLoaded', function() {
        if (!window.JPList || !plTable) return;
        plDt = JPList.init({
            table: plTable, key: 'pasar-lista', ordering: false,
            search: '#pl-search', searchAttrs: ['name', 'email'],
        });
    });

    // Acciones masivas: fijan el estado de TODOS los alumnos a la vez.
    document.querySelectorAll('[data-bulk]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var target = this.dataset.bulk;
            var changed = false;
            plAll('.att-select').forEach(function(sel) {
                if (sel.value !== target) { sel.value = target; syncRow(sel); changed = true; }
            });
            if (changed) { recomputeDirty(); updateCounts(); }
        });
    });

    // Aviso al salir con cambios sin guardar
    form.addEventListener('submit', function() { plSyncOffscreen(); dirty = false; });
    window.addEventListener('beforeunload', function(e) {
        if (dirty) { e.preventDefault(); e.returnValue = ''; }
    });

    // ── Bono: descontar / devolver (delegación: los botones se re-generan) ──
    var CSRF_NAME = <?= json_encode(csrf_token()) ?>;

    function escHtml(t) {
        return String(t == null ? '' : t).replace(/[&<>"']/g, function(c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    // Una línea por bono del alumno en la celda. El DOM es la fuente de verdad
    // del saldo mostrado: el selector de bono se rellena a partir de ellas.
    function bonoLines(uid) {
        var cell = plOne(uid, '.bono-cell-' + uid);
        return cell ? Array.prototype.slice.call(cell.querySelectorAll('.pl-bono-line')) : [];
    }
    function usableLines(uid) {
        return bonoLines(uid).filter(function(l) { return parseInt(l.dataset.rem, 10) > 0; });
    }
    function lineLabel(l) {
        return (l.dataset.name || 'Bono') + ' · quedan ' + l.dataset.rem + (l.dataset.exp ? ' · caduca ' + l.dataset.exp : '');
    }

    function setRemaining(playerId, n, bonoId, bonoName) {
        var cell = plOne(playerId, '.bono-cell-' + playerId);
        if (!cell || n === null || n === undefined) return;
        var line = bonoId
            ? cell.querySelector('.pl-bono-line[data-bono="' + bonoId + '"]')
            : cell.querySelector('.pl-bono-line');
        if (!line) {
            // el bono estaba agotado (sin línea en pantalla) y ahora tiene
            // saldo tras la devolución: creamos la línea.
            var empty = cell.querySelector('.pl-bono-empty');
            line = document.createElement('div');
            line.className = 'pl-bono-line';
            line.style.cssText = 'display:flex;align-items:center;gap:6px';
            line.dataset.bono = bonoId || '';
            line.dataset.name = bonoName || '';
            line.dataset.exp  = '';
            line.innerHTML = '<span class="bono-remaining-' + playerId + ' pl-bono-num"></span>' +
                '<span class="pl-bono-name" style="font-size:11px;color:var(--text-muted)">' + escHtml(bonoName || '') + '</span>';
            cell.insertBefore(line, empty || cell.firstChild);
            if (empty) empty.remove();
        }
        var remEl = line.querySelector('.pl-bono-num');
        line.dataset.rem = n;
        remEl.textContent = n;
        remEl.classList.toggle('is-low', n <= 1);
        remEl.classList.toggle('is-ok',  n > 1);
        renderAction(playerId);
    }

    // Botones de bono de la fila. Con VARIOS bonos con saldo hay un botón por
    // bono ("Descontar de «X»"): no hay ninguno por defecto, siempre se elige.
    // Con uno solo, el botón de siempre. Todo sale del estado de la fila
    // (data-* de .pl-bono-action) y de las líneas de bono de la celda.
    function renderAction(uid) {
        var act = plOne(uid, '.pl-bono-action[data-uid="' + uid + '"]');
        if (!act) return;
        var sid    = act.dataset.session;
        var closed = act.dataset.closed === '1';
        var sel    = plOne(uid, '.att-select');
        var can    = sel && canDeductFor(sel.value);
        var lines  = usableLines(uid);
        var html   = '';

        if (act.dataset.state === 'deducted') {
            html += '<span class="pl-bono-done" title="' + escHtml(act.dataset.doneTitle || 'Bono descontado') + '"><i class="bi bi-check-circle-fill me-1"></i>Descontado' +
                (act.dataset.fromName ? ' · ' + escHtml(act.dataset.fromName) : '') + '</span>';
            if (!closed) {
                var others = lines.filter(function(l) { return l.dataset.bono !== act.dataset.from; });
                if (others.length) {
                    html += '<span class="pl-bono-btns"><span class="pl-bono-btns-label">Pasar a otro bono:</span>' + others.map(function(l) {
                        return '<button type="button" class="btn-jp btn-jp-sm pl-change" data-session="' + sid + '" data-player="' + uid + '" data-bono="' + escHtml(l.dataset.bono) + '">' +
                            '<i class="bi bi-arrow-left-right me-1"></i>' + escHtml(lineLabel(l)) + '</button>';
                    }).join('') + '</span>';
                }
                html += '<button type="button" class="btn-jp btn-jp-sm pl-refund" data-session="' + sid + '" data-player="' + uid + '">' +
                    '<i class="bi bi-arrow-counterclockwise me-1"></i>Devolver bono</button>' +
                    '<span class="pl-row-hint"' + (can ? ' hidden' : '') + '>Al guardar se le devolverá el bono (asistencia sin clase).</span>';
            }
        } else if (!closed && lines.length) {
            if (!can) {
                html += '<span class="pl-deduct-hint" style="font-size:11px;color:var(--text-muted)">Marcar presente / no justif.</span>';
            } else if (lines.length === 1) {
                html += '<button type="button" class="btn-jp btn-jp-sm btn-deduct pl-deduct" data-session="' + sid + '" data-player="' + uid + '" data-bono="' + escHtml(lines[0].dataset.bono) + '">' +
                    '<i class="bi bi-dash-circle-fill me-1"></i>Descontar bono</button>';
            } else {
                html += '<span class="pl-bono-btns"><span class="pl-bono-btns-label">Descontar de:</span>' + lines.map(function(l) {
                    return '<button type="button" class="btn-jp btn-jp-sm btn-deduct pl-deduct" data-session="' + sid + '" data-player="' + uid + '" data-bono="' + escHtml(l.dataset.bono) + '">' +
                        '<i class="bi bi-dash-circle-fill me-1"></i>' + escHtml(lineLabel(l)) + '</button>';
                }).join('') + '</span>';
            }
        }
        act.innerHTML = html;
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
        var cBtn = ev.target.closest('.pl-change');

        function lineOf(pid, bonoId) {
            var cell = plOne(pid, '.bono-cell-' + pid);
            return cell ? cell.querySelector('.pl-bono-line[data-bono="' + bonoId + '"]') : null;
        }
        function setRowState(pid, state, fromId, fromName) {
            var act = plOne(pid, '.pl-bono-action[data-uid="' + pid + '"]');
            if (!act) return;
            act.dataset.state    = state;
            act.dataset.from     = fromId || '';
            act.dataset.fromName = fromName || '';
            act.dataset.doneTitle = state === 'deducted' ? 'Bono descontado ahora' : '';
        }

        if (dBtn) {
            var sid = dBtn.dataset.session, pid = dBtn.dataset.player, chosenId = dBtn.dataset.bono || '';
            var sel = plOne(pid, '.att-select');
            var isUnj = sel && sel.value === 'unjustified';
            var chosenLine = chosenId ? lineOf(pid, chosenId) : null;
            var bonoTxt = chosenLine ? 'del bono «' + (chosenLine.dataset.name || '') + '»' : 'de su bono';
            askConfirm({
                title: isUnj ? '¿Descontar bono por falta no justificada?' : '¿Descontar 1 sesión ' + bonoTxt + '?',
                description: isUnj
                    ? 'Se consume 1 sesión ' + bonoTxt + ' y la falta queda registrada. Podrás devolverla o cambiarla de bono si te equivocas.'
                    : 'Se consume 1 sesión ' + bonoTxt + '. Podrás devolverla o cambiarla de bono si te equivocas.',
                confirmLabel: 'Descontar'
            }).then(function(ok) {
                if (!ok) return;
                // Enviamos la asistencia elegida: descontar bono también la
                // registra (no hace falta "Guardar" antes).
                bonoRequest('/clases/' + sid + '/jugadores/' + pid + '/descontar-bono', dBtn, 'Descontando…', null, function(data) {
                    setRowState(pid, 'deducted', data.bono_id, data.bono_name);
                    setRemaining(pid, data.sessions_remaining, data.bono_id, data.bono_name);
                    if (sel) { sel.dataset.saved = sel.value; recomputeDirty(); }
                }, { attendance: sel ? sel.value : '', bono_id: chosenId });
            });
        } else if (cBtn) {
            var sid3 = cBtn.dataset.session, pid3 = cBtn.dataset.player, toId = cBtn.dataset.bono;
            var toLine = lineOf(pid3, toId);
            askConfirm({
                title: '¿Cambiar de bono?',
                description: 'La sesión se devuelve al bono actual y se descuenta de «' + (toLine ? toLine.dataset.name : '') + '».',
                confirmLabel: 'Cambiar bono'
            }).then(function(ok) {
                if (!ok) return;
                bonoRequest('/clases/' + sid3 + '/jugadores/' + pid3 + '/cambiar-bono', cBtn, 'Cambiando…', null, function(data) {
                    setRowState(pid3, 'deducted', data.to.id, data.to.bono_name);
                    if (data.from) setRemaining(pid3, data.from.sessions_remaining, data.from.id);
                    setRemaining(pid3, data.to.sessions_remaining, data.to.id, data.to.bono_name);
                    if (window.showAlert) showAlert('Sesión pasada a «' + (data.to.bono_name || 'otro bono') + '».', 'success');
                }, { bono_id: toId });
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
                    setRowState(pid2, 'open', '', '');
                    setRemaining(pid2, data.sessions_remaining, data.bono_id, data.bono_name);
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
            plAll('.att-select').forEach(function(s) {
                if (s.value === 'pending') pending++;
            });
            var sinBono = 0;
            plAll('.pl-deduct').forEach(function(b) {
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
                plSyncOffscreen();
                form.submit();
            });
        });
    }
})();

<?php $renewalClassId = session()->getFlashdata('offer_series_renewal_class_id'); ?>
<?php if (!empty($renewalClassId)): ?>
// Se acaba de cerrar la última sesión programada de una clase recurrente
// (ver ClasesService::cerrarSesion) y aún no se ha continuado la serie.
// Se ofrece generar el mes siguiente; si acepta, vamos a la ficha de la
// sesión con ?renovar=1 para abrir el modal ya precargado.
//
// Se espera a DOMContentLoaded porque este bloque vive en page_content,
// que el layout renderiza ANTES de cargar radix-ui.js (va en el footer de
// layouts/base.php) — sin esperar, RadixUI aún no existiría y se colaría
// el confirm() nativo del navegador.
document.addEventListener('DOMContentLoaded', function () {
    if (!window.RadixUI || typeof RadixUI.confirm !== 'function') return; // nunca alert()/confirm() nativo
    // Botón resaltado (confirmLabel) = "Cancelar clases": la opción segura
    // por defecto es NO continuar sin que el admin lo pida a propósito.
    RadixUI.confirm({
        title: '¿Continuar esta clase recurrente?',
        description: 'Era la última sesión programada de esta serie. ¿Quieres generar las sesiones del mes siguiente (mismos días, editable antes de confirmar)?',
        confirmLabel: 'Cancelar clases',
        cancelLabel: 'Continuar con las clases'
    }).then(function (cancelled) {
        if (!cancelled) window.location = '/clases/<?= (int) $session['id'] ?>?renovar=1';
    });
});
<?php endif; ?>
</script>

<?= $this->endSection() ?>
