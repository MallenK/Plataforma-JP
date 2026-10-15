/**
 * Test de public/sw.js (service worker): push, clic en la notificación, caché y cambio de suscripción.
 *   node tests/js/sw.test.cjs     (sin dependencias: ejecuta el SW en un contexto `vm` con mocks)
 */
'use strict';
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const SRC = fs.readFileSync(path.join(__dirname, '..', '..', 'public', 'sw.js'), 'utf8');
const SCOPE = 'https://app.test/plataforma/';

let failed = 0;
const ok = (c, l) => { console.log((c ? '  OK   ' : '  FAIL ') + l); if (!c) failed++; };

function loadSW({ windows = [], badge = true } = {}) {
    const listeners = {};
    const rec = { shown: [], opened: [], badges: [], subscribed: [], cachePuts: [], cachesOpened: [], deleted: [] };

    const cacheStore = { addAll: async () => {}, put: async (req) => { rec.cachePuts.push(req.url); } };
    const caches = {
        open: async (name) => { rec.cachesOpened.push(name); return cacheStore; },
        match: async (r) => (typeof r === 'string' && r.endsWith('offline.html') ? { offline: true } : undefined),
        keys: async () => ['jp-sw-v1-shell', 'jp-sw-v2-shell', 'otra-cosa'],
        delete: async (k) => { rec.deleted.push(k); },
    };
    const self = {
        registration: {
            scope: SCOPE,
            showNotification: async (title, opts) => { rec.shown.push({ title, opts }); },
            pushManager: { subscribe: async (o) => { rec.subscribed.push(o); return {}; } },
        },
        clients: {
            matchAll: async () => windows,
            openWindow: async (u) => { rec.opened.push(u); },
            claim: async () => {},
        },
        navigator: badge ? { setAppBadge: async (n) => { rec.badges.push(n); } } : {},
        skipWaiting: async () => {},
        addEventListener: (name, fn) => { listeners[name] = fn; },
    };
    const ctx = vm.createContext({
        self, caches, URL, Promise, console,
        location: { origin: 'https://app.test' },
        fetch: async () => { throw new Error('offline'); },
    });
    vm.runInContext(SRC, ctx);

    // Dispara un evento y espera a lo que pasó a waitUntil/respondWith.
    const fire = async (name, ev = {}) => {
        let pending;
        ev.waitUntil = (p) => { pending = p; };
        ev.respondWith = (p) => { pending = p; };
        listeners[name](ev);
        return pending ? pending : undefined;
    };
    return { listeners, rec, fire, ctx, self };
}

const win = (url, extra = {}) => {
    const w = { url, focused: false, navigatedTo: null, messages: [],
        focus: async () => { w.focused = true; },
        navigate: async (u) => { w.navigatedTo = u; },
        postMessage: (m) => { w.messages.push(m); }, ...extra };
    return w;
};
const pushEvent = (data) => ({ data: data === undefined ? null : { json: () => (typeof data === 'string' ? JSON.parse(data) : data), text: () => String(data) } });

