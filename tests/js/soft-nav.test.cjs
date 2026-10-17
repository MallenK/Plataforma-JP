/**
 * Test de public/assets/js/soft-nav.js (navegación y formularios sin recargar) y loaders.js.
 *   node tests/js/soft-nav.test.cjs     (requiere jsdom; sale con 2 si no está)
 */
'use strict';
const fs = require('fs');
const path = require('path');

let JSDOM;
try { JSDOM = require('jsdom').JSDOM; } catch (e) { console.error('SKIP: jsdom no disponible'); process.exit(2); }

const JS = (f) => fs.readFileSync(path.join(__dirname, '..', '..', 'public', 'assets', 'js', f), 'utf8');
const SOFT = JS('soft-nav.js');
const LOADERS = JS('loaders.js');

let failed = 0;
const ok = (c, l) => { console.log((c ? '  OK   ' : '  FAIL ') + l); if (!c) failed++; };
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

const REGION = (inner) => '<div data-soft-region="finanzas" data-soft-prefix="finanzas">' + inner + '</div>';
const PAGE = (title, inner, extra) => '<!doctype html><title>' + title + '</title><body>'
    + '<header class="topbar-title"><h1>' + title + '</h1></header>'
    + '<nav><a class="sidebar-nav-link ' + ((extra && extra.activeFin) ? 'active' : '') + '" href="/finanzas">Finanzas</a><a class="sidebar-nav-link" href="/bonos">Bonos</a></nav>'
    + REGION(inner) + '</body>';

/** Página con región; `routes` mapea "MÉTODO ruta" → respuesta simulada del servidor. */
function page(initialInner, routes, opts) {
    opts = opts || {};
    const dom = new JSDOM(PAGE('Inicial', initialInner), { url: 'https://app.test/finanzas/resumen', runScripts: opts.run || 'outside-only', pretendToBeVisual: true });
    const w = dom.window;
    w.APP_BASE = 'https://app.test';
    w.scrollTo = () => {};
    const calls = [];
    const toasts = [];
    const navs = { assign: [], reload: 0 };
    w.showAlert = (m, t) => toasts.push({ m, t });
    w.fetch = async (url, init) => {
        init = init || {};
        const method = (init.method || 'GET').toUpperCase();
        calls.push({ url, method, init });
        const key = method + ' ' + String(url).split('?')[0];
        const r = routes[key] || routes['*'];
        if (!r) throw new Error('sin ruta simulada: ' + key);
        if (r.throw) throw new Error('red caída');
        const finalUrl = r.finalUrl || String(url);
        return {
            ok: r.status ? r.status < 400 : true,
            status: r.status || 200,
            url: new URL(finalUrl, 'https://app.test').href,
            headers: { get: () => r.type || 'text/html; charset=UTF-8' },
            text: async () => r.html || '',
        };
    };
    if (opts.loaders) w.eval(LOADERS);
    w.eval(SOFT);
    w.JPSoft._test.nav.assign = (u) => navs.assign.push(u);
    w.JPSoft._test.nav.reload = () => { navs.reload++; };
    return { w, doc: w.document, calls, toasts, navs };
}

const link = (w, href, attrs) => {
    const a = w.document.createElement('a');
    a.setAttribute('href', href);
    Object.keys(attrs || {}).forEach((k) => a.setAttribute(k, attrs[k]));
    return a;
};
const clickOn = (w, el, init) => {
    const ev = new w.MouseEvent('click', Object.assign({ bubbles: true, cancelable: true, button: 0 }, init || {}));
    el.dispatchEvent(ev);
    return ev;
};

