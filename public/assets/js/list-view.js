/**
 * JPList — listados paginados en el navegador (DataTables) con vista Lista / Cuadrícula.
 *
 * La búsqueda y los filtros actúan sobre TODAS las filas (no solo la página visible);
 * la paginación es solo de presentación (25 por defecto).
 *
 * La vista (lista/cuadrícula) y las filas por página que elige el usuario se
 * recuerdan por listado en localStorage (`jp:view:<key>` y `jp:size:<key>`), así
 * que `pageLength` y `defaultView` solo mandan mientras no haya elegido nada.
 *
 * Uso:
 *   JPList.init({
 *     table:   '#alumnos-table',
 *     key:     'alumnos',                       // clave para recordar vista y tamaño
 *     search:  '#search-input',                 // opcional
 *     filters: [{ el: '#filter-status', attr: 'status' }],  // compara con data-<attr> del <tr>
 *     count:   '#total-count', noun: 'alumnos', // opcional
 *     pageLength: 10, defaultView: 'list',      // opcional (por defecto 25 y lista/cuadrícula según pantalla)
 *   });
 * Botones de vista: cualquier <button data-jp-view="list|grid" data-jp-view-for="<key>">.
 * Columnas no ordenables: <th class="no-sort">. Sin etiqueta en tarjeta: <th class="no-label">.
 */
