/**
 * Lista de personas externas de una clase (clase de prueba / suelta, sin cuenta de alumno).
 * Se rellena el borrador de una persona, se pulsa «Guardar persona» y pasa a la lista; se pueden
 * guardar varias. Lo que quede escrito en el borrador al enviar también se incluye.
 *
 * Marcado esperado dentro de un contenedor [data-ex-root]:
 *   · campos del borrador con data-ex="first_name|last_name|birth_date|phone|email|guardian_name|
 *     guardian_phone|image_rights|price|method_id|paid_now"  (SIN atributo name)
 *   · [data-ex-save]   botón «Guardar persona»
 *   · [data-ex-list]   donde se pintan las personas guardadas
 *   · [data-ex-error]  aviso de error (opcional)
 *   · [data-ex-hidden] (formulario completo) donde se generan los inputs externos[i][campo]
 *   · data-initial     JSON con personas ya guardadas (al volver de un error de validación)
 *
 * Formulario completo: se engancha solo al submit del <form> que lo contiene.
 * Pop-up: clase-modal.js usa ExternosList.collect(root) / appendTo(root, formData).
 */
(function () {
    'use strict';

    var CSS = '.ex-chips{display:flex;flex-direction:column;gap:6px;margin:0 0 12px}' +
        '.ex-chip{display:flex;align-items:center;justify-content:space-between;gap:10px;padding:8px 12px;' +
        'background:var(--bg-app,#f1f5f9);border:1px solid var(--border,#e2e8f0);border-radius:8px;font-size:13px}' +
        '.ex-chip b{font-weight:600}.ex-chip small{color:var(--text-muted,#64748b);margin-left:6px}' +
        '.ex-chip>span:last-child{display:flex;gap:4px}.ex-chip button{background:none;border:0;color:var(--danger,#dc2626);cursor:pointer;padding:2px 4px;line-height:1}' +
        '.ex-draft-title{font-size:12px;font-weight:700;color:var(--text-muted,#64748b);margin:0 0 8px;text-transform:uppercase;letter-spacing:.03em}' +
        '.ex-err{color:var(--danger,#dc2626);font-size:12px;margin:6px 0 0}';

    function injectCss() {
        if (document.getElementById('ex-list-css')) return;
        var s = document.createElement('style');
        s.id = 'ex-list-css';
        s.textContent = CSS;
        document.head.appendChild(s);
    }

    function esc(s) {
        return String(s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function attach(root) {
        if (!root) return null;
        if (root._exList) return root._exList;
        injectCss();

        var prefix  = root.getAttribute('data-ex-prefix') || 'externos';
        var fields  = [].slice.call(root.querySelectorAll('[data-ex]'));
        var listEl  = root.querySelector('[data-ex-list]');
        var errEl   = root.querySelector('[data-ex-error]');
        var hidden  = root.querySelector('[data-ex-hidden]');
        var saveBtn = root.querySelector('[data-ex-save]');
        var people  = [];
        var editing = false;

        try {
            var init = JSON.parse(root.getAttribute('data-initial') || '[]');
            if (Array.isArray(init)) {
                people = init.filter(function (p) { return p && typeof p === 'object' && (p.first_name || '').trim() !== ''; });
            }
        } catch (e) { /* sin datos previos */ }

        function readDraft() {
            var o = {};
            fields.forEach(function (el) {
                var k = el.getAttribute('data-ex');
                if (el.type === 'checkbox') { if (el.checked) o[k] = el.value || '1'; }
                else o[k] = el.value;
            });
            return o;
        }
        function hasName(o) { return (o.first_name || '').trim() !== ''; }
        function hasAny(o) {
            return Object.keys(o).some(function (k) { return k !== 'method_id' && String(o[k]).trim() !== ''; });
        }
        function clearDraft() {
            fields.forEach(function (el) {
                if (el.type === 'checkbox') el.checked = false;
                else if (el.tagName !== 'SELECT') el.value = '';
            });
        }
        function showErr(msg) {
            if (!errEl) return;
            errEl.textContent = msg || '';
            errEl.className = msg ? 'ex-err' : '';
            errEl.hidden = !msg;
        }

        function label(p) {
            var name = ((p.first_name || '') + ' ' + (p.last_name || '')).trim();
            var bits = [];
            if (p.price !== undefined && String(p.price).trim() !== '') {
                bits.push(String(p.price).trim() + ' €' + (p.paid_now ? ' · cobrado' : ''));
            }
            return '<span><b>' + esc(name) + '</b>' + (bits.length ? '<small>' + esc(bits.join(' · ')) + '</small>' : '') + '</span>';
        }

        function render() {
            if (!listEl) return;
            listEl.className = people.length ? 'ex-chips' : '';
            listEl.innerHTML = people.map(function (p, i) {
                return '<div class="ex-chip">' + label(p) +
                    '<span><button type="button" data-ex-edit="' + i + '" title="Editar" aria-label="Editar a ' + esc(p.first_name || '') + '" style="color:var(--accent,#2563eb)"><i class="bi bi-pencil-fill"></i></button>' +
                    '<button type="button" data-ex-del="' + i + '" title="Quitar" aria-label="Quitar a ' + esc(p.first_name || '') + '"><i class="bi bi-x-lg"></i></button></span></div>';
            }).join('');
            if (saveBtn) {
                saveBtn.innerHTML = editing
                    ? '<i class="bi bi-check-lg me-1"></i>Guardar cambios'
                    : '<i class="bi bi-person-plus-fill me-1"></i>' + (people.length ? 'Guardar y añadir otra persona' : 'Guardar persona');
            }
        }

        function save() {
            var d = readDraft();
            if (!hasName(d)) {
                showErr(hasAny(d) ? 'Indica el nombre de la persona.' : 'Escribe al menos el nombre de la persona.');
                return false;
            }
            showErr('');
            people.push(d);
            editing = false;
            clearDraft();
            render();
            var first = fields[0];
            if (first && first.focus) first.focus();
            return true;
        }

        /** Personas guardadas + el borrador si tiene nombre. error si el borrador tiene datos pero no nombre. */
        function collect() {
            var d = readDraft(), out = people.slice(), error = '';
            if (hasName(d)) out.push(d);
            else if (hasAny(d)) error = 'Indica el nombre de la persona.';
            return { people: out, error: error };
        }

        function syncHidden() {
            if (!hidden) return true;
            hidden.innerHTML = '';
            if (root.classList.contains('d-none')) return true;   // modo «alumno registrado»: no se envía nada
            var c = collect();
            if (c.error) { showErr(c.error); return false; }
            c.people.forEach(function (p, i) {
                Object.keys(p).forEach(function (k) {
                    var inp = document.createElement('input');
                    inp.type = 'hidden';
                    inp.name = prefix + '[' + i + '][' + k + ']';
                    inp.value = p[k];
                    hidden.appendChild(inp);
                });
            });
            return true;
        }

        function reset() { people = []; editing = false; clearDraft(); showErr(''); render(); }

        /** Carga una persona guardada en el borrador para corregirla (si el borrador tenía a otra, se guarda antes). */
        function edit(i) {
            var p = people[i];
            if (!p) return;
            var cur = readDraft();
            if (hasName(cur)) people.push(cur);
            people.splice(people.indexOf(p), 1);
            fields.forEach(function (el) {
                var k = el.getAttribute('data-ex');
                if (el.type === 'checkbox') el.checked = !!p[k];
                else if (p[k] !== undefined) el.value = p[k];
                else if (el.tagName !== 'SELECT') el.value = '';
            });
            editing = true;
            showErr('');
            render();
            if (fields[0] && fields[0].focus) fields[0].focus();
        }

        if (saveBtn) saveBtn.addEventListener('click', save);
        if (listEl) {
            listEl.addEventListener('click', function (e) {
                var ed = e.target.closest('[data-ex-edit]');
                if (ed) { edit(parseInt(ed.getAttribute('data-ex-edit'), 10)); return; }
                var b = e.target.closest('[data-ex-del]');
                if (!b) return;
                people.splice(parseInt(b.getAttribute('data-ex-del'), 10), 1);
                render();
            });
        }
        // Enter en un campo del borrador = guardar persona (no enviar todo el formulario).
        fields.forEach(function (el) {
            if (el.tagName === 'INPUT' && el.type !== 'checkbox') {
                el.addEventListener('keydown', function (e) {
                    if (e.key === 'Enter') { e.preventDefault(); save(); }
                });
            }
        });
        var form = root.closest('form');
        if (form && hidden) {
            form.addEventListener('submit', function (e) {
                if (!syncHidden()) e.preventDefault();
            }, true);
        }

        render();
        var api = { save: save, collect: collect, reset: reset, syncHidden: syncHidden, edit: edit, count: function () { return people.length; } };
        root._exList = api;
        return api;
    }

    /** Añade a un FormData las personas como externos[i][campo] (pop-up de clase rápida). */
    function appendTo(root, fd, prefix) {
        var api = attach(root);
        var c = api.collect();
        if (c.error) return c.error;
        c.people.forEach(function (p, i) {
            Object.keys(p).forEach(function (k) { fd.append((prefix || 'externos') + '[' + i + '][' + k + ']', p[k]); });
        });
        return '';
    }

    window.ExternosList = { attach: attach, appendTo: appendTo };

    document.addEventListener('DOMContentLoaded', function () {
        [].forEach.call(document.querySelectorAll('[data-ex-root]'), attach);
    });
})();
