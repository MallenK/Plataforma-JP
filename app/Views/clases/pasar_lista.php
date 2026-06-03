<?= $this->extend('layouts/app') ?>

<?php
$statusMap = [
    'scheduled' => ['Programada', '#2563eb', 'bi-calendar-event-fill'],
    'completed' => ['Completada', '#059669', 'bi-check-circle-fill'],
    'cancelled' => ['Cancelada',  '#dc2626', 'bi-x-circle-fill'],
];
[$statusLabel, $statusColor, $statusIcon] = $statusMap[$session['status']] ?? ['—', '#6b7280', 'bi-dash'];

$attendanceOpts = [
    'pending'   => 'Pendiente',
    'present'   => 'Presente',
    'absent'    => 'Ausente',
    'confirmed' => 'Confirmado',
    'declined'  => 'Declinado',
];
?>

<?= $this->section('page_content') ?>


<?php if (session()->getFlashdata('success')): ?>
<div class="alert-jp alert-jp-success mb-4"><?= session()->getFlashdata('success') ?></div>
<?php endif; ?>
<?php if (session()->getFlashdata('error')): ?>
<div class="alert-jp alert-jp-danger mb-4"><?= session()->getFlashdata('error') ?></div>
<?php endif; ?>

<div class="card-jp">
    <div class="card-jp-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
        <span style="font-weight:600;font-size:1rem">
            <i class="bi bi-people-fill me-2" style="color:#7c3aed"></i>
            Alumnos (<?= count($session['players']) ?>)
        </span>
        <?php if (!empty($session['players'])): ?>
        <div id="lista-stats" style="display:flex;gap:12px;font-size:13px;color:var(--text-muted)">
            <span><span id="cnt-present" style="font-weight:700;color:#059669">0</span> presentes</span>
            <span><span id="cnt-absent" style="font-weight:700;color:#dc2626">0</span> ausentes</span>
            <span><span id="cnt-pending" style="font-weight:700;color:#d97706">0</span> pendientes</span>
        </div>
        <?php endif; ?>
    </div>

    <?php if (empty($session['players'])): ?>
    <div style="padding:32px;text-align:center;color:var(--text-muted)">
        <i class="bi bi-people" style="font-size:2rem;display:block;margin-bottom:8px"></i>
        No hay alumnos asignados a esta sesión.
    </div>
    <?php else: ?>

    <form id="form-lista" action="/clases/<?= $session['id'] ?>/asistencia" method="POST">
        <?= csrf_field() ?>
        <input type="hidden" name="absence_reason_hidden" value="">

        <div style="overflow-x:auto">
        <table class="table-jp" style="min-width:700px">
            <thead>
                <tr>
                    <th style="width:28%">Alumno</th>
                    <th style="width:18%">Asistencia</th>
                    <th style="width:18%">Razón ausencia</th>
                    <th style="width:20%">Nota adicional</th>
                    <th style="width:16%;text-align:center">Bono</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($session['players'] as $p): ?>
            <?php
                $uid        = (int)$p['user_id'];
                $att        = $p['attendance'] ?? 'pending';
                $reason     = $p['absence_reason'] ?? '';
                $notes      = $p['absence_notes'] ?? '';
                $bono       = $p['active_bono'] ?? null;
                $deducted   = !empty($p['bono_deducted_at']);
                $isAbsent   = ($att === 'absent');
                $isPresent  = ($att === 'present');
            ?>
            <tr data-uid="<?= $uid ?>" data-attendance="<?= esc($att) ?>">
                <td>
                    <div style="font-weight:600;font-size:0.95rem"><?= esc($p['name']) ?></div>
                    <div style="font-size:12px;color:var(--text-muted)"><?= esc($p['email'] ?? '') ?></div>
                </td>
                <td>
                    <select name="attendance[<?= $uid ?>]"
                            class="form-select-jp att-select"
                            data-uid="<?= $uid ?>"
                            style="width:100%;font-size:13px">
                        <?php foreach ($attendanceOpts as $val => $label): ?>
                        <option value="<?= $val ?>" <?= $att === $val ? 'selected' : '' ?>>
                            <?= $label ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td class="absence-col-<?= $uid ?>" style="<?= !$isAbsent ? 'opacity:.35;pointer-events:none' : '' ?>">
                    <select name="absence_reason[<?= $uid ?>]"
                            class="form-select-jp"
                            style="width:100%;font-size:13px">
                        <option value="">— Seleccionar —</option>
                        <?php foreach ($absenceReasons as $r): ?>
                        <option value="<?= esc($r) ?>" <?= $reason === $r ? 'selected' : '' ?>>
                            <?= esc($r) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                </td>
                <td class="notes-col-<?= $uid ?>" style="<?= !$isAbsent ? 'opacity:.35;pointer-events:none' : '' ?>">
                    <input type="text"
                           name="absence_notes[<?= $uid ?>]"
                           class="form-control-jp"
                           placeholder="Nota opcional…"
                           value="<?= esc($notes) ?>"
                           style="width:100%;font-size:13px">
                </td>
                <td style="text-align:center">
                    <?php if ($bono): ?>
                    <div class="bono-cell-<?= $uid ?>" style="display:flex;flex-direction:column;align-items:center;gap:4px">
                        <span class="bono-remaining-<?= $uid ?>" style="font-weight:700;font-size:1.1rem;color:<?= ($bono['sessions_remaining'] <= 1) ? '#dc2626' : '#059669' ?>">
                            <?= (int)$bono['sessions_remaining'] ?>
                        </span>
                        <span style="font-size:11px;color:var(--text-muted)"><?= esc($bono['bono_name'] ?? '') ?></span>
                        <?php if ($deducted): ?>
                        <span style="font-size:11px;color:#059669;font-weight:600">
                            <i class="bi bi-check-circle-fill me-1"></i>Descontado
                        </span>
                        <?php elseif ($isPresent): ?>
                        <button type="button"
                                class="btn-jp btn-jp-sm btn-deduct"
                                data-session="<?= $session['id'] ?>"
                                data-player="<?= $uid ?>"
                                style="background:#ede9fe;color:#5b21b6;border:1px solid #c4b5fd;font-size:11px;padding:3px 8px;margin-top:2px">
                            <i class="bi bi-dash-circle-fill me-1"></i>Descontar bono
                        </button>
                        <?php else: ?>
                        <span style="font-size:11px;color:var(--text-muted)">Marcar presente</span>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <span style="font-size:12px;color:var(--text-muted)">Sin bono</span>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>

        <div style="padding:16px 20px;border-top:1px solid var(--border-color);display:flex;justify-content:flex-end;gap:10px">
            <a href="/clases/<?= $session['id'] ?>" class="btn-jp btn-jp-secondary btn-jp-sm">Cancelar</a>
            <button type="submit" class="btn-jp btn-jp-sm">
                <i class="bi bi-floppy-fill me-1"></i>Guardar asistencia
            </button>
        </div>
    </form>

    <?php endif; ?>
