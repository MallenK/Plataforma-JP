<?php
/**
 * Partial: selector de posición(es) — checkboxes múltiples.
 * Espera en scope: $selectedPositions (string[] de claves de
 * PlayerProfileModel::POSITIONS ya seleccionadas).
 *
 * Un alumno puede jugar en más de una posición (p. ej. "Extremo" y
 * "Mediapunta"); antes era un único texto libre, lo que llevaba a
 * valores largos tipo "Extremo/mediapunta" que desbordaban la ficha
 * (ver TICKET-002). Ahora se elige de un catálogo fijo y se guarda
 * como lista.
 */
$selectedPositions = $selectedPositions ?? [];
?>
<div class="form-group">
    <label class="form-label">Posición(es)</label>
    <div style="display:flex;flex-wrap:wrap;gap:6px 14px;padding:10px 12px;
                border:1px solid var(--border);border-radius:var(--radius-sm);
                background:var(--bg-input)">
        <?php foreach (\App\Models\PlayerProfileModel::POSITIONS as $key => $label): ?>
        <label style="display:flex;align-items:center;gap:5px;font-size:12.5px;
                       font-weight:500;color:var(--text-body);cursor:pointer;margin:0">
            <input type="checkbox" name="position[]" value="<?= esc($key) ?>"
                   <?= in_array($key, $selectedPositions, true) ? 'checked' : '' ?>>
            <?= esc($label) ?>
        </label>
        <?php endforeach; ?>
    </div>
    <small style="color:var(--text-muted);font-size:11px">Puedes marcar varias.</small>
</div>
