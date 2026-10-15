/**
 * Test de public/assets/js/pwa.js: activar/desactivar notificaciones, errores y resincronización.
 *   node tests/js/pwa.test.cjs     (sin dependencias: ejecuta pwa.js en un contexto `vm` con mocks)
 */
'use strict';
const fs = require('fs');
const path = require('path');
const vm = require('vm');

const SRC = fs.readFileSync(path.join(__dirname, '..', '..', 'public', 'assets', 'js', 'pwa.js'), 'utf8');
const flush = () => new Promise((r) => setImmediate(r));

let failed = 0;
const ok = (c, l) => { console.log((c ? '  OK   ' : '  FAIL ') + l); if (!c) failed++; };
const rejectsWith = async (p, re) => { try { await p; return false; } catch (e) { return re.test(String(e && e.message)); } };

/**
 * @param {object} o
 * @param {string} [o.permission]   Notification.permission inicial
 * @param {boolean} [o.push]        el navegador soporta PushManager
 * @param {object|null} [o.cfg]     window.JP_PUSH
 * @param {object} [o.store]        localStorage inicial
 * @param {string} [o.ua]
 * @param {object} [o.existingSub]  suscripción que ya tiene el navegador
 */
function load(o = {}) {
    const rec = { fetches: [], events: [], warns: [], subscribeCalls: [], unsubscribed: [], swMessage: null };
    const store = { ...(o.store || {}) };
    const mkSub = (endpoint) => ({
        endpoint,
        toJSON: () => ({ endpoint, keys: { p256dh: 'P', auth: 'A' } }),
        unsubscribe: async () => { rec.unsubscribed.push(endpoint); return true; },
    });

    let current = o.existingSub ? mkSub(o.existingSub) : null;
    let n = 0;
    const reg = {
        pushManager: {
            getSubscription: async () => current,
            subscribe: async (opts) => {
                rec.subscribeCalls.push(opts);
                if (o.subscribeError && rec.subscribeCalls.length === 1) { const e = new Error('clave distinta'); e.name = o.subscribeError; throw e; }
                current = mkSub('https://fcm.googleapis.com/fcm/send/new' + (++n));
                return current;
            },
        },
    };

    const responses = o.responses || [];
    const fetchMock = async (url, init) => {
        rec.fetches.push({ url, init, body: init && init.body ? JSON.parse(init.body) : null });
        const r = responses.length ? responses.shift() : { status: 200, json: { ok: true, csrf: 'csrf-' + rec.fetches.length } };
        if (r.reject) throw new Error('sin red');
        return { ok: r.status >= 200 && r.status < 300, status: r.status, json: async () => { if (r.badJson) throw new Error('json'); return r.json; } };
    };

    const Notification = { permission: o.permission || 'default' };
    Notification.requestPermission = async () => {
        if (o.requestResult) Notification.permission = o.requestResult;
        return o.requestResult || Notification.permission;
    };

    const window = {
        APP_BASE: '/plataforma',
        isSecureContext: true,
        JP_PUSH: o.cfg === undefined ? { key: 'AQID', csrfName: 'jp_csrf_token', csrfHash: 'csrf-0', sessionHasSub: false } : o.cfg,
        matchMedia: () => ({ matches: false }),
        navigator: {},
        addEventListener: () => {},
        dispatchEvent: (ev) => { rec.events.push(ev.type); },
        Notification,
    };
    if (o.push !== false) window.PushManager = function PushManager() {};

    const navigator = {
        userAgent: o.ua || 'Mozilla/5.0 (X11; Linux x86_64) Chrome/124.0',
        platform: 'Linux x86_64',
        maxTouchPoints: 0,
        serviceWorker: {
            register: async () => reg,
            ready: Promise.resolve(reg),
            addEventListener: (name, fn) => { if (name === 'message') rec.swMessage = fn; },
        },
    };
    window.navigator = navigator;

    const ctx = vm.createContext({
        window, navigator, Notification,
        document: { currentScript: null, readyState: 'complete', addEventListener: () => {} },
        location: { hostname: 'app.test' },
        localStorage: {
            getItem: (k) => (k in store ? store[k] : null),
            setItem: (k, v) => { store[k] = String(v); },
            removeItem: (k) => { delete store[k]; },
        },
        fetch: fetchMock,
        CustomEvent: class { constructor(type, init) { this.type = type; this.detail = init && init.detail; } },
        atob: (s) => Buffer.from(s, 'base64').toString('binary'),
        Uint8Array, Date, JSON, Promise, parseInt, Error,
        console: { warn: (...a) => rec.warns.push(a.join(' ')), log() {} },
    });
    vm.runInContext(SRC, ctx);
    return { api: window.JPPwa, rec, store, window, Notification, cfg: window.JP_PUSH, setCurrent: (e) => { current = e ? mkSub(e) : null; } };
}

