/**
 * PersonPicker — buscador de personas que sustituye a un <select>.
 *
 *   const pk = PersonPicker.attach({
 *     root:        elemento contenedor (.pp) con un <input> dentro,
 *     getOptions:  () => [{id, name}],          // se llama en cada búsqueda (admite listas cambiantes)
 *     isTaken:     (id) => bool,                // opcional: oculta a quien ya está elegido
 *     onPick:      (option) => void,
 *     emptyText:   'Sin resultados'
 *   });
 *
 * Teclado: ↑ ↓ mueven, Intro elige, Esc cierra. Ignora acentos y mayúsculas. Máx. 8 resultados.
 * Los nombres se insertan con textContent (nunca como HTML).
 */
(function (global) {
    'use strict';

    // Estilos propios (una sola vez): así el buscador funciona igual en el formulario y en el pop-up.
    function injectStyles() {
        if (document.getElementById('pp-styles')) return;
        var st = document.createElement('style');
        st.id = 'pp-styles';
        st.textContent =
            '.pp{position:relative}' +
            '.pp-list{position:absolute;z-index:1060;left:0;right:0;top:100%;margin:2px 0 0;padding:4px;list-style:none;max-height:260px;overflow-y:auto;' +
            'background:var(--bg-card,#fff);border:1px solid var(--border);border-radius:var(--radius-sm,8px);box-shadow:0 8px 24px rgba(15,23,42,.14)}' +
            '.pp-list[hidden]{display:none}' +
            '.pp-item,.pp-empty{padding:9px 10px;border-radius:6px;font-size:14px}' +
            '.pp-item{cursor:pointer;color:var(--text-h)}' +
            '.pp-item:hover,.pp-item.is-active{background:var(--accent-light);color:var(--accent)}' +
            '.pp-empty{color:var(--text-muted);font-size:13px}';
        document.head.appendChild(st);
    }

    function norm(s) {
        return String(s || '').toLowerCase().normalize('NFD').replace(/[̀-ͯ]/g, '').trim();
    }

    function attach(cfg) {
        injectStyles();
        var root  = cfg.root;
        var input = root.querySelector('input');
        var list  = root.querySelector('.pp-list');
        var active = -1;
        var shown  = [];
        var uid = 'pp' + Math.random().toString(36).slice(2, 8);

        list.id = list.id || uid;
        input.setAttribute('role', 'combobox');
        input.setAttribute('aria-autocomplete', 'list');
        input.setAttribute('aria-expanded', 'false');
        input.setAttribute('aria-controls', list.id);
        list.setAttribute('role', 'listbox');
        input.setAttribute('autocomplete', 'off');

        function close() {
            list.hidden = true;
            input.setAttribute('aria-expanded', 'false');
            active = -1;
        }

        function setActive(i) {
            var items = list.children;
            if (active >= 0 && items[active]) items[active].classList.remove('is-active');
            active = i;
            if (active >= 0 && items[active]) {
                items[active].classList.add('is-active');
                items[active].scrollIntoView({ block: 'nearest' });
                input.setAttribute('aria-activedescendant', items[active].id);
            }
        }

        function pick(opt) {
            input.value = '';
            close();
            cfg.onPick(opt);
        }

        function render() {
            var q = norm(input.value);
            var all = (cfg.getOptions() || []).filter(function (o) {
                return !(cfg.isTaken && cfg.isTaken(o.id));
            });
            shown = all.filter(function (o) { return q === '' || norm(o.name).indexOf(q) !== -1; }).slice(0, 8);

            list.innerHTML = '';
            if (!shown.length) {
                var empty = document.createElement('li');
                empty.className = 'pp-empty';
                empty.textContent = all.length ? (cfg.emptyText || 'Sin resultados') : 'No queda nadie por añadir';
                list.appendChild(empty);
            } else {
                shown.forEach(function (o, i) {
                    var li = document.createElement('li');
                    li.className = 'pp-item';
                    li.id = list.id + '-o' + i;
                    li.setAttribute('role', 'option');
                    li.textContent = o.name;
                    // mousedown (no click) para que se elija antes de que el input pierda el foco
                    li.addEventListener('mousedown', function (e) { e.preventDefault(); pick(o); });
                    list.appendChild(li);
                });
            }
            list.hidden = false;
            input.setAttribute('aria-expanded', 'true');
            active = -1;
            if (shown.length && q !== '') setActive(0);
        }

        input.addEventListener('focus', render);
        input.addEventListener('input', render);
        input.addEventListener('blur', function () { setTimeout(close, 120); });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                if (list.hidden) render();
                if (shown.length) setActive((active + 1) % shown.length);
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                if (shown.length) setActive((active - 1 + shown.length) % shown.length);
            } else if (e.key === 'Enter') {
                // Dentro de un formulario, Intro NUNCA debe enviarlo por error.
                e.preventDefault();
                if (!list.hidden && shown[active >= 0 ? active : 0]) pick(shown[active >= 0 ? active : 0]);
            } else if (e.key === 'Escape') {
                close();
            }
        });

        return {
            refresh: function () { if (!list.hidden) render(); },
            setPlaceholder: function (t) { input.placeholder = t; },
            show: function (v) { root.style.display = v ? '' : 'none'; }
        };
    }

    global.PersonPicker = { attach: attach, norm: norm };
})(window);
