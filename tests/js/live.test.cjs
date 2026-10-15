/**
 * Test de public/assets/js/live.js (actualización en vivo sin recargar).
 *   node tests/js/live.test.cjs     (requiere jsdom; sale con 2 si no está)
 */
'use strict';
const fs = require('fs');
const path = require('path');

let JSDOM;
try { JSDOM = require('jsdom').JSDOM; } catch (e) { console.error('SKIP: jsdom no disponible'); process.exit(2); }

const SRC = fs.readFileSync(path.join(__dirname, '..', '..', 'public', 'assets', 'js', 'live.js'), 'utf8');

let failed = 0;
const ok = (c, l) => { console.log((c ? '  OK   ' : '  FAIL ') + l); if (!c) failed++; };
const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

/** Página con zonas data-live; `server.html` es lo que devolvería el servidor al volver a pedirla. */
function page(bodyHtml, server) {
    const dom = new JSDOM('<!doctype html><body>' + bodyHtml + '</body>', { url: 'https://app.test/dashboard', runScripts: 'outside-only', pretendToBeVisual: true });
    const w = dom.window;
    const calls = [];
    w.fetch = async (url, opts) => {
        calls.push({ url, opts });
        if (server.fail) return { ok: false, redirected: false, url, text: async () => '' };
        if (server.redirectToLogin) return { ok: true, redirected: true, url: 'https://app.test/login', text: async () => '<html></html>' };
        return { ok: true, redirected: false, url, text: async () => '<!doctype html><body>' + server.html + '</body>' };
    };
    w.eval(SRC);
    return { w, calls, doc: w.document };
}

const region = (id, kinds, inner) => '<div id="' + id + '" data-live="' + id + '" data-live-kinds="' + kinds + '">' + inner + '</div>';

