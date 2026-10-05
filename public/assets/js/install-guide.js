/* JP Preparation — guía para instalar la app (PWA), adaptada al dispositivo.
 *
 * iOS no permite instalar desde un botón (no existe beforeinstallprompt): hay que explicar el
 * paso manual de Safari. En Android/escritorio sí hay botón cuando el navegador lo permite.
 *
 *   JPInstall.detect({ ua, touchPoints, standalone, canInstall })  → clave del caso (pura, testeable)
 *   JPInstall.mount(rootEl, { share: true })                        → pinta la guía (+ compartir/QR)
 */
(function (root) {
    'use strict';

    var ICON = {
        share: '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M12 15V3"/><path d="M8 7l4-4 4 4"/><path d="M6 11H5a1 1 0 0 0-1 1v8a1 1 0 0 0 1 1h14a1 1 0 0 0 1-1v-8a1 1 0 0 0-1-1h-1"/></svg>',
        add:   '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="3"/><path d="M12 8v8M8 12h8"/></svg>',
        dots:  '<svg viewBox="0 0 24 24" width="22" height="22" fill="currentColor" aria-hidden="true"><circle cx="12" cy="5" r="1.8"/><circle cx="12" cy="12" r="1.8"/><circle cx="12" cy="19" r="1.8"/></svg>',
        check: '<svg viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M5 13l4 4L19 7"/></svg>'
    };

    /**
     * Caso de instalación según el dispositivo. Claves:
     *  installed | ios-safari | ios-other | ios-inapp | android | mac-safari | desktop
     */
    function detect(env) {
        env = env || {};
        var ua = env.ua || '';
        if (env.standalone) return 'installed';

        var ios = /iPhone|iPad|iPod/.test(ua) || (/Macintosh/.test(ua) && (env.touchPoints || 0) > 1); // iPadOS se hace pasar por Mac
        if (ios) {
            if (/CriOS|FxiOS|EdgiOS|OPiOS|OPT\/|DuckDuckGo|Brave/.test(ua)) return 'ios-other';
            // Navegadores integrados en apps (Instagram, Facebook, Gmail…): WKWebView no lleva el token "Safari/"
            if (!/Safari\//.test(ua) || /FBAN|FBAV|Instagram|Line\/|Twitter|MicroMessenger|Snapchat|GSA\//.test(ua)) return 'ios-inapp';
            return 'ios-safari';
        }
        if (/Android/.test(ua)) return 'android';
        if (/Macintosh/.test(ua) && /Safari\//.test(ua) && !/Chrome|Chromium|Edg\/|OPR\/|Firefox/.test(ua)) return 'mac-safari';
        return 'desktop';
    }

    function steps(list) {
        return '<ol class="ig-steps">' + list.map(function (s, i) {
            return '<li><span class="ig-n">' + (i + 1) + '</span><span class="ig-t">' + s + '</span></li>';
        }).join('') + '</ol>';
    }
    function chip(icon, label) { return '<span class="ig-chip">' + icon + '<b>' + label + '</b></span>'; }

    var TITLES = {
        'installed':  'La app ya está instalada',
        'ios-safari': 'iPhone / iPad (Safari)',
        'ios-other':  'iPhone / iPad (otro navegador)',
        'ios-inapp':  'iPhone / iPad (navegador de otra app)',
        'android':    'Android',
        'mac-safari': 'Mac (Safari)',
        'desktop':    'Ordenador (Chrome, Edge…)'
    };

    function body(kase, env) {
        switch (kase) {
            case 'installed':
                return '<p class="ig-ok">' + ICON.check + '<span>Estás usando la app instalada. Para recibir avisos al instante ve a <b>Configuración → Notificaciones</b> y pulsa «Activar notificaciones».</span></p>';

            case 'ios-safari':
                return steps([
                    'Pulsa el botón <b>Compartir</b> ' + chip(ICON.share, 'Compartir') + ' de Safari (abajo en iPhone; arriba a la derecha en iPad).',
                    'Desliza el menú hacia arriba y elige <b>«Añadir a pantalla de inicio»</b> ' + chip(ICON.add, 'Añadir a pantalla de inicio') + '. Si no aparece, pulsa «Más» al final de la lista.',
                    'Pulsa <b>«Añadir»</b> arriba a la derecha. Verás el icono de JP Preparation en tu pantalla de inicio.',
                    '<b>Abre la app desde ese icono</b> (no desde Safari) y ve a <b>Configuración → Notificaciones → Activar notificaciones</b>.'
                ]) + '<p class="ig-note">Necesitas iOS 16.4 o superior. En el iPhone los avisos solo funcionan con la app instalada.</p>';

            case 'ios-other':
            case 'ios-inapp':
                return '<p class="ig-warn">' + (kase === 'ios-inapp'
                        ? 'Estás en el navegador integrado de otra aplicación (WhatsApp, Instagram, Gmail…). Desde aquí no se puede instalar.'
                        : 'Para instalar la app en el iPhone o iPad hay que usar <b>Safari</b>.') + '</p>' +
                    steps([
                        'Copia el enlace con el botón de abajo.',
                        'Abre <b>Safari</b> y pega el enlace en la barra de direcciones.',
                        'Sigue los pasos de «iPhone / iPad (Safari)».'
                    ]) + '<button type="button" class="ig-btn" data-ig-copy>Copiar enlace</button>';

            case 'android':
                return env.canInstall
                    ? '<p>Tu navegador permite instalarla con un toque:</p><button type="button" class="ig-btn" data-ig-install>Instalar la app</button>'
                    : steps([
                        'Abre el menú del navegador ' + chip(ICON.dots, 'Menú') + '.',
                        'Elige <b>«Instalar app»</b> o <b>«Añadir a pantalla de inicio»</b>.',
                        'Confirma. La app aparecerá entre tus aplicaciones.'
                    ]);

            case 'mac-safari':
                return steps([
                    'En Safari abre el menú <b>Archivo</b>.',
                    'Elige <b>«Añadir al Dock…»</b> (Safari 17 o superior).',
                    'Las notificaciones funcionan en Safari de Mac (macOS 13 o superior) con solo activarlas en <b>Configuración → Notificaciones</b>.'
                ]);

            default: // desktop
                return env.canInstall
                    ? '<p>Tu navegador permite instalarla con un clic:</p><button type="button" class="ig-btn" data-ig-install>Instalar la app</button>'
                    : steps([
                        'Busca el icono de instalar en el extremo derecho de la barra de direcciones (un monitor con una flecha).',
                        'O abre el menú ' + chip(ICON.dots, 'Menú') + ' → <b>«Guardar y compartir» → «Instalar JP Preparation»</b>.'
                    ]);
        }
    }

    function card(kase, env, open) {
        var inner = '<h3 class="ig-h">' + TITLES[kase] + '</h3>' + body(kase, env);
        return open
            ? '<section class="ig-card ig-main">' + inner + '</section>'
            : '<details class="ig-card ig-other"><summary>' + TITLES[kase] + '</summary>' + body(kase, { canInstall: false }) + '</details>';
    }

    function render(rootEl, env) {
        var kase = detect(env);
        var others = ['ios-safari', 'android', 'mac-safari', 'desktop'].filter(function (k) { return k !== kase; });
        rootEl.innerHTML = card(kase, env, true) +
            (kase === 'installed' ? '' : '<div class="ig-others"><p class="ig-more">¿Otro dispositivo?</p>' + others.map(function (k) { return card(k, env, false); }).join('') + '</div>');
        rootEl.setAttribute('data-ig-case', kase);
        return kase;
    }

    function copy(text, done) {
        if (navigator.clipboard && navigator.clipboard.writeText) navigator.clipboard.writeText(text).then(done, function () { fallback(); });
        else fallback();
        function fallback() {
            var t = document.createElement('textarea'); t.value = text; t.setAttribute('readonly', ''); t.style.position = 'fixed'; t.style.opacity = '0';
            document.body.appendChild(t); t.select(); try { document.execCommand('copy'); } catch (_) {} document.body.removeChild(t); done();
        }
    }

    /** Bloque «Compartir con los alumnos»: enlace, copiar, WhatsApp y QR. */
    function renderShare(el, url) {
        var wa = 'https://wa.me/?text=' + encodeURIComponent('Instala la app de JP Preparation: ' + url);
        el.innerHTML = '<h3 class="ig-h">Compartir con alumnos y entrenadores</h3>' +
            '<p class="ig-note" style="margin-top:0">Esta página explica la instalación según el dispositivo y no necesita iniciar sesión.</p>' +
            '<div class="ig-share-row"><input class="ig-url" readonly value="' + url.replace(/"/g, '&quot;') + '" aria-label="Enlace de instalación">' +
            '<button type="button" class="ig-btn ig-btn-sm" data-ig-copy-url>Copiar</button>' +
            '<a class="ig-btn ig-btn-sm ig-btn-wa" href="' + wa + '" target="_blank" rel="noopener">WhatsApp</a></div>' +
            '<div class="ig-qr" id="ig-qr" aria-label="Código QR del enlace"></div>';
        el.querySelector('[data-ig-copy-url]').addEventListener('click', function (e) {
            var b = e.currentTarget; copy(url, function () { b.textContent = '¡Copiado!'; setTimeout(function () { b.textContent = 'Copiar'; }, 1800); });
        });
        var s = document.createElement('script');
        s.src = 'https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js';
        s.onload = function () { if (root.QRCode) new root.QRCode(document.getElementById('ig-qr'), { text: url, width: 168, height: 168, correctLevel: root.QRCode.CorrectLevel.M }); };
        document.head.appendChild(s);
    }

    function mount(rootEl, opts) {
        opts = opts || {};
        var pageUrl = opts.url || location.href;
        function paint() {
            var st = root.matchMedia && root.matchMedia('(display-mode: standalone)').matches || (navigator.standalone === true);
            render(rootEl, { ua: navigator.userAgent, touchPoints: navigator.maxTouchPoints, standalone: st, canInstall: false });
            // canInstall lo sabe pwa.js (evento beforeinstallprompt, solo Chrome/Edge/Android)
            if (root.JPPwa) root.JPPwa.state().then(function (s) {
                if (s.canInstall) render(rootEl, { ua: navigator.userAgent, touchPoints: navigator.maxTouchPoints, standalone: st, canInstall: true });
            });
        }
        rootEl.addEventListener('click', function (e) {
            if (e.target.closest('[data-ig-install]') && root.JPPwa) root.JPPwa.install();
            var c = e.target.closest('[data-ig-copy]');
            if (c) copy(pageUrl, function () { c.textContent = '¡Enlace copiado!'; });
        });
        paint();
        root.addEventListener('jp:pwa-state', paint);
        if (opts.share) { var sh = document.getElementById(opts.share); if (sh) renderShare(sh, opts.url || pageUrl); }
    }

    root.JPInstall = { detect: detect, render: render, mount: mount };
    if (typeof module !== 'undefined' && module.exports) module.exports = root.JPInstall;
})(typeof window !== 'undefined' ? window : globalThis);
