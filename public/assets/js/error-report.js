/* error-report.js — "Reportar problema" desde una alerta de error/permiso.
 *
 * Expone:
 *   window.reportProblem({ ref, message, endpoint, url, autoShot })
 *     → abre el modal #modalTicketRapido precargado con el contexto.
 *   showAlert(msg, type, { ref, message, endpoint })
 *     → el toast muestra un botón "Reportar" si hay ref.
 *
 * La captura de pantalla usa html2canvas (cdnjs), cargado bajo demanda.
 */
(function () {
    'use strict';

    var HTML2CANVAS_SRC = 'https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js';
    var _h2cLoading = null;

    function loadHtml2canvas() {
        if (window.html2canvas) return Promise.resolve(window.html2canvas);
        if (_h2cLoading) return _h2cLoading;
        _h2cLoading = new Promise(function (resolve, reject) {
            var s = document.createElement('script');
            s.src = HTML2CANVAS_SRC;
            s.onload = function () { resolve(window.html2canvas); };
            s.onerror = function () { reject(new Error('no se pudo cargar html2canvas')); };
            document.head.appendChild(s);
        });
        return _h2cLoading;
    }

    // ── Captura de pantalla del estado actual ────────────────────────
    function captureScreenshot() {
        return loadHtml2canvas().then(function (h2c) {
            // Ocultamos el propio modal durante la captura.
            var modalEl = document.getElementById('modalTicketRapido');
            var prevVis = modalEl ? modalEl.style.visibility : null;
            if (modalEl) modalEl.style.visibility = 'hidden';

            return h2c(document.body, {
                logging: false,
                useCORS: true,
                scale: Math.min(window.devicePixelRatio || 1, 2),
                windowWidth: document.documentElement.clientWidth,
                windowHeight: document.documentElement.clientHeight,
                x: window.scrollX,
                y: window.scrollY,
                width: window.innerWidth,
                height: window.innerHeight,
            }).then(function (canvas) {
                if (modalEl) modalEl.style.visibility = prevVis;
                return new Promise(function (res) {
                    canvas.toBlob(function (blob) {
                        res(new File([blob], 'captura.png', { type: 'image/png' }));
                    }, 'image/png', 0.85);
                });
            }).catch(function (e) {
                if (modalEl) modalEl.style.visibility = prevVis;
                throw e;
            });
        });
    }

    // ── Abrir el modal precargado ───────────────────────────────────
    function reportProblem(ctx) {
        ctx = ctx || {};
        var modalEl = document.getElementById('modalTicketRapido');
        if (!modalEl || !window.bootstrap) {
            // Sin modal (p. ej. página de login): a la creación normal.
            var qs = 'origin=error&ref=' + encodeURIComponent(ctx.ref || '') +
                     '&url=' + encodeURIComponent(ctx.url || window.location.href) +
                     '&msg=' + encodeURIComponent(ctx.message || '');
            window.location.href = (window.APP_BASE || '') + '/tickets/create?' + qs;
            return;
        }

        // Cierra cualquier otro modal abierto (p. ej. el de enviar notificación).
        document.querySelectorAll('.modal.show').forEach(function (m) {
            if (m !== modalEl) {
                var i = window.bootstrap.Modal.getInstance(m);
                if (i) i.hide();
            }
        });

        var f = modalEl.querySelector('form');
        var set = function (name, val) {
            var el = f.querySelector('[name="' + name + '"]');
            if (el) el.value = val;
        };

        var seccion = (ctx.url || window.location.pathname || '').replace(window.location.origin, '');
        set('title', 'Error en ' + (seccion || 'la plataforma'));
        set('category', 'bug');
        set('priority', 'alta');
        set('origin', 'error');
        set('error_ref', ctx.ref || '');
        set('context', JSON.stringify({
            url:       ctx.url || window.location.href,
            endpoint:  ctx.endpoint || '',
            message:   ctx.message || '',
            client_ts: new Date().toISOString(),
        }));
        set('description',
            '— Cuéntanos qué estabas haciendo cuando ocurrió —\n\n' +
            '———————————————\n' +
            (ctx.message ? 'Mensaje: ' + ctx.message + '\n' : '') +
            (ctx.ref ? 'Referencia: ' + ctx.ref + '\n' : ''));

        var shotWrap = modalEl.querySelector('#tk-shot-wrap');
        if (shotWrap) shotWrap.hidden = false;

        window.bootstrap.Modal.getOrCreateInstance(modalEl).show();

        if (ctx.autoShot !== false) {
            // Captura automática tras un pequeño respiro para que el modal no tape nada.
            triggerCapture(modalEl, true);
        }
    }

    // ── Botón / captura dentro del modal ────────────────────────────
    function triggerCapture(modalEl, auto) {
        var btn    = modalEl.querySelector('#tk-shot-btn');
        var status = modalEl.querySelector('#tk-shot-status');
        var fileIn = modalEl.querySelector('input[name="attachment"]');
        if (!fileIn) return;

        if (btn) btn.disabled = true;
        if (status) status.textContent = auto ? 'Capturando pantalla…' : 'Capturando…';

        // Cerramos visualmente el modal un instante para capturar el fondo.
        var inst = window.bootstrap && window.bootstrap.Modal.getInstance(modalEl);
        var body = modalEl.querySelector('.modal-content');
        if (body) body.style.opacity = '0';

        setTimeout(function () {
            captureScreenshot().then(function (file) {
                var dt = new DataTransfer();
                dt.items.add(file);
                fileIn.files = dt.files;
                fileIn.dispatchEvent(new Event('change', { bubbles: true }));
                if (status) status.textContent = '✓ Captura adjuntada (puedes quitarla abajo)';
            }).catch(function () {
                if (status) status.textContent = 'No se pudo capturar. Adjunta una manualmente.';
            }).finally(function () {
                if (body) body.style.opacity = '';
                if (btn) btn.disabled = false;
            });
        }, 180);
    }

    document.addEventListener('click', function (e) {
        var b = e.target.closest('#tk-shot-btn');
        if (b) {
            e.preventDefault();
            triggerCapture(document.getElementById('modalTicketRapido'), false);
        }
    });

    // ── Enganche a showAlert ───────────────────────────────────────
    var _origShowAlert = window.showAlert;
    window.showAlert = function (msg, type, opts) {
        opts = opts || {};
        if (!opts.ref || typeof Toastify === 'undefined') {
            return _origShowAlert ? _origShowAlert(msg, type) : alert(msg);
        }
        Toastify({
            text: msg + '  ',
            duration: 8000,
            close: true,
            gravity: 'top',
            position: 'right',
            style: { background: 'linear-gradient(135deg, #e74c3c, #c0392b)' },
            stopOnFocus: true,
            node: (function () {
                var wrap = document.createElement('span');
                wrap.textContent = msg + ' ';
                var a = document.createElement('a');
                a.textContent = 'Reportar';
                a.href = '#';
                a.style.cssText = 'color:#fff;text-decoration:underline;font-weight:700;margin-left:6px';
                a.onclick = function (ev) {
                    ev.preventDefault();
                    reportProblem({ ref: opts.ref, message: opts.message || msg, endpoint: opts.endpoint });
                };
                wrap.appendChild(a);
                return wrap;
            })(),
        }).showToast();
    };

    // ── Helper para respuestas fetch de la app ─────────────────────
    // Uso opcional: const data = await res.json(); if (handleApiError(data)) return;
    window.handleApiError = function (data, endpoint) {
        if (data && data.error_ref) {
            window.showAlert(data.error || 'Ha ocurrido un error.', 'error', {
                ref: data.error_ref, message: data.error, endpoint: endpoint,
            });
            return true;
        }
        return false;
    };

    window.reportProblem = reportProblem;
})();
