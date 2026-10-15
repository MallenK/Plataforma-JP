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

    console.log(failed ? '\n' + failed + ' FALLO(S)' : '\nTodo OK');
    process.exit(failed ? 1 : 0);
})();
