/* JP Preparation — Service Worker (PWA + notificaciones push)
 *
 * - Push: muestra la notificación y la abre en su origen (/notificaciones/:id/ir).
 * - Offline: solo las pantallas estáticas y los assets. NUNCA se cachea HTML de
 *   la plataforma (es contenido privado, por usuario, y cambia a cada momento).
 * - Sube VERSION cuando cambies este fichero o offline.html.
 */
const VERSION = 'jp-sw-v3';
const SHELL   = VERSION + '-shell';
const ASSETS  = VERSION + '-assets';
const SCOPE   = self.registration.scope; // termina en "/"
const OFFLINE = new URL('offline.html', SCOPE).href;
const PRECACHE = [
  OFFLINE,
  new URL('assets/img/pwa/icon-192.png', SCOPE).href,
];

self.addEventListener('install', (event) => {
  event.waitUntil(caches.open(SHELL).then((c) => c.addAll(PRECACHE)).then(() => self.skipWaiting()));
});

self.addEventListener('activate', (event) => {
  event.waitUntil((async () => {
    const keep = [SHELL, ASSETS];
    for (const k of await caches.keys()) if (k.startsWith('jp-sw-') && !keep.includes(k)) await caches.delete(k);
    await self.clients.claim();
  })());
});

self.addEventListener('fetch', (event) => {
  const req = event.request;
  if (req.method !== 'GET') return;
  const url = new URL(req.url);
  if (url.origin !== location.origin) return;

  // Navegación: red; sin red, pantalla "sin conexión".
  if (req.mode === 'navigate') {
    event.respondWith(fetch(req).catch(() => caches.match(OFFLINE)));
    return;
  }

  // Assets propios: red primero (siempre lo último desplegado), caché como respaldo offline.
  if (url.pathname.includes('/assets/') && !req.headers.has('range')) {
    event.respondWith((async () => {
      try {
        const res = await fetch(req);
        if (res.ok) (await caches.open(ASSETS)).put(req, res.clone());
        return res;
      } catch (e) {
        const hit = await caches.match(req);
        if (hit) return hit;
        throw e;
      }
    })());
  }
});

/* ── Push ─────────────────────────────────────────────────────────────── */

/* Anti-saturación: si ya hay GROUP_AT avisos (contando el que llega) sin atender en la
 * bandeja del móvil, se sustituyen por UNO solo: «Tienes N notificaciones nuevas». Las
 * notificaciones siguen todas en la campanita de la plataforma. El servidor ya resume las
 * ráfagas de una misma pasada; esto cubre las que llegan una a una en poco tiempo. */
const GROUP_AT  = 3;
const GROUP_TAG = 'jp-group';

async function buildDisplay(data) {
  const icon  = new URL('assets/img/pwa/icon-192.png', SCOPE).href;
  const badge = new URL('assets/img/pwa/badge-96.png', SCOPE).href;
  const incoming = data.count || 1;
  const tag = data.tag || (data.id ? 'jp-n-' + data.id : undefined);

  let shown = [];
  try { shown = await self.registration.getNotifications(); } catch (_) { /* sin soporte: se muestra tal cual */ }

  // Lo que ya hay en pantalla, sin contar lo que este aviso va a sustituir (misma tag).
  const others = shown.filter((n) => !tag || n.tag !== tag);
  const pending = others.reduce((sum, n) => sum + ((n.data && n.data.count) || 1), 0);
  const total = pending + incoming;

  if (total >= GROUP_AT) {
    others.forEach((n) => n.close());
    return {
      title: 'Tienes ' + total + ' notificaciones nuevas',
      opts: {
        body: 'Toca para verlas todas.',
        icon, badge, lang: 'es',
        tag: GROUP_TAG,
        renotify: false, // al crecer el grupo se actualiza sin volver a sonar
        data: { url: 'notificaciones', id: null, count: total },
      },
    };
  }

  return {
    title: data.title || 'JP Preparation',
    opts: {
      body: data.body || '',
      icon, badge, lang: 'es',
      tag,
      renotify: !!data.tag && data.tag !== GROUP_TAG, // chat: cada mensaje avisa, pero en una sola tarjeta
      data: { url: data.url || 'notificaciones', id: data.id || null, count: incoming },
    },
  };
}

self.addEventListener('push', (event) => {
  let data = {};
  try { data = event.data ? event.data.json() : {}; }
  catch (_) { data = { title: 'JP Preparation', body: event.data ? event.data.text() : '' }; }

  event.waitUntil((async () => {
    // userVisibleOnly: SIEMPRE se muestra algo, aunque lo demás falle (si no, el navegador revoca el permiso).
    let view;
    try { view = await buildDisplay(data); }
    catch (_) {
      view = { title: data.title || 'JP Preparation', opts: { body: data.body || '', lang: 'es', data: { url: data.url || 'notificaciones', id: data.id || null } } };
    }
    await self.registration.showNotification(view.title, view.opts);
    try {
      if (typeof data.unread === 'number' && self.navigator && self.navigator.setAppBadge) {
        await self.navigator.setAppBadge(data.unread);
      }
      // Pestañas abiertas: que refresquen la campanita al instante.
      const wins = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
      wins.forEach((w) => w.postMessage({ type: 'jp-push', payload: data }));
    } catch (_) { /* sin soporte de badge o sin ventanas: la notificación ya se mostró */ }
  })());
});

self.addEventListener('notificationclick', (event) => {
  event.notification.close();
  const target = new URL((event.notification.data && event.notification.data.url) || 'notificaciones', SCOPE).href;

  event.waitUntil((async () => {
    const wins = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    const mine = wins.find((w) => w.url.startsWith(SCOPE));
    if (mine) {
      try {
        await mine.focus();
        if ('navigate' in mine) await mine.navigate(target);
        else await self.clients.openWindow(target);
        return;
      } catch (_) { /* cae a openWindow */ }
    }
    await self.clients.openWindow(target);
  })());
});

/* El navegador rotó o caducó la suscripción: crear otra con la misma clave del servidor y avisar a las
 * pestañas para que la registren en el servidor (el SW no tiene el token CSRF; lo hace pwa.js). Si no hay
 * pestañas, pwa.js detecta el cambio de endpoint al abrir la siguiente página. */
self.addEventListener('pushsubscriptionchange', (event) => {
  event.waitUntil((async () => {
    try {
      const key = event.oldSubscription && event.oldSubscription.options && event.oldSubscription.options.applicationServerKey;
      if (key) await self.registration.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: key });
    } catch (_) { /* pwa.js lo reintenta al abrir la app */ }
    const wins = await self.clients.matchAll({ type: 'window', includeUncontrolled: true });
    wins.forEach((w) => w.postMessage({ type: 'jp-subchange' }));
  })());
});
