/**
 * series-builder.js — TICKET-013: configurar una serie de clases recurrentes.
 *
 * Pinta los campos de la serie y calcula las fechas:
 *   · Primera clase (fecha de inicio)
 *   · Nº de clases y/o Fecha límite (vale lo que se cumpla primero)
 *   · Casilla "Todas las clases el mismo día de la semana y a la misma hora".
 *     Si se desmarca, aparece una tabla para ajustar clase a clase
 *     (fecha, hora de inicio y de fin, quitar, añadir).
 *
 * Los días de la semana y la hora por defecto los pone quien lo usa (cada
 * pantalla ya los tenía), por eso se piden con callbacks.
 *
 *   const sb = SeriesBuilder.attach({
 *       root:     document.getElementById('series-builder'),
 *       getDays:  () => [1, 4],                         // 1=Lun … 7=Dom
 *       getTimes: () => ({ start: '09:00', end: '10:00' }),
 *       onChange: () => {}
 *   });
 *   sb.refresh();        // llamar si cambian días u hora
 *   sb.appendTo(fd);     // añade los campos al FormData (modal)
 *   sb.validate();       // null si todo bien, o el texto del error
 *   sb.setCount(n);      // "Ajustar al bono"
 *
 * Con `simple: true` (clase rápida) solo se ven "Primera clase" y "Nº de clases":
 * la fecha límite y el calendario clase a clase quedan para el formulario completo.
 * La lógica es la misma (los campos siguen ahí, ocultos y vacíos).
 *
 * En el formulario completo los inputs llevan `name`, así que viajan solos al
 * enviar (recurrence_start, recurrence_count, recurrence_end, custom_schedule).
 */