(function (window, $) {
    'use strict';
    if (!$ || !$.fn.dataTable) return;

    var LANG = {
        emptyTable:   'No hay datos disponibles',
        zeroRecords:  'Sin resultados para esta búsqueda',
        info:         'Mostrando _START_–_END_ de _TOTAL_',
        infoEmpty:    'Sin registros',
        infoFiltered: '(de _MAX_ en total)',
        lengthMenu:   '_MENU_ por página',
        paginate:     { first: '«', previous: '‹', next: '›', last: '»' },
    };

    function load(key) {
        try { return window.localStorage.getItem('jp:view:' + key); } catch (e) { return null; }
    }
    function save(key, v) {
        try { window.localStorage.setItem('jp:view:' + key, v); } catch (e) { /* sin persistencia */ }
    }
    function initialView(key, def) {
        var v = load(key);
        if (v === 'list' || v === 'grid') return v;
        if (def) return def;
        return window.matchMedia && window.matchMedia('(max-width: 767px)').matches ? 'grid' : 'list';
    }

    // Filas por página elegidas por el usuario, recordadas por listado.
    function loadSize(key, def) {
        var n;
        try { n = parseInt(window.localStorage.getItem('jp:size:' + key), 10); } catch (e) { n = NaN; }
        return n > 0 ? n : def;
    }
    function saveSize(key, n) {
        try { window.localStorage.setItem('jp:size:' + key, String(n)); } catch (e) { /* sin persistencia */ }
    }
    // El menú siempre debe contener el tamaño activo, o el <select> saldría vacío.
    function sizeMenu(size, base) {
        var menu = (base || [10, 25, 50, 100]).slice();
        if (menu.indexOf(size) === -1) menu.push(size);
        return menu.sort(function (a, b) { return a - b; });
    }

    function init(o) {
        var tableEl = typeof o.table === 'string' ? document.querySelector(o.table) : o.table;
        if (!tableEl) return null;

        var query = '';
        var filters = (o.filters || []).map(function (f) {
            return { el: document.querySelector(f.el), attr: f.attr };
        }).filter(function (f) { return f.el; });

        $.fn.dataTable.ext.search.push(function (settings, data, idx) {
            if (settings.nTable !== tableEl) return true;
            if (!document.body.contains(tableEl)) return true; // tabla reemplazada (p. ej. modal): no filtrar
            var tr = settings.aoData[idx] && settings.aoData[idx].nTr;
            if (!tr) return true;
            if (o.rowFilter && !o.rowFilter(tr)) return false;
            if (o.searchAttrs && query) {
                var hay = o.searchAttrs.map(function (a) { return tr.dataset[a] || ''; }).join(' ').toLowerCase();
                if (hay.indexOf(query) === -1) return false;
            }
            for (var i = 0; i < filters.length; i++) {
                var v = filters[i].el.value;
                if (v && (tr.dataset[filters[i].attr] || '') !== v) return false;
            }
            return true;
        });

        var labels = Array.prototype.map.call(tableEl.querySelectorAll('thead th'), function (th, i) {
            var t = (th.textContent || '').trim();
            return (i === 0 || !t || th.classList.contains('no-label') || t === 'Acciones') ? '' : t;
        });

        var size = loadSize(o.key, o.pageLength || 25);
        var fits = !!o.pagerOnlyIfNeeded && tableEl.querySelectorAll('tbody tr:not(:has(.dt-empty))').length <= size;
        var dt = $(tableEl).DataTable({
            pageLength: size,
            lengthMenu: sizeMenu(size, o.lengthMenu),
            order: o.order || [],
            ordering: o.ordering !== false,
            language: LANG,
            autoWidth: false,
            columnDefs: [{ targets: '.no-sort', orderable: false }],
            layout: {
                topStart: o.dtSearch ? 'search' : null, topEnd: null,
                // pagerOnlyIfNeeded: sin controles de paginación si todo cabe en una página
                bottomStart: fits ? null : ['pageLength', 'info'],
                bottomEnd: fits ? null : 'paging',
            },
        });

        var container = $(dt.table().container());
        container.addClass('jp-list');

        function decorate() {
            dt.rows({ page: 'current' }).nodes().each(function (tr) {
                Array.prototype.forEach.call(tr.children, function (td, i) {
                    td.setAttribute('data-label', labels[i] || '');
                });
            });
            if (o.count) {
                var c = document.querySelector(o.count);
                if (c) c.textContent = dt.page.info().recordsDisplay + ' ' + (o.noun || 'registros');
            }
        }
        dt.on('draw', decorate);
        decorate();

        dt.on('length.dt', function (e, settings, len) { if (len > 0) saveSize(o.key, len); });
        // Al volver atrás, el navegador repone el valor del <select> sin avisar a
        // DataTables: se veían 25 filas con "10" elegido. Mandan las filas pintadas.
        window.addEventListener('pageshow', function () {
            var sel = container.find('.dt-length select')[0];
            if (sel && sel.value !== String(dt.page.len())) sel.value = String(dt.page.len());
        });

        if (o.search) {
            var s = document.querySelector(o.search);
            if (s) s.addEventListener('input', function () {
                if (o.searchAttrs) { query = s.value.trim().toLowerCase(); dt.draw(); }
                else dt.search(s.value.trim()).draw();
            });
        }
        filters.forEach(function (f) { f.el.addEventListener('change', function () { dt.draw(); }); });

        // Vista lista / cuadrícula
        function setView(v, persist) {
            container.toggleClass('jp-view-grid', v === 'grid').toggleClass('jp-view-list', v !== 'grid');
            var btns = document.querySelectorAll('[data-jp-view-for="' + o.key + '"]');
            Array.prototype.forEach.call(btns, function (b) {
                var on = b.getAttribute('data-jp-view') === v;
                b.setAttribute('aria-pressed', on ? 'true' : 'false');
                b.classList.toggle('active', on);
            });
            if (persist) save(o.key, v);
        }
        Array.prototype.forEach.call(document.querySelectorAll('[data-jp-view-for="' + o.key + '"]'), function (b) {
            b.addEventListener('click', function () { setView(b.getAttribute('data-jp-view'), true); });
        });
        setView(initialView(o.key, o.defaultView), false);

        return dt;
    }

    /**
     * JPCards — paginación + lista/cuadrícula para listados que NO son tablas (tarjetas, <li>).
     *   JPCards.init({ list: '#notif-list', item: '.notif-item', key: 'notif', search: true, noun: 'notificaciones' })
     * search:true inserta buscador + toggle encima (busca en el texto de TODOS los elementos).
     * search:false: el toggle se enlaza a [data-jp-view-for=key] y el filtrado externo usa setFilter(fn).
     * Devuelve { setFilter(fn) -> nº de coincidencias, render() }.
     */
    var TOGGLE_HTML = function (key) {
        return '<div class="jp-view-toggle" role="group" aria-label="Cambiar vista">' +
            '<button type="button" data-jp-view="list" data-jp-view-for="' + key + '" aria-pressed="true" title="Vista de lista" aria-label="Vista de lista"><i class="bi bi-list-ul"></i></button>' +
            '<button type="button" data-jp-view="grid" data-jp-view-for="' + key + '" aria-pressed="false" title="Vista de cuadrícula" aria-label="Vista de cuadrícula"><i class="bi bi-grid-3x3-gap"></i></button>' +
            '</div>';
    };

    function cards(o) {
        var list = document.querySelector(o.list);
        if (!list) return null;
        var items = Array.prototype.slice.call(list.querySelectorAll(o.item));
        var size = loadSize(o.key, o.pageSize || 25), page = 1, q = '';
        var pred = function () { return true; };
        var texts = items.map(function (el) { return (el.textContent || '').toLowerCase().replace(/\s+/g, ' '); });
        var countEl = null;

        var pager = document.createElement('div');
        pager.className = 'jp-pager';
        list.parentNode.insertBefore(pager, list.nextSibling);
        list.classList.add('jp-cards-list');

        if (o.search) {
            var bar = document.createElement('div');
            bar.className = 'jp-cards-toolbar';
            bar.innerHTML = '<div class="input-search"><i class="bi bi-search"></i><input type="text" placeholder="Buscar…" aria-label="Buscar"></div>' +
                '<span class="jp-cards-count"></span>' + TOGGLE_HTML(o.key);
            list.parentNode.insertBefore(bar, list);
            countEl = bar.querySelector('.jp-cards-count');
            bar.querySelector('input').addEventListener('input', function (e) {
                q = e.target.value.trim().toLowerCase(); page = 1; render();
            });
        }

        function render() {
            var m = items.filter(function (el, i) { return (!q || texts[i].indexOf(q) !== -1) && pred(el); });
            var pages = Math.max(1, Math.ceil(m.length / size));
            if (page > pages) page = pages;
            var from = (page - 1) * size, to = from + size;
            items.forEach(function (el) { el.hidden = true; });
            m.slice(from, to).forEach(function (el) { el.hidden = false; });
            if (countEl) countEl.textContent = m.length + ' ' + (o.noun || 'resultados');
            if (o.onRender) o.onRender(m);

            if (m.length === 0) {
                pager.innerHTML = q || o.search ? '<div class="jp-pager-empty">Sin resultados para esta búsqueda</div>' : '';
            } else {
                var opts = sizeMenu(size, o.lengthMenu).map(function (n) {
                    return '<option value="' + n + '"' + (n === size ? ' selected' : '') + '>' + n + '</option>';
                }).join('');
                pager.innerHTML =
                    '<label class="jp-pager-size"><select>' + opts + '</select> por página</label>' +
                    '<span class="jp-pager-info">' + (from + 1) + '–' + Math.min(to, m.length) + ' de ' + m.length + '</span>' +
                    (pages > 1
                        ? '<div class="jp-pager-btns"><button type="button" data-p="' + (page - 1) + '"' + (page === 1 ? ' disabled' : '') + ' aria-label="Anterior">‹</button>' +
                          '<span class="jp-pager-cur">' + page + ' / ' + pages + '</span>' +
                          '<button type="button" data-p="' + (page + 1) + '"' + (page === pages ? ' disabled' : '') + ' aria-label="Siguiente">›</button></div>'
                        : '');
            }
            return m.length;
        }

        pager.addEventListener('click', function (e) {
            var b = e.target.closest('button[data-p]');
            if (b && !b.disabled) { page = parseInt(b.getAttribute('data-p'), 10); render(); }
        });
        pager.addEventListener('change', function (e) {
            if (e.target.tagName === 'SELECT') { size = parseInt(e.target.value, 10); saveSize(o.key, size); page = 1; render(); }
        });

        var sel = '[data-jp-view-for="' + o.key + '"]';
        function setView(v, persist) {
            if (o.nested) list.classList.toggle('jp-nested-list', v === 'list');
            else list.classList.toggle('jp-cards-grid', v === 'grid');
            Array.prototype.forEach.call(document.querySelectorAll(sel), function (b) {
                var on = b.getAttribute('data-jp-view') === v;
                b.setAttribute('aria-pressed', on ? 'true' : 'false');
                b.classList.toggle('active', on);
            });
            if (persist) save(o.key, v);
        }
        Array.prototype.forEach.call(document.querySelectorAll(sel), function (b) {
            b.addEventListener('click', function () { setView(b.getAttribute('data-jp-view'), true); });
        });
        setView(initialView(o.key, o.defaultView), false);
        render();

        return {
            setFilter: function (fn) { pred = fn || function () { return true; }; page = 1; return render(); },
            render: render,
        };
    }

    window.JPList = { init: init, toggleHtml: function (key) { return TOGGLE_HTML(key); } };
    window.JPCards = { init: cards };

    /**
     * Auto-inicialización: <table data-jp-list="clave"> → tabla paginada + toggle lista/cuadrícula
     * (buscador si tiene más de 10 filas). Para tablas embebidas sin lógica propia.
     */
    document.addEventListener('DOMContentLoaded', function () {
        Array.prototype.forEach.call(document.querySelectorAll('table[data-jp-list]'), function (t) {
            var key = t.getAttribute('data-jp-list');
            var anchor = t.closest('.table-responsive') || t;
            var bar = document.createElement('div');
            bar.className = 'jp-files-toolbar';
            bar.innerHTML = TOGGLE_HTML(key);
            anchor.parentNode.insertBefore(bar, anchor);
            init({ table: t, key: key, ordering: false, pageLength: parseInt(t.getAttribute('data-jp-page-size'), 10) || 25, pagerOnlyIfNeeded: t.hasAttribute('data-jp-page-size'), dtSearch: t.querySelectorAll('tbody tr').length > 10 });
        });
    });
})(window, window.jQuery);
