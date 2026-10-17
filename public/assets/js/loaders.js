/* JP Preparation — indicadores de carga (JPLoader).
 *
 * 1. Barra de progreso superior: se enciende sola al pulsar un enlace que va a recargar la
 *    página o al enviar un formulario normal, y la usan las cargas sin recarga (live.js,
 *    soft-nav.js) con JPLoader.start() / JPLoader.done().
 * 2. Botones ocupados: al enviar un formulario el botón se desactiva y muestra un spinner
 *    (evita el doble clic). Excluir: data-no-loader en el formulario o el botón.
 * 3. Esqueleto (skeleton) para regiones que se están cargando: JPLoader.skeleton(el, true).
 *
 * Todo es opcional: si este fichero no carga, la plataforma funciona igual.
 */
(function (root, factory) {
    var api = factory(root);
    if (typeof module === 'object' && module.exports) { module.exports = api; }
    root.JPLoader = api;
    if (root.document) { api.boot(); }
})(typeof window !== 'undefined' ? window : globalThis, function (window) {
    'use strict';

    var SAFETY_MS = 9000;          // si algo nunca termina (p. ej. una descarga), la barra se apaga sola
    var SKELETON_DELAY_MS = 140;   // las cargas rápidas no enseñan esqueleto (evita parpadeos)

    var active = 0;                // cargas en curso
    var bar = null, fill = null, trickle = null, safety = null, level = 0;
    var statusEl = null;

    function doc() { return window.document; }

    // ── Barra superior ─────────────────────────────────────────────
    function ensureBar() {
        if (bar || !doc() || !doc().body) return;
        bar = doc().createElement('div');
        bar.id = 'jp-topbar';
        bar.setAttribute('aria-hidden', 'true');
        bar.innerHTML = '<div class="jp-topbar-bar"></div>';
        fill = bar.firstChild;
        doc().body.appendChild(bar);
    }

    function setLevel(n) {
        level = Math.max(level, Math.min(n, .94));
        if (fill) fill.style.transform = 'scaleX(' + level + ')';
    }

    function start() {
        ensureBar();
        if (!bar) return;
        active++;
        if (active === 1) {
            level = 0;
            bar.classList.add('is-on');
            setLevel(.08);
            clearInterval(trickle);
            // Avanza cada vez más despacio sin llegar nunca al final: la sensación de «está trabajando».
            trickle = setInterval(function () { setLevel(level + (1 - level) * .12); }, 260);
        }
        clearTimeout(safety);
        // Si nada termina (descarga que no recarga la página, red caída…), se libera todo:
        // barra apagada y botones otra vez utilizables.
        safety = setTimeout(reset, SAFETY_MS);
    }

    function done() {
        if (!bar) return;
        active = Math.max(0, active - 1);
        if (active > 0) return;
        clearInterval(trickle);
        clearTimeout(safety);
        level = 1;
        if (fill) fill.style.transform = 'scaleX(1)';
        setTimeout(function () {
            if (active === 0 && bar) { bar.classList.remove('is-on'); level = 0; if (fill) fill.style.transform = 'scaleX(0)'; }
        }, 220);
    }

    function reset() {
        active = 1; done();
        busyButtons.forEach(function (b) { setBusy(b, false); });
    }

    // ── Botones ocupados ───────────────────────────────────────────
    var busyButtons = [];

    function setBusy(btn, on, label) {
        if (!btn) return;
        if (on) {
            if (btn.classList.contains('is-loading')) return;
            btn.classList.add('is-loading');
            btn.setAttribute('aria-busy', 'true');
            btn._jpPrev = { html: btn.innerHTML, disabled: btn.disabled, minWidth: btn.style.minWidth, value: btn.value };
            if (btn.offsetWidth) btn.style.minWidth = btn.offsetWidth + 'px';   // el botón no «salta» de tamaño
            if (btn.tagName === 'INPUT') {
                btn.value = label || btn.value;
            } else {
                btn.innerHTML = '<span class="jp-btn-spin" aria-hidden="true"></span>' + (label ? escapeHtml(label) : btn.innerHTML);
            }
            btn.disabled = true;
            busyButtons.push(btn);
        } else if (btn._jpPrev) {
            btn.classList.remove('is-loading');
            btn.removeAttribute('aria-busy');
            if (btn.tagName === 'INPUT') { btn.value = btn._jpPrev.value; } else { btn.innerHTML = btn._jpPrev.html; }
            btn.disabled = btn._jpPrev.disabled;
            btn.style.minWidth = btn._jpPrev.minWidth;
            btn._jpPrev = null;
            busyButtons = busyButtons.filter(function (b) { return b !== btn; });
        }
    }

    function escapeHtml(s) {
        return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; });
    }

    /** Ocupa los botones de envío de un formulario (o solo el pulsado). */
    function busyForm(form, submitter, on) {
        if (!form) return;
        var target = submitter && submitter.form === form ? [submitter]
            : Array.prototype.slice.call(form.querySelectorAll('button[type=submit], button:not([type]), input[type=submit]'));
        target.forEach(function (b) { if (!b.hasAttribute('data-no-loader')) setBusy(b, on); });
        if (on) form.setAttribute('aria-busy', 'true'); else form.removeAttribute('aria-busy');
    }

    // ── Esqueleto de región ────────────────────────────────────────
    var SKELETON_HTML =
        '<div class="jp-skel-overlay" aria-hidden="true">' +
            '<div class="d-flex gap-2 mb-3"><span class="jp-skel jp-skel-pill"></span><span class="jp-skel jp-skel-pill"></span><span class="jp-skel jp-skel-pill"></span></div>' +
            '<div class="row g-3 mb-3">' +
                '<div class="col-6 col-lg-3"><span class="jp-skel jp-skel-metric"></span></div>' +
                '<div class="col-6 col-lg-3"><span class="jp-skel jp-skel-metric"></span></div>' +
                '<div class="col-6 col-lg-3"><span class="jp-skel jp-skel-metric"></span></div>' +
                '<div class="col-6 col-lg-3"><span class="jp-skel jp-skel-metric"></span></div>' +
            '</div>' +
            '<div class="jp-skel-card"><span class="jp-skel jp-skel-title"></span>' +
                '<span class="jp-skel jp-skel-line"></span><span class="jp-skel jp-skel-line" style="width:92%"></span>' +
                '<span class="jp-skel jp-skel-line" style="width:84%"></span><span class="jp-skel jp-skel-line" style="width:90%"></span>' +
                '<span class="jp-skel jp-skel-line" style="width:70%"></span>' +
            '</div>' +
        '</div>';

    /** Activa/desactiva el esqueleto sobre una región. Devuelve una función para cancelar. */
    function skeleton(region, on) {
        if (!region) return function () {};
        if (!on) {
            clearTimeout(region._jpSkelTimer);
            region.classList.remove('jp-busy');
            region.removeAttribute('aria-busy');
            var old = region.querySelector(':scope > .jp-skel-overlay');
            if (old) old.remove();
            announce('');
            return function () {};
        }
        region.setAttribute('aria-busy', 'true');
        clearTimeout(region._jpSkelTimer);
        region._jpSkelTimer = setTimeout(function () {
            if (region.getAttribute('aria-busy') !== 'true' || region.querySelector(':scope > .jp-skel-overlay')) return;
            region.classList.add('jp-busy');
            region.insertAdjacentHTML('afterbegin', SKELETON_HTML);
            announce('Cargando…');
        }, SKELETON_DELAY_MS);
        return function () { skeleton(region, false); };
    }

    /** Aviso para lectores de pantalla (zona aria-live). */
    function announce(msg) {
        if (!doc() || !doc().body) return;
        if (!statusEl) {
            statusEl = doc().createElement('div');
            statusEl.className = 'jp-sr-status';
            statusEl.setAttribute('role', 'status');
            statusEl.setAttribute('aria-live', 'polite');
            doc().body.appendChild(statusEl);
        }
        statusEl.textContent = msg;
    }

    /** Anima la entrada de contenido recién pintado. */
    function enter(el) {
        if (!el) return;
        el.classList.remove('jp-enter');
        void el.offsetWidth;                 // reinicia la animación
        el.classList.add('jp-enter');
        setTimeout(function () { el.classList.remove('jp-enter'); }, 420);
    }

    // ── Enganches automáticos (navegación y formularios «de siempre») ──
    var NOT_NAV = /^(#|javascript:|mailto:|tel:|blob:|data:)/i;
    var DOWNLOAD = /(\.(pdf|csv|xlsx?|docx?|zip|png|jpe?g|gif|webp|mp4)(\?|$))|([?&]export=)|(\/(adjunto|download|descargar|imprimir)(\/|\?|$))/i;

    /** ¿Este clic en un enlace va a cargar otra página completa? (pura, para tests) */
    function isFullNavigation(a, e, here) {
        if (!a || !a.getAttribute) return false;
        var href = a.getAttribute('href') || '';
        if (!href || NOT_NAV.test(href)) return false;
        if (a.hasAttribute('download') || a.hasAttribute('data-no-loader')) return false;
        var t = a.getAttribute('target');
        if (t && t !== '_self') return false;
        if (e && (e.defaultPrevented || e.button > 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey)) return false;
        var url;
        try { url = new URL(a.href, here || (window.location && window.location.href)); } catch (err) { return false; }
        if (url.origin !== (here ? new URL(here).origin : window.location.origin)) return false;
        if (DOWNLOAD.test(url.pathname + url.search)) return false;
        var cur = here ? new URL(here) : window.location;
        if (url.pathname === cur.pathname && url.search === cur.search && url.hash) return false;   // ancla en la misma página
        return true;
    }

    function boot() {
        var d = doc();
        if (!d || boot.done) return;
        boot.done = true;

        // Enlaces que recargan la página
        d.addEventListener('click', function (e) {
            var a = e.target && e.target.closest ? e.target.closest('a[href]') : null;
            // Un instante después: si otro código (soft-nav) lo ha gestionado, defaultPrevented ya es true.
            setTimeout(function () { if (isFullNavigation(a, e)) start(); }, 0);
        });

        // Formularios que recargan la página (los gestionados con fetch hacen preventDefault)
        d.addEventListener('submit', function (e) {
            var form = e.target, submitter = e.submitter;
            setTimeout(function () {
                if (e.defaultPrevented || !(form instanceof window.HTMLFormElement)) return;
                if (form.hasAttribute('data-no-loader') || (form.target && form.target !== '_self')) return;
                busyForm(form, submitter, true);
                start();
            }, 0);
        });

        // Volver con «atrás» desde la caché del navegador: nada debe quedar a medias
        window.addEventListener('pageshow', function (e) { if (e.persisted) reset(); });
        window.addEventListener('pagehide', function () { /* la página se va: nada que limpiar */ });

        // Entrada suave de la página
        var body = d.querySelector('.page-body');
        if (body) enter(body);
    }

    return {
        boot: boot, start: start, done: done, reset: reset,
        busy: setBusy, busyForm: busyForm, skeleton: skeleton, enter: enter, announce: announce,
        _test: { isFullNavigation: isFullNavigation, SKELETON_DELAY_MS: SKELETON_DELAY_MS },
    };
});
