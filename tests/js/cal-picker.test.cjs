/**
 * Test de public/assets/js/cal-picker.js (modal «Elige qué clase quieres ver»).
 *   node tests/js/cal-picker.test.cjs     (requiere jsdom; sale con 2 si no está)
 */
'use strict';
const fs = require('fs');
const path = require('path');

let JSDOM;
try { JSDOM = require('jsdom').JSDOM; } catch (e) { console.error('SKIP: jsdom no disponible'); process.exit(2); }

const SRC = fs.readFileSync(path.join(__dirname, '..', '..', 'public', 'assets', 'js', 'cal-picker.js'), 'utf8');
let failed = 0;
const ok = (c, l) => { console.log((c ? '  OK   ' : '  FAIL ') + l); if (!c) failed++; };

const EVTS = [
    { id: 1, title: 'Tecnificación', start: '16:00', end: '17:00', status: 'scheduled', alert_level: 'pending', color: '#3b82f6', player_label: 'José Álvarez', responsable_name: 'Marta Soler' },
    { id: 2, title: 'Tecnificación', start: '18:30', end: '19:30', status: 'completed', alert_level: 'ok', color: '#10b981', player_label: 'Leo Martínez', responsable_name: 'Joan Puig' },
    { id: 3, title: 'Porteros', start: '17:00', end: '18:00', status: 'scheduled', alert_level: 'warn', alert_reason: 'Sin entrenador asignado', color: '#f59e0b', player_label: 'Hugo García', responsable_name: null },
    { id: 4, title: 'Individual', start: '19:00', end: '20:00', status: 'cancelled', alert_level: 'cancelled', color: '#ef4444', player_label: 'Biel Roca', responsable_name: 'Marta Soler' },
    // alumno: el servidor no manda alert_level, solo status
    { id: 5, title: 'Mi clase', start: '10:00', end: '11:00', status: 'completed', color: '#10b981' }
];

const dom = new JSDOM('<!doctype html><body></body>', { url: 'https://app.test/clases', runScripts: 'outside-only', pretendToBeVisual: true });
const w = dom.window, d = w.document;
w.matchMedia = () => ({ matches: true });
w.eval(SRC);
const P = w.CalPicker;
const ids = () => [].map.call(d.querySelectorAll('.cpk-row'), (a) => +a.getAttribute('href').split('/').pop());
const type = (v) => { const i = d.querySelector('.cpk-search input'); i.value = v; i.dispatchEvent(new w.Event('input', { bubbles: true })); };
const click = (el) => el.dispatchEvent(new w.MouseEvent('click', { bubbles: true }));

P.show('Lunes 16 nov', EVTS);
ok(!!d.getElementById('cal-picker'), 'se abre el modal');
ok(ids().join() === [4, 2, 3, 1, 5].join(), 'por defecto: hora DESCENDENTE (19:00, 18:30, 17:00, 16:00, 10:00)');
click(d.querySelector('.cpk-sort'));
ok(ids().join() === [5, 1, 3, 2, 4].join(), 'un clic en «Hora» invierte el orden (ascendente)');
click(d.querySelector('.cpk-sort'));

// estados
const chips = [].map.call(d.querySelectorAll('.cpk-chip'), (c) => c.getAttribute('data-state'));
ok(chips.join() === 'all,pending,ok,warn,cancelled', 'filtros: todas + los estados que hay, en orden fijo');
ok(d.querySelector('.cpk-chip[data-state="ok"] b').textContent === '2', 'el alumno (sin alert_level) cuenta por status: «Lista pasada» = 2');
click(d.querySelector('.cpk-chip[data-state="cancelled"]'));
ok(ids().join() === '4', 'filtro Cancelada → solo la cancelada');
ok(d.querySelector('.cpk-row').classList.contains('is-cancelled'), 'la cancelada se marca en rojo (is-cancelled)');
click(d.querySelector('.cpk-chip[data-state="all"]'));
ok(ids().length === 5, 'Todas → vuelven las 5');

// búsqueda
type('jose');
ok(ids().join() === '1', 'busca sin distinguir acentos: «jose» encuentra «José»');
type('marta');
ok(ids().join() === [4, 1].join(), 'busca por entrenador (y respeta el orden)');
type('marta cancel');
ok(ids().join() === '4', 'varias palabras = todas deben coincidir (entrenador + estado)');
type('17:00');
ok(ids().includes(3) && ids().includes(1), 'busca por hora (inicio o fin)');
type('zzz');
ok(d.querySelector('.cal-picker-empty') && /coincide/.test(d.querySelector('.cal-picker-empty').textContent), 'sin resultados: mensaje claro');
type('');

// combinar filtro + búsqueda
click(d.querySelector('.cpk-chip[data-state="warn"]'));
type('hugo');
ok(ids().join() === '3', 'filtro + búsqueda se combinan');
ok(/Sin entrenador/.test(d.querySelector('.cpk-row').textContent), 'sin responsable se dice «Sin entrenador»');
type(''); click(d.querySelector('.cpk-chip[data-state="all"]'));

// inyección: los textos se escapan
P.show('x', [{ id: 9, title: '<img src=x onerror=alert(1)>', start: '10:00', status: 'scheduled', player_label: '<b>Hola</b>', responsable_name: '"><script>1</script>' }]);
ok(!d.querySelector('.cpk-row img') && !d.querySelector('.cpk-row b') && !d.querySelector('.cpk-row script'), 'XSS: nombres y títulos se escapan');

// cerrar
P.show('x', EVTS);
d.dispatchEvent(new w.KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
ok(!d.getElementById('cal-picker'), 'Esc cierra');
P.show('x', EVTS);
click(d.getElementById('cal-picker'));
ok(!d.getElementById('cal-picker'), 'clic fuera cierra');
ok(d.body.style.overflow === '', 'al cerrar se libera el scroll de la página');
P.show('x', []);
ok(/No hay clases/.test(d.querySelector('.cal-picker-empty').textContent), 'sin clases: «No hay clases»');

console.log(failed ? '\n' + failed + ' FALLO(S)' : '\nTodo OK');
process.exit(failed ? 1 : 0);
