<?php
/**
 * Interfaz de las notificaciones push / instalación de la app (PWA).
 *   view('partials/push_ui', ['mode' => 'banner'])  → aviso descartable (layout)
 *   view('partials/push_ui', ['mode' => 'card'])    → tarjeta con ajustes (Centro de notificaciones)
 * Toda la lógica está en assets/js/pwa.js (window.JPPwa).
 */
$mode = $mode ?? 'card';
?>
<?php if ($mode === 'banner'): ?>
<?php /* d-flex lleva !important y anularía [hidden]: va en un contenedor interno. */ ?>
<div id="push-banner" class="alert alert-primary mb-3" role="status" hidden>
    <div class="d-flex align-items-center justify-content-between flex-wrap gap-2">
        <span><i class="bi bi-bell-fill me-2"></i><span id="push-banner-text"></span></span>
        <span class="d-flex gap-2">
            <button type="button" class="btn btn-sm btn-primary" id="push-banner-action"></button>
            <button type="button" class="btn btn-sm btn-outline-secondary" id="push-banner-dismiss">Ahora no</button>
        </span>
    </div>
</div>
<?php else: ?>
<div class="<?= !empty($embedded) ? '' : 'card mb-3' ?>" id="push-card" hidden>
    <div class="<?= !empty($embedded) ? '' : 'card-body ' ?>d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div>
            <div class="fw-semibold"><i class="bi bi-phone-vibrate me-2"></i>Avisos en este dispositivo</div>
            <div class="text-muted" style="font-size:13px" id="push-card-status">Comprobando…</div>
        </div>
        <div class="d-flex gap-2 flex-wrap" id="push-card-actions">
            <button type="button" class="btn btn-sm btn-primary" data-act="enable" hidden>
                <i class="bi bi-bell me-1"></i>Activar notificaciones
            </button>
            <button type="button" class="btn btn-sm btn-outline-secondary" data-act="test" hidden>
                <i class="bi bi-send me-1"></i>Enviar prueba
            </button>
            <button type="button" class="btn btn-sm btn-outline-danger" data-act="disable" hidden>
                <i class="bi bi-bell-slash me-1"></i>Desactivar
            </button>
            <button type="button" class="btn btn-sm btn-outline-primary" data-act="install" hidden>
                <i class="bi bi-download me-1"></i>Instalar la app
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<script>
(function () {
    var MODE = <?= json_encode($mode) ?>;
    var DISMISS_KEY = 'jp_push_banner_dismissed';
    var IOS_HELP = 'Para instalar la app en el iPhone/iPad: abre esta página en Safari, pulsa el botón Compartir (el cuadrado con la flecha hacia arriba), elige «Añadir a pantalla de inicio» y abre la app desde ese icono. Los avisos se activan después, desde la app instalada.';

    function store(op, k, v) { try { return op === 'get' ? localStorage.getItem(k) : localStorage.setItem(k, v); } catch (_) { return null; } }
    function alertMsg(msg, type) {
        if (typeof window.showAlert === 'function') window.showAlert(msg, type || 'error');
        else alert(msg);
    }
    function run(promise, okMsg) {
        return promise.then(function () { if (okMsg) alertMsg(okMsg, 'success'); })
                      .catch(function (e) { alertMsg(e && e.message ? e.message : 'No se pudo completar la acción.', 'error'); });
    }

    function renderBanner(s) {
        var box = document.getElementById('push-banner');
        if (!box) return;
        // En el Centro de notificaciones ya hay una tarjeta con los mismos controles.
        if (document.getElementById('push-card')) { box.hidden = true; return; }
        var dismissed = parseInt(store('get', DISMISS_KEY) || '0', 10);
        var snoozed = Date.now() - dismissed < 7 * 86400000;
        var text = '', action = '', handler = null;

        if (s.needsInstall) {
            // iPhone/iPad en el navegador: aquí no hay botón «Instalar», hay que explicarlo (con o sin push configurado)
            text = IOS_HELP; action = '';
        } else if (s.configured && s.supported && s.permission === 'default' && !s.subscribed) {
            text = 'Activa las notificaciones para enterarte al momento de mensajes, clases y avisos.';
            action = 'Activar'; handler = function () { return run(window.JPPwa.enable(), 'Notificaciones activadas en este dispositivo.'); };
        }
        box.hidden = !text || snoozed || s.subscribed;
        document.getElementById('push-banner-text').textContent = text;
        var btn = document.getElementById('push-banner-action');
        btn.hidden = !action; btn.textContent = action; btn.onclick = handler;
    }

    function renderCard(s) {
        var card = document.getElementById('push-card');
        if (!card) return;
        var status = document.getElementById('push-card-status');
        var show = function (act, on) { var b = card.querySelector('[data-act="' + act + '"]'); if (b) b.hidden = !on; };
        var msg;

        if (s.needsInstall) msg = IOS_HELP;
        else if (!s.configured) msg = 'Las notificaciones push no están configuradas en el servidor.';
        else if (!s.supported) msg = 'Este navegador no admite notificaciones push. Seguirás viéndolas en la campanita.';
        else if (s.permission === 'denied') msg = 'Las notificaciones están bloqueadas para este sitio. Permítelas desde los ajustes del navegador.';
        else if (s.subscribed) msg = 'Activadas: recibirás los avisos de la plataforma aunque no tengas la web abierta.';
        else msg = 'Desactivadas en este dispositivo. Actívalas para recibir los avisos al instante.';

        status.textContent = msg;
        show('enable', s.configured && s.supported && s.permission !== 'denied' && !s.subscribed);
        show('test', s.subscribed);
        show('disable', s.subscribed);
        show('install', s.canInstall);
        card.hidden = false;
    }

    function refresh() {
        if (!window.JPPwa) return;
        window.JPPwa.state().then(function (s) { MODE === 'banner' ? renderBanner(s) : renderCard(s); });
    }

    if (MODE === 'banner') {
        document.getElementById('push-banner-dismiss')?.addEventListener('click', function () {
            store('set', DISMISS_KEY, String(Date.now()));
            document.getElementById('push-banner').hidden = true;
        });
    } else {
        document.getElementById('push-card')?.addEventListener('click', function (e) {
            var b = e.target.closest('[data-act]');
            if (!b || !window.JPPwa) return;
            var P = window.JPPwa;
            if (b.dataset.act === 'enable')  run(P.enable(), 'Notificaciones activadas en este dispositivo.');
            if (b.dataset.act === 'disable') run(P.disable(), 'Notificaciones desactivadas en este dispositivo.');
            if (b.dataset.act === 'test')    run(P.test());
            if (b.dataset.act === 'install') run(P.install());
        });
    }

    window.addEventListener('jp:pwa-state', refresh);
    window.addEventListener('load', refresh);
})();
</script>
