/* JP Preparation — PWA: service worker, instalación y notificaciones push.
 *
 * Compatibilidad: Chrome/Edge/Opera/Brave/Samsung/Firefox (Android y escritorio),
 * Safari macOS 13+ y Safari iOS/iPadOS 16.4+ (en iOS SOLO con la app instalada en
 * la pantalla de inicio). Donde no hay push, la campanita sigue sondeando.
 *
 * API pública: window.JPPwa.{state, enable, disable, test, install, setBadge}
 * Eventos:     'jp:push' (llega un push con la pestaña abierta), 'jp:pwa-state'.
 */
(function () {
    'use strict';

    var script = document.currentScript;
    var BASE = (script && script.dataset.base) || (window.APP_BASE ? window.APP_BASE + '/' : '/');
    if (BASE.slice(-1) !== '/') BASE += '/';

    var CFG = window.JP_PUSH || null; // solo en páginas autenticadas: { key, csrfName, csrfHash }
    var supportsSW = 'serviceWorker' in navigator && (window.isSecureContext || location.hostname === 'localhost');
    var supportsPush = supportsSW && 'PushManager' in window && 'Notification' in window;
    var ua = navigator.userAgent || '';
    var isIOS = /iPad|iPhone|iPod/.test(ua) || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    var isStandalone = window.matchMedia('(display-mode: standalone)').matches || window.navigator.standalone === true;
    var deferredInstall = null;

    function safeStorage(op, k, v) {
        try { return op === 'get' ? localStorage.getItem(k) : localStorage.setItem(k, v); } catch (_) { return null; }
    }
    function emit(name, detail) { window.dispatchEvent(new CustomEvent(name, { detail: detail })); }

    // ── Service worker ──────────────────────────────────────────────────
    var swReady = null;
    if (supportsSW) {
        swReady = navigator.serviceWorker.register(BASE + 'sw.js', { scope: BASE })
            .then(function () { return navigator.serviceWorker.ready; })
            .catch(function (e) { console.warn('[pwa] service worker:', e); return null; });

        navigator.serviceWorker.addEventListener('message', function (e) {
            if (e.data && e.data.type === 'jp-push') emit('jp:push', e.data.payload);
        });
    }

    // ── Instalación ─────────────────────────────────────────────────────
    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        deferredInstall = e;
        emit('jp:pwa-state');
    });
    window.addEventListener('appinstalled', function () {
        deferredInstall = null;
        isStandalone = true;
        emit('jp:pwa-state');
    });

    // ── Push ────────────────────────────────────────────────────────────
    function b64uToUint8(s) {
        var pad = '='.repeat((4 - s.length % 4) % 4);
        var raw = atob((s + pad).replace(/-/g, '+').replace(/_/g, '/'));
        var out = new Uint8Array(raw.length);
        for (var i = 0; i < raw.length; i++) out[i] = raw.charCodeAt(i);
        return out;
    }

    function api(path, body) {
        return fetch(BASE + path, {
            method: 'POST',
            credentials: 'same-origin',
            headers: { 'Content-Type': 'application/json', 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': CFG.csrfHash },
            body: JSON.stringify(body || {})
        }).then(function (res) {
            return res.json().catch(function () { return {}; }).then(function (data) {
                if (data.csrf) CFG.csrfHash = data.csrf;
                if (!res.ok || data.ok === false) throw new Error(data.error || ('Error ' + res.status));
                return data;
            });
        });
    }

    function currentSub() {
        if (!supportsPush || !swReady) return Promise.resolve(null);
        return swReady.then(function (reg) { return reg ? reg.pushManager.getSubscription() : null; });
    }

    /** @returns {Promise<{supported:boolean, configured:boolean, permission:string, subscribed:boolean, ios:boolean, standalone:boolean, needsInstall:boolean, canInstall:boolean}>} */
    function state() {
        return currentSub().then(function (sub) {
            return {
                supported: supportsPush,
                configured: !!(CFG && CFG.key),
                permission: supportsPush ? Notification.permission : 'unsupported',
                subscribed: !!sub,
                ios: isIOS,
                standalone: isStandalone,
                needsInstall: isIOS && !isStandalone && !supportsPush, // iOS Safari: push solo con la app instalada
                canInstall: !!deferredInstall && !isStandalone
            };
        });
    }

    function subscribeNow(reg) {
        var opts = { userVisibleOnly: true, applicationServerKey: b64uToUint8(CFG.key) };
        return reg.pushManager.subscribe(opts).catch(function (err) {
            // Cambió la clave VAPID del servidor: la suscripción vieja ya no vale.
            if (err && err.name === 'InvalidStateError') {
                return reg.pushManager.getSubscription()
                    .then(function (old) { return old ? old.unsubscribe() : null; })
                    .then(function () { return reg.pushManager.subscribe(opts); });
            }
            throw err;
        });
    }

    function enable() {
        if (!CFG || !CFG.key) return Promise.reject(new Error('Las notificaciones no están configuradas en el servidor.'));
        if (!supportsPush) return Promise.reject(new Error('Este navegador no admite notificaciones push.'));
        return Notification.requestPermission().then(function (perm) {
            if (perm !== 'granted') {
                throw new Error(perm === 'denied'
                    ? 'Las notificaciones están bloqueadas para este sitio. Actívalas en los ajustes del navegador.'
                    : 'No se concedió el permiso de notificaciones.');
            }
            return swReady;
        }).then(function (reg) {
            if (!reg) throw new Error('El service worker no está disponible.');
            return subscribeNow(reg);
        }).then(function (sub) {
            return api('push/subscribe', { subscription: sub.toJSON() }).catch(function (err) {
                return sub.unsubscribe().then(function () { throw err; }); // sin fila en servidor = sin suscripción local
            });
        }).then(function () { safeStorage('set', 'jp_push_synced', String(Date.now())); emit('jp:pwa-state'); });
    }

    function disable() {
        return currentSub().then(function (sub) {
            if (!sub) return null;
            var endpoint = sub.endpoint;
            return sub.unsubscribe().then(function () { return api('push/unsubscribe', { endpoint: endpoint }); });
        }).then(function () { emit('jp:pwa-state'); });
    }

    function test() { return api('push/test'); }

    function install() {
        if (!deferredInstall) return Promise.resolve(false);
        deferredInstall.prompt();
        return deferredInstall.userChoice.then(function (r) { deferredInstall = null; emit('jp:pwa-state'); return r.outcome === 'accepted'; });
    }

    /** Número en el icono de la app instalada (Badging API). */
    function setBadge(n) {
        try {
            if (n > 0 && navigator.setAppBadge) navigator.setAppBadge(n);
            else if (navigator.clearAppBadge) navigator.clearAppBadge();
        } catch (_) { /* sin soporte */ }
    }

    // Al cargar una página autenticada: si ya hay permiso, mantener la suscripción viva y
    // asociada al usuario ACTUAL (otro usuario puede haber entrado en este navegador).
    function resync() {
        if (!CFG || !CFG.key || !supportsPush || Notification.permission !== 'granted') return;
        var last = parseInt(safeStorage('get', 'jp_push_synced') || '0', 10);
        if (Date.now() - last < 6 * 3600 * 1000 && CFG.sessionHasSub) return;
        swReady && swReady.then(function (reg) {
            if (!reg) return null;
            return reg.pushManager.getSubscription().then(function (sub) { return sub || subscribeNow(reg); });
        }).then(function (sub) {
            if (!sub) return null;
            return api('push/subscribe', { subscription: sub.toJSON() }).then(function () { safeStorage('set', 'jp_push_synced', String(Date.now())); });
        }).catch(function (e) { console.warn('[pwa] resync push:', e && e.message); });
    }

    window.JPPwa = { state: state, enable: enable, disable: disable, test: test, install: install, setBadge: setBadge };
    if (CFG) { if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', resync); else resync(); }
})();
