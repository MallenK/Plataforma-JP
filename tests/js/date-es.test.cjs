/**
 * Test de public/assets/js/date-es.js (fechas DD/MM/AAAA en toda la app) y de externos-list.js.
 *   node tests/js/date-es.test.cjs     (sale con 2 si no hay jsdom)
 */
'use strict';
const fs = require('fs');
const path = require('path');
let JSDOM;
try { JSDOM = require('jsdom').JSDOM; } catch (e) { console.error('SKIP: jsdom no disponible'); process.exit(2); }

const JS = (f) => fs.readFileSync(path.join(__dirname, '..', '..', 'public', 'assets', 'js', f), 'utf8');
let fails = 0;
const ok = (c, m) => { if (!c) { fails++; console.error('FALLO: ' + m); } else { console.log('ok  ' + m); } };

function page(html, scripts) {
    const dom = new JSDOM('<!doctype html><html><body>' + html + '</body></html>', { runScripts: 'outside-only', pretendToBeVisual: true });
    scripts.forEach((s) => dom.window.eval(JS(s)));
    return dom;
}
function type(w, el, text) {
    el.value = text;
    el.dispatchEvent(new w.Event('input', { bubbles: true }));
}

(async () => {
    // ── date-es ─────────────────────────────────────────────
    const dom = page(
        '<form id="f"><label for="d">Fecha</label><input type="date" id="d" name="session_date" class="form-control-jp" required max="2026-12-31" min="2026-01-01" value="2026-11-10">' +
        '<input type="date" id="b" name="birth" data-native-date></form>', ['date-es.js']);
    const w = dom.window, d = w.document;
    await new Promise((r) => setTimeout(r, 0));

    const orig = d.getElementById('d');
    const txt = d.querySelector('.date-es input[type=text]');
    ok(orig.type === 'hidden', 'el input original pasa a hidden (conserva id y name)');
    ok(txt && txt.value === '10/11/2026', 'se muestra DD/MM/AAAA: ' + (txt && txt.value));
    ok(orig.value === '2026-11-10', 'el valor real sigue en ISO');
    ok(d.getElementById('b').type === 'date', 'data-native-date se respeta');
    ok(txt.required === true, 'required pasa al campo visible');

    // máscara al escribir solo dígitos
    type(w, txt, '02052026');
    ok(txt.value === '02/05/2026', 'máscara automática: ' + txt.value);
    ok(orig.value === '2026-05-02', 'al completar la fecha el ISO se actualiza: ' + orig.value);
    ok(txt.validity.valid, 'fecha válida');

    // incompleta
    type(w, txt, '0205');
    ok(orig.value === '' && !txt.validity.valid, 'fecha incompleta: valor vacío y campo inválido');
    // inexistente
    type(w, txt, '31022026');
    ok(orig.value === '' && /no existe/.test(txt.validationMessage), 'fecha inexistente (31/02): ' + txt.validationMessage);
    // fuera de rango
    type(w, txt, '01012027');
    ok(/posterior/.test(txt.validationMessage) && orig.value === '', 'respeta max: ' + txt.validationMessage);
    type(w, txt, '01012025');
    ok(/anterior/.test(txt.validationMessage), 'respeta min: ' + txt.validationMessage);

    // escribir .value desde JS actualiza lo visible
    orig.value = '2026-03-04';
    ok(txt.value === '04/03/2026' && orig.value === '2026-03-04', 'asignar .value desde JS actualiza el campo visible');
    orig.value = '';
    ok(txt.value === '' , 'vaciar .value vacía el campo visible');

    // eventos: change se dispara en el original
    let changes = 0;
    orig.addEventListener('change', () => changes++);
    type(w, txt, '15062026');
    ok(changes === 1, 'se emite change en el input original (1): ' + changes);

    // disabled dinámico
    orig.disabled = true;
    await new Promise((r) => setTimeout(r, 10));
    ok(txt.disabled === true, 'disabled se refleja en el campo visible');

    // label[for] enfoca el campo visible
    orig.disabled = false;
    await new Promise((r) => setTimeout(r, 10));
    ok(txt.disabled === false, 'al reactivar el original se reactiva el visible');
    let focused = false;
    txt.addEventListener('focus', () => { focused = true; });
    d.querySelector('label').dispatchEvent(new w.MouseEvent('click', { bubbles: true }));
    ok(focused, 'clic en la etiqueta enfoca el campo visible');

    // input añadido después (dinámico)
    const late = d.createElement('input'); late.type = 'date'; late.id = 'late'; late.value = '2026-01-02';
    d.getElementById('f').appendChild(late);
    await new Promise((r) => setTimeout(r, 10));
    ok(late.type === 'hidden' && d.querySelectorAll('.date-es').length === 2, 'los inputs de fecha añadidos después también se sustituyen');

    // funciones puras
    ok(w.JPDateEs.es2iso('29/02/2028') === '2028-02-29' && w.JPDateEs.es2iso('29/02/2027') === null, 'bisiestos');
    ok(w.JPDateEs.mask('1') === '1' && w.JPDateEs.mask('123') === '12/3' && w.JPDateEs.mask('12345') === '12/34/5', 'máscara parcial');

    // ── externos-list ───────────────────────────────────────
    const dom2 = page(
        '<form id="f2"><div data-ex-root class="box"><div data-ex-list></div><div data-ex-hidden></div>' +
        '<input data-ex="first_name" id="n"><input data-ex="last_name"><input type="date" data-ex="birth_date" id="bd">' +
        '<select data-ex="method_id"><option value="1">Efectivo</option><option value="2">Bizum</option></select>' +
        '<input type="checkbox" data-ex="paid_now" value="1"><input data-ex="price" id="pr">' +
        '<button type="button" data-ex-save>Guardar persona</button><p data-ex-error hidden></p></div></form>',
        ['date-es.js', 'externos-list.js']);
    const w2 = dom2.window, d2 = w2.document;
    await new Promise((r) => setTimeout(r, 0));
    const root = d2.querySelector('[data-ex-root]');
    const api = w2.ExternosList.attach(root);
    const q = (s) => d2.querySelector(s);

    q('[data-ex-save]').click();
    ok(!q('[data-ex-error]').hidden && api.count() === 0, 'guardar sin nombre avisa y no añade');

    q('#n').value = 'Leo'; q('#pr').value = '25'; q('[data-ex=paid_now]').checked = true;
    q('[data-ex-save]').click();
    ok(api.count() === 1 && q('#n').value === '' && q('[data-ex=paid_now]').checked === false, 'guardar persona: pasa a la lista y limpia el borrador');
    ok(/Guardar y añadir otra/.test(q('[data-ex-save]').textContent), 'el botón invita a añadir otra');
    ok(/Leo/.test(q('[data-ex-list]').textContent) && /25 €/.test(q('[data-ex-list]').textContent) && /cobrado/.test(q('[data-ex-list]').textContent), 'la lista muestra nombre, precio y cobrado');

    q('#n').value = 'Ana';
    q('[data-ex-save]').click();
    ok(api.count() === 2, 'se pueden guardar varias personas');

    // editar: la persona vuelve al borrador
    q('[data-ex-edit="0"]').click();
    ok(q('#n').value === 'Leo' && q('#pr').value === '25' && q('[data-ex=paid_now]').checked, 'editar carga a la persona en el formulario');
    ok(api.count() === 1 && /Guardar cambios/.test(q('[data-ex-save]').textContent), 'mientras se edita sale de la lista y el botón dice «Guardar cambios»');
    q('#n').value = 'Leonel';
    q('[data-ex-save]').click();
    ok(api.count() === 2 && /Leonel/.test(q('[data-ex-list]').textContent), 'guardar cambios devuelve a la persona corregida');

    // quitar
    q('[data-ex-del="0"]').click();
    ok(api.count() === 1, 'quitar elimina de la lista');

    // Enter guarda la persona (no envía el formulario)
    let submitted = false;
    d2.getElementById('f2').addEventListener('submit', (e) => { submitted = true; e.preventDefault(); });
    q('#n').value = 'Marta';
    q('#n').dispatchEvent(new w2.KeyboardEvent('keydown', { key: 'Enter', bubbles: true, cancelable: true }));
    ok(api.count() === 2 && !submitted, 'Enter en un campo guarda la persona sin enviar el formulario');

    // fecha de nacimiento (date-es) dentro del borrador
    q('#bd').value = '2012-05-01';
    q('#n').value = 'Pau';
    const c = api.collect();
    ok(c.people.length === 3 && c.people[2].birth_date === '2012-05-01', 'el borrador con nombre se incluye al enviar y la fecha viaja en ISO');

    // envío: genera inputs externos[i][campo]
    d2.getElementById('f2').dispatchEvent(new w2.Event('submit', { cancelable: true }));
    const names = [...d2.querySelectorAll('[data-ex-hidden] input')].map((i) => i.name);
    ok(names.includes('externos[0][first_name]') && names.includes('externos[2][first_name]') && names.includes('externos[2][birth_date]'), 'al enviar se generan los externos[i][campo]');

    // borrador sin nombre pero con datos → bloquea
    d2.querySelector('[data-ex-hidden]').innerHTML = '';
    q('#n').value = ''; q('[data-ex=last_name]').value = 'Solo apellido';
    const ev = new w2.Event('submit', { cancelable: true });
    d2.getElementById('f2').dispatchEvent(ev);
    ok(ev.defaultPrevented, 'un borrador con datos pero sin nombre bloquea el envío');

    // modo «alumno registrado» (root oculto): no se envía nada
    q('[data-ex-error]').hidden = true;
    q('[data-ex=last_name]').value = '';
    root.classList.add('d-none');
    d2.querySelector('[data-ex-hidden]').innerHTML = '';
    d2.getElementById('f2').dispatchEvent(new w2.Event('submit', { cancelable: true }));
    ok(d2.querySelectorAll('[data-ex-hidden] input').length === 0, 'en modo alumno registrado no se envía ningún externo');

    // ── Cambio de pestaña: nunca se descarta nada sin preguntar ──
    const dom3 = page('<div data-ex-root><div data-ex-list></div><input data-ex="first_name"><button type="button" data-ex-save>x</button></div>', ['externos-list.js']);
    const w3 = dom3.window, d3 = w3.document;
    const root3 = d3.querySelector('[data-ex-root]');
    ok(w3.ExternosList.pendingCount(root3) === 0, 'sin personas: nada que descartar');
    d3.querySelector('input').value = 'Leo';
    ok(w3.ExternosList.pendingCount(root3) === 1, 'una persona a medio escribir cuenta como pendiente');
    d3.querySelector('[data-ex-save]').click();
    d3.querySelector('input').value = 'Ana';
    ok(w3.ExternosList.pendingCount(root3) === 2, 'guardada + a medio escribir = 2 pendientes');
    let asked = null;
    w3.RadixUI = { confirm: (o) => { asked = o; return Promise.resolve(false); } };
    const res = await w3.ExternosList.confirmDiscard({ title: 'T', description: 'D', confirmLabel: 'Quitar', cancelLabel: 'Mantener' });
    ok(res === false && asked && asked.danger === true && asked.cancelLabel === 'Mantener', 'confirmDiscard usa el diálogo de la plataforma y respeta «Volver atrás»');
    w3.ExternosList.attach(root3).reset();
    ok(w3.ExternosList.pendingCount(root3) === 0, 'tras confirmar, reset deja la lista vacía');

    console.log(fails ? '\n' + fails + ' FALLO(S)' : '\nTodo OK');
    process.exit(fails ? 1 : 0);
})();
