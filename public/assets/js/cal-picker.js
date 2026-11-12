/**
 * Selector «Elige qué clase quieres ver» del calendario (Clases y Dashboard).
 *
 * Se abre al pulsar «Ver todas» o un día con muchas clases. Con la carga actual (decenas de clases en un
 * día) una lista corta no sirve, así que es un modal ancho con:
 *   · buscador (alumno, entrenador, título, estado u hora)
 *   · filtro por estado (solo salen los estados que hay ese día, con su contador)
 *   · orden por hora: DESCENDENTE por defecto, un clic para invertirlo
 * Las clases canceladas salen en rojo y tachadas (siguen en el calendario).
 *
 * API: CalPicker.show(titulo, eventos) · CalPicker.close()
 * Cada evento es el JSON de /clases/api/calendario ({id,title,start,end,status,color,alert_level,
 * alert_reason,player_label,responsable_name,…}). Sin dependencias.
 */
(function () {
    'use strict';
    if (window.CalPicker) return;

    var STATES = {
        pending:   { label: 'Sin pasar lista', color: '#3b82f6', order: 1 },
        ok:        { label: 'Lista pasada',    color: '#10b981', order: 2 },
        warn:      { label: 'Aviso',           color: '#f59e0b', order: 3 },
        cancelled: { label: 'Cancelada',       color: '#ef4444', order: 4 }
    };

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    /** minúsculas y sin acentos, para buscar «jose» y encontrar «José» */
    function norm(s) {
        s = String(s == null ? '' : s).toLowerCase();
        try { s = s.normalize('NFD').replace(/[̀-ͯ]/g, ''); } catch (e) { /* navegador antiguo: se busca con acentos */ }
        return s;
    }

    /** Estado de una clase para filtrar y etiquetar. El alumno no recibe alert_level: se usa status. */
    function stateOf(ev) {
        if (ev.status === 'cancelled') return 'cancelled';
        if (ev.alert_level && STATES[ev.alert_level]) return ev.alert_level;
        return ev.status === 'completed' ? 'ok' : 'pending';
    }

    var overlay = null, lastFocus = null;

    function close() {
        if (overlay) { overlay.remove(); overlay = null; }
        document.removeEventListener('keydown', onKey, true);
        document.body.style.overflow = '';
        if (lastFocus && lastFocus.focus) { try { lastFocus.focus(); } catch (e) { /* elemento ya no existe */ } }
        lastFocus = null;
    }
    function onKey(e) { if (e.key === 'Escape') { e.stopPropagation(); close(); } }

    function show(titleTxt, evts) {
        close();
        lastFocus = document.activeElement;

        var all = (evts || []).map(function (ev) {
            var st = stateOf(ev);
            return {
                ev: ev,
                state: st,
                hay: norm([ev.player_label, ev.title, ev.responsable_name, ev.start, ev.end, STATES[st].label, ev.alert_reason].join(' '))
            };
        });
        var counts = {};
        all.forEach(function (x) { counts[x.state] = (counts[x.state] || 0) + 1; });

        var view = { q: '', state: 'all', dir: 'desc' };   // hora descendente por defecto

        overlay = document.createElement('div');
        overlay.id = 'cal-picker';
        overlay.className = 'cal-picker-overlay';

        var chips = '<button type="button" class="cpk-chip is-on" data-state="all" aria-pressed="true">Todas <b>' + all.length + '</b></button>' +
            Object.keys(STATES).filter(function (k) { return counts[k]; }).sort(function (a, b) { return STATES[a].order - STATES[b].order; })
                .map(function (k) {
                    return '<button type="button" class="cpk-chip" data-state="' + k + '" aria-pressed="false">' +
                        '<i style="background:' + STATES[k].color + '"></i>' + STATES[k].label + ' <b>' + counts[k] + '</b></button>';
                }).join('');

        overlay.innerHTML =
            '<div class="cal-picker-modal cpk" role="dialog" aria-modal="true" aria-label="' + esc(titleTxt) + '">' +
                '<div class="cal-picker-head">' +
                    '<span><i class="bi bi-calendar3"></i> ' + esc(titleTxt) + '</span>' +
                    '<button type="button" class="cal-picker-close" aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>' +
                '</div>' +
                '<div class="cpk-tools">' +
                    '<div class="cpk-search"><i class="bi bi-search"></i>' +
                        '<input type="search" placeholder="Buscar por alumno, entrenador, título u hora…" aria-label="Buscar clase" autocomplete="off">' +
                    '</div>' +
                    '<button type="button" class="cpk-sort" aria-label="Cambiar el orden por hora"></button>' +
                '</div>' +
                '<div class="cpk-chips" role="group" aria-label="Filtrar por estado">' + chips + '</div>' +
                '<div class="cpk-meta" aria-live="polite"></div>' +
                '<div class="cal-picker-list cpk-list"></div>' +
            '</div>';

        var list = overlay.querySelector('.cpk-list'), meta = overlay.querySelector('.cpk-meta'),
            input = overlay.querySelector('.cpk-search input'), sortBtn = overlay.querySelector('.cpk-sort');

        function row(x) {
            var ev = x.ev, st = STATES[x.state], cancelled = x.state === 'cancelled';
            var main = ev.player_label || ev.title || 'Clase';
            // El título solo se repite como línea secundaria si aporta algo distinto al alumno
            var sub  = (ev.player_label && ev.title && ev.title !== ev.player_label) ? ev.title : '';
            return '<a href="/clases/' + encodeURIComponent(ev.id) + '" class="cal-picker-row cpk-row' + (cancelled ? ' is-cancelled' : '') + '"' +
                    (ev.alert_reason ? ' title="' + esc(ev.alert_reason) + '"' : '') + '>' +
                '<span class="cal-picker-time">' + esc(ev.start) + (ev.end ? '<small>–' + esc(ev.end) + '</small>' : '') + '</span>' +
                '<span class="cpk-main"><span class="cpk-name">' + esc(main) + '</span>' +
                    (sub ? '<small>' + esc(sub) + '</small>' : '') + '</span>' +
                '<span class="cpk-coach">' + (ev.responsable_name ? '<i class="bi bi-person-workspace"></i>' + esc(ev.responsable_name) : '<em>Sin entrenador</em>') + '</span>' +
                '<span class="cpk-badge" style="color:' + st.color + ';background:' + st.color + '1a;border-color:' + st.color + '44">' + st.label + '</span>' +
                '<i class="bi bi-chevron-right cal-picker-arrow"></i>' +
                '</a>';
        }

        function render() {
            var q = norm(view.q).trim(), terms = q ? q.split(/\s+/) : [];
            var rows = all.filter(function (x) {
                if (view.state !== 'all' && x.state !== view.state) return false;
                return terms.every(function (t) { return x.hay.indexOf(t) >= 0; });
            }).sort(function (a, b) {
                var c = String(a.ev.start).localeCompare(String(b.ev.start)) || String(a.ev.end || '').localeCompare(String(b.ev.end || ''));
                if (c === 0) c = String(a.ev.player_label || a.ev.title).localeCompare(String(b.ev.player_label || b.ev.title));
                return view.dir === 'desc' ? -c : c;
            });
            list.innerHTML = rows.length ? rows.map(row).join('')
                : '<div class="cal-picker-empty">' + (all.length ? 'Ninguna clase coincide con la búsqueda o el filtro.' : 'No hay clases.') + '</div>';
            meta.textContent = (rows.length === all.length ? all.length + ' clase' + (all.length === 1 ? '' : 's') : rows.length + ' de ' + all.length + ' clases') +
                ' · Elige la que quieres ver';
            sortBtn.innerHTML = '<i class="bi bi-sort-' + (view.dir === 'desc' ? 'numeric-down-alt' : 'numeric-down') + '"></i> Hora ' + (view.dir === 'desc' ? 'descendente' : 'ascendente');
            list.scrollTop = 0;
        }

        input.addEventListener('input', function () { view.q = input.value; render(); });
        sortBtn.addEventListener('click', function () { view.dir = view.dir === 'desc' ? 'asc' : 'desc'; render(); });
        overlay.querySelector('.cpk-chips').addEventListener('click', function (e) {
            var b = e.target.closest ? e.target.closest('.cpk-chip') : null;
            if (!b) return;
            view.state = b.getAttribute('data-state');
            [].forEach.call(overlay.querySelectorAll('.cpk-chip'), function (c) {
                var on = c === b; c.classList.toggle('is-on', on); c.setAttribute('aria-pressed', on ? 'true' : 'false');
            });
            render();
        });
        overlay.addEventListener('click', function (e) { if (e.target === overlay) close(); });
        overlay.querySelector('.cal-picker-close').addEventListener('click', close);
        document.addEventListener('keydown', onKey, true);

        document.body.appendChild(overlay);
        document.body.style.overflow = 'hidden';
        render();
        // En móvil no se enfoca el buscador: abriría el teclado y taparía la lista
        if (window.matchMedia && window.matchMedia('(min-width: 700px)').matches) input.focus();
    }

    window.CalPicker = { show: show, close: close, stateOf: stateOf, norm: norm };
})();
