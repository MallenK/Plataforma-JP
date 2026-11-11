/**
 * Botones «blandos» que guían al usuario (JPGuard).
 *
 * Mientras falte algo, el botón de envío NO se deshabilita del todo: se ve apagado (aria-disabled), una línea
 * fija debajo dice «Falta: título, fecha…» y, al pulsarlo, no envía nada y LLEVA al primer campo que falta
 * (lo resalta y despliega la sección si está plegada). Así funciona también en móvil (sin tooltips), con
 * teclado y con lector de pantalla. Nunca se bloquea Cancelar/Cerrar.
 *
 * El servidor sigue siendo la autoridad: estas reglas solo anticipan lo que ya exige (y se prueban para
 * que no se desincronicen).
 *
 * Uso declarativo (formularios sencillos): <form data-guard> → se vigilan los campos [required].
 *   · data-guard-label="Nombre"      etiqueta que sale en «Falta: …» (si no, <label for>, aria-label, placeholder)
 *   · data-guard-money[="zero"]      importe válido y > 0 (con «zero» también vale 0)
 *   · data-guard-max="2500"          máximo en céntimos (p. ej. lo pendiente de cobrar)
 *   · data-guard-min="1000"          mínimo en céntimos (p. ej. lo ya cobrado)
 *   · data-guard-needs="#precio"     (en un checkbox) si está marcado, ese campo debe ser un importe > 0
 *   · data-guard-discount            descuento válido: «10», «10,50» o «10%» (vacío vale)
 *   · data-guard-ignore (botón)      no vigilar este botón
 *   · data-guard-changes (form)      además exige haber cambiado algo desde que se abrió (editar)
 *   · data-guard-hint="after" (form) la línea «Falta: …» va debajo del formulario (para formularios en una fila)
 *   · data-guard-hint="none" (form)  sin línea de texto (celdas de tabla): solo tooltip y resaltar el campo al pulsar
 *
 * Uso programático: JPGuard.attach({ root, buttons, rules:[{label, ok:()=>bool, focus:()=>el}],
 *                                    needsChange:()=>bool, blocking:false, info:()=>texto,
 *                                    hintAfter:el, hintClass:'jpg-hint-bar' })   (la barra va fuera de la zona con scroll)
 */
