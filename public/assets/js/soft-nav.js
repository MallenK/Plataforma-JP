/* JP Preparation — navegación y formularios SIN recargar la página (JPSoft).
 *
 * Una pantalla se marca como «región blanda» con
 *     <div data-soft-region="finanzas" data-soft-prefix="finanzas"> …contenido… </div>
 * y dentro de ella:
 *   - los enlaces a páginas bajo ese prefijo se abren pidiendo la página por fetch y cambiando
 *     SOLO el contenido de la región (título, barra superior y menú lateral se actualizan);
 *   - los formularios (GET y POST) se envían por fetch; el servidor responde como siempre
 *     (redirige con su mensaje) y se pinta la pantalla resultante, el mensaje sale como aviso
 *     emergente (los elementos [data-flash]);
 *   - el botón «atrás» del navegador funciona (history.pushState / popstate);
 *   - mientras carga: barra de progreso superior + esqueleto (JPLoader).
 *
 * Seguridad y robustez: ante CUALQUIER cosa rara (sesión caducada, error del servidor, página sin
 * la región, respuesta que no es HTML, fallo de red) se hace la navegación normal de siempre, así
 * que el peor caso es el comportamiento de antes. Sin JS o sin fetch, todo funciona como siempre.
 *
 * Excluir un enlace o formulario: data-no-soft. Los enlaces de descarga/impresión nunca se interceptan.
 * Código propio que deba rearrancar tras pintar contenido: window.addEventListener('jp:soft-loaded', …).
 */
