/**
 * scroll-guard.js — red de seguridad contra páginas "atascadas" sin scroll.
 *
 * Muchos modales/menús bloquean el scroll con `document.body.style.overflow = 'hidden'` y
 * dependen de que su cierre lo restaure. En tablet es fácil que ese cierre no llegue
 * (menú lateral + navegación sin recargar, volver atrás desde la caché del navegador,
 * rotar la pantalla, un modal que se oculta por otra vía…) y la página entera se queda sin scroll.
 *
 * Este módulo vigila el bloqueo: si el `body` está bloqueado pero no hay NINGUNA capa fija
 * (modal, menú, visor…) tapando la pantalla, lo libera. No toca nada si hay un modal de verdad.
 *
 * API: window.JPScrollGuard.check() → fuerza una comprobación; .release() → libera ya.
 */
(function () {
    'use strict';
    if (window.JPScrollGuard) return;

    var body = document.body;
    var timer = null;
    var strikes = 0;

    function locked() {
        return body.style.overflow === 'hidden' || body.classList.contains('modal-open');
    }

    /** ¿Hay una capa fija y visible bajo el centro / las esquinas de la pantalla? */
    function hasBlocker() {
        var w = window.innerWidth, h = window.innerHeight;
        var pts = [[w / 2, h / 2], [w / 2, 8], [w / 2, h - 8], [8, h / 2], [w - 8, h / 2]];
        for (var i = 0; i < pts.length; i++) {
            var el = document.elementFromPoint(pts[i][0], pts[i][1]);
            while (el && el !== document.documentElement) {
                var cs = window.getComputedStyle(el);
                if (cs.position === 'fixed' && cs.visibility !== 'hidden' && cs.display !== 'none' && parseFloat(cs.opacity) > 0) {
                    var r = el.getBoundingClientRect();
                    // Solo cuentan las capas grandes (un botón flotante o un aviso pequeño no bloquean).
                    if (r.width >= w * 0.5 && r.height >= h * 0.3) return true;
                }
                el = el.parentElement;
            }
        }
        return false;
    }

    function closeSidebarState() {
        var sb = document.querySelector('.sidebar.open');
        if (sb) sb.classList.remove('open');
        var ov = document.getElementById('sidebar-overlay');
        if (ov) ov.classList.remove('active');
        var icon = document.querySelector('#mobile-menu-btn i');
        if (icon) icon.className = 'bi bi-list';
        var btn = document.getElementById('mobile-menu-btn');
        if (btn) btn.setAttribute('aria-expanded', 'false');
    }

    function release() {
        body.style.overflow = '';
        body.style.paddingRight = '';
        body.classList.remove('modal-open');
        document.documentElement.style.overflow = '';
        strikes = 0;
    }

    function check() {
        if (!locked()) { strikes = 0; return; }
        if (hasBlocker()) { strikes = 0; return; }
        // Dos lecturas seguidas: evita liberar durante la animación de apertura de un modal.
        if (++strikes >= 2) {
            closeSidebarState();
            release();
        } else {
            schedule(250);
        }
    }

    function schedule(ms) {
        clearTimeout(timer);
        timer = setTimeout(check, ms == null ? 120 : ms);
    }

    // Cualquier cambio del estilo/clase del body (alguien bloquea o desbloquea).
    if (window.MutationObserver) {
        new MutationObserver(function () { if (locked()) schedule(400); else strikes = 0; })
            .observe(body, { attributes: true, attributeFilter: ['style', 'class'] });
    }

    // Momentos en los que un bloqueo huérfano es típico en tablet.
    window.addEventListener('pageshow', function (e) {
        if (e.persisted) { closeSidebarState(); release(); } else { schedule(); }
    });
    window.addEventListener('jp:soft-loaded', function () { closeSidebarState(); schedule(); });
    window.addEventListener('orientationchange', function () { schedule(300); });
    window.addEventListener('resize', function () { if (locked()) schedule(300); });
    document.addEventListener('visibilitychange', function () { if (!document.hidden) schedule(); });
    // El propio intento de hacer scroll es la señal más fiable de que algo está atascado.
    ['touchstart', 'wheel', 'keydown'].forEach(function (ev) {
        document.addEventListener(ev, function () { if (locked()) schedule(60); }, { passive: true, capture: true });
    });

    window.JPScrollGuard = { check: check, release: release };
    schedule(300);
})();