(function () {
    'use strict';
    if (window.JPGuard) return;

    var CSS = '.jpg-soft-disabled{opacity:.55;cursor:not-allowed;filter:saturate(.7)}' +
        '.jpg-hint{font-size:12px;line-height:1.4;margin-top:6px;color:var(--text-muted,#64748b);width:100%}' +
        '.jpg-hint.jpg-warn{color:#b45309}.jpg-hint i{margin-right:4px}' +
        '.jpg-hint.jpg-hint-bar{margin:0;padding:8px 20px;border-top:1px solid var(--border,#e2e8f0);background:var(--bg-card,#fff);flex:0 0 auto}' +
        '.jpg-flash{outline:2px solid #f59e0b!important;outline-offset:2px;border-radius:6px;animation:jpgFlash 1.4s ease-out 1}' +
        '@keyframes jpgFlash{0%{box-shadow:0 0 0 0 rgba(245,158,11,.55)}100%{box-shadow:0 0 0 10px rgba(245,158,11,0)}}' +
        '@media (prefers-reduced-motion:reduce){.jpg-flash{animation:none}}';

    function injectCss() {
        if (document.getElementById('jpg-css')) return;
        var s = document.createElement('style');
        s.id = 'jpg-css';
        s.textContent = CSS;
        document.head.appendChild(s);
    }
    function esc(s) { return String(s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }

    // ── Reglas puras (espejo de las del servidor) ──────────────────────────
    /** Igual que RevisionService::parseEuroToCents: «25», «25,50», «1.225,50», «25 €». null si no es válido. */
    function parseEuroToCents(raw) {
        var s = String(raw == null ? '' : raw).replace(/[€\s ]/g, '');
        if (s === '') return null;
        if (/^\d{1,3}(\.\d{3})+(,\d{1,2})?$/.test(s)) s = s.replace(/\./g, '').replace(',', '.');
        else s = s.replace(',', '.');
        if (!/^\d+(\.\d{1,2})?$/.test(s)) return null;
        return Math.round(parseFloat(s) * 100);
    }
    /** Descuento: vacío vale; «10%» (0–100) o un importe. */
    function discountValid(raw) {
        var s = String(raw == null ? '' : raw).trim();
        if (s === '') return true;
        if (/%$/.test(s)) { var p = parseFloat(s.replace(/[%\s]/g, '').replace(',', '.')); return isFinite(p) && p >= 0 && p <= 100; }
        return parseEuroToCents(s) !== null;
    }
    function moneyValid(raw, allowZero, maxCents, minCents) {
        var c = parseEuroToCents(raw);
        if (c === null) return false;
        if (c <= 0 && !allowZero) return false;
        if (maxCents != null && c > maxCents) return false;
        if (minCents != null && c < minCents) return false;
        return true;
    }

    function labelOf(el) {
        var l = el.getAttribute('data-guard-label');
        if (!l && el.id) {
            var lab = document.querySelector('label[for="' + el.id + '"]');
            if (lab) l = lab.textContent;
        }
        if (!l) l = el.getAttribute('aria-label') || el.getAttribute('placeholder') || el.name || 'campo';
        return String(l).replace(/\*/g, '').replace(/\([^)]*\)/g, '').replace(/\s+/g, ' ').trim().toLowerCase();
    }

    function revealField(el) {
        if (!el) return;
        var d = el.closest && el.closest('details');
        while (d) { d.open = true; d = d.parentElement && d.parentElement.closest('details'); }
        try { el.scrollIntoView({ block: 'center', behavior: 'smooth' }); } catch (e) { /* jsdom */ }
        if (el.focus) { try { el.focus({ preventScroll: true }); } catch (e) { el.focus(); } }
        el.classList.add('jpg-flash');
        setTimeout(function () { el.classList.remove('jpg-flash'); }, 1500);
    }

    /** Reglas automáticas de un <form>: campos [required] + importes + descuentos. */
    function autoRules(form) {
        var rules = [];
        [].forEach.call(form.querySelectorAll('input, select, textarea'), function (el) {
            if (el.type === 'hidden' || el.type === 'radio' || el.type === 'submit' || el.type === 'button' || el.type === 'file' && !el.required) return;
            var money = el.hasAttribute('data-guard-money');
            var disc  = el.hasAttribute('data-guard-discount');
            var needs = el.type === 'checkbox' ? el.getAttribute('data-guard-needs') : null;
            if (needs) {
                rules.push({
                    label: function () { var t = form.querySelector(needs); return (t ? labelOf(t) : 'el importe') + ' mayor que 0'; },
                    focus: function () { return form.querySelector(needs); },
                    ok: function () { var t = form.querySelector(needs); return !el.checked || !t || moneyValid(t.value, false); }
                });
                return;
            }
            if (!el.required && !money && !disc) return;
            rules.push({
                label: labelOf(el),
                focus: function () { return el; },
                ok: function () {
                    if (el.disabled || el.closest('[hidden], .d-none')) return true;
                    var v = el.type === 'checkbox' ? el.checked : String(el.value).trim();
                    if (el.required && (v === '' || v === false)) return false;
                    if (el.type === 'checkbox') return true;
                    if (money) {
                        var max = el.getAttribute('data-guard-max'), min = el.getAttribute('data-guard-min');
                        if (v === '' && !el.required) return true;
                        return moneyValid(v, el.getAttribute('data-guard-money') === 'zero', max !== null ? parseInt(max, 10) : null, min !== null ? parseInt(min, 10) : null);
                    }
                    if (disc) return discountValid(v);
                    return el.validity ? el.validity.valid : true;
                }
            });
        });
        return rules;
    }

    function snapshot(form) {
        var parts = [];
        [].forEach.call(form.elements, function (e) {
            if (!e.name || e.disabled || /csrf/i.test(e.name) || e.type === 'submit' || e.type === 'button') return;
            if ((e.type === 'checkbox' || e.type === 'radio') && !e.checked) return;
            parts.push(e.name + '=' + e.value);
        });
        return parts.join('&');
    }

    function attach(opts) {
        injectCss();
        var root = opts.root || document;
        var buttons = (typeof opts.buttons === 'function' ? opts.buttons() : opts.buttons) || [];
        buttons = [].slice.call(buttons).filter(Boolean);
        if (!buttons.length) return null;
        var rules = opts.rules || [];
        var blocking = opts.blocking !== false;
        var hint = opts.hint;
        if (!hint) {
            hint = document.createElement('div');
            hint.className = 'jpg-hint' + (opts.hintClass ? ' ' + opts.hintClass : '');
            hint.setAttribute('role', 'status');
            hint.setAttribute('aria-live', 'polite');
            hint.hidden = true;
            var last = buttons[buttons.length - 1];
            var host = opts.hintAfter || last;
            var par = host.parentElement;
            // si el botón está en una fila de botones, la línea va debajo de la fila entera
            if (!opts.hintAfter && par && par !== root && [].every.call(par.children, function (c) { return /^(BUTTON|A|INPUT|SPAN)$/.test(c.tagName); })) host = par;
            host.insertAdjacentElement('afterend', hint);
        }
        var blocked = false, raf = 0, busy = false;

        function missing() {
            var m = [];
            rules.forEach(function (r) { var ok = true; try { ok = r.ok(); } catch (e) { ok = true; } if (!ok) m.push(r); });
            return m;
        }
        function noChange() { return !!(opts.needsChange && !opts.needsChange()); }

        function refresh() {
            if (busy) return;
            busy = true;
            var miss = missing();
            var nochg = !miss.length && noChange();
            blocked = blocking && (miss.length > 0 || nochg);
            var text = '';
            if (miss.length) {
                var seen = {};
                var names = miss.map(function (r) { return typeof r.label === 'function' ? r.label() : r.label; }).filter(function (n) { if (seen[n]) return false; seen[n] = 1; return true; });
                text = 'Falta: ' + names.join(', ') + '.';
            }
            else if (nochg) text = 'Aún no has cambiado nada.';
            else if (opts.info) text = opts.info() || '';
            buttons.forEach(function (b) {
                b.classList.toggle('jpg-soft-disabled', blocked);
                if (blocked) {
                    b.setAttribute('aria-disabled', 'true');
                    if (b.getAttribute('data-jpg-title') === null) b.setAttribute('data-jpg-title', b.getAttribute('title') || '');
                    b.setAttribute('title', text);
                } else {
                    b.removeAttribute('aria-disabled');
                    if (b.getAttribute('data-jpg-title') !== null) {
                        var t = b.getAttribute('data-jpg-title');
                        if (t) b.setAttribute('title', t); else b.removeAttribute('title');
                        b.removeAttribute('data-jpg-title');
                    }
                }
            });
            hint.hidden = !text;
            hint.classList.toggle('jpg-warn', blocked);
            hint.innerHTML = text ? '<i class="bi bi-' + (blocked ? 'info-circle-fill' : 'info-circle') + '"></i>' + esc(text) : '';
            busy = false;
            return miss;
        }
        function schedule() {
            if (raf) return;
            var f = window.requestAnimationFrame || function (fn) { return setTimeout(fn, 16); };
            raf = f(function () { raf = 0; refresh(); });
        }

        function reveal() {
            var miss = missing();
            var target = null;
            if (miss.length) {
                var f = miss[0].focus;
                target = typeof f === 'function' ? f() : f;
            }
            if (target) revealField(target);
            hint.classList.add('jpg-flash');
            setTimeout(function () { hint.classList.remove('jpg-flash'); }, 1500);
        }

        // Pulsar un botón apagado: no envía y guía.
        function onClick(e) {
            if (!blocked) return;
            var b = null;
            for (var i = 0; i < buttons.length; i++) { if (buttons[i] === e.target || buttons[i].contains(e.target)) { b = buttons[i]; break; } }
            if (!b) return;
            e.preventDefault(); e.stopPropagation(); e.stopImmediatePropagation();
            reveal();
        }
        // Enter dentro del formulario = enviar: también se frena
        function onSubmit(e) {
            if (!blocked) return;
            var f = buttons[0].form;
            if (!f || e.target !== f) return;
            e.preventDefault(); e.stopPropagation(); e.stopImmediatePropagation();
            reveal();
        }
        document.addEventListener('click', onClick, true);
        document.addEventListener('submit', onSubmit, true);
        ['input', 'change', 'keyup'].forEach(function (ev) { root.addEventListener(ev, schedule, true); });
        root.addEventListener('click', function () { setTimeout(schedule, 0); }, false);
        var mo = null;
        if (window.MutationObserver && root.nodeType === 1) {
            mo = new MutationObserver(function (muts) {
                for (var i = 0; i < muts.length; i++) {
                    var t = muts[i].target;
                    if (hint.contains(t) || buttons.indexOf(t) !== -1) continue;
                    schedule(); return;
                }
            });
            mo.observe(root, { childList: true, subtree: true, attributes: true, attributeFilter: ['class', 'disabled', 'hidden', 'value', 'checked'] });
        }

        var api = {
            refresh: refresh, missing: missing, isBlocked: function () { return blocked; }, hint: hint,
            destroy: function () {
                document.removeEventListener('click', onClick, true);
                document.removeEventListener('submit', onSubmit, true);
                if (mo) mo.disconnect();
            }
        };
        refresh();
        return api;
    }

    /** Vigila un <form data-guard> con reglas automáticas (+ extras opcionales). */
    function auto(form, extra) {
        if (!form || form._jpGuard) return form && form._jpGuard;
        var buttons = [].filter.call(form.querySelectorAll('button[type=submit], button:not([type]), input[type=submit]'), function (b) {
            return !b.hasAttribute('data-guard-ignore') && !b.hasAttribute('formnovalidate');
        });
        var opts = { root: form, buttons: buttons, rules: autoRules(form).concat((extra && extra.rules) || []) };
        if (form.hasAttribute('data-guard-changes')) {
            var base = snapshot(form);
            opts.needsChange = function () { return snapshot(form) !== base; };
        }
        if (form.getAttribute('data-guard-hint') === 'after') opts.hintAfter = form;
        if (form.getAttribute('data-guard-hint') === 'none') opts.hint = document.createElement('div');   // sin línea (celdas de tabla): solo tooltip + resaltar el campo   // formularios en una sola fila
        if (extra) Object.keys(extra).forEach(function (k) { if (k !== 'rules') opts[k] = extra[k]; });
        form._jpGuard = attach(opts);
        return form._jpGuard;
    }

    function scan(root) {
        [].forEach.call((root || document).querySelectorAll('form[data-guard]'), function (f) { auto(f); });
    }

    window.JPGuard = { attach: attach, auto: auto, scan: scan, parseEuroToCents: parseEuroToCents, discountValid: discountValid, moneyValid: moneyValid, snapshot: snapshot };

    function start() { scan(document); }
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start); else start();
    document.addEventListener('jp:soft-loaded', start);
})();