(function (root, factory) {
    var api = factory(root);
    if (typeof module === 'object' && module.exports) { module.exports = api; }
    root.JPSoft = api;
    if (root.document) { api.boot(); }
})(typeof window !== 'undefined' ? window : globalThis, function (window) {
    'use strict';

    var DOWNLOAD = /(\.(pdf|csv|xlsx?|docx?|zip|png|jpe?g|gif|webp|mp4)(\?|$))|([?&]export=)|(\/(adjunto|download|descargar|imprimir)(\/|\?|$))/i;

    // Navegación aislada para poder sustituirla en los tests.
    var nav = {
        assign: function (u) { window.location.assign(u); },
        reload: function () { window.location.reload(); },
    };

    var navToken = 0;           // cada navegación nueva invalida a la anterior que siga en vuelo
    var booted = false;
    var rendered = null;        // ruta+query de lo que hay pintado (distingue un «atrás» real de un simple cambio de #ancla)

    function loader() { return window.JPLoader || null; }
    function region() { return window.document.querySelector('[data-soft-region]'); }

    function basePath() {
        try { return new URL(window.APP_BASE || '/', window.location.href).pathname.replace(/\/+$/, ''); }
        catch (e) { return ''; }
    }

    /** Ruta absoluta del prefijo de la región (p. ej. «/finanzas»). */
    function prefixPath(reg) {
        var p = (reg && reg.getAttribute('data-soft-prefix')) || '';
        return basePath() + '/' + p.replace(/^\/+|\/+$/g, '');
    }

    function inPrefix(pathname, reg) {
        var pre = prefixPath(reg).replace(/\/+$/, '');
        return pathname === pre || pathname.indexOf(pre + '/') === 0;
    }

    /** ¿Se puede abrir este enlace sin recargar? (pura: recibe todo lo que necesita) */
    function eligible(a, e, reg, here) {
        if (!a || !a.getAttribute || !reg) return false;
        var href = a.getAttribute('href') || '';
        if (!href || /^(#|javascript:|mailto:|tel:|blob:|data:)/i.test(href)) return false;
        if (a.hasAttribute('download') || a.hasAttribute('data-no-soft')) return false;
        var t = a.getAttribute('target');
        if (t && t !== '_self') return false;
        if (e && (e.defaultPrevented || e.button > 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey)) return false;
        var cur = new URL(here || window.location.href);
        var url;
        try { url = new URL(a.href, cur.href); } catch (err) { return false; }
        if (url.origin !== cur.origin) return false;
        if (!inPrefix(url.pathname, reg)) return false;
        if (DOWNLOAD.test(url.pathname + url.search)) return false;
        if (url.pathname === cur.pathname && url.search === cur.search && url.hash) return false;   // ancla en la misma página
        return true;
    }

    function formEligible(form, reg) {
        if (!form || !reg || !reg.contains(form)) return false;
        if (form.hasAttribute('data-no-soft') || form.hasAttribute('data-live-form')) return false;
        if (form.target && form.target !== '_self') return false;
        var action = form.getAttribute('action');
        var url;
        try { url = new URL(action || window.location.href, window.location.href); } catch (e) { return false; }
        if (url.origin !== window.location.origin) return false;
        // Un POST puede ir a una ruta de otra zona (p. ej. /bonos/:id/precio) y volver a la región: se admite;
        // un GET (filtros, buscadores) debe quedarse dentro del prefijo para poder pintarse.
        var method = (form.getAttribute('method') || 'get').toLowerCase();
        if (method === 'get' && !inPrefix(url.pathname, reg)) return false;
        return !DOWNLOAD.test(url.pathname + url.search);
    }

    /** ¿Lo gestiona JPSoft? (lo usa radix-ui.js tras confirmar un formulario) */
    function handles(form) { return formEligible(form, region()); }

    // ── Pintado ────────────────────────────────────────────────────
    function hard(url) {
        // Navegación de siempre: la barra queda encendida hasta que cargue la página nueva.
        nav.assign(url);
    }

    function runScripts(container) {
        var loaded = {};
        Array.prototype.forEach.call(window.document.querySelectorAll('script[src]'), function (s) {
            loaded[(s.getAttribute('src') || '').split('?')[0]] = true;
        });
        Array.prototype.slice.call(container.querySelectorAll('script')).forEach(function (old) {
            var src = old.getAttribute('src');
            if (src && loaded[src.split('?')[0]]) { old.remove(); return; }      // ya cargado: no se duplica
            var s = window.document.createElement('script');
            Array.prototype.forEach.call(old.attributes, function (at) { s.setAttribute(at.name, at.value); });
            if (!src) s.text = old.textContent;
            old.parentNode.replaceChild(s, old);
        });
    }

    function syncChrome(doc) {
        // Título de la pestaña y de la barra superior
        if (doc.title) window.document.title = doc.title;
        var cur = window.document.querySelector('.topbar-title');
        var nw = doc.querySelector('.topbar-title');
        if (cur && nw && cur.innerHTML !== nw.innerHTML) cur.innerHTML = nw.innerHTML;

        // Opción activa del menú lateral
        var freshLinks = {};
        Array.prototype.forEach.call(doc.querySelectorAll('.sidebar-nav-link'), function (l) {
            freshLinks[l.getAttribute('href')] = l.classList.contains('active');
        });
        Array.prototype.forEach.call(window.document.querySelectorAll('.sidebar-nav-link'), function (l) {
            var h = l.getAttribute('href');
            if (h in freshLinks) l.classList.toggle('active', freshLinks[h]);
        });

        // Los tokens CSRF del resto de la página siguen siendo válidos (regenerate=false), pero se
        // alinean con los de la página nueva por si el servidor los cambió.
        var any = doc.querySelector('input[type=hidden][name*="csrf"]');
        if (any) {
            var name = any.getAttribute('name');
            Array.prototype.forEach.call(window.document.querySelectorAll('input[type=hidden][name="' + name + '"]'), function (i) {
                if (!(region() && region().contains(i))) i.value = any.value;
            });
        }
    }

    function toast(msg, type) {
        if (typeof window.showAlert === 'function') window.showAlert(msg, type);
    }

    /** Pinta la región con la página recibida. Devuelve false si no se puede (→ navegación normal). */
    function swap(doc, finalUrl, o) {
        var reg = region();
        if (!reg) return false;
        var id = reg.getAttribute('data-soft-region');
        var fresh = Array.prototype.find.call(doc.querySelectorAll('[data-soft-region]'), function (n) { return n.getAttribute('data-soft-region') === id; });
        if (!fresh) return false;

        // Los mensajes de la página (flash) salen como aviso emergente y no se pintan dos veces.
        var msgs = [];
        Array.prototype.forEach.call(fresh.querySelectorAll('[data-flash]'), function (el) {
            var text = (el.textContent || '').trim();
            if (text) msgs.push({ type: el.classList.contains('error') ? 'error' : 'success', text: text });
            el.remove();
        });

        // Tooltips abiertos de la pantalla anterior
        Array.prototype.forEach.call(window.document.querySelectorAll('.tooltip.show'), function (t) { t.remove(); });

        reg.innerHTML = fresh.innerHTML;
        syncChrome(doc);
        runScripts(reg);

        var u = new URL(finalUrl, window.location.href);
        if (o.history) {
            var target = u.pathname + u.search + (o.hash || '');
            var current = window.location.pathname + window.location.search;
            try {
                if (o.history === 'push' && (u.pathname + u.search) !== current) window.history.pushState({ soft: 1 }, '', target);
                else window.history.replaceState({ soft: 1 }, '', target);
            } catch (e) { /* sin historial: no pasa nada */ }
        }
        rendered = u.pathname + u.search;
        if (o.hash) {
            var anchor = null;
            try { anchor = window.document.querySelector(o.hash); } catch (e) { anchor = null; }
            if (anchor && anchor.scrollIntoView) anchor.scrollIntoView();
            else if (o.scrollTop) window.scrollTo(0, 0);
        } else if (o.scrollTop) {
            window.scrollTo(0, 0);
        }
        if (loader()) loader().enter(reg);

        msgs.forEach(function (m) { toast(m.text, m.type); });
        window.dispatchEvent(new window.CustomEvent('jp:soft-loaded', { detail: { region: reg, url: String(finalUrl), messages: msgs } }));
        return true;
    }

    /** Pide una página y pinta la región. */
    async function request(url, init, o) {
        var token = ++navToken;
        var reg = region();
        if (!reg) { hard(url); return; }
        var L = loader();
        init = init || {};
        var isPost = String(init.method || 'GET').toUpperCase() === 'POST';
        if (L) { L.start(); L.skeleton(reg, true); }

        // Cada start() lleva SIEMPRE su done(), también cuando otra navegación más reciente deja
        // obsoleta esta (si no, la barra de progreso se quedaba encendida). El esqueleto solo lo
        // retira la navegación más reciente, que es la que lo está usando. Si se hace una navegación
        // normal (hard) se deja todo encendido hasta que la página nueva cargue.
        var leaving = false;
        var toHard = function (u) { leaving = true; hard(u); };

        // Un envío (POST) que falla NO se repite como navegación: la ruta del POST no admite GET.
        // Se avisa y se vuelve a pintar la pantalla actual, que es segura de recargar.
        var failPost = function (msg, reload) {
            toast(msg, 'error');
            if (reload) setTimeout(function () { nav.reload(); }, 1400);
        };

        try {
            init.credentials = 'same-origin';
            init.headers = Object.assign({ 'X-JP-Soft': '1', 'Accept': 'text/html' }, init.headers || {});
            var res = await window.fetch(url, init);
            if (token !== navToken) return;                          // otra navegación más reciente manda

            var finalUrl = new URL(res.url || url, window.location.href);
            var type = (res.headers && res.headers.get && res.headers.get('Content-Type')) || 'text/html';

            if (/\/login\/?$/.test(finalUrl.pathname)) { toHard(String(finalUrl)); return; }   // sesión caducada
            if (!res.ok || type.indexOf('html') < 0) {
                if (!isPost) { toHard(String(finalUrl)); return; }                            // que el servidor pinte su página de error
                failPost(res.status === 403 || res.status === 419
                    ? 'Esta página ha caducado. Se recargará.'
                    : 'No se pudo completar la acción. Se recargará la página.', true);
                return;
            }

            var doc = new window.DOMParser().parseFromString(await res.text(), 'text/html');
            if (token !== navToken) return;
            if (!swap(doc, finalUrl, o)) { toHard(String(finalUrl)); return; }                // otra zona: navegación normal
        } catch (e) {
            if (token !== navToken) return;
            if (isPost) { failPost('No se pudo completar la acción. Revisa la conexión e inténtalo de nuevo.', false); return; }
            toHard(typeof url === 'string' ? url : String(url));      // sin red u otro fallo: como siempre
        } finally {
            if (L && !leaving) {
                if (token === navToken) L.skeleton(reg, false);
                L.done();
            }
        }
    }

    // ── API ────────────────────────────────────────────────────────
    function go(url, opts) {
        opts = opts || {};
        var u = new URL(url, window.location.href);
        return request(u.pathname + u.search, { method: 'GET' }, {
            history: opts.history === undefined ? 'push' : opts.history,
            hash: u.hash,
            scrollTop: opts.scrollTop === undefined ? true : opts.scrollTop,
        });
    }

    function submit(form, submitter) {
        var method = (form.getAttribute('method') || 'get').toUpperCase();
        var action = new URL(form.getAttribute('action') || window.location.href, window.location.href);
        var L = loader();
        form.setAttribute('aria-busy', 'true');                  // también frena el doble envío si no hay cargador
        if (L) L.busyForm(form, submitter, true);

        var finish = function () {
            form.removeAttribute('aria-busy');
            if (L) L.busyForm(form, submitter, false);
        };

        if (method === 'GET') {
            var fd = new window.FormData(form, submitter || undefined);
            var qs = new window.URLSearchParams();
            fd.forEach(function (v, k) { if (typeof v === 'string' && v !== '') qs.append(k, v); });
            var p = go(action.pathname + (qs.toString() ? '?' + qs.toString() : ''), { scrollTop: false });
            return Promise.resolve(p).then(finish, finish);
        }

        var same = action.pathname === window.location.pathname;
        var req = request(action.pathname + action.search, { method: 'POST', body: new window.FormData(form, submitter || undefined) }, {
            history: 'replace',                  // tras un envío no se añade una entrada más al historial
            scrollTop: !same,                    // misma pantalla → se queda donde estaba el usuario
        });
        return Promise.resolve(req).then(finish, finish);
    }

    function boot() {
        if (booted || !window.document) return;
        booted = true;
        var d = window.document;
        if (!window.fetch || !window.DOMParser || !window.FormData || !window.history || !window.history.pushState) return;

        d.addEventListener('click', function (e) {
            var reg = region();
            if (!reg) return;
            var a = e.target && e.target.closest ? e.target.closest('a[href]') : null;
            if (!eligible(a, e, reg)) return;
            e.preventDefault();
            go(a.href);
        });

        d.addEventListener('submit', function (e) {
            var form = e.target;
            var reg = region();
            if (!reg || !(form instanceof window.HTMLFormElement) || e.defaultPrevented) return;
            if (form.hasAttribute('data-ru-confirm')) return;           // lo gestiona el diálogo de confirmación (radix-ui.js)
            if (!formEligible(form, reg)) return;
            if (form.getAttribute('aria-busy') === 'true') { e.preventDefault(); return; }   // doble envío
            e.preventDefault();
            submit(form, e.submitter);
        });

        // Botón «atrás» / «adelante»
        window.addEventListener('popstate', function () {
            if (!region()) return;
            // Solo cambió el #ancla: no hay nada que pedir.
            if (window.location.pathname + window.location.search === rendered) return;
            go(window.location.href, { history: false, scrollTop: false });
        });
        rendered = window.location.pathname + window.location.search;
        try { window.history.replaceState({ soft: 1 }, '', window.location.href); } catch (e) { /* nada */ }
    }

    return {
        boot: boot, go: go, submit: submit, handles: handles,
        _test: { eligible: eligible, formEligible: formEligible, inPrefix: inPrefix, swap: swap, nav: nav, request: request, region: region },
    };
});