(async () => {
    // ── Helpers puros ───────────────────────────────────────────────────
    {
        const { w } = page('', { html: '' });
        const t = w.JPLive._test;
        const S = (...a) => new w.Set(a);
        ok(t.wants('class,bono', S('class')) && !t.wants('class,bono', S('ticket')), 'wants: coincide solo con sus tipos');
        ok(t.wants('class', S('*')) && t.wants('*', S('ticket')) && t.wants(null, S('bono')), 'wants: «*» (aviso general) y zona sin tipos refrescan siempre');
        ok(t.normalize('general') === '*' && t.normalize('') === '*' && t.normalize('class') === 'class', 'normalize: general → *');
    }

    // ── Un push de clase actualiza solo las zonas de clase ──────────────
    {
        const p = page(region('a', 'class', 'viejo-a') + region('b', 'ticket', 'viejo-b'),
            { html: region('a', 'class', 'nuevo-a') + region('b', 'ticket', 'nuevo-b') });
        p.w.dispatchEvent(new p.w.CustomEvent('jp:push', { detail: { kind: 'class', id: 5 } }));
        await sleep(1000);
        ok(p.doc.getElementById('a').innerHTML === 'nuevo-a', 'push de clase: la zona de clases se actualiza');
        ok(p.doc.getElementById('b').innerHTML === 'viejo-b', 'push de clase: la zona de tickets NO se toca');
        ok(p.calls.length === 1 && p.calls[0].opts.headers['X-Requested-With'] === 'XMLHttpRequest' && p.calls[0].opts.credentials === 'same-origin', 'una sola petición, con la sesión');
    }

    // ── Varios avisos seguidos = una sola recarga ───────────────────────
    {
        const p = page(region('a', 'class', 'x'), { html: region('a', 'class', 'y') });
        for (let i = 0; i < 5; i++) p.w.dispatchEvent(new p.w.CustomEvent('jp:push', { detail: { kind: 'class' } }));
        await sleep(1000);
        ok(p.calls.length === 1, 'ráfaga de 5 avisos → 1 sola petición');
    }

    // ── Aviso general (kind general) refresca todo ──────────────────────
    {
        const p = page(region('a', 'class', 'x') + region('b', 'ticket', 'x'), { html: region('a', 'class', 'A2') + region('b', 'ticket', 'B2') });
        p.w.dispatchEvent(new p.w.CustomEvent('jp:push', { detail: { kind: 'general' } }));
        await sleep(1000);
        ok(p.doc.getElementById('a').innerHTML === 'A2' && p.doc.getElementById('b').innerHTML === 'B2', 'aviso general: se actualizan todas las zonas');
    }

    // ── No pisa lo que el usuario está haciendo ─────────────────────────
    {
        const p = page(region('a', 'class', '<textarea id="t">escribiendo</textarea>'), { html: region('a', 'class', 'nuevo') });
        p.doc.getElementById('t').focus();
        p.w.dispatchEvent(new p.w.CustomEvent('jp:push', { detail: { kind: 'class' } }));
        await sleep(1000);
        ok(p.doc.getElementById('t') && p.doc.getElementById('t').value === 'escribiendo', 'no se toca una zona con un campo enfocado');
    }
    {
        const p = page(region('a', 'class', '<details open><summary>s</summary>form</details>'), { html: region('a', 'class', 'nuevo') });
        p.w.dispatchEvent(new p.w.CustomEvent('jp:push', { detail: { kind: 'class' } }));
        await sleep(1000);
        ok(p.doc.querySelector('#a details'), 'no se toca una zona con un desplegable abierto');
    }
    {
        const p = page('<div id="a" data-live="a" data-live-kinds="class" data-live-skip>viejo</div>', { html: region('a', 'class', 'nuevo') });
        p.w.dispatchEvent(new p.w.CustomEvent('jp:push', { detail: { kind: 'class' } }));
        await sleep(1000);
        ok(p.doc.getElementById('a').innerHTML === 'viejo', 'data-live-skip: la zona se respeta');
    }

    // ── Errores: no hacen nada ──────────────────────────────────────────
    {
        const p = page(region('a', 'class', 'viejo'), { fail: true });
        p.w.dispatchEvent(new p.w.CustomEvent('jp:push', { detail: { kind: 'class' } }));
        await sleep(1000);
        ok(p.doc.getElementById('a').innerHTML === 'viejo', 'error del servidor: no cambia nada');
    }
    {
        const p = page(region('a', 'class', 'viejo'), { redirectToLogin: true });
        p.w.dispatchEvent(new p.w.CustomEvent('jp:push', { detail: { kind: 'class' } }));
        await sleep(1000);
        ok(p.doc.getElementById('a').innerHTML === 'viejo', 'sesión caducada (redirige al login): no cambia nada');
    }

    // ── Código propio (calendarios) ─────────────────────────────────────
    {
        const p = page('', { html: '' });
        let ran = 0, other = 0;
        p.w.JPLive.on(['class'], () => ran++);
        p.w.JPLive.on(['ticket'], () => other++);
        p.w.dispatchEvent(new p.w.CustomEvent('jp:push', { detail: { kind: 'class' } }));
        await sleep(1000);
        ok(ran === 1 && other === 0, 'JPLive.on: solo corre el código de ese tipo de aviso');
        ok(p.calls.length === 0, 'sin zonas data-live no se pide la página');
    }

    // ── Campanita (sin push) ────────────────────────────────────────────
    {
        const p = page(region('a', 'bono', 'viejo'), { html: region('a', 'bono', 'nuevo') });
        p.w.dispatchEvent(new p.w.CustomEvent('jp:notif-new', { detail: { kinds: ['bono'] } }));
        await sleep(1000);
        ok(p.doc.getElementById('a').innerHTML === 'nuevo', 'jp:notif-new (usuarios sin push) también actualiza');
    }

    // ── Con push, el mismo aviso detectado por la campanita no repite ───
    {
        const p = page(region('a', 'class', 'x'), { html: region('a', 'class', 'y') });
        p.w.dispatchEvent(new p.w.CustomEvent('jp:push', { detail: { kind: 'class' } }));
        await sleep(1000);
        p.w.dispatchEvent(new p.w.CustomEvent('jp:notif-new', { detail: { kinds: ['class'] } }));
        await sleep(1000);
        ok(p.calls.length === 1, 'push + campanita del mismo aviso → una sola petición');
    }

    // ── Pestaña oculta: espera a volver ─────────────────────────────────
    {
        const p = page(region('a', 'class', 'viejo'), { html: region('a', 'class', 'nuevo') });
        Object.defineProperty(p.doc, 'hidden', { configurable: true, get: () => true });
        p.w.dispatchEvent(new p.w.CustomEvent('jp:push', { detail: { kind: 'class' } }));
        await sleep(1000);
        ok(p.calls.length === 0 && p.doc.getElementById('a').innerHTML === 'viejo', 'pestaña oculta: no pide nada');
        Object.defineProperty(p.doc, 'hidden', { configurable: true, get: () => false });
        p.doc.dispatchEvent(new p.w.Event('visibilitychange'));
        await sleep(1200);
        ok(p.doc.getElementById('a').innerHTML === 'nuevo', 'al volver a la pestaña se actualiza');
    }

    // ════════════════ FASE 2 — sondeo único ════════════════
    /** Página con un `fetch` totalmente controlado por `handler(url, opts)`. */
    const page2 = (bodyHtml, handler, url) => {
        const dom = new JSDOM('<!doctype html><body>' + bodyHtml + '</body>', { url: url || 'https://app.test/clases/7', runScripts: 'outside-only', pretendToBeVisual: true });
        const w = dom.window;
        const calls = [];
        const alerts = [];
        const navs = [];
        w.fetch = async (u, o) => { calls.push({ url: u, opts: o }); return handler(u, o, calls.length); };
        w.showAlert = (m, t) => alerts.push({ m, t });
        w.eval(SRC);
        w.JPLive._test.nav.assign = (u) => navs.push(['assign', u]);
        w.JPLive._test.nav.reload = () => navs.push(['reload']);
        return { w, calls, alerts, navs, doc: w.document };
    };
    const json = (obj) => ({ ok: true, redirected: false, url: 'https://app.test/live/ping', json: async () => obj });
    const snap = (extra) => Object.assign({ unread: 0, msg_unread: 0, tickets_pending: null, max_id: 10, new_kinds: [], stamps: { class: 'A', bono: 'B', ticket: 'C' } }, extra);

    {
        const { w } = page2('', () => json({}));
        const t = w.JPLive._test;
        ok(JSON.stringify(t.changedKinds({ class: 'A', bono: 'B' }, { class: 'A2', bono: 'B' })) === '["class"]', 'changedKinds: solo la zona cuyo sello cambió');
        ok(t.changedKinds(null, { class: 'A' }).length === 0 && t.changedKinds({ class: 'A' }, { class: null }).length === 0, 'changedKinds: sin referencia o sin sello (null) no cuenta como cambio');
    }
    {
        // El primer sondeo solo fija la referencia; el segundo, con otro sello de clases, actualiza.
        let n = 0;
        const p = page2(region('a', 'class', 'viejo') + region('b', 'bono', 'viejo-b'), (u) => {
            if (/live\/ping/.test(u)) { n++; return json(snap(n === 1 ? {} : { stamps: { class: 'A2', bono: 'B', ticket: 'C' } })); }
            return { ok: true, redirected: false, url: u, text: async () => '<body>' + region('a', 'class', 'NUEVO') + region('b', 'bono', 'NUEVO-B') + '</body>' };
        });
        await p.w.JPLive.ping();
        await sleep(900);
        ok(p.calls.length === 1 && p.doc.getElementById('a').innerHTML === 'viejo', 'primer sondeo: fija la referencia y no refresca nada');
        await p.w.JPLive.ping();
        await sleep(1000);
        ok(p.doc.getElementById('a').innerHTML === 'NUEVO', 'cambia el sello de clases → se actualiza la zona de clases (aunque no haya aviso)');
        ok(p.doc.getElementById('b').innerHTML === 'viejo-b', 'cambia solo el sello de clases → la zona de bonos no se toca');
        ok(/since=10/.test(p.calls[1].url), 'el sondeo manda el último id de aviso visto (since)');
    }
    {
        // Avisos nuevos (new_kinds) del servidor
        let n = 0;
        const p = page2(region('a', 'bono', 'viejo'), (u) => {
            if (/live\/ping/.test(u)) { n++; return json(snap(n === 1 ? {} : { new_kinds: ['bono'], max_id: 11 })); }
            return { ok: true, redirected: false, url: u, text: async () => '<body>' + region('a', 'bono', 'NUEVO') + '</body>' };
        });
        await p.w.JPLive.ping(); await p.w.JPLive.ping();
        await sleep(1000);
        ok(p.doc.getElementById('a').innerHTML === 'NUEVO', 'new_kinds del servidor → se actualiza la zona de ese tipo');
    }
    {
        // La barra superior recibe los contadores en un evento
        const p = page2('', () => json(snap({ unread: 4, msg_unread: 2, tickets_pending: 3 })));
        let got = null;
        p.w.addEventListener('jp:live-ping', (e) => { got = e.detail; });
        await p.w.JPLive.ping();
        ok(got && got.unread === 4 && got.msg_unread === 2 && got.tickets_pending === 3, 'jp:live-ping lleva los contadores para la barra superior');
    }
    {
        // Sesión caducada / error: ni evento ni cambios
        const p = page2(region('a', 'class', 'viejo'), () => ({ ok: true, redirected: true, url: 'https://app.test/login', json: async () => { throw new Error('html'); } }));
        let fired = false;
        p.w.addEventListener('jp:live-ping', () => { fired = true; });
        await p.w.JPLive.ping();
        ok(!fired, 'sondeo con sesión caducada: no emite nada y no rompe');
        const q = page2('', () => { throw new Error('sin red'); });
        let threw = false;
        try { await q.w.JPLive.ping(); } catch (e) { threw = true; }
        ok(!threw, 'sondeo sin red: no lanza');
    }

    // ════════════════ FASE 3 — formularios sin recarga ════════════════
    const formPage = (handler) => page2(
        region('mi', 'class', '<form id="f" action="/clases/7/ausencia" method="POST" data-live-form><input name="reason" value="enfermo"><button type="submit">Enviar</button></form>') +
        region('otra', 'bono', 'sin-cambios'),
        handler);
    const submit = (p) => p.doc.getElementById('f').dispatchEvent(new p.w.Event('submit', { bubbles: true, cancelable: true }));
    const okPage = (url, body) => ({ ok: true, redirected: true, url, text: async () => '<body><div class="alert-jp success">Aviso enviado</div>' + body + '</body>' });

    {
        const p = formPage((u, o) => okPage('https://app.test/clases/7', region('mi', 'class', '<p id=\"estado\">Aviso enviado: no vas a venir</p>') + region('otra', 'bono', 'sin-cambios')));
        const ev = submit(p);
        await sleep(300);
        ok(p.calls.length === 1 && p.calls[0].opts.method === 'POST' && p.calls[0].opts.body && p.calls[0].url === '/clases/7/ausencia', 'formulario: se envía por fetch (POST) a su action');
        ok(p.calls[0].opts.body.get('reason') === 'enfermo', 'formulario: lleva sus campos (FormData)');
        ok(p.doc.getElementById('estado'), 'formulario: la zona del propio formulario se actualiza con la respuesta');
        ok(p.alerts.length === 1 && p.alerts[0].m === 'Aviso enviado' && p.alerts[0].t === 'success', 'formulario: el mensaje del servidor sale como aviso emergente');
        ok(p.navs.length === 0, 'formulario: sin recarga ni navegación');
    }
    {
        // El formulario con foco/desplegable abierto en SU zona sí se actualiza; otra zona ocupada no
        const p = formPage((u) => okPage('https://app.test/clases/7', region('mi', 'class', 'listo') + region('otra', 'bono', 'CAMBIADA')));
        p.doc.getElementById('otra').setAttribute('data-live-skip', '');
        submit(p);
        await sleep(300);
        ok(p.doc.getElementById('mi').innerHTML === 'listo' && p.doc.getElementById('otra').innerHTML === 'sin-cambios', 'formulario: respeta data-live-skip en otras zonas');
    }
    {
        // Doble envío mientras está en curso
        let resolve;
        const p = formPage(() => new Promise((r) => { resolve = r; }));
        submit(p); submit(p);
        await sleep(100);
        ok(p.calls.length === 1, 'formulario: un doble clic no envía dos veces');
        ok(p.doc.querySelector('#f button').disabled, 'formulario: mientras envía, los botones quedan desactivados');
        resolve({ ok: true, redirected: true, url: 'https://app.test/clases/7', text: async () => '<body>' + region('mi', 'class', 'fin') + '</body>' });
        await sleep(100);
    }
    {
        // Redirige a otra pantalla → navegación normal
        const p = formPage(() => okPage('https://app.test/clases', 'otra pantalla'));
        submit(p);
        await sleep(300);
        ok(p.navs.length === 1 && p.navs[0][0] === 'assign' && p.navs[0][1] === 'https://app.test/clases', 'formulario: si el servidor redirige a otra pantalla, se navega a ella');
    }
    {
        // Error del servidor: aviso + recarga (nunca se queda a medias)
        const p = formPage(() => ({ ok: false, status: 500, redirected: false, url: 'https://app.test/clases/7/ausencia', text: async () => 'error' }));
        submit(p);
        await sleep(1500);
        ok(p.alerts.length === 1 && p.alerts[0].t === 'error', 'formulario con error 500: avisa');
        ok(p.navs.some((n) => n[0] === 'reload'), 'formulario con error 500: recarga la página');
    }
    {
        // No interfiere con formularios normales ni con los que piden confirmación
        const dom = page2('<form id="n" action="/x" method="POST"></form><form id="c" action="/y" method="POST" data-live-form data-ru-confirm="¿Seguro?"></form>', () => json({}));
        const evN = new dom.w.Event('submit', { bubbles: true, cancelable: true });
        dom.doc.getElementById('n').dispatchEvent(evN);
        const evC = new dom.w.Event('submit', { bubbles: true, cancelable: true });
        dom.doc.getElementById('c').dispatchEvent(evC);
        await sleep(100);
        ok(!evN.defaultPrevented && dom.calls.length === 0, 'formulario sin data-live-form: no se toca');
        ok(!evC.defaultPrevented, 'formulario con data-ru-confirm: sigue su camino (el diálogo de confirmación)');
    }
    {
        const doc = new JSDOM('<body><div class="alert-jp success">Hecho</div><div class="alert-jp error">Ups</div></body>').window.document;
        const { w } = page2('', () => json({}));
        const f = w.JPLive._test.flashFrom(doc);
        ok(f.length === 2 && f[0].type === 'success' && f[1].type === 'error' && f[1].text === 'Ups', 'flashFrom: lee los mensajes de la página (éxito/error)');
    }

    console.log(failed ? '\n' + failed + ' FALLO(S)' : '\nTodo OK');
    process.exit(failed ? 1 : 0);
})();