(function () {
    'use strict';

    var MAX = 60;
    var WD = ['dom', 'lun', 'mar', 'mié', 'jue', 'vie', 'sáb'];

    function esc(s) {
        return String(s == null ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;')
            .replace(/>/g, '&gt;').replace(/"/g, '&quot;');
    }
    function iso(d) {
        return d.getFullYear() + '-' + String(d.getMonth() + 1).padStart(2, '0') + '-' + String(d.getDate()).padStart(2, '0');
    }
    function parse(s) { return new Date(s + 'T00:00:00'); }
    function nice(s) {
        var d = parse(s);
        return WD[d.getDay()] + ' ' + String(d.getDate()).padStart(2, '0') + '/' + String(d.getMonth() + 1).padStart(2, '0');
    }
    function niceFull(s) {
        var d = parse(s);
        return WD[d.getDay()] + ' ' + String(d.getDate()).padStart(2, '0') + '/' + String(d.getMonth() + 1).padStart(2, '0') + '/' + d.getFullYear();
    }

    /** Fechas del patrón: mismas reglas que el servidor (ClasesService::resolveSeriesDates). */
    function patternDates(days, start, count, end) {
        var out = [];
        if (!days.length || !start || (!(count >= 1) && !end)) { return out; }
        var max = count >= 1 ? Math.min(count, MAX) : MAX;
        var cur = parse(start);
        var limit = end ? parse(end) : null;
        for (var i = 0; i < 740 && out.length < max; i++) {
            if (limit && cur > limit) { break; }
            var n = cur.getDay() === 0 ? 7 : cur.getDay();
            if (days.indexOf(n) !== -1) { out.push(iso(cur)); }
            cur.setDate(cur.getDate() + 1);
        }
        return out;
    }

    function attach(cfg) {
        var root = cfg.root;
        var today = iso(new Date());
        var rows = [];      // calendario clase a clase (modo "no todas iguales")
        var edited = false; // el admin tocó la tabla

        root.innerHTML =
            '<div class="sb' + (cfg.simple ? ' sb-simple' : '') + '">'
          +   '<div class="sb-grid">'
          +     '<div><label class="form-label">Primera clase <span class="sb-req">*</span></label>'
          +       '<input type="date" class="form-control-jp" data-sb="start" name="recurrence_start" value="' + today + '"></div>'
          +     '<div><label class="form-label">Nº de clases</label>'
          +       '<input type="number" class="form-control-jp" data-sb="count" name="recurrence_count" min="1" max="' + MAX + '" value="4" placeholder="Ej: 4"></div>'
          +     '<div data-sb-adv><label class="form-label">Fecha límite <small>(opcional)</small></label>'
          +       '<input type="date" class="form-control-jp" data-sb="end" name="recurrence_end"></div>'
          +   '</div>'
          +   '<div class="sb-help sb-help-simple">Se crean tantas clases como indiques, el mismo día de la semana y a la misma hora.</div>'
          +   '<div class="sb-help" data-sb-adv>Se crean clases hasta llegar al <strong>nº de clases</strong> o a la <strong>fecha límite</strong>, lo que pase primero. '
          +     'Ejemplo: 8 clases con límite el 31/10 → si el 31/10 llega antes de la 8ª, se para ahí. Puedes rellenar solo una de las dos.</div>'
          +   '<label class="sb-check" data-sb-adv><input type="checkbox" data-sb="same" checked> '
          +     '<span><strong>Todas las clases el mismo día de la semana y a la misma hora</strong>'
          +     '<small>Desmárcalo si alguna clase cambia de día u hora: podrás ajustar cada una.</small></span></label>'
          +   '<div data-sb="summary" class="sb-summary"></div>'
          +   '<div data-sb="table" class="sb-table d-none"></div>'
          +   '<input type="hidden" data-sb="schedule" name="custom_schedule" value="">'
          +   (cfg.simple && cfg.moreUrl
                ? '<div class="sb-more">¿Necesitas fecha límite o cambiar el día u hora de alguna clase? '
                  + '<a href="' + esc(cfg.moreUrl) + '">Usa el formulario completo</a>.</div>'
                : '')
          + '</div>';

        function q(k) { return root.querySelector('[data-sb="' + k + '"]'); }
        var elStart = q('start'), elCount = q('count'), elEnd = q('end'), elSame = q('same');
        var elSummary = q('summary'), elTable = q('table'), elSchedule = q('schedule');

        function times() {
            var t = cfg.getTimes ? cfg.getTimes() : null;
            return { start: (t && t.start) || '09:00', end: (t && t.end) || '' };
        }
        function days() { return (cfg.getDays ? cfg.getDays() : []).map(Number); }
        function isSame() { return elSame.checked; }

        function changed() { if (typeof cfg.onChange === 'function') { cfg.onChange(); } }

        function fromPattern() {
            var t = times();
            return patternDates(days(), elStart.value, parseInt(elCount.value || '0', 10), elEnd.value || '')
                .map(function (d) { return { date: d, start: t.start, end: t.end }; });
        }

        function syncHidden() {
            elSchedule.value = isSame() ? '' : JSON.stringify(rows);
            // En modo "clase a clase" el patrón no manda: sus campos no se envían.
            elCount.disabled = !isSame();
            elEnd.disabled   = !isSame();
        }

        function renderSummary() {
            var t = times();
            if (!isSame()) {
                elSummary.innerHTML = '<div class="sb-info"><i class="bi bi-pencil-square me-1"></i>Estás ajustando <strong>' + rows.length
                    + ' clase' + (rows.length === 1 ? '' : 's') + '</strong> una a una. Los cambios de días, nº de clases y fecha límite de arriba ya no se aplican.</div>';
                return;
            }
            var ds = fromPattern();
            if (!days().length) {
                elSummary.innerHTML = '<div class="sb-warn"><i class="bi bi-exclamation-circle me-1"></i>Elige los días de la semana.</div>';
            } else if (!ds.length) {
                elSummary.innerHTML = '<div class="sb-warn"><i class="bi bi-exclamation-circle me-1"></i>Indica el nº de clases o una fecha límite.</div>';
            } else {
                elSummary.innerHTML = '<div class="sb-info"><i class="bi bi-calendar-check me-1"></i>Se crearán <strong>' + ds.length
                    + ' clase' + (ds.length === 1 ? '' : 's') + '</strong> de ' + esc(t.start) + (t.end ? '–' + esc(t.end) : '')
                    + ': ' + ds.map(function (r) { return esc(nice(r.date)); }).join(', ')
                    + '. La última es el <strong>' + esc(niceFull(ds[ds.length - 1].date)) + '</strong>.</div>';
            }
        }

        function renderTable() {
            if (isSame()) { elTable.classList.add('d-none'); elTable.innerHTML = ''; return; }
            elTable.classList.remove('d-none');
            var html = '<table><thead><tr><th>#</th><th>Día</th><th>Inicio</th><th>Fin</th><th></th></tr></thead><tbody>';
            rows.forEach(function (r, i) {
                html += '<tr data-i="' + i + '">'
                    + '<td>' + (i + 1) + '</td>'
                    + '<td><input type="date" class="form-control-jp" data-f="date" value="' + esc(r.date) + '"></td>'
                    + '<td><input type="time" class="form-control-jp" data-f="start" value="' + esc(r.start) + '"></td>'
                    + '<td><input type="time" class="form-control-jp" data-f="end" value="' + esc(r.end) + '"></td>'
                    + '<td><button type="button" class="sb-x" data-del="' + i + '" aria-label="Quitar esta clase" title="Quitar esta clase"><i class="bi bi-x-lg"></i></button></td>'
                    + '</tr>';
            });
            html += '</tbody></table>'
                + '<div class="sb-table-actions">'
                + '<button type="button" class="btn-jp btn-jp-secondary btn-jp-sm" data-add>+ Añadir clase</button>'
                + '<button type="button" class="btn-jp btn-jp-secondary btn-jp-sm" data-regen>Volver a generar desde el patrón</button>'
                + '</div>'
                + '<div class="sb-help">Después de crearlas, cada clase se puede editar por separado desde su ficha.</div>';
            elTable.innerHTML = html;
        }

        function renderAll() {
            syncHidden();
            renderSummary();
            renderTable();
            changed();
        }

        // ── Eventos ──
        root.addEventListener('input', function (e) {
            var t = e.target;
            if (t === elCount || t === elEnd || t === elStart) {
                if (isSame()) { renderAll(); }
                else if (t === elStart) { /* el inicio solo manda en modo patrón */ }
                return;
            }
            var tr = t.closest ? t.closest('tr[data-i]') : null;
            if (tr && t.dataset.f) {
                rows[parseInt(tr.dataset.i, 10)][t.dataset.f] = t.value;
                edited = true;
                syncHidden();
                changed();
            }
        });
        root.addEventListener('change', function (e) {
            if (e.target === elSame) {
                if (!isSame()) {
                    rows = fromPattern();
                    edited = false;
                    if (!rows.length) {
                        var t0 = times();
                        rows = [{ date: elStart.value || today, start: t0.start, end: t0.end }];
                    }
                } else if (edited && !window.confirm('Volverás a generar todas las clases desde el patrón y se perderán los cambios que hiciste una a una. ¿Continuar?')) {
                    elSame.checked = false;
                    return;
                }
                renderAll();
                return;
            }
            // al salir de un campo de fecha/hora de la tabla, reordenar por fecha
            var tr = e.target.closest ? e.target.closest('tr[data-i]') : null;
            if (tr) {
                rows.sort(function (a, b) { return (a.date + a.start) < (b.date + b.start) ? -1 : 1; });
                renderAll();
            }
        });
        root.addEventListener('click', function (e) {
            var del = e.target.closest ? e.target.closest('[data-del]') : null;
            if (del) {
                rows.splice(parseInt(del.dataset.del, 10), 1);
                edited = true;
                renderAll();
                return;
            }
            if (e.target.closest && e.target.closest('[data-add]')) {
                if (rows.length >= MAX) { return; }
                var last = rows[rows.length - 1];
                var d = last ? parse(last.date) : parse(elStart.value || today);
                if (last) { d.setDate(d.getDate() + 7); }
                var t = last || times();
                rows.push({ date: iso(d), start: t.start, end: t.end });
                edited = true;
                renderAll();
                return;
            }
            if (e.target.closest && e.target.closest('[data-regen]')) {
                if (edited && !window.confirm('Se perderán los cambios que hiciste clase a clase. ¿Volver a generar desde el patrón?')) { return; }
                rows = fromPattern();
                edited = false;
                renderAll();
            }
        });

        renderAll();

        return {
            /** Llamar cuando cambian los días o la hora por defecto. */
            refresh: function () {
                if (isSame()) { renderSummary(); }
                else if (!edited) { rows = fromPattern(); renderTable(); }
                syncHidden();
            },
            /** Reaplica los disabled (el formulario completo habilita/deshabilita todo el bloque). */
            syncDisabled: function () { syncHidden(); },
            reset: function (startDate, count) {
                elStart.value = startDate || today;
                elCount.value = count || 4;
                elEnd.value = '';
                elSame.checked = true;
                rows = []; edited = false;
                renderAll();
            },
            setCount: function (n) {
                if (!(n >= 1)) { return; }
                elSame.checked = true;
                elCount.value = n;
                rows = []; edited = false;
                renderAll();
            },
            setStart: function (d) { elStart.value = d; renderAll(); },
            /** Fechas que se van a crear (para avisos/cobertura). */
            dates: function () { return isSame() ? fromPattern().map(function (r) { return r.date; }) : rows.map(function (r) { return r.date; }); },
            validate: function () {
                if (isSame()) {
                    if (!days().length) { return 'Elige los días de la semana.'; }
                    if (!elStart.value) { return 'Indica la fecha de la primera clase.'; }
                    var n = parseInt(elCount.value || '0', 10);
                    if (!(n >= 1) && !elEnd.value) { return 'Indica cuántas clases quieres o hasta qué fecha (o las dos cosas).'; }
                    if (n > MAX) { return 'Una serie puede tener como máximo ' + MAX + ' clases.'; }
                    if (elEnd.value && elEnd.value < elStart.value) { return 'La fecha límite no puede ser anterior a la primera clase.'; }
                    if (!fromPattern().length) { return 'Con esos días y fechas no sale ninguna clase.'; }
                    return null;
                }
                if (!rows.length) { return 'Añade al menos una clase.'; }
                var seen = {};
                for (var i = 0; i < rows.length; i++) {
                    var r = rows[i];
                    if (!r.date) { return 'La clase ' + (i + 1) + ' no tiene fecha.'; }
                    if (!r.start) { return 'La clase ' + (i + 1) + ' (' + nice(r.date) + ') no tiene hora de inicio.'; }
                    if (r.end && r.end <= r.start && r.end !== '00:00') { return 'En la clase ' + (i + 1) + ' (' + nice(r.date) + ') la hora de fin debe ser posterior a la de inicio.'; }
                    if (seen[r.date]) { return 'Hay dos clases el mismo día (' + niceFull(r.date) + ').'; }
                    seen[r.date] = true;
                }
                return null;
            },
            /** Añade los campos al FormData (modal rápido y vista previa de cobertura). */
            appendTo: function (fd) {
                if (isSame()) {
                    fd.append('recurrence_start', elStart.value);
                    if (parseInt(elCount.value || '0', 10) >= 1) { fd.append('recurrence_count', elCount.value); }
                    if (elEnd.value) { fd.append('recurrence_end', elEnd.value); }
                } else {
                    fd.append('recurrence_start', rows.length ? rows[0].date : elStart.value);
                    fd.append('custom_schedule', JSON.stringify(rows));
                }
                return fd;
            },
            isCustom: function () { return !isSame(); }
        };
    }

    window.SeriesBuilder = { attach: attach };
})();