(async () => {
    // ── eligible(): qué enlaces se abren sin recargar ───────────────────
    {
        const p = page('<p>x</p>', {});
        const reg = p.w.JPSoft._test.region();
        const el = (p.w.JPSoft._test.eligible);
        const L = (h, a, ev) => el(link(p.w, h, a), ev, reg);
        ok(L('/finanzas/cobros') === true, 'enlace dentro del prefijo: se abre sin recargar');
        ok(L('/finanzas/cobros?mes=2026-10') === true, 'con parámetros también');
        ok(L('/bonos') === false, 'fuera del prefijo: navegación normal');
        ok(L('/finanzasfalsa') === false, 'un prefijo parecido no cuenta (/finanzasfalsa)');
        ok(L('https://otro.test/finanzas/cobros') === false, 'otro origen: nunca');
        ok(L('/finanzas/cobros', { target: '_blank' }) === false, 'target=_blank: nunca');
        ok(L('/finanzas/cobros', { download: '' }) === false, 'download: nunca');
        ok(L('/finanzas/cobros', { 'data-no-soft': '' }) === false, 'data-no-soft: respeta la exclusión');
        ok(L('#sin-cerrar') === false && L('javascript:void(0)') === false && L('mailto:a@b.c') === false, '#ancla, javascript: y mailto: no se tocan');
        ok(L('/finanzas/ayuda/manual.pdf') === false, 'PDF: descarga normal');
        ok(L('/finanzas/movimientos?export=csv') === false, 'export=csv: descarga normal');
        ok(L('/finanzas/gastos/12/adjunto') === false, 'adjunto: descarga normal');
        ok(L('/finanzas/ayuda/imprimir') === false, 'imprimir: página aparte');
        ok(L('/finanzas/cobros', {}, { metaKey: true, button: 0 }) === false && L('/finanzas/cobros', {}, { ctrlKey: true, button: 0 }) === false, 'ctrl/cmd+clic: pestaña nueva');
        ok(L('/finanzas/cobros', {}, { button: 1 }) === false, 'clic central: no se intercepta');
        ok(L('/finanzas/resumen#algo') === false, 'ancla de la misma página: no se pide de nuevo');
    }

    // ── Clic en un enlace: se pide la página y se cambia solo la región ─
    {
        const p = page('<p id="old">viejo</p>', {
            'GET /finanzas/cobros': { html: PAGE('Cobros', '<p id="new">nuevo</p><div class="alert-jp success" data-flash>Hecho</div>', { activeFin: true }) },
        });
        const a = link(p.w, '/finanzas/cobros');
        p.doc.querySelector('[data-soft-region]').appendChild(a);
        const ev = clickOn(p.w, a);
        ok(ev.defaultPrevented === true, 'clic: se cancela la navegación del navegador');
        await sleep(60);
        ok(p.calls.length === 1 && p.calls[0].init.headers['X-JP-Soft'] === '1' && p.calls[0].init.credentials === 'same-origin', 'una petición con la sesión y la marca X-JP-Soft');
        ok(!!p.doc.getElementById('new') && !p.doc.getElementById('old'), 'la región se sustituye por la nueva');
        ok(p.doc.title === 'Cobros', 'el título de la pestaña se actualiza');
        ok(p.doc.querySelector('.topbar-title h1').textContent === 'Cobros', 'el título de la barra superior se actualiza');
        ok(p.w.location.pathname === '/finanzas/cobros', 'la URL cambia (pushState)');
        ok(p.doc.querySelector('.sidebar-nav-link').classList.contains('active'), 'el menú lateral marca la opción activa');
        ok(p.toasts.length === 1 && p.toasts[0].m === 'Hecho' && p.toasts[0].t === 'success', 'el mensaje flash sale como aviso emergente');
        ok(!p.doc.querySelector('[data-flash]'), '…y no se pinta dos veces');
        ok(p.navs.assign.length === 0 && p.navs.reload === 0, 'sin navegación completa');
    }

    // ── Evento para reiniciar código de la página ───────────────────────
    {
        const p = page('<p>x</p>', { 'GET /finanzas/cobros': { html: PAGE('C', '<p>y</p>') } });
        let fired = null;
        p.w.addEventListener('jp:soft-loaded', (e) => { fired = e.detail; });
        await p.w.JPSoft.go('/finanzas/cobros');
        ok(fired && /\/finanzas\/cobros$/.test(fired.url) && fired.region === p.w.JPSoft._test.region(), 'jp:soft-loaded avisa con la región y la URL');
    }

    // ── Fallbacks: ante algo raro, navegación normal ────────────────────
    {
        const login = page('<p>x</p>', { 'GET /finanzas/cobros': { html: '<html></html>', finalUrl: '/login?expired=1' } });
        await login.w.JPSoft.go('/finanzas/cobros');
        ok(login.navs.assign.length === 1 && /\/login/.test(login.navs.assign[0]), 'sesión caducada (login) → navegación normal');
        ok(login.doc.body.textContent.indexOf('x') >= 0, '…y la pantalla actual no se toca');

        const noRegion = page('<p>x</p>', { 'GET /finanzas/cobros': { html: '<!doctype html><body><p>otra cosa</p></body>' } });
        await noRegion.w.JPSoft.go('/finanzas/cobros');
        ok(noRegion.navs.assign.length === 1, 'respuesta sin la región → navegación normal');

        const err = page('<p>x</p>', { 'GET /finanzas/cobros': { status: 500, html: 'boom' } });
        await err.w.JPSoft.go('/finanzas/cobros');
        ok(err.navs.assign.length === 1, 'error 500 en un GET → navegación normal (el servidor pinta su página de error)');

        const forbidden = page('<p>x</p>', { 'GET /finanzas/cobros': { status: 403, html: 'no' } });
        await forbidden.w.JPSoft.go('/finanzas/cobros');
        ok(forbidden.navs.assign.length === 1, 'error 403 en un GET → navegación normal (verá la página de acceso denegado)');

        const json = page('<p>x</p>', { 'GET /finanzas/cobros': { type: 'application/json', html: '{}' } });
        await json.w.JPSoft.go('/finanzas/cobros');
        ok(json.navs.assign.length === 1, 'respuesta que no es HTML → navegación normal');

        const down = page('<p>x</p>', { 'GET /finanzas/cobros': { throw: true } });
        await down.w.JPSoft.go('/finanzas/cobros');
        ok(down.navs.assign.length === 1, 'sin red en un GET → navegación normal');
    }

    // ── Formularios ─────────────────────────────────────────────────────
    {
        // POST que redirige a la misma pantalla con mensaje
        const p = page('<form id="f" action="/finanzas/cobros" method="post"><input name="amount" value="12"><button type="submit" id="b">Guardar</button></form>', {
            'POST /finanzas/cobros': { finalUrl: '/finanzas/cobros', html: PAGE('Cobros', '<p id="ok">lista</p><div class="alert-jp success" data-flash>Cobro registrado.</div>') },
        });
        const f = p.doc.getElementById('f');
        const ev = new p.w.Event('submit', { bubbles: true, cancelable: true });
        f.dispatchEvent(ev);
        ok(ev.defaultPrevented === true, 'POST dentro de la región: se cancela el envío normal');
        await sleep(60);
        ok(p.calls.length === 1 && p.calls[0].method === 'POST' && p.calls[0].init.body instanceof p.w.FormData, 'se envía por fetch con FormData');
        ok(!!p.doc.getElementById('ok'), 'se pinta la pantalla resultante');
        ok(p.toasts.some((t) => t.m === 'Cobro registrado.' && t.t === 'success'), 'el mensaje del servidor sale como aviso');
        ok(p.w.location.pathname === '/finanzas/cobros', 'la URL final queda reflejada');
        ok(p.navs.assign.length === 0 && p.navs.reload === 0, 'sin recargar');
    }
    {
        // POST cuya respuesta va a otra zona (ej. /bonos/12): navegación normal a ese destino
        const p = page('<form id="f" action="/bonos/12/precio" method="post"><button>Ok</button></form>', {
            'POST /bonos/12/precio': { finalUrl: '/bonos/12', html: '<!doctype html><body>bono</body>' },
        });
        p.doc.getElementById('f').dispatchEvent(new p.w.Event('submit', { bubbles: true, cancelable: true }));
        await sleep(60);
        ok(p.navs.assign.length === 1 && /\/bonos\/12$/.test(p.navs.assign[0]), 'POST que termina en otra zona → se va a ese destino (sin repetir el POST)');
    }
    {
        // POST: fallo de red → aviso, NUNCA navegar a la ruta del POST
        const p = page('<form id="f" action="/finanzas/cobros" method="post"><button>Ok</button></form>', { 'POST /finanzas/cobros': { throw: true } });
        p.doc.getElementById('f').dispatchEvent(new p.w.Event('submit', { bubbles: true, cancelable: true }));
        await sleep(60);
        ok(p.navs.assign.length === 0, 'POST sin red: no se navega a la ruta del POST (no admite GET)');
        ok(p.toasts.length === 1 && p.toasts[0].t === 'error', 'POST sin red: aviso de error');
        ok(p.doc.getElementById('f').getAttribute('aria-busy') !== 'true', 'POST sin red: el formulario vuelve a poder usarse');
    }
    {
        // POST: 403 (CSRF caducado) → aviso y recarga de la pantalla actual
        const p = page('<form id="f" action="/finanzas/cobros" method="post"><button>Ok</button></form>', { 'POST /finanzas/cobros': { status: 403, html: 'caducada' } });
        p.doc.getElementById('f').dispatchEvent(new p.w.Event('submit', { bubbles: true, cancelable: true }));
        await sleep(1700);
        ok(p.toasts.length === 1 && /caducado/.test(p.toasts[0].m), 'POST 403: avisa de que la página caducó');
        ok(p.navs.reload === 1 && p.navs.assign.length === 0, 'POST 403: recarga la pantalla actual');
    }
    {
        // GET (filtro): construye la query y pinta la región
        const p = page('<form id="f" method="get" action="/finanzas/cobros"><input name="mes" value="2026-10"><input name="vacio" value=""><button>Ver</button></form>', {
            'GET /finanzas/cobros': { html: PAGE('C', '<p id="r">filtrado</p>') },
        });
        p.doc.getElementById('f').dispatchEvent(new p.w.Event('submit', { bubbles: true, cancelable: true }));
        await sleep(60);
        ok(p.calls[0].method === 'GET' && p.calls[0].url === '/finanzas/cobros?mes=2026-10', 'GET: query con los campos con valor (sin los vacíos)');
        ok(!!p.doc.getElementById('r'), 'GET: se pinta la región');
    }
    {
        // Exclusiones de formulario
        const p = page('<form id="a" data-no-soft action="/finanzas/x" method="post"></form><form id="b" data-live-form action="/finanzas/x" method="post"></form><form id="c" method="get" action="/clases"></form><form id="d" data-ru-confirm="¿?" action="/finanzas/x" method="post"></form>', {});
        const ev = (id) => { const e = new p.w.Event('submit', { bubbles: true, cancelable: true }); p.doc.getElementById(id).dispatchEvent(e); return e.defaultPrevented; };
        ok(!ev('a'), 'data-no-soft: el formulario va a su aire');
        ok(!ev('b'), 'data-live-form: lo gestiona live.js, no soft-nav');
        ok(!ev('c'), 'GET a otra zona: navegación normal');
        ok(!ev('d'), 'data-ru-confirm: lo gestiona el diálogo de confirmación');
        ok(p.w.JPSoft.handles(p.doc.getElementById('d')) === true && p.w.JPSoft.handles(p.doc.getElementById('b')) === false, 'handles(): un formulario con confirmación sí es de la región (se envía tras confirmar)');
    }

    // ── Doble envío ─────────────────────────────────────────────────────
    {
        const p = page('<form id="f" action="/finanzas/cobros" method="post"><button>Ok</button></form>', { 'POST /finanzas/cobros': { html: PAGE('C', '<p>x</p>') } });
        const f = p.doc.getElementById('f');
        f.dispatchEvent(new p.w.Event('submit', { bubbles: true, cancelable: true }));
        const second = new p.w.Event('submit', { bubbles: true, cancelable: true });
        f.dispatchEvent(second);
        await sleep(60);
        ok(second.defaultPrevented && p.calls.length === 1, 'doble clic: solo se envía una vez');
    }

    // ── Dos navegaciones seguidas: manda la última ──────────────────────
    {
        let release;
        const gate = new Promise((r) => { release = r; });
        const p = page('<p>x</p>', {}, { loaders: true });
        p.w.fetch = async (url) => {
            if (String(url).indexOf('/lenta') >= 0) await gate;
            const inner = String(url).indexOf('/lenta') >= 0 ? '<p id="lenta">lenta</p>' : '<p id="rapida">rápida</p>';
            return { ok: true, status: 200, url: new URL(url, 'https://app.test').href, headers: { get: () => 'text/html' }, text: async () => PAGE('t', inner) };
        };
        const a = p.w.JPSoft.go('/finanzas/lenta');
        const b = p.w.JPSoft.go('/finanzas/rapida');
        await b;
        release();
        await a;
        await sleep(30);
        ok(!!p.doc.getElementById('rapida') && !p.doc.getElementById('lenta'), 'una respuesta lenta antigua no pisa a la más reciente');
        await sleep(350);
        const bar = p.doc.getElementById('jp-topbar');
        ok(bar && !bar.classList.contains('is-on'), 'la barra de progreso se apaga aunque una de las dos navegaciones quedara obsoleta');
        ok(!p.doc.querySelector('[data-soft-region] > .jp-skel-overlay') && !p.doc.querySelector('[data-soft-region]').hasAttribute('aria-busy'), 'y no queda ningún esqueleto ni aria-busy colgado');
    }

    // ── Atrás / adelante ────────────────────────────────────────────────
    {
        const p = page('<p id="a">A</p>', {
            'GET /finanzas/cobros': { html: PAGE('Cobros', '<p id="b">B</p>') },
            'GET /finanzas/resumen': { html: PAGE('Resumen', '<p id="a2">A</p>') },
        });
        await p.w.JPSoft.go('/finanzas/cobros');
        ok(p.w.location.pathname === '/finanzas/cobros', 'tras navegar, la URL es la nueva');
        p.w.history.back();
        await sleep(120);
        ok(p.w.location.pathname === '/finanzas/resumen' && !!p.doc.getElementById('a2'), 'atrás: vuelve a la pantalla anterior sin recargar');
        ok(p.navs.assign.length === 0 && p.navs.reload === 0, 'atrás: sin navegación completa');
        const before = p.calls.length;
        p.w.location.hash = '#seccion';
        await sleep(80);
        ok(p.calls.length === before, 'cambiar solo el #ancla no pide nada');
    }

    // ── Scripts de la región ────────────────────────────────────────────
    {
        const p = page('<p>x</p>', {
            'GET /finanzas/cobros': { html: PAGE('C', '<script src="/assets/js/fin-ui.js?v=2"></script><script>window.__ran = (window.__ran || 0) + 1;</script><p id="n">n</p>') },
        }, { run: 'dangerously' });
        const tag = p.doc.createElement('script'); tag.setAttribute('src', '/assets/js/fin-ui.js?v=1'); p.doc.head.appendChild(tag);
        await p.w.JPSoft.go('/finanzas/cobros');
        await sleep(30);
        ok(p.w.__ran === 1, 'los scripts en línea de la región se ejecutan');
        ok(p.doc.querySelectorAll('script[src*="fin-ui.js"]').length === 1, 'un script externo ya cargado no se duplica');
    }

    // ── Loaders: barra, botones y esqueleto ─────────────────────────────
    {
        const p = page('<form id="f" action="/finanzas/cobros" method="post"><button id="b" type="submit">Guardar</button></form>', {}, { loaders: true });
        const L = p.w.JPLoader;
        ok(!!L && typeof L.start === 'function', 'JPLoader existe');

        L.start();
        const bar = p.doc.getElementById('jp-topbar');
        ok(bar && bar.classList.contains('is-on'), 'start(): la barra superior se enciende');
        L.start(); L.done();
        ok(bar.classList.contains('is-on'), 'dos cargas, una termina: sigue encendida');
        L.done();
        await sleep(300);
        ok(!bar.classList.contains('is-on'), 'al terminar todas, se apaga');

        const b = p.doc.getElementById('b');
        L.busy(b, true);
        ok(b.disabled && b.classList.contains('is-loading') && !!b.querySelector('.jp-btn-spin'), 'botón ocupado: desactivado y con spinner');
        L.busy(b, false);
        ok(!b.disabled && b.textContent === 'Guardar' && !b.querySelector('.jp-btn-spin'), 'botón liberado: vuelve a como estaba');

        const reg = p.doc.querySelector('[data-soft-region]');
        L.skeleton(reg, true);
        ok(reg.getAttribute('aria-busy') === 'true' && !reg.querySelector('.jp-skel-overlay'), 'esqueleto: no aparece de inmediato (cargas rápidas sin parpadeo)');
        await sleep(L._test.SKELETON_DELAY_MS + 60);
        ok(!!reg.querySelector('.jp-skel-overlay') && reg.classList.contains('jp-busy'), 'esqueleto: aparece si la carga tarda');
        L.skeleton(reg, false);
        ok(!reg.querySelector('.jp-skel-overlay') && !reg.classList.contains('jp-busy') && !reg.hasAttribute('aria-busy'), 'esqueleto: se retira al terminar');

        // Una carga rápida no llega a enseñar esqueleto
        L.skeleton(reg, true); L.skeleton(reg, false);
        await sleep(L._test.SKELETON_DELAY_MS + 60);
        ok(!reg.querySelector('.jp-skel-overlay'), 'una carga rápida cancela el esqueleto pendiente');
    }
    {
        const p = page('<p>x</p>', {}, { loaders: true });
        const f = p.w.JPLoader._test.isFullNavigation;
        const here = 'https://app.test/bonos';
        const A = (h, attrs) => link(p.w, h, attrs);
        ok(f(A('/clases'), null, here) === true, 'enlace normal: la barra se enciende');
        ok(f(A('/clases', { target: '_blank' }), null, here) === false && f(A('#x'), null, here) === false, 'pestaña nueva y #ancla: no');
        ok(f(A('/finanzas/x?export=csv'), null, here) === false && f(A('/a/manual.pdf'), null, here) === false, 'descargas: no (la página no se va a recargar)');
        ok(f(A('https://otro.test/x'), null, here) === false, 'otro origen: no');
        ok(f(A('/clases', { 'data-no-loader': '' }), null, here) === false, 'data-no-loader: no');
    }

    console.log(failed ? '\n' + failed + ' FALLO(S)' : '\nTodo OK');
    process.exit(failed ? 1 : 0);
})();
