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
            // Destello suave: se nota que algo cambió, sin tapar el contenido ni mover la pantalla.
            el.classList.remove('jp-refreshed'); void el.offsetWidth; el.classList.add('jp-refreshed');
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

    // ════════════════════════════════════════════════════════════════════
    //  FASE 2 — sondeo único (GET live/ping) cada 30 s con la pestaña visible
    //  Sustituye a los 3 sondeos sueltos de la barra superior (campanita, mensajes,
    //  tickets). Devuelve contadores + «sellos de cambio» por zona: si un sello cambia
    //  (alguien tocó una clase, un bono, un ticket) se actualizan las zonas de esa zona,
    //  haya habido aviso o no. Emite 'jp:live-ping' para que la barra superior pinte sus
    //  números.
    // ════════════════════════════════════════════════════════════════════
    var PING_EVERY_MS = 30000;
    var stamps = null;          // null = aún sin referencia (el primer sondeo no refresca nada)
    var maxId  = 0;
    var pingTimer = null;
    var pinging = false;

    function baseUrl() {
        var s = document.querySelector('script[data-base]');
        var b = (s && s.getAttribute('data-base')) || '/';
        return b.charAt(b.length - 1) === '/' ? b : b + '/';
    }

    /** Zonas cuyo sello cambió entre dos sondeos. Pura. */
    function changedKinds(prev, next) {
        var out = [];
        if (!prev || !next) return out;
        Object.keys(next).forEach(function (k) {
            if (next[k] != null && prev[k] != null && next[k] !== prev[k]) out.push(k);
        });
        return out;
    }

    async function ping() {
        if (pinging) return;
        pinging = true;
        try {
            var res = await fetch(baseUrl() + 'live/ping?since=' + maxId, {
                credentials: 'same-origin',
                cache: 'no-store',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
            });
            if (!res.ok || res.redirected) return;                // sesión caducada: no insistir
            var data = await res.json();

            var kinds = (data.new_kinds || []).slice();
            changedKinds(stamps, data.stamps).forEach(function (k) { if (kinds.indexOf(k) < 0) kinds.push(k); });
            stamps = data.stamps || stamps;
            maxId  = Math.max(maxId, parseInt(data.max_id, 10) || 0);

            window.dispatchEvent(new CustomEvent('jp:live-ping', { detail: data }));

            // Con push el mismo cambio ya llegó por 'jp:push' hace un instante: no repetir.
            if (kinds.length && !(Date.now() - lastRun < 5000 && pending.size === 0)) schedule(kinds);
        } catch (e) {
            /* sin red: se reintenta en el próximo ciclo */
        } finally {
            pinging = false;
        }
    }

    function startPinging() {
        if (pingTimer) return;
        ping();
        pingTimer = setInterval(function () { if (!document.hidden) ping(); }, PING_EVERY_MS);
        document.addEventListener('visibilitychange', function () { if (!document.hidden) ping(); });
        window.addEventListener('jp:push', function () { ping(); }); // refresca los números al instante
    }

    // ════════════════════════════════════════════════════════════════════
    //  FASE 3 — formularios sin recarga:  <form data-live-form> …
    //  Se envía por fetch, el servidor responde como siempre (redirige a la página con su
    //  mensaje) y de esa respuesta se toman las zonas data-live y el mensaje, que sale como
    //  aviso emergente. Sin JS, o si algo falla, el formulario funciona igual que antes.
    //  Los formularios con confirmación (data-ru-confirm) siguen su camino normal.
    // ════════════════════════════════════════════════════════════════════
    // Navegación aislada para poder sustituirla en los tests.
    var nav = { assign: function (u) { location.assign(u); }, reload: function () { location.reload(); } };

    function toast(msg, type) {
        if (typeof window.showAlert === 'function') window.showAlert(msg, type);
    }

    /** Mensajes (.alert-jp) de una página ya parseada → [{type, text}]. */
    function flashFrom(doc) {
        return Array.prototype.map.call(doc.querySelectorAll('.alert-jp'), function (el) {
            return { type: el.classList.contains('error') ? 'error' : 'success', text: (el.textContent || '').trim() };
        }).filter(function (m) { return m.text; });
    }

    async function submitLiveForm(form, submitter) {
        var buttons = form.querySelectorAll('button, input[type=submit]');
        buttons.forEach(function (b) { b.disabled = true; });
        form.setAttribute('aria-busy', 'true');
        // Indicador de carga: barra superior + spinner en el botón pulsado (si el cargador está disponible).
        if (window.JPLoader) { window.JPLoader.start(); if (submitter) window.JPLoader.busy(submitter, true); }
        try {
            var res = await fetch(form.getAttribute('action') || location.href, {
                method: (form.getAttribute('method') || 'POST').toUpperCase(),
                body: new FormData(form, submitter || undefined),
                credentials: 'same-origin',
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-JP-Live': '1' },
            });
            if (!res.ok) throw new Error('http ' + res.status);

            var finalUrl = new URL(res.url, location.href);
            if (/\/login/.test(finalUrl.pathname) || finalUrl.pathname !== location.pathname) {
                nav.assign(res.url);                              // otra pantalla: navegación normal
                return;
            }

            var doc = new DOMParser().parseFromString(await res.text(), 'text/html');
            var swapped = 0;
            document.querySelectorAll('[data-live]').forEach(function (el) {
                var id = el.getAttribute('data-live');
                var fresh = Array.prototype.find.call(doc.querySelectorAll('[data-live]'), function (n) { return n.getAttribute('data-live') === id; });
                if (!fresh) return;
                // La zona del propio formulario SÍ se actualiza (es lo que acaba de cambiar).
                if (!el.contains(form) && busyRegion(el)) return;
                if (el.innerHTML !== fresh.innerHTML) { el.innerHTML = fresh.innerHTML; swapped++; }
            });

            var msgs = flashFrom(doc);
            msgs.forEach(function (m) { toast(m.text, m.type); });
            if (!swapped) { nav.reload(); return; }               // nada que pintar: recarga normal
            window.dispatchEvent(new CustomEvent('jp:live-form-done', { detail: { form: form, messages: msgs } }));
            // Código propio (calendarios…): las zonas ya están al día, no hace falta pedirlas otra vez.
            hooks.forEach(function (h) { try { h.fn(['*']); } catch (err) { console.warn('[live] hook:', err); } });
        } catch (e) {
            toast('No se pudo completar la acción. Se recargará la página.', 'error');
            setTimeout(function () { nav.reload(); }, 1200);
        } finally {
            if (window.JPLoader) { window.JPLoader.done(); if (submitter) window.JPLoader.busy(submitter, false); }
        }
    }

    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-live-form')) return;
        if (form.hasAttribute('data-ru-confirm') || e.defaultPrevented) return;
        if (!window.fetch || !window.FormData || !window.DOMParser) return;
        if (form.getAttribute('aria-busy') === 'true') { e.preventDefault(); return; }   // doble clic
        e.preventDefault();
        submitLiveForm(form, e.submitter);
    });

    // Arranque: solo en pantallas con barra superior (usuario con sesión).
    function boot() { if (document.getElementById('topbar-notif-btn')) startPinging(); }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', boot); else boot();

    window.JPLive.ping = ping;
    window.JPLive.submitForm = submitLiveForm;      // lo usa radix-ui.js al confirmar un formulario data-live-form
    window.JPLive._test ={ wants: wants, normalize: normalize, busyRegion: busyRegion, changedKinds: changedKinds, flashFrom: flashFrom, nav: nav };
})();