(async () => {
    // ── enable ──────────────────────────────────────────────────────────
    {
        const t = load({ requestResult: 'granted' });
        await t.api.enable();
        const call = t.rec.fetches.find((f) => f.url.endsWith('/plataforma/push/subscribe'));
        ok(!!call, 'enable: registra la suscripción en /push/subscribe (respeta APP_BASE)');
        ok(call.init.headers['X-CSRF-TOKEN'] === 'csrf-0', 'enable: envía el token CSRF');
        ok(call.init.headers['X-Requested-With'] === 'XMLHttpRequest', 'enable: marca la petición como AJAX (para recibir JSON en errores)');
        ok(call.body.subscription.endpoint.startsWith('https://fcm.googleapis.com/'), 'enable: envía la suscripción del navegador');
        ok(t.rec.subscribeCalls[0].userVisibleOnly === true && t.rec.subscribeCalls[0].applicationServerKey instanceof Uint8Array, 'enable: suscribe con la clave VAPID (Uint8Array) y userVisibleOnly');
        ok(t.cfg.csrfHash === 'csrf-1', 'enable: renueva el CSRF con el que devuelve el servidor');
        ok(t.store.jp_push_endpoint === call.body.subscription.endpoint, 'enable: recuerda el endpoint registrado');
        ok(t.rec.events.includes('jp:pwa-state'), 'enable: emite jp:pwa-state');
    }
    {
        const t = load({ requestResult: 'denied' });
        ok(await rejectsWith(t.api.enable(), /bloqueadas/), 'enable: permiso denegado → mensaje de ajustes del navegador');
        ok(t.rec.fetches.length === 0 && t.rec.subscribeCalls.length === 0, 'enable: permiso denegado → no se suscribe ni llama al servidor');
    }
    {
        const t = load({ requestResult: 'default' });
        ok(await rejectsWith(t.api.enable(), /No se concedió/), 'enable: permiso ignorado → mensaje claro');
    }
    {
        const t = load({ requestResult: 'granted', responses: [{ status: 503, json: { ok: false, error: 'Las notificaciones push no están configuradas en el servidor.' } }] });
        ok(await rejectsWith(t.api.enable(), /no están configuradas/), 'enable: error del servidor → se propaga su mensaje');
        ok(t.rec.unsubscribed.length === 1, 'enable: si el servidor falla, se deshace la suscripción local (sin fila servidor = sin suscripción)');
        ok(!t.store.jp_push_endpoint, 'enable: si el servidor falla, no se anota como sincronizada');
    }
    {
        const t = load({ requestResult: 'granted', responses: [{ status: 500, badJson: true }] });
        ok(await rejectsWith(t.api.enable(), /Error 500/), 'enable: respuesta 500 sin JSON → "Error 500"');
    }
    {
        const t = load({ requestResult: 'granted', responses: [{ reject: true }] });
        ok(await rejectsWith(t.api.enable(), /sin red/), 'enable: sin red → error y suscripción local deshecha');
        ok(t.rec.unsubscribed.length === 1, 'enable: sin red → no deja suscripción huérfana');
    }
    {
        const t = load({ cfg: null });
        ok(await rejectsWith(t.api.enable(), /no están configuradas/), 'enable: sin clave VAPID en la página → rechaza sin tocar nada');
    }
    {
        const t = load({ push: false });
        ok(await rejectsWith(t.api.enable(), /no admite/), 'enable: navegador sin PushManager → mensaje claro');
        const s = await t.api.state();
        ok(s.supported === false && s.permission === 'unsupported', 'state: navegador sin push → supported=false');
    }
    {
        // La clave VAPID cambió en el servidor: InvalidStateError → se descarta la vieja y se reintenta.
        const t = load({ requestResult: 'granted', subscribeError: 'InvalidStateError', existingSub: 'https://fcm.googleapis.com/fcm/send/vieja' });
        await t.api.enable();
        ok(t.rec.unsubscribed.includes('https://fcm.googleapis.com/fcm/send/vieja'), 'enable: clave VAPID cambiada → descarta la suscripción vieja');
        ok(t.rec.subscribeCalls.length === 2, 'enable: …y vuelve a suscribir con la clave nueva');
    }

    // ── disable ─────────────────────────────────────────────────────────
    {
        const t = load({ existingSub: 'https://fcm.googleapis.com/fcm/send/mia', store: { jp_push_synced: '1', jp_push_endpoint: 'x' } });
        await t.api.disable();
        ok(t.rec.unsubscribed[0] === 'https://fcm.googleapis.com/fcm/send/mia', 'disable: da de baja la suscripción del navegador');
        const call = t.rec.fetches.find((f) => f.url.endsWith('/push/unsubscribe'));
        ok(call && call.body.endpoint === 'https://fcm.googleapis.com/fcm/send/mia', 'disable: avisa al servidor con el endpoint');
        ok(!t.store.jp_push_synced && !t.store.jp_push_endpoint, 'disable: limpia lo recordado');
    }
    {
        const t = load();
        await t.api.disable();
        ok(t.rec.fetches.length === 0, 'disable: sin suscripción → no llama al servidor');
    }

    // ── state ───────────────────────────────────────────────────────────
    {
        const ios = 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_4 like Mac OS X) AppleWebKit/605.1.15 Version/17.4 Mobile/15E148 Safari/604.1';
        const t = load({ ua: ios });
        const s = await t.api.state();
        ok(s.ios && s.needsInstall && !s.standalone, 'state: iPhone sin instalar → needsInstall (el push exige la app en pantalla de inicio)');
        ok(s.configured === true, 'state: configured=true con clave VAPID');
    }

    // ── resync ──────────────────────────────────────────────────────────
    const EP = 'https://fcm.googleapis.com/fcm/send/mia';
    const subscribes = (t) => t.rec.fetches.filter((f) => f.url.endsWith('/push/subscribe')).length;
    {
        const t = load({ permission: 'granted', existingSub: EP, store: { jp_push_synced: String(Date.now()), jp_push_endpoint: EP },
            cfg: { key: 'AQID', csrfHash: 'c', sessionHasSub: true } });
        await flush();
        ok(subscribes(t) === 0, 'resync: reciente + misma suscripción + sesión con suscripción → no llama al servidor');
    }
    {
        const t = load({ permission: 'granted', existingSub: EP, store: { jp_push_synced: String(Date.now()), jp_push_endpoint: 'https://fcm.googleapis.com/fcm/send/OTRA' },
            cfg: { key: 'AQID', csrfHash: 'c', sessionHasSub: true } });
        await flush();
        ok(subscribes(t) === 1, 'resync: el navegador cambió de endpoint → se registra YA, sin esperar las 6 h');
    }
    {
        const t = load({ permission: 'granted', existingSub: EP, store: { jp_push_synced: String(Date.now()), jp_push_endpoint: EP },
            cfg: { key: 'AQID', csrfHash: 'c', sessionHasSub: false } });
        await flush();
        ok(subscribes(t) === 1, 'resync: otro usuario inició sesión en este navegador (sin suscripción en sesión) → reasigna');
    }
    {
        const t = load({ permission: 'granted', existingSub: EP, store: { jp_push_synced: String(Date.now() - 7 * 3600 * 1000), jp_push_endpoint: EP },
            cfg: { key: 'AQID', csrfHash: 'c', sessionHasSub: true } });
        await flush();
        ok(subscribes(t) === 1, 'resync: pasaron más de 6 h → renueva');
    }
    {
        const t = load({ permission: 'granted', existingSub: null, cfg: { key: 'AQID', csrfHash: 'c', sessionHasSub: true } });
        await flush();
        ok(t.rec.subscribeCalls.length === 1 && subscribes(t) === 1, 'resync: permiso concedido pero el navegador perdió la suscripción → crea otra y la registra');
    }
    {
        const t = load({ permission: 'default', existingSub: EP });
        await flush();
        ok(subscribes(t) === 0 && t.rec.subscribeCalls.length === 0, 'resync: sin permiso concedido → no hace nada (nunca pide permiso solo)');
    }
    {
        const t = load({ permission: 'denied', existingSub: EP });
        await flush();
        ok(subscribes(t) === 0, 'resync: permiso denegado → no hace nada');
    }
    {
        const t = load({ permission: 'granted', existingSub: EP, responses: [{ reject: true }], cfg: { key: 'AQID', csrfHash: 'c', sessionHasSub: false } });
        let threw = false;
        try { await flush(); await t.api.resync(true); } catch (e) { threw = true; }
        ok(!threw, 'resync: un fallo de red nunca rompe la página (no rechaza)');
        ok(t.rec.warns.some((w) => /resync push/.test(w)), 'resync: …y queda registrado en la consola');
    }
    {
        const t = load({ permission: 'granted', existingSub: EP, store: { jp_push_synced: String(Date.now()), jp_push_endpoint: EP },
            cfg: { key: 'AQID', csrfHash: 'c', sessionHasSub: true } });
        await flush();
        const before = subscribes(t);
        await t.api.resync(true);
        ok(subscribes(t) === before + 1, 'resync(force): ignora el reloj y el endpoint recordado');
    }
    {
        const t = load({ permission: 'granted', existingSub: EP, store: { jp_push_synced: String(Date.now()), jp_push_endpoint: EP },
            cfg: { key: 'AQID', csrfHash: 'c', sessionHasSub: true } });
        await flush();
        const before = subscribes(t);
        t.setCurrent('https://fcm.googleapis.com/fcm/send/rotada');
        t.rec.swMessage({ data: { type: 'jp-subchange' } });
        await flush();
        ok(subscribes(t) === before + 1, 'mensaje jp-subchange del service worker → se registra la suscripción nueva');
    }
    {
        const t = load();
        t.rec.swMessage({ data: { type: 'jp-push', payload: { id: 1 } } });
        ok(t.rec.events.includes('jp:push'), 'mensaje jp-push del service worker → emite jp:push (la campanita se refresca)');
    }
    {
        const t = load({ cfg: null, permission: 'granted', existingSub: EP });
        await flush();
        ok(t.rec.fetches.length === 0, 'páginas públicas (sin JP_PUSH): no sincroniza nada');
    }

    console.log(failed ? `\n${failed} FALLO(S)` : '\nTodo OK');
    process.exit(failed ? 1 : 0);
})().catch((e) => { console.error(e); process.exit(1); });
