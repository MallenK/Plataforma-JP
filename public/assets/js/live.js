/* JP Preparation — actualización en vivo sin recargar (Fase 1).
 *
 * Cuando llega un aviso (push con la pestaña abierta, o la campanita detecta uno nuevo),
 * las zonas de la pantalla marcadas con data-live se vuelven a pedir al servidor y se
 * sustituyen solas. Sin sondeos nuevos: reaprovecha el aviso que ya llega.
 *
 * Marcar una zona:   <div data-live="id-unico" data-live-kinds="class,bono"> … </div>
 *   kinds: class · bono · ticket · conversation · * (cualquier aviso)
 * Código propio de la página (p. ej. calendarios que se pintan con JS):
 *   JPLive.on(['class'], function () { CAL.load(); });
 *
 * Reglas de seguridad: nunca se pisa una zona donde el usuario está escribiendo o tiene
 * un desplegable abierto, la pestaña oculta espera a volver a ser visible, y un fallo
 * (sesión caducada, sin red) no hace nada.
 */
(function () {
    'use strict';
    if (window.JPLive) return;

    var DEBOUNCE_MS = 700;      // agrupa varios avisos seguidos en una sola recarga
    var MIN_GAP_MS  = 2500;     // nunca más de una recarga cada 2,5 s

    var hooks   = [];           // { kinds: Set, fn }
    var pending = new Set();
    var timer   = null;
    var lastRun = 0;
    var running = false;

    function normalize(kind) {
        var k = String(kind || '*');
        return (k === 'general' || k === '') ? '*' : k;
    }

    function wants(kindsAttr, kinds) {
        if (kinds.has('*')) return true;
        var list = String(kindsAttr || '*').split(',');
        for (var i = 0; i < list.length; i++) {
            var k = list[i].trim();
            if (k === '*' || kinds.has(k)) return true;
        }
        return false;
    }

    /** La zona no se toca si el usuario está interactuando dentro. */
    function busyRegion(el) {
        var a = document.activeElement;
        if (a && el.contains(a) && /^(INPUT|TEXTAREA|SELECT)$/.test(a.tagName)) return true;
        if (el.querySelector('details[open]')) return true;
        if (el.hasAttribute('data-live-skip')) return true;
        return false;
    }

    function schedule(kinds) {
        (kinds || ['*']).forEach(function (k) { pending.add(normalize(k)); });
        if (timer) clearTimeout(timer);
        timer = setTimeout(run, DEBOUNCE_MS);
    }

    async function run() {
        timer = null;
        if (running) { schedule(); return; }
        if (document.hidden) return;                       // se retoma al volver (visibilitychange)
        var wait = MIN_GAP_MS - (Date.now() - lastRun);
        if (wait > 0) { timer = setTimeout(run, wait); return; }

        var kinds = pending;
        pending = new Set();
        if (kinds.size === 0) return;

        running = true;
        lastRun = Date.now();
        try {
            hooks.forEach(function (h) {
                if (kinds.has('*') || Array.from(h.kinds).some(function (k) { return kinds.has(k); })) {
                    try { h.fn(Array.from(kinds)); } catch (e) { console.warn('[live] hook:', e); }
                }
            });

            var regions = Array.prototype.filter.call(document.querySelectorAll('[data-live]'), function (el) {
                return wants(el.getAttribute('data-live-kinds'), kinds);
            });
            if (regions.length) await refreshRegions(regions);
        } catch (e) {
            console.warn('[live]', e);
        } finally {
            running = false;
        }
    }

    async function refreshRegions(regions) {
        var res = await fetch(location.pathname + location.search, {
            credentials: 'same-origin',
            cache: 'no-store',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-JP-Live': '1' },
        });
        // Sesión caducada (redirige al login) o error: no tocar nada.
        if (!res.ok || res.redirected && /\/login/.test(res.url)) return;
        var html = await res.text();
        var doc  = new DOMParser().parseFromString(html, 'text/html');

        regions.forEach(function (el) {
            var id = el.getAttribute('data-live');
            var fresh = Array.prototype.find.call(doc.querySelectorAll('[data-live]'), function (n) { return n.getAttribute('data-live') === id; });
            if (!fresh || busyRegion(el)) return;
            if (el.innerHTML === fresh.innerHTML) return;
            el.innerHTML = fresh.innerHTML;
            el.dispatchEvent(new CustomEvent('jp:live-updated', { bubbles: true, detail: { id: id } }));
        });
    }

    // ── API ─────────────────────────────────────────────────────────────
    window.JPLive = {
        /** Registra código propio: fn se ejecuta cuando llega un aviso de alguno de esos tipos. */
        on: function (kinds, fn) {
            hooks.push({ kinds: new Set((kinds || ['*']).map(normalize)), fn: fn });
        },
        /** Pide una actualización (por si una acción propia quiere refrescar otras zonas). */
        refresh: function (kinds) { schedule(kinds); },
    };

    // Push recibido con la pestaña abierta: el aviso ya dice de qué trata (kind).
    window.addEventListener('jp:push', function (e) {
        schedule([(e.detail && e.detail.kind) || '*']);
    });

    // La campanita detectó avisos nuevos (usuarios sin push): lista de tipos.
    window.addEventListener('jp:notif-new', function (e) {
        // Con push activo el mismo aviso ya llegó por 'jp:push' hace un instante: no repetir.
        if (Date.now() - lastRun < 5000 && pending.size === 0) return;
        var kinds = (e.detail && e.detail.kinds && e.detail.kinds.length) ? e.detail.kinds : ['*'];
        schedule(kinds);
    });

    // Si llegaron avisos con la pestaña oculta, al volver se actualiza una vez.
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden && pending.size) schedule();
    });
    window.addEventListener('pageshow', function (e) { if (e.persisted) schedule(['*']); });

    // Tipos de aviso → los de notification.source_type (conversation, ticket, class, bono).
    window.JPLive._test = { wants: wants, normalize: normalize, busyRegion: busyRegion };
})();
