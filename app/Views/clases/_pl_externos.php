<?php
/**
 * Personas externas de una clase dentro de Pasar lista. Mismo aspecto que la asistencia de los
 * alumnos (tabla .table-jp, selector .form-select-jp, misma cabecera y contadores); no usan bono ni
 * justificación de ausencia. Se guarda con el resto (externo_attendance[...]).
 *
 * Variables: $externos (array), $isClosed (bool),
 *            $standalone (bool) → clase SOLO con externos: tarjeta propia con cabecera «Asistencia».
 *            Sin $standalone → bloque dentro de la tarjeta de alumnos, bajo su tabla.
 * Los selectores llevan la clase .ext-att-select (el JS de los alumnos no los toca).
 */
if (empty($externos)) { return; }
helper('attendance');
$plOpts     = ['pending' => attendance_meta('pending')['label'], 'present' => attendance_meta('present')['label'], 'absent' => attendance_meta('absent')['label']];
$standalone = !empty($standalone);
$cnt        = ['present' => 0, 'absent' => 0, 'pending' => 0];
foreach ($externos as $ex) { $cnt[$ex['attendance'] ?? 'pending'] = ($cnt[$ex['attendance'] ?? 'pending'] ?? 0) + 1; }
?>
<?php if ($standalone): ?>
<div class="card-jp-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px">
    <span class="card-jp-title"><i class="bi bi-people-fill me-2" style="color:var(--accent)"></i>Asistencia</span>
    <div class="pl-statline">
        <span><b data-ext-cnt="present" style="color:#059669"><?= $cnt['present'] ?></b> presentes</span>
        <span><b data-ext-cnt="absent" style="color:#dc2626"><?= $cnt['absent'] ?></b> ausentes</span>
        <span><b data-ext-cnt="pending" style="color:#d97706"><?= $cnt['pending'] ?></b> pendientes</span>
    </div>
</div>
<?php else: ?>
<div class="card-jp-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:10px;border-top:1px solid var(--border)">
    <span class="card-jp-title" style="font-size:13px"><i class="bi bi-person-badge-fill me-2" style="color:#0ea5e9"></i>Personas externas</span>
    <div class="pl-statline">
        <span><b data-ext-cnt="present" style="color:#059669"><?= $cnt['present'] ?></b> presentes</span>
        <span><b data-ext-cnt="absent" style="color:#dc2626"><?= $cnt['absent'] ?></b> ausentes</span>
        <span><b data-ext-cnt="pending" style="color:#d97706"><?= $cnt['pending'] ?></b> pendientes</span>
    </div>
</div>
<?php endif; ?>

<div class="pl-scroll" data-ext-table>
<table class="table-jp" style="min-width:720px">
    <thead>
        <tr>
            <th style="width:<?= $standalone ? 32 : 26 ?>%"><?= $standalone ? 'Persona' : 'Persona externa' ?></th>
            <th style="width:24%">Asistencia</th>
            <th>Observaciones</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($externos as $ex): ?>
        <tr>
            <td>
                <div style="font-weight:600"><?= esc($ex['name']) ?></div>
                <div style="font-size:12px;color:var(--text-muted)">Externo · sin cuenta de alumno</div>
            </td>
            <td>
                <select name="externo_attendance[<?= (int) $ex['link_id'] ?>]" class="form-select-jp ext-att-select" style="width:100%"
                        data-state="<?= esc($ex['attendance']) ?>" <?= !empty($isClosed) ? 'disabled' : '' ?> aria-label="Asistencia de <?= esc($ex['name'], 'attr') ?>">
                    <?php foreach ($plOpts as $val => $label): ?>
                    <option value="<?= $val ?>" <?= $ex['attendance'] === $val ? 'selected' : '' ?>><?= esc($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </td>
            <td style="font-size:12px;color:var(--text-muted)">No usa bono ni necesita justificar la ausencia.</td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</div>

<script>
(function () {
    var box = document.currentScript.previousElementSibling;   // .pl-scroll de esta tabla
    var root = box.parentNode;
    function recount() {
        var c = { present: 0, absent: 0, pending: 0 };
        box.querySelectorAll('.ext-att-select').forEach(function (s) { c[s.value] = (c[s.value] || 0) + 1; });
        Object.keys(c).forEach(function (k) {
            var el = root.querySelector('[data-ext-cnt="' + k + '"]');
            if (el) el.textContent = c[k];
        });
    }
    box.addEventListener('change', recount);
})();
</script>
