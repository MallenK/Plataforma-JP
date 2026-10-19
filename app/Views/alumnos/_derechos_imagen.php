<?php
/**
 * Interruptor «Derechos de imagen»: se guarda solo al pulsar (sin botón).
 * Variables: $playerId (int), $signed (bool).
 */
$playerId = (int) ($playerId ?? 0);
$signed   = !empty($signed);
?>
<form class="img-rights-form" data-url="<?= site_url('alumnos/' . $playerId . '/derechos-imagen') ?>" onsubmit="return false">
    <?= csrf_field() ?>
    <label style="display:inline-flex;align-items:center;gap:8px;margin:0;cursor:pointer">
        <span class="img-rights-label" style="font-size:13px;font-weight:600;color:<?= $signed ? 'var(--success)' : 'var(--text-muted)' ?>">
            <?= $signed ? 'Firmados' : 'Pendientes' ?>
        </span>
        <input type="checkbox" class="img-rights-check" <?= $signed ? 'checked' : '' ?>
               aria-label="Derechos de imagen firmados"
               style="width:18px;height:18px;cursor:pointer;accent-color:var(--accent)">
    </label>
</form>
<script>
(function () {
    if (window.__imgRightsBound) return;
    window.__imgRightsBound = true;
    document.addEventListener('change', function (e) {
        var box = e.target.closest ? e.target.closest('.img-rights-check') : null;
        if (!box) return;
        var form  = box.closest('.img-rights-form');
        var label = form.querySelector('.img-rights-label');
        var tokenInput = form.querySelector('input[type="hidden"]');
        var want = box.checked;
        var paint = function (on) {
            box.checked = on;
            label.textContent = on ? 'Firmados' : 'Pendientes';
            label.style.color = on ? 'var(--success)' : 'var(--text-muted)';
        };
        var body = new FormData();
        body.append(tokenInput.name, tokenInput.value);
        body.append('signed', want ? '1' : '0');
        box.disabled = true;
        paint(want);
        fetch(form.dataset.url, {
            method: 'POST', body: body, credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
        }).then(function (r) { return r.json().catch(function () { return {}; }); })
          .then(function (d) {
              if (d.csrf) tokenInput.value = d.csrf;
              if (!d.success) throw d;
              paint(!!d.signed);
          })
          .catch(function (d) {
              paint(!want);
              if (d && d.csrf) tokenInput.value = d.csrf;
              if (window.handleApiError) window.handleApiError(d || {}, 'derechos-imagen');
              else if (window.showAlert) window.showAlert((d && d.error) || 'No se pudo guardar el cambio', 'error');
          })
          .finally(function () { box.disabled = false; });
    });
})();
</script>
