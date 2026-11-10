/**
 * Fechas en formato DD/MM/AAAA en TODA la app.
 *
 * El <input type="date"> nativo muestra el formato del navegador (MM/DD/AAAA en inglés). Aquí cada
 * input de fecha se sustituye por un campo de texto con máscara DD/MM/AAAA y un botón de calendario.
 * El input ORIGINAL se conserva (type="hidden", mismo id, name, min, max…) y sigue guardando la
 * fecha en ISO (AAAA-MM-DD): el servidor y el resto del JS no notan nada (leer/escribir .value sigue
 * funcionando; escribir .value actualiza lo que se ve).
 *
 *  · Escribir: solo dígitos, las barras se ponen solas (2 → 02/ ... 02/05/2026).
 *  · Fecha incompleta o inexistente (31/02/2026): el campo avisa y el formulario no se envía.
 *  · Respeta min / max / required / disabled del original (también si cambian después).
 *  · Para excluir un campo: data-native-date en el input.
 */
(function () {
    'use strict';
    if (window.JPDateEs) return;

    var valueDesc = Object.getOwnPropertyDescriptor(HTMLInputElement.prototype, 'value');

    function iso2es(v) {
        var m = /^(\d{4})-(\d{2})-(\d{2})$/.exec(v || '');
        return m ? m[3] + '/' + m[2] + '/' + m[1] : '';
    }
    /** 'dd/mm/aaaa' → ISO si existe en el calendario; si no, null. */
    function es2iso(s) {
        var m = /^(\d{2})\/(\d{2})\/(\d{4})$/.exec(s || '');
        if (!m) return null;
        var d = +m[1], mo = +m[2], y = +m[3];
        var dt = new Date(Date.UTC(y, mo - 1, d));
        if (y < 1000 || dt.getUTCFullYear() !== y || dt.getUTCMonth() !== mo - 1 || dt.getUTCDate() !== d) return null;
        return m[3] + '-' + m[2] + '-' + m[1];
    }
    /** Solo dígitos → 'dd/mm/aaaa' parcial. */
    function mask(raw) {
        var d = String(raw).replace(/\D/g, '').slice(0, 8);
        if (d.length > 4) return d.slice(0, 2) + '/' + d.slice(2, 4) + '/' + d.slice(4);
        if (d.length > 2) return d.slice(0, 2) + '/' + d.slice(2);
        return d;
    }

    function injectCss() {
        if (document.getElementById('date-es-css')) return;
        var s = document.createElement('style');
        s.id = 'date-es-css';
        s.textContent =
            '.date-es{position:relative;display:block}' +
            '.date-es>input[type=text]{width:100%;padding-right:38px}' +
            '.date-es-btn{position:absolute;right:4px;top:50%;transform:translateY(-50%);background:none;border:0;' +
            'color:var(--text-muted,#64748b);cursor:pointer;padding:6px 8px;line-height:1;font-size:15px}' +
            '.date-es-btn:hover{color:var(--accent,#2563eb)}.date-es-btn:disabled{opacity:.4;cursor:not-allowed}' +
            '.date-es-pick{position:absolute;right:0;bottom:0;width:1px;height:1px;opacity:0;pointer-events:none;border:0;padding:0}';
        document.head.appendChild(s);
    }

    function fire(el, type) {
        el.dispatchEvent(new Event(type, { bubbles: true }));
    }

    function enhance(el) {
        if (!el || el.type !== 'date' || el.hasAttribute('data-native-date') || el._dateEs) return;
        injectCss();

        var txt = document.createElement('input');
        txt.type = 'text';
        txt.className = el.className;
        txt.placeholder = 'DD/MM/AAAA';
        txt.maxLength = 10;
        txt.setAttribute('inputmode', 'numeric');
        txt.setAttribute('autocomplete', 'off');
        txt.required = el.required;
        txt.disabled = el.disabled;
        txt.readOnly = el.readOnly;
        ['aria-label', 'title', 'tabindex'].forEach(function (a) { if (el.hasAttribute(a)) txt.setAttribute(a, el.getAttribute(a)); });
        if (!txt.hasAttribute('aria-label') && el.id) {
            var lab = document.querySelector('label[for="' + el.id + '"]');
            if (lab) txt.setAttribute('aria-label', lab.textContent.replace('*', '').trim());
        }

        var wrap = document.createElement('span');
        wrap.className = 'date-es';
        if (el.style.cssText) wrap.style.cssText = el.style.cssText;   // anchos, flex… del original

        var btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'date-es-btn';
        btn.setAttribute('aria-label', 'Abrir calendario');
        btn.innerHTML = '<i class="bi bi-calendar3"></i>';
        btn.disabled = el.disabled;

        var pick = document.createElement('input');   // selector nativo, solo para el calendario
        pick.type = 'date';
        pick.className = 'date-es-pick';
        pick.tabIndex = -1;
        pick.setAttribute('aria-hidden', 'true');
        pick.setAttribute('data-native-date', '');
        if (el.min) pick.min = el.min;
        if (el.max) pick.max = el.max;

        wrap.appendChild(txt);
        wrap.appendChild(btn);
        wrap.appendChild(pick);
        el.parentNode.insertBefore(wrap, el);

        var iso = valueDesc.get.call(el);
        el.type = 'hidden';
        valueDesc.set.call(el, iso);
        el.style.display = 'none';
        txt.value = iso2es(iso);

        function setIso(v, silent) {
            if (valueDesc.get.call(el) === v) return;
            valueDesc.set.call(el, v);
            if (!silent) { fire(el, 'input'); fire(el, 'change'); }
        }
        function validate() {
            var v = txt.value, msg = '';
            if (v !== '') {
                var i = es2iso(v);
                if (!i) msg = v.length < 10 ? 'Escribe la fecha completa: DD/MM/AAAA.' : 'Esa fecha no existe. Usa DD/MM/AAAA.';
                else if (el.max && i > el.max) msg = 'La fecha no puede ser posterior al ' + iso2es(el.max) + '.';
                else if (el.min && i < el.min) msg = 'La fecha no puede ser anterior al ' + iso2es(el.min) + '.';
            }
            txt.setCustomValidity(msg);
            return msg === '' ? (v === '' ? '' : es2iso(v)) : null;
        }

        txt.addEventListener('input', function () {
            var m = mask(txt.value);
            if (m !== txt.value) txt.value = m;
            var i = validate();
            setIso(i === null ? '' : i);   // incompleta o inválida → el valor real queda vacío (el campo avisa)
        });
        txt.addEventListener('blur', function () {
            var i = validate();
            if (i === null && txt.value !== '') txt.reportValidity && txt.reportValidity();
        });

        btn.addEventListener('click', function () {
            if (txt.disabled) return;
            pick.min = el.min || '';
            pick.max = el.max || '';
            pick.value = valueDesc.get.call(el);
            if (pick.showPicker) { try { pick.showPicker(); return; } catch (e) { /* cae al foco */ } }
            txt.focus();
        });
        pick.addEventListener('change', function () {
            txt.value = iso2es(pick.value);
            validate();
            setIso(pick.value);
        });

        // Escribir .value desde JS (el.value = '2026-05-02') actualiza el campo visible.
        Object.defineProperty(el, 'value', {
            configurable: true,
            get: function () { return valueDesc.get.call(el); },
            set: function (v) {
                valueDesc.set.call(el, v);
                txt.value = iso2es(v);
                validate();
            }
        });
        el.focus = function () { txt.focus(); };
        el._dateEs = txt;

        // Atributos que cambian después (disabled, required, min, max)
        new MutationObserver(function () {
            txt.disabled = el.disabled; btn.disabled = el.disabled;
            txt.required = el.required;
            pick.min = el.min || ''; pick.max = el.max || '';
            validate();
        }).observe(el, { attributes: true, attributeFilter: ['disabled', 'required', 'min', 'max'] });

        // Restablecer formulario
        if (el.form) {
            el.form.addEventListener('reset', function () {
                setTimeout(function () {
                    valueDesc.set.call(el, el.getAttribute('value') || '');
                    txt.value = iso2es(valueDesc.get.call(el));
                    validate();
                }, 0);
            });
        }
    }

    function scan(root) {
        if (!root || !root.querySelectorAll) return;
        if (root.matches && root.matches('input[type=date]')) enhance(root);
        [].forEach.call(root.querySelectorAll('input[type=date]'), enhance);
    }

    // Clic en <label for="id"> → enfocar el campo visible
    document.addEventListener('click', function (e) {
        var lab = e.target.closest && e.target.closest('label[for]');
        if (!lab) return;
        var t = document.getElementById(lab.getAttribute('for'));
        if (t && t._dateEs) t._dateEs.focus();
    });

    function start() {
        scan(document);
        new MutationObserver(function (muts) {
            muts.forEach(function (m) { [].forEach.call(m.addedNodes, function (n) { if (n.nodeType === 1) scan(n); }); });
        }).observe(document.documentElement, { childList: true, subtree: true });
    }

    window.JPDateEs = { iso2es: iso2es, es2iso: es2iso, mask: mask, scan: scan };
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', start);
    else start();
})();
