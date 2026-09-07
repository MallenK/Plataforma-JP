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
        <?php if ($isClosed): ?>
        <span class="pl-tag is-done"><i class="bi bi-lock-fill"></i>Sesión cerrada</span>
        <?php elseif ($listaSaved): ?>
        <span class="pl-tag is-done"><i class="bi bi-clipboard2-check-fill"></i>Lista guardada · pendiente de cerrar</span>
        <?php else: ?>
        <span class="pl-tag is-pending"><i class="bi bi-hourglass-split"></i>Por pasar</span>
        <?php endif; ?>
    </div>

    <?php if (empty($players)): ?>
    <div class="card-jp" style="padding:32px;text-align:center;color:var(--text-muted)">
        <i class="bi bi-people" style="font-size:2rem;display:block;margin-bottom:8px"></i>
        No hay alumnos asignados a esta sesión.
    </div>
    <?php else: ?>

    <div class="pl-legend">
        <span><b>Ausente</b> — falta avisada o justificada.</span>
        <span><b>No justificado</b> — no se presentó y no avisó. Permite descontar el bono como falta.</span>
        <span><b>Descontar bono</b> — consume 1 sesión del bono activo del alumno. Queda registrado.</span>
    </div>

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

            <?php if (!$isClosed): ?>
            <div class="pl-bulk">
                <span>Marcar pendientes:</span>
                <button type="button" class="btn-jp btn-jp-sm btn-jp-secondary" data-bulk="present">Todos presentes</button>
                <button type="button" class="btn-jp btn-jp-sm btn-jp-secondary" data-bulk="absent">Todos ausentes</button>
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
                    $absLike   = in_array($att, ['absent', 'unjustified'], true);
                    $canDeduct = in_array($att, ['present', 'confirmed', 'unjustified'], true);
                    $remaining = $bono ? (int)$bono['sessions_remaining'] : null;
                ?>
                <tr data-uid="<?= $uid ?>">
                    <td>
                        <div style="font-weight:600"><?= esc($p['name']) ?></div>
                        <div style="font-size:12px;color:var(--text-muted)"><?= esc($p['email'] ?? '') ?></div>
                    </td>
                    <td>
                        <select name="attendance[<?= $uid ?>]" class="form-select-jp att-select" data-uid="<?= $uid ?>"
                                data-state="<?= esc($att) ?>" style="width:100%" <?= $isClosed ? 'disabled' : '' ?>>
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
                        <?php if ($bono): ?>
                        <div class="bono-cell-<?= $uid ?>" style="display:flex;flex-direction:column;align-items:center;gap:3px">
                            <span class="bono-remaining-<?= $uid ?> pl-bono-num <?= $remaining <= 1 ? 'is-low' : 'is-ok' ?>"><?= $remaining ?></span>
                            <span style="font-size:11px;color:var(--text-muted)"><?= esc($bono['bono_name'] ?? '') ?></span>
                            <?php if ($deducted): ?>
                            <span class="pl-bono-done"><i class="bi bi-check-circle-fill me-1"></i>Descontado</span>
                            <?php elseif ($canDeduct && !$isClosed): ?>
                            <button type="button" class="btn-jp btn-jp-sm btn-deduct pl-deduct"
                                    data-session="<?= $session['id'] ?>" data-player="<?= $uid ?>">
                                <i class="bi bi-dash-circle-fill me-1"></i>Descontar bono
                            </button>
                            <?php else: ?>
                            <span style="font-size:11px;color:var(--text-muted)">Marcar presente / no justif.</span>
                            <?php endif; ?>
                        </div>
                        <?php else: ?>
                        <span style="font-size:12px;color:var(--text-muted)">Sin bono activo</span>
                        <?php endif; ?>
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
                <?php if (!$isClosed): ?>
                <div style="display:flex;gap:8px;flex-wrap:wrap">
                    <button type="submit" class="btn-jp btn-jp-sm btn-jp-primary">
                        <i class="bi bi-floppy-fill me-1"></i>Guardar asistencia
                    </button>
                    <?php if ($session['status'] === 'scheduled'): ?>
                    <button type="button" id="btn-cerrar-sesion" class="btn-jp btn-jp-sm btn-jp-danger">
                        <i class="bi bi-lock-fill me-1"></i>Cerrar sesión
                    </button>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </form>

        <?php if ($session['status'] === 'scheduled'): ?>
        <form id="form-cerrar" action="/clases/<?= $session['id'] ?>/cerrar" method="POST" hidden><?= csrf_field() ?></form>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>

