<?php
/**
 * Guía de instalación de la app (PWA) adaptada al dispositivo. Toda la lógica está en
 * assets/js/install-guide.js. Parámetros: $share (bool) → bloque «compartir» con enlace/QR.
 */
$share = !empty($share);
?>
<style>
.ig-wrap { display:flex; flex-direction:column; gap:14px; }
.ig-card { background:var(--bg-card,#fff); border:1px solid var(--border,#e2e8f0); border-radius:14px; padding:16px; }
.ig-main { border-color:var(--accent,#3b82f6); box-shadow:0 0 0 3px rgba(59,130,246,.10); }
.ig-h { font-size:1rem; font-weight:700; margin:0 0 12px; color:var(--text-h,#0f172a); }
.ig-steps { list-style:none; margin:0; padding:0; display:flex; flex-direction:column; gap:12px; }
.ig-steps li { display:flex; gap:12px; align-items:flex-start; }
.ig-n { flex:0 0 28px; height:28px; border-radius:50%; background:var(--accent,#3b82f6); color:#fff; font-weight:700; font-size:14px; display:grid; place-items:center; }
.ig-t { font-size:14.5px; line-height:1.5; color:var(--text-body,#475569); padding-top:2px; }
.ig-chip { display:inline-flex; align-items:center; gap:6px; vertical-align:middle; margin:2px 2px; padding:3px 10px; border-radius:8px; background:#eff6ff; color:#1d4ed8; font-size:13px; }
.ig-chip svg { flex:0 0 auto; }
.ig-note { font-size:12.5px; color:var(--text-muted,#94a3b8); margin:12px 0 0; }
.ig-warn { background:#fef3c7; color:#92400e; border-radius:10px; padding:10px 12px; font-size:14px; margin:0 0 12px; }
.ig-ok { display:flex; gap:10px; align-items:flex-start; margin:0; color:#047857; font-size:14.5px; }
.ig-btn { display:inline-flex; align-items:center; justify-content:center; min-height:46px; padding:0 20px; border:0; border-radius:10px; background:var(--accent,#3b82f6); color:#fff; font-weight:600; font-size:15px; text-decoration:none; margin-top:12px; cursor:pointer; }
.ig-btn:active { background:var(--accent-dark,#1d4ed8); }
.ig-btn-sm { min-height:40px; padding:0 14px; margin-top:0; font-size:14px; }
.ig-btn-wa { background:#16a34a; }
.ig-more { font-size:12px; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:var(--text-muted,#94a3b8); margin:6px 0 8px; }
.ig-others { display:flex; flex-direction:column; gap:8px; }
.ig-other { padding:0; }
.ig-other summary { padding:14px 16px; font-weight:600; font-size:14.5px; cursor:pointer; list-style:none; min-height:48px; display:flex; align-items:center; }
.ig-other summary::-webkit-details-marker { display:none; }
.ig-other summary::after { content:'▾'; margin-left:auto; color:var(--text-muted,#94a3b8); }
.ig-other[open] summary::after { content:'▴'; }
.ig-other > :not(summary) { margin-left:16px; margin-right:16px; }
.ig-other > :last-child { margin-bottom:16px; }
.ig-share-row { display:flex; gap:8px; flex-wrap:wrap; align-items:center; }
.ig-url { flex:1 1 220px; min-width:0; min-height:40px; border:1px solid var(--border,#e2e8f0); border-radius:10px; padding:0 12px; font-size:14px; background:#f8fafc; color:var(--text-body,#475569); }
.ig-qr { margin-top:14px; display:inline-block; padding:10px; background:#fff; border:1px solid var(--border,#e2e8f0); border-radius:12px; min-width:188px; min-height:188px; }
</style>

<div class="ig-wrap">
    <div id="install-guide" aria-live="polite"></div>
    <?php if ($share): ?>
    <section class="ig-card" id="install-share"></section>
    <?php endif; ?>
</div>

<script src="<?= base_url('assets/js/install-guide.js') ?>?v=<?= (int) @filemtime(FCPATH . 'assets/js/install-guide.js') ?>"></script>
<script>
(function () {
    function go() {
        JPInstall.mount(document.getElementById('install-guide'), {
            url: <?= json_encode(base_url('instalar')) ?>,
            share: <?= $share ? "'install-share'" : 'null' ?>
        });
    }
    if (document.readyState === 'complete') go(); else window.addEventListener('load', go);
})();
</script>
