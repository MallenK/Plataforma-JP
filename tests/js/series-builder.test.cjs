/**
 * Test de public/assets/js/series-builder.js (configuración de series recurrentes,
 * TICKET-013): nº de clases, fecha límite, "todas iguales" y calendario clase a clase.
 *
 *   node tests/js/series-builder.test.cjs
 *
 * Sale con 0 si todo pasa, 1 si falla, 2 si no hay jsdom.
 */
'use strict';

const fs = require('fs');
const path = require('path');

let JSDOM;
try { JSDOM = require('jsdom').JSDOM; }
catch (e) { console.error('SKIP: jsdom no disponible (' + e.message + ')'); process.exit(2); }

const SRC = fs.readFileSync(path.join(__dirname, '..', '..', 'public', 'assets', 'js', 'series-builder.js'), 'utf8');

let failed = 0;
function assert(cond, label) {
    console.log((cond ? '  OK   ' : '  FAIL ') + label);
    if (!cond) failed++;
}

function make(days, times) {
    const dom = new JSDOM('<!doctype html><html><body><div id="root"></div></body></html>', { runScripts: 'outside-only' });
    const w = dom.window;
    w.confirm = () => true;
    w.eval(SRC);
    const state = { days: days || [4], times: times || { start: '09:00', end: '10:00' }, changes: 0 };
    const sb = w.SeriesBuilder.attach({
        root: w.document.getElementById('root'),
        getDays: () => state.days,
        getTimes: () => state.times,
        onChange: () => { state.changes++; }
    });
    const $ = k => w.document.querySelector('[data-sb="' + k + '"]');
    const fire = (el, type) => el.dispatchEvent(new w.Event(type, { bubbles: true }));
    return { w, sb, $, fire, state };
}

// ── 1) Por defecto: 4 clases, jueves, desde el jueves 1/10/2026 ──
{
    console.log('Patrón por defecto');
    const { sb, $, fire, w } = make([4]);
    $('start').value = '2026-10-01';
    fire($('start'), 'input');
    assert(JSON.stringify(sb.dates()) === JSON.stringify(['2026-10-01', '2026-10-08', '2026-10-15', '2026-10-22']), '4 jueves: 1, 8, 15 y 22 de octubre');
    assert(sb.validate() === null, 'es válida');
    assert(/Se crearán <strong>4 clases/.test($('summary').innerHTML), 'el resumen dice "Se crearán 4 clases"');
    assert(/jue 22\/10\/2026/.test($('summary').innerHTML), 'el resumen indica la última clase (jue 22/10/2026)');
    const fd = new w.FormData(); sb.appendTo(fd);
    assert(fd.get('recurrence_start') === '2026-10-01' && fd.get('recurrence_count') === '4', 'appendTo envía inicio y nº de clases');
    assert(!fd.has('recurrence_end') && !fd.has('custom_schedule'), 'sin fecha límite ni calendario manual');
}

// ── 2) Fecha límite + nº de clases: gana lo que se cumpla primero ──
{
    console.log('Nº de clases y fecha límite a la vez');
    const { sb, $, fire } = make([4]);
    $('start').value = '2026-10-01';
    $('count').value = '8';
    $('end').value = '2026-10-31';
    fire($('end'), 'input');
    assert(sb.dates().length === 5, 'límite 31/10 llega antes que la 8ª: 5 clases (1, 8, 15, 22, 29)');
    $('count').value = '3';
    fire($('count'), 'input');
    assert(sb.dates().length === 3, 'con 3 clases manda el nº de clases');
    $('count').value = '';
    fire($('count'), 'input');
    assert(sb.dates().length === 5 && sb.validate() === null, 'solo fecha límite también vale');
    $('end').value = '';
    fire($('end'), 'input');
    assert(sb.validate() !== null, 'sin nº de clases ni fecha límite: error claro');
}