<script>
(function () {
    var form = document.getElementById('form-lista');
    if (!form) return;
    var dirty = false;

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
        var canDeduct = ['present','confirmed','unjustified'].indexOf(sel.value) !== -1;
        var cell = document.querySelector('.bono-cell-' + uid);
        if (cell) {
            var btn = cell.querySelector('.btn-deduct');
            if (btn) btn.style.display = canDeduct ? '' : 'none';
        }
        sel.dataset.state = sel.value;
    }

    document.querySelectorAll('.att-select').forEach(function(sel) {
        syncRow(sel);
        sel.addEventListener('change', function() { dirty = true; syncRow(sel); updateCounts(); });
    });
    updateCounts();

    // Acciones masivas
    document.querySelectorAll('[data-bulk]').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var target = this.dataset.bulk;
            document.querySelectorAll('.att-select').forEach(function(sel) {
                if (sel.value === 'pending') { sel.value = target; syncRow(sel); dirty = true; }
            });
            updateCounts();
        });
    });

    // Aviso al salir con cambios sin guardar
    form.addEventListener('submit', function() { dirty = false; });
    window.addEventListener('beforeunload', function(e) {
        if (dirty) { e.preventDefault(); e.returnValue = ''; }
    });

    // Descontar bono
    document.querySelectorAll('.btn-deduct').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var sessionId = this.dataset.session, playerId = this.dataset.player, self = this;
            var sel  = document.querySelector('.att-select[data-uid="' + playerId + '"]');
            var isUnj = sel && sel.value === 'unjustified';
            var msg = isUnj
                ? '¿Descontar 1 sesión del bono por falta NO justificada? Quedará registrada como falta.'
                : '¿Descontar 1 sesión del bono de este alumno?';
            if (!confirm(msg)) return;

            self.disabled = true;
            self.innerHTML = 'Descontando…';

            fetch('/clases/' + sessionId + '/jugadores/' + playerId + '/descontar-bono', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': '<?= csrf_hash() ?>' },
                body: JSON.stringify({ <?= json_encode(csrf_token()) ?>: '<?= csrf_hash() ?>' })
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    var cell = document.querySelector('.bono-cell-' + playerId);
                    var remEl = cell && cell.querySelector('.bono-remaining-' + playerId);
                    if (remEl) {
                        remEl.textContent = data.sessions_remaining;
                        remEl.classList.toggle('is-low', data.sessions_remaining <= 1);
                        remEl.classList.toggle('is-ok', data.sessions_remaining > 1);
                    }
                    self.outerHTML = '<span class="pl-bono-done"><i class="bi bi-check-circle-fill me-1"></i>Descontado</span>';
                } else {
                    if (!(window.handleApiError && window.handleApiError(data, 'clases.descontar-bono'))) {
                        showAlert(data.error || 'Error al descontar el bono.');
                    }
                    self.disabled = false;
                    self.innerHTML = '<i class="bi bi-dash-circle-fill me-1"></i>Descontar bono';
                }
            })
            .catch(function() {
                showAlert('Error de red. Inténtalo de nuevo.');
                self.disabled = false;
                self.innerHTML = '<i class="bi bi-dash-circle-fill me-1"></i>Descontar bono';
            });
        });
    });

    var btnCerrar = document.getElementById('btn-cerrar-sesion');
    if (btnCerrar) {
        btnCerrar.addEventListener('click', function() {
            if (!confirm('¿Cerrar esta sesión? Quedará como completada y ya no podrá editarse la asistencia.')) return;
            dirty = false;
            document.getElementById('form-cerrar').submit();
        });
    }
})();
</script>

<?= $this->endSection() ?>