</div>

<script>
(function () {
    // Actualizar contadores al cargar
    function updateCounts() {
        var rows   = document.querySelectorAll('tr[data-uid]');
        var cnt    = { present: 0, absent: 0, pending: 0 };
        rows.forEach(function(r) {
            var v = r.querySelector('.att-select').value;
            if (cnt[v] !== undefined) cnt[v]++;
            else cnt.pending++;
        });
        var elP = document.getElementById('cnt-present');
        var elA = document.getElementById('cnt-absent');
        var elN = document.getElementById('cnt-pending');
        if (elP) elP.textContent = cnt.present;
        if (elA) elA.textContent = cnt.absent;
        if (elN) elN.textContent = cnt.pending;
    }

    // Toggle razón/nota según asistencia
    document.querySelectorAll('.att-select').forEach(function(sel) {
        sel.addEventListener('change', function() {
            var uid     = this.dataset.uid;
            var isAbs   = (this.value === 'absent');
            var isPres  = (this.value === 'present');
            var absCol  = document.querySelector('.absence-col-' + uid);
            var notCol  = document.querySelector('.notes-col-' + uid);
            var row     = document.querySelector('tr[data-uid="' + uid + '"]');

            if (absCol) { absCol.style.opacity = isAbs ? '1' : '0.35'; absCol.style.pointerEvents = isAbs ? '' : 'none'; }
            if (notCol) { notCol.style.opacity = isAbs ? '1' : '0.35'; notCol.style.pointerEvents = isAbs ? '' : 'none'; }

            // Mostrar/ocultar botón de descontar bono
            var cell = document.querySelector('.bono-cell-' + uid);
            if (cell) {
                var btn = cell.querySelector('.btn-deduct');
                var hint = cell.querySelector('.bono-hint');
                if (btn) btn.style.display = isPres ? '' : 'none';
            }

            row.dataset.attendance = this.value;
            updateCounts();
        });
    });

    updateCounts();

    // Descontar bono vía AJAX
    document.querySelectorAll('.btn-deduct').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var sessionId = this.dataset.session;
            var playerId  = this.dataset.player;
            var self      = this;

            if (!confirm('¿Descontar 1 sesión del bono de este jugador?')) return;

            self.disabled = true;
            self.textContent = 'Descontando…';

            fetch('/clases/' + sessionId + '/jugadores/' + playerId + '/descontar-bono', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || ''
                },
                body: JSON.stringify({ <?= json_encode(csrf_token()) ?>: '<?= csrf_hash() ?>' })
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (data.success) {
                    var rem = data.sessions_remaining;
                    var cell = document.querySelector('.bono-cell-' + playerId);
                    if (cell) {
                        var remEl = cell.querySelector('.bono-remaining-' + playerId);
                        if (remEl) {
                            remEl.textContent = rem;
                            remEl.style.color = rem <= 1 ? '#dc2626' : '#059669';
                        }
                        self.outerHTML = '<span style="font-size:11px;color:#059669;font-weight:600"><i class="bi bi-check-circle-fill me-1"></i>Descontado</span>';
                    }
                } else {
                    showAlert(data.error || 'Error al descontar el bono.');
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

    // Al cambiar asistencia a presente, mostrar botón si hay bono
    document.querySelectorAll('.att-select').forEach(function(sel) {
        var uid  = sel.dataset.uid;
        var cell = document.querySelector('.bono-cell-' + uid);
        if (!cell) return;
        var btn  = cell.querySelector('.btn-deduct');
        if (btn && sel.value !== 'present') btn.style.display = 'none';
    });
})();
</script>

<?= $this->endSection() ?>
