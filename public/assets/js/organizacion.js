/* Organización interna (TICKET-014): tablero de tareas por estados y agenda.
 * Todo en IIFE. Los datos vienen de GET /organizacion/data; las escrituras son POST con CSRF por cabecera. */
(function () {
    'use strict';
    var CFG = window.ORG;
    if (!CFG) return;

    var MONTHS = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
    var DAYS = ['lun', 'mar', 'mié', 'jue', 'vie', 'sáb', 'dom'];
    var PRIO = { baja: 'Baja', normal: 'Normal', alta: 'Alta' };

    var state = {
        view: 'board', filter: 'all',
        tasks: [], events: [], classes: [],
        month: CFG.today.slice(0, 7), // YYYY-MM
        selDay: CFG.today
    };
    var sortables = [];

    // ── Utilidades de fecha (siempre cadenas Y-m-d, hora local) ──
    function pd(s) { var p = s.split('-'); return new Date(+p[0], +p[1] - 1, +p[2], 12); }
    function fd(d) { return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2); }
    function addDays(s, n) { var d = pd(s); d.setDate(d.getDate() + n); return fd(d); }
    function mondayOf(s) { var d = pd(s); var w = (d.getDay() + 6) % 7; d.setDate(d.getDate() - w); return fd(d); }
    function longDay(s) { var d = pd(s); return DAYS[(d.getDay() + 6) % 7] + ' ' + d.getDate() + ' ' + MONTHS[d.getMonth()]; }
    function esc(v) {
        return String(v == null ? '' : v).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }
    function $(id) { return document.getElementById(id); }

    // ── Red ──
    function fail(data, endpoint) {
        if (window.handleApiError && window.handleApiError(data, endpoint)) return;
        var msg = (data && data.error) || 'No se ha podido completar la acción.';
        if (window.showAlert) window.showAlert(msg, 'error'); else alert(msg);
    }
    function post(path, obj) {
        var fd2 = new FormData();
        Object.keys(obj || {}).forEach(function (k) {
            var v = obj[k];
            if (Array.isArray(v)) v.forEach(function (x) { fd2.append(k + '[]', x); });
            else if (v != null) fd2.append(k, v);
        });
        var h = { 'X-Requested-With': 'XMLHttpRequest' };
        h[CFG.csrfHeader] = CFG.csrfHash;
        return fetch(CFG.base + path, { method: 'POST', body: fd2, headers: h, credentials: 'same-origin' })
            .then(function (r) { return r.json().catch(function () { return { error: 'Respuesta inválida del servidor.' }; }).then(function (j) { j._ok = r.ok; return j; }); })
            .then(function (j) { if (!j._ok) { fail(j, path); throw j; } return j; });
    }
    function rangeForView() {
        var first = mondayOf(state.month + '-01');
        return [first, addDays(first, 41)];
    }
    function load() {
        var r = rangeForView();
        return fetch(CFG.base + '/data?from=' + r[0] + '&to=' + r[1], { headers: { 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' })
            .then(function (res) { return res.json().then(function (j) { j._ok = res.ok; return j; }); })
            .then(function (j) {
                if (!j._ok) { fail(j, '/data'); return; }
                state.tasks = j.tasks || []; state.events = j.events || []; state.classes = j.classes || [];
                $('org-loading').hidden = true;
                render();
            })
            .catch(function () { $('org-loading').textContent = 'No se pudo cargar. Recarga la página.'; });
    }

    // ── Reglas (espejo de OrganizacionService; el servidor manda) ──
    function isAdmin() { return CFG.role === 'admin' || CFG.role === 'superadmin'; }
    function canEdit(it) { return +it.created_by === CFG.userId || (it.visibility === 'team' && isAdmin()); }
    function canMove(it) { return canEdit(it) || (it.assigned_to && +it.assigned_to === CFG.userId); }
    function passes(it) {
        if (state.filter === 'private') return it.visibility === 'personal';
        if (state.filter === 'mine') return +it.created_by === CFG.userId || (it.assigned_to && +it.assigned_to === CFG.userId);
        return true;
    }

    // ── Fragmentos ──
    function taskCard(t) {
        var overdue = t.due_date && t.status !== 'hecho' && t.due_date < CFG.today;
        var h = '<div class="org-card prio-' + esc(t.priority) + (t.status === 'hecho' ? ' is-done' : '') + '" data-id="' + t.id + '">';
        h += '<div class="org-card-t">' + (t.visibility === 'personal' ? '<i class="bi bi-lock-fill" title="Solo yo"></i> ' : '') + esc(t.title) + '</div>';
        h += '<div class="org-card-m">';
        if (t.priority === 'alta') h += '<span class="org-b b-alta">Alta</span>';
        if (t.due_date) h += '<span class="org-b' + (overdue ? ' b-late' : '') + '"><i class="bi bi-flag"></i> ' + esc(longDay(t.due_date)) + '</span>';
        if (t.assigned_name) h += '<span class="org-b b-who"><i class="bi bi-person"></i> ' + esc(t.assigned_name) + '</span>';
        h += '</div></div>';
        return h;
    }
    function eventChip(e, cls) {
        var time = e.all_day ? 'Todo el día' : e.start_at.slice(11, 16) + (e.end_at ? '–' + e.end_at.slice(11, 16) : '');
        return '<button type="button" class="org-chip kind-event ' + (cls || '') + '" data-event="' + e.id + '" title="' + esc(e.title) + '">' +
            (e.visibility === 'personal' ? '<i class="bi bi-lock-fill"></i> ' : '') +
            '<span class="org-chip-time">' + esc(time) + '</span> <span class="org-chip-t">' + esc(e.title) + '</span></button>';
    }
    function classChip(c) {
        return '<a class="org-chip kind-class" href="' + esc(window.APP_BASE || '') + '/clases/' + c.id + '" title="Clase: ' + esc(c.title) + '">' +
            '<i class="bi bi-trophy"></i> <span class="org-chip-time">' + esc(c.start) + '</span> <span class="org-chip-t">' + esc(c.title) + '</span></a>';
    }
    function dayEvents(day) {
        return state.events.filter(function (e) { return e.start_at.slice(0, 10) === day && passes(e); });
    }
    function dayClasses(day) { return state.filter === 'all' ? state.classes.filter(function (c) { return c.date === day; }) : []; }
    function dayDue(day) { return state.tasks.filter(function (t) { return t.due_date === day && t.status !== 'hecho' && passes(t); }); }

    // ── Vistas ──
    function destroySortables() { sortables.forEach(function (s) { s.destroy(); }); sortables = []; }

    function renderBoard() {
        var h = '<div class="org-board">';
        Object.keys(CFG.statuses).forEach(function (st) {
            var list = state.tasks.filter(function (t) { return t.status === st && passes(t); });
            h += '<div class="org-col" data-status="' + st + '"><div class="org-col-h"><span>' + esc(CFG.statuses[st]) + '</span><span class="org-count">' + list.length + '</span></div>';
            h += '<div class="org-list" data-status="' + st + '">' + list.map(taskCard).join('') + '</div>';
            h += '<button type="button" class="org-add" data-add-status="' + st + '"><i class="bi bi-plus"></i> Añadir</button></div>';
        });
        $('org-board').innerHTML = h + '</div>';

        Array.prototype.forEach.call($('org-board').querySelectorAll('.org-list'), function (el) {
            sortables.push(Sortable.create(el, {
                group: 'tasks', animation: 150, delay: 150, delayOnTouchOnly: true, ghostClass: 'org-ghost',
                onEnd: function (ev) {
                    var to = ev.to, id = +ev.item.dataset.id;
                    var order = Array.prototype.map.call(to.querySelectorAll('.org-card'), function (c) { return c.dataset.id; });
                    post('/tareas/' + id + '/mover', { status: to.dataset.status, order: order })
                        .then(function (j) { replaceTask(j.task); render(); })
                        .catch(function () { load(); });
                }
            }));
        });
    }

    function renderAgenda() {
        var y = +state.month.slice(0, 4), m = +state.month.slice(5, 7) - 1;
        var first = mondayOf(state.month + '-01');
        var h = '<div class="org-nav"><button type="button" class="btn btn-light btn-sm" data-mo="-1" aria-label="Mes anterior"><i class="bi bi-chevron-left"></i></button>' +
            '<strong>' + esc(MONTHS[m].charAt(0).toUpperCase() + MONTHS[m].slice(1)) + ' ' + y + '</strong>' +
            '<button type="button" class="btn btn-light btn-sm" data-mo="1" aria-label="Mes siguiente"><i class="bi bi-chevron-right"></i></button>' +
            '<button type="button" class="btn btn-outline-secondary btn-sm" data-mo="0">Hoy</button></div>';
        h += '<div class="org-month"><div class="org-dow">' + DAYS.map(function (d) { return '<span>' + d + '</span>'; }).join('') + '</div><div class="org-grid">';
        for (var i = 0; i < 42; i++) {
            var day = addDays(first, i);
            if (i >= 35 && pd(day).getMonth() !== m) break;
            var items = dayClasses(day).map(classChip).concat(dayEvents(day).map(function (e) { return eventChip(e); }));
            dayDue(day).forEach(function (t) { items.push('<span class="org-chip kind-task"><i class="bi bi-flag"></i> <span class="org-chip-t">' + esc(t.title) + '</span></span>'); });
            h += '<div class="org-cell' + (pd(day).getMonth() !== m ? ' is-out' : '') + (day === CFG.today ? ' is-today' : '') + (day === state.selDay ? ' is-sel' : '') + '" data-day="' + day + '">' +
                '<div class="org-cell-n">' + pd(day).getDate() + '</div><div class="org-cell-c">' + items.slice(0, 3).join('') + (items.length > 3 ? '<span class="org-more">+' + (items.length - 3) + '</span>' : '') + '</div></div>';
        }
        h += '</div></div>';

        // Detalle del día seleccionado
        var sd = state.selDay;
        h += '<div class="org-daybox"><div class="org-daybox-h"><strong>' + esc(longDay(sd)) + '</strong>' +
            '<button type="button" class="btn btn-outline-primary btn-sm" data-new-event-day="' + sd + '"><i class="bi bi-calendar-plus"></i> Evento este día</button></div>';
        var rows = dayClasses(sd).map(classChip).concat(dayEvents(sd).map(function (e) { return eventChip(e, 'wide'); }));
        var tasks = state.tasks.filter(function (t) { return t.due_date === sd && passes(t); });
        h += rows.length || tasks.length ? '<div class="org-daylist">' + rows.join('') + '</div>' +
            (tasks.length ? '<div class="org-daytasks">' + tasks.map(taskCard).join('') + '</div>' : '') : '<p class="org-empty">Nada este día.</p>';
        $('org-agenda').innerHTML = h + '</div>';
    }

    function render() {
        destroySortables();
        ['board', 'agenda'].forEach(function (v) { $('org-' + v).hidden = state.view !== v; });
        if (state.view === 'board') renderBoard();
        else renderAgenda();
    }

    // ── Estado local ──
    function findTask(id) { for (var i = 0; i < state.tasks.length; i++) if (state.tasks[i].id === +id) return state.tasks[i]; return null; }
    function replaceTask(t) {
        for (var i = 0; i < state.tasks.length; i++) if (state.tasks[i].id === t.id) { state.tasks[i] = t; return; }
        state.tasks.push(t);
    }
    function findEvent(id) { for (var i = 0; i < state.events.length; i++) if (state.events[i].id === +id) return state.events[i]; return null; }

    // ── Diálogos ──
    var taskDlg = $('org-task-dlg'), taskForm = $('org-task-form');
    var eventDlg = $('org-event-dlg'), eventForm = $('org-event-form');

    (function fillPeople() {
        var sel = taskForm.elements.assigned_to;
        CFG.people.forEach(function (p) { sel.insertAdjacentHTML('beforeend', '<option value="' + p.id + '">' + esc(p.name) + '</option>'); });
        $('org-event-people').innerHTML = CFG.people.map(function (p) {
            return '<label class="org-check"><input type="checkbox" name="attendees[]" value="' + p.id + '"> ' + esc(p.name) + '</label>';
        }).join('');
    })();

    function setDisabled(form, names, off) {
        names.forEach(function (n) { if (form.elements[n]) form.elements[n].disabled = off; });
    }
    var TASK_FIELDS = ['title', 'description', 'priority', 'assigned_to', 'due_date', 'visibility'];
    var MOVE_FIELDS = ['status'];

    function openTask(t, preset) {
        taskForm.reset();
        var isNew = !t;
        t = t || { status: 'pendiente', priority: 'normal', visibility: 'team', created_by: CFG.userId };
        Object.keys(preset || {}).forEach(function (k) { t[k] = preset[k]; });
        $('org-task-h').textContent = isNew ? 'Nueva tarea' : 'Tarea';
        ['id', 'title', 'description', 'status', 'priority', 'due_date', 'visibility'].forEach(function (k) {
            taskForm.elements[k].value = t[k] == null ? '' : t[k];
        });
        taskForm.elements.assigned_to.value = t.assigned_to == null ? '' : t.assigned_to;
        var edit = isNew || canEdit(t), move = isNew || canMove(t);
        setDisabled(taskForm, TASK_FIELDS, !edit);
        setDisabled(taskForm, MOVE_FIELDS, !move);
        // La visibilidad solo la cambia quien creó la tarea.
        taskForm.elements.visibility.disabled = !isNew && +t.created_by !== CFG.userId;
        $('org-task-save').hidden = !move;
        $('org-task-archive').hidden = isNew || !edit;
        var note = $('org-task-note');
        note.hidden = move;
        note.textContent = move ? '' : 'Solo lectura: no tienes permiso para modificar esta tarea.';
        if (!isNew && !edit && move) { note.hidden = false; note.textContent = 'Puedes cambiar su estado; el resto lo edita quien la creó.'; }
        taskDlg.showModal();
    }
    function openEvent(e, day) {
        eventForm.reset();
        var isNew = !e;
        $('org-event-h').textContent = isNew ? 'Nuevo evento' : 'Evento';
        eventForm.elements.id.value = isNew ? '' : e.id;
        eventForm.elements.title.value = isNew ? '' : e.title;
        eventForm.elements.date.value = isNew ? (day || CFG.today) : e.start_at.slice(0, 10);
        eventForm.elements.all_day.checked = !isNew && e.all_day;
        eventForm.elements.start_time.value = isNew || e.all_day ? '' : e.start_at.slice(11, 16);
        eventForm.elements.end_time.value = isNew || !e.end_at ? '' : e.end_at.slice(11, 16);
        eventForm.elements.location_text.value = isNew ? '' : e.location_text;
        eventForm.elements.description.value = isNew ? '' : e.description;
        eventForm.elements.visibility.value = isNew ? 'team' : e.visibility;
        var ids = isNew ? [] : e.attendees.map(function (a) { return a.id; });
        Array.prototype.forEach.call(eventForm.querySelectorAll('input[name="attendees[]"]'), function (c) { c.checked = ids.indexOf(+c.value) !== -1; });
        var edit = isNew || canEdit(e);
        Array.prototype.forEach.call(eventForm.elements, function (el) { if (el.name) el.disabled = !edit; });
        eventForm.elements.visibility.disabled = !edit || (!isNew && +e.created_by !== CFG.userId);
        $('org-event-save').hidden = !edit;
        $('org-event-archive').hidden = isNew || !edit;
        var note = $('org-event-note');
        note.hidden = edit;
        note.textContent = edit ? '' : 'Solo lectura. Lo creó ' + (e && e.creator_name ? e.creator_name : 'otra persona') + '.';
        eventDlg.showModal();
    }

    taskForm.addEventListener('submit', function (ev) {
        ev.preventDefault();
        var o = {};
        Array.prototype.forEach.call(taskForm.elements, function (el) { if (el.name && !el.disabled) o[el.name] = el.value; });
        var id = taskForm.elements.id.value;
        var req;
        if (id && taskForm.elements.title.disabled) {
            // Sin permiso de edición total (p. ej. la persona asignada): solo el estado.
            req = post('/tareas/' + id + '/mover', { status: o.status });
        } else {
            req = post('/tareas/guardar', o);
        }
        $('org-task-save').disabled = true;
        req.then(function () { taskDlg.close(); return load(); })
            .catch(function () { })
            .then(function () { $('org-task-save').disabled = false; });
    });
    eventForm.addEventListener('submit', function (ev) {
        ev.preventDefault();
        var o = { attendees: [] };
        Array.prototype.forEach.call(eventForm.elements, function (el) {
            if (!el.name || el.disabled) return;
            if (el.name === 'attendees[]') { if (el.checked) o.attendees.push(el.value); }
            else if (el.type === 'checkbox') { if (el.checked) o[el.name] = el.value; }
            else o[el.name] = el.value;
        });
        $('org-event-save').disabled = true;
        post('/eventos/guardar', o)
            .then(function () { eventDlg.close(); state.selDay = o.date || state.selDay; return load(); })
            .catch(function () { })
            .then(function () { $('org-event-save').disabled = false; });
    });
    $('org-task-archive').addEventListener('click', function () {
        if (!confirm('¿Archivar esta tarea? Dejará de verse, pero queda en el registro.')) return;
        post('/tareas/' + taskForm.elements.id.value + '/archivar').then(function () { taskDlg.close(); return load(); }).catch(function () { });
    });
    $('org-event-archive').addEventListener('click', function () {
        if (!confirm('¿Archivar este evento? Dejará de verse, pero queda en el registro.')) return;
        post('/eventos/' + eventForm.elements.id.value + '/archivar').then(function () { eventDlg.close(); return load(); }).catch(function () { });
    });
    [taskDlg, eventDlg].forEach(function (d) {
        d.addEventListener('click', function (e) { if (e.target === d || (e.target.closest && e.target.closest('[data-close]'))) d.close(); });
    });

    // ── Eventos de la página ──
    $('org-new-task').addEventListener('click', function () { openTask(null); });
    $('org-new-event').addEventListener('click', function () { openEvent(null, state.view === 'agenda' ? state.selDay : CFG.today); });

    Array.prototype.forEach.call(document.querySelectorAll('.org-tab'), function (b) {
        b.addEventListener('click', function () {
            state.view = b.dataset.view;
            Array.prototype.forEach.call(document.querySelectorAll('.org-tab'), function (x) { x.classList.toggle('active', x === b); });
            load();
        });
    });
    Array.prototype.forEach.call(document.querySelectorAll('.org-filter'), function (b) {
        b.addEventListener('click', function () {
            state.filter = b.dataset.filter;
            Array.prototype.forEach.call(document.querySelectorAll('.org-filter'), function (x) { x.classList.toggle('active', x === b); });
            render();
        });
    });

    $('org-app').addEventListener('click', function (e) {
        var t = e.target;
        var card = t.closest('.org-card');
        if (card) { var task = findTask(card.dataset.id); if (task) openTask(task); return; }
        var ev = t.closest('[data-event]');
        if (ev) { var evt = findEvent(ev.dataset.event); if (evt) openEvent(evt); return; }
        var add = t.closest('[data-add-status]');
        if (add) { openTask(null, { status: add.dataset.addStatus }); return; }
        var mo = t.closest('[data-mo]');
        if (mo) {
            var k = +mo.dataset.mo;
            if (k === 0) { state.month = CFG.today.slice(0, 7); state.selDay = CFG.today; }
            else {
                var d = pd(state.month + '-01'); d.setMonth(d.getMonth() + k);
                state.month = fd(d).slice(0, 7); state.selDay = state.month + '-01';
            }
            load(); return;
        }
        var ne = t.closest('[data-new-event-day]');
        if (ne) { openEvent(null, ne.dataset.newEventDay); return; }
        var cell = t.closest('.org-cell');
        if (cell && !t.closest('a')) { state.selDay = cell.dataset.day; renderAgenda(); }
    });

    // Enlace de un aviso (?tarea=ID / ?evento=ID): abre el elemento al cargar.
    function openFromLink() {
        var o = CFG.open;
        if (!o) return;
        CFG.open = null;
        if (o.type === 'task') {
            var t = findTask(o.id);
            if (t) openTask(t);
        } else if (o.type === 'event') {
            var e = findEvent(o.id);
            if (e) openEvent(e);
        }
    }

    if (CFG.open && CFG.open.type === 'event') {
        // El evento puede caer fuera del mes actual: arrancamos en la agenda, en su mes.
        state.view = 'agenda';
        state.month = CFG.open.date.slice(0, 7);
        state.selDay = CFG.open.date;
        Array.prototype.forEach.call(document.querySelectorAll('.org-tab'), function (x) { x.classList.toggle('active', x.dataset.view === 'agenda'); });
    }

    // Tiempo real: otra persona cambia una tarea/evento → se recarga (sin pisar un diálogo abierto).
    // live.js se carga al final de la página: registramos el gancho cuando ya existe.
    window.addEventListener('load', function () {
        if (!window.JPLive || !window.JPLive.on) return;
        window.JPLive.on(['org', 'org_task', 'org_event'], function () {
            if (taskDlg.open || eventDlg.open) return;
            load();
        });
    });

    load().then(openFromLink);
})();