(async () => {
    // ── Push ────────────────────────────────────────────────────────────
    {
        const w = win(SCOPE + 'dashboard');
        const sw = loadSW({ windows: [w] });
        await sw.fire('push', pushEvent({ id: 42, title: 'Nuevo mensaje', body: 'Hola', url: 'notificaciones/42/ir', unread: 3 }));
        const n = sw.rec.shown[0];
        ok(sw.rec.shown.length === 1, 'push: muestra una notificación');
        ok(n.title === 'Nuevo mensaje' && n.opts.body === 'Hola', 'push: título y cuerpo del payload');
        ok(n.opts.tag === 'jp-n-42', 'push: tag por id (evita duplicados)');
        ok(n.opts.data.url === 'notificaciones/42/ir', 'push: guarda la URL de destino');
        ok(n.opts.icon === SCOPE + 'assets/img/pwa/icon-192.png', 'push: icono dentro del scope');
        ok(sw.rec.badges[0] === 3, 'push: pone el número en el icono de la app');
        ok(w.messages[0] && w.messages[0].type === 'jp-push', 'push: avisa a las pestañas abiertas');
    }
    {
        const sw = loadSW();
        await sw.fire('push', pushEvent('texto plano que no es json'));
        ok(sw.rec.shown[0].title === 'JP Preparation' && sw.rec.shown[0].opts.body === 'texto plano que no es json', 'push: payload no JSON → título por defecto y texto como cuerpo');
    }
    {
        const sw = loadSW();
        await sw.fire('push', pushEvent(undefined));
        ok(sw.rec.shown.length === 1 && sw.rec.shown[0].title === 'JP Preparation', 'push: sin datos → igualmente muestra algo (userVisibleOnly)');
        ok(sw.rec.shown[0].opts.data.url === 'notificaciones', 'push: sin URL → /notificaciones');
    }
    {
        const sw = loadSW({ badge: false });
        let threw = false;
        try { await sw.fire('push', pushEvent({ id: 1, title: 'x', unread: 2 })); } catch (e) { threw = true; }
        ok(!threw && sw.rec.shown.length === 1, 'push: navegador sin Badging API → no falla');
    }
    {
        const sw = loadSW({ windows: [{ url: SCOPE, postMessage: () => { throw new Error('ventana muerta'); } }] });
        let threw = false;
        try { await sw.fire('push', pushEvent({ id: 1, title: 'x' })); } catch (e) { threw = true; }
        ok(!threw && sw.rec.shown.length === 1, 'push: una ventana rota no impide haber mostrado la notificación');
    }

    // ── Clic en la notificación ─────────────────────────────────────────
    {
        const w = win(SCOPE + 'clases');
        const sw = loadSW({ windows: [w] });
        let closed = false;
        await sw.fire('notificationclick', { notification: { close: () => { closed = true; }, data: { url: 'notificaciones/7/ir' } } });
        ok(closed, 'clic: cierra la notificación');
        ok(w.focused && w.navigatedTo === SCOPE + 'notificaciones/7/ir', 'clic: reutiliza la ventana abierta y navega a /ir');
        ok(sw.rec.opened.length === 0, 'clic: no abre otra ventana si ya hay una');
    }
    {
        const sw = loadSW({ windows: [win('https://otro-sitio.test/')] });
        await sw.fire('notificationclick', { notification: { close() {}, data: { url: 'notificaciones/7/ir' } } });
        ok(sw.rec.opened[0] === SCOPE + 'notificaciones/7/ir', 'clic: ventanas de otro origen se ignoran → abre una nueva');
    }
    {
        const sw = loadSW();
        await sw.fire('notificationclick', { notification: { close() {}, data: null } });
        ok(sw.rec.opened[0] === SCOPE + 'notificaciones', 'clic: sin datos → abre /notificaciones');
    }
    {
        const w = win(SCOPE + 'x', { navigate: async () => { throw new Error('no se puede navegar'); } });
        const sw = loadSW({ windows: [w] });
        await sw.fire('notificationclick', { notification: { close() {}, data: { url: 'notificaciones/9/ir' } } });
        ok(sw.rec.opened[0] === SCOPE + 'notificaciones/9/ir', 'clic: si navigate() falla, cae a openWindow');
    }

    // ── Caché / offline ─────────────────────────────────────────────────
    {
        const sw = loadSW();
        const post = { method: 'POST', url: SCOPE + 'x', mode: 'cors', headers: new Map() };
        ok((await sw.fire('fetch', { request: post })) === undefined, 'fetch: las peticiones POST no se tocan');

        const other = { method: 'GET', url: 'https://cdn.otro.com/a.js', mode: 'cors', headers: new Map() };
        ok((await sw.fire('fetch', { request: other })) === undefined, 'fetch: otros orígenes no se tocan');

        const html = { method: 'GET', url: SCOPE + 'dashboard', mode: 'navigate', headers: new Map() };
        const res = await sw.fire('fetch', { request: html });
        ok(res && res.offline === true, 'fetch: navegación sin red → pantalla offline');
        ok(sw.rec.cachePuts.length === 0, 'fetch: NUNCA se cachea HTML de la plataforma');

        const dyn = { method: 'GET', url: SCOPE + 'api/clases/buscar?q=ana', mode: 'cors', headers: new Map() };
        ok((await sw.fire('fetch', { request: dyn })) === undefined, 'fetch: las llamadas AJAX/JSON no se interceptan');
    }
    {
        const sw = loadSW();
        await sw.fire('activate', {});
        ok(sw.rec.deleted.length === 1 && sw.rec.deleted[0] === 'jp-sw-v1-shell', 'activate: borra cachés jp-sw- antiguas y deja las demás');
    }

    // ── Cambio de suscripción ───────────────────────────────────────────
    {
        const w = win(SCOPE);
        const sw = loadSW({ windows: [w] });
        const key = new Uint8Array([4, 1, 2, 3]);
        await sw.fire('pushsubscriptionchange', { oldSubscription: { options: { applicationServerKey: key } } });
        ok(sw.rec.subscribed.length === 1 && sw.rec.subscribed[0].applicationServerKey === key && sw.rec.subscribed[0].userVisibleOnly === true,
            'cambio de suscripción: se vuelve a suscribir con la misma clave del servidor');
        ok(w.messages[0] && w.messages[0].type === 'jp-subchange', 'cambio de suscripción: avisa a las pestañas para registrarla en el servidor');
    }
    {
        const w = win(SCOPE);
        const sw = loadSW({ windows: [w] });
        let threw = false;
        try { await sw.fire('pushsubscriptionchange', {}); } catch (e) { threw = true; }
        ok(!threw && w.messages[0].type === 'jp-subchange', 'cambio de suscripción: sin oldSubscription no falla y avisa igualmente');
    }

    console.log(failed ? `\n${failed} FALLO(S)` : '\nTodo OK');
    process.exit(failed ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