// ── 3) Validaciones ──
{
    console.log('Validaciones');
    const { sb, $, fire, state } = make([4]);
    $('start').value = '2026-10-01';
    $('count').value = '99';
    fire($('count'), 'input');
    assert(/máximo 60/.test(sb.validate() || ''), 'más de 60 clases: error');
    $('count').value = '4'; $('end').value = '2026-09-01';
    fire($('end'), 'input');
    assert(/límite no puede ser anterior/.test(sb.validate() || ''), 'límite anterior al inicio: error');
    $('end').value = '';
    state.days = [];
    sb.refresh();
    assert(/días de la semana/.test(sb.validate() || ''), 'sin días de la semana: error');
}

// ── 4) Desmarcar "todas iguales": una a una ──
{
    console.log('Clase a clase');
    const { sb, $, fire, w, state } = make([4]);
    $('start').value = '2026-10-01';
    fire($('start'), 'input');
    $('same').checked = false;
    fire($('same'), 'change');

    const rows = $('table').querySelectorAll('tbody tr');
    assert(rows.length === 4, 'aparece una fila por clase (4)');
    assert($('count').disabled && $('end').disabled, 'el patrón (nº de clases / límite) queda desactivado');
    assert(!$('table').classList.contains('d-none'), 'la tabla se muestra');

    // cambia la 2ª clase: otro día y otra hora
    const r2 = rows[1];
    r2.querySelector('[data-f="date"]').value = '2026-10-09';
    fire(r2.querySelector('[data-f="date"]'), 'input');
    r2.querySelector('[data-f="start"]').value = '18:30';
    fire(r2.querySelector('[data-f="start"]'), 'input');
    r2.querySelector('[data-f="end"]').value = '19:30';
    fire(r2.querySelector('[data-f="end"]'), 'input');

    const sched = JSON.parse($('schedule').value);
    assert(sched.length === 4 && sched[1].date === '2026-10-09' && sched[1].start === '18:30' && sched[1].end === '19:30',
        'el calendario manual recoge día y horas editados');
    assert(sched[0].start === '09:00', 'las demás conservan la hora por defecto');
    assert(sb.validate() === null, 'sigue siendo válido');

    const fd = new w.FormData(); sb.appendTo(fd);
    assert(fd.has('custom_schedule') && !fd.has('recurrence_count'), 'appendTo envía el calendario y no el patrón');

    // dos clases el mismo día → error
    rows[2].querySelector('[data-f="date"]').value = '2026-10-09';
    fire(rows[2].querySelector('[data-f="date"]'), 'input');
    assert(/mismo día/.test(sb.validate() || ''), 'dos clases el mismo día: error');

    // fin antes que inicio → error
    rows[2].querySelector('[data-f="date"]').value = '2026-10-16';
    fire(rows[2].querySelector('[data-f="date"]'), 'input');
    rows[2].querySelector('[data-f="end"]').value = '08:00';
    fire(rows[2].querySelector('[data-f="end"]'), 'input');
    assert(/hora de fin/.test(sb.validate() || ''), 'hora de fin anterior a la de inicio: error');

    // quitar y añadir
    $('table').querySelector('[data-del="3"]').click();
    assert($('table').querySelectorAll('tbody tr').length === 3, 'quitar una clase');
    $('table').querySelector('[data-add]').click();
    assert($('table').querySelectorAll('tbody tr').length === 4, 'añadir una clase');

    // volver a marcar → regenera desde el patrón
    $('same').checked = true;
    fire($('same'), 'change');
    assert($('schedule').value === '' && !$('count').disabled, 'al volver a "todas iguales" se envía el patrón otra vez');
}

// ── 5) Ajustar al bono y cambios de hora ──
{
    console.log('Ajustar al bono / hora por defecto');
    const { sb, $, state, fire } = make([4]);
    $('start').value = '2026-10-01';
    fire($('start'), 'input');
    sb.setCount(2);
    assert($('count').value === '2' && sb.dates().length === 2, 'setCount(2) deja 2 clases');
    state.times = { start: '17:00', end: '18:00' };
    sb.refresh();
    assert(/17:00–18:00/.test($('summary').innerHTML), 'si cambia la hora por defecto, el resumen se actualiza');
}

process.exit(failed ? 1 : 0);
