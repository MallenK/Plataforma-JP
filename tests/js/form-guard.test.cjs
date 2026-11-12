/**
 * Test de public/assets/js/form-guard.js (botones «blandos» que guían al usuario).
 *   node tests/js/form-guard.test.cjs     (sale con 2 si no hay jsdom)
 */
'use strict';
const fs = require('fs');
const path = require('path');
let JSDOM;
try { JSDOM = require('jsdom').JSDOM; } catch (e) { console.error('SKIP: jsdom no disponible'); process.exit(2); }

const SRC = fs.readFileSync(path.join(__dirname, '..', '..', 'public', 'assets', 'js', 'form-guard.js'), 'utf8');
let fails = 0;
const ok = (c, m) => { if (!c) { fails++; console.error('FALLO: ' + m); } else { console.log('ok  ' + m); } };
const tick = (w, ms = 40) => new Promise((r) => setTimeout(r, ms));

function page(html) {
    const dom = new JSDOM('<!doctype html><html><body>' + html + '</body></html>', { runScripts: 'outside-only', pretendToBeVisual: true });
    dom.window.eval(SRC);
    return dom;
}
const type = (w, el, v) => { el.value = v; el.dispatchEvent(new w.Event('input', { bubbles: true })); };

(async () => {
    // ── reglas puras: importes y descuentos (espejo del servidor) ──
    {
        const w = page('').window, G = w.JPGuard;
        ok(G.parseEuroToCents('25') === 2500 && G.parseEuroToCents('25,50') === 2550 && G.parseEuroToCents('1.225,50') === 122550 && G.parseEuroToCents(' 25 € ') === 2500, 'importes: 25 · 25,50 · 1.225,50 · «25 €»');
        ok(G.parseEuroToCents('') === null && G.parseEuroToCents('abc') === null && G.parseEuroToCents('2,555') === null && G.parseEuroToCents('-5') === null, 'importes inválidos → null');
        ok(G.moneyValid('0', false) === false && G.moneyValid('0', true) === true, 'importe 0 solo vale si se permite');
        ok(G.moneyValid('30', false, 2500) === false && G.moneyValid('25', false, 2500) === true, 'importe por encima del máximo no vale');
        ok(G.discountValid('') && G.discountValid('10') && G.discountValid('10,5') && G.discountValid('10%') && !G.discountValid('150%') && !G.discountValid('abc'), 'descuento: vacío, importe y % válidos; 150 % y texto no');
    }

    // ── paridad con el servidor: los mismos casos que tests/unit/FormGuardTest.php ──
    {
        const w = page('').window, G = w.JPGuard;
        const cases = JSON.parse(fs.readFileSync(path.join(__dirname, '..', 'fixtures', 'money-cases.json'), 'utf8'));
        const bad = cases.filter(([input, expected]) => G.parseEuroToCents(input) !== expected);
        ok(bad.length === 0, 'importes: el JS coincide con el servidor en los ' + cases.length + ' casos' + (bad.length ? ' — difieren: ' + JSON.stringify(bad) : ''));
    }

    // ── formulario declarativo ──
    const dom = page(
        '<form id="f" data-guard action="/x" method="post">' +
        '<label for="n">Nombre *</label><input id="n" name="name" required>' +
        '<label for="e">Email</label><input id="e" name="email" type="email" required>' +
        '<input id="p" name="price" data-guard-money data-guard-max="2500" data-guard-label="Importe">' +
        '<details id="d"><summary>más</summary><input id="m" name="motivo" required data-guard-label="Motivo"></details>' +
        '<div class="row"><button id="b" type="submit">Guardar</button><button id="c" type="button" data-guard-ignore>Cancelar</button></div>' +
        '</form>');
    const w = dom.window, d = w.document;
    const form = d.getElementById('f'), btn = d.getElementById('b'), cancel = d.getElementById('c');
    await tick(w);
    const hint = () => d.querySelector('.jpg-hint');
    ok(btn.getAttribute('aria-disabled') === 'true' && btn.classList.contains('jpg-soft-disabled'), 'sin rellenar: el botón se ve apagado (aria-disabled), no deshabilitado de verdad');
    ok(btn.disabled === false, 'el botón NO usa disabled (sigue recibiendo el clic para guiar)');
    ok(/Falta: nombre, email, motivo\./.test(hint().textContent), 'la línea dice qué falta: ' + hint().textContent);
    ok(cancel.getAttribute('aria-disabled') === null, 'Cancelar nunca se apaga');
    ok(hint().previousElementSibling === btn.parentElement, 'la línea queda debajo de la fila de botones');
    ok(btn.getAttribute('title') === hint().textContent.replace(/^\s*/, '') || /Falta/.test(btn.getAttribute('title')), 'tooltip con lo que falta');

    // clic en el botón apagado: no envía, lleva al primer campo que falta y despliega la sección
    let submitted = 0, clicked = 0;
    form.addEventListener('submit', (e) => { submitted++; e.preventDefault(); });
    btn.addEventListener('click', () => { clicked++; });
    btn.click();
    ok(clicked === 0 && submitted === 0, 'clic con datos incompletos: no se ejecuta el botón ni se envía');
    ok(d.activeElement === d.getElementById('n') && d.getElementById('n').classList.contains('jpg-flash'), 'lleva al primer campo que falta y lo resalta');
    // Enter dentro del formulario también se frena
    form.dispatchEvent(new w.Event('submit', { bubbles: true, cancelable: true }));
    ok(submitted === 0, 'Enter (submit) también se frena');

    // se va completando
    type(w, d.getElementById('n'), 'Leo'); await tick(w);
    ok(/Falta: email, motivo\./.test(hint().textContent), 'al rellenar el nombre deja de pedirlo: ' + hint().textContent);
    type(w, d.getElementById('e'), 'no-es-email'); await tick(w);
    ok(/email/.test(hint().textContent), 'un email mal escrito sigue contando como pendiente');
    type(w, d.getElementById('e'), 'leo@correo.com'); await tick(w);
    // el motivo está dentro de un <details> cerrado: el clic lo despliega
    btn.click();
    ok(d.getElementById('d').open === true && d.activeElement === d.getElementById('m'), 'si lo que falta está en una sección plegada, la despliega y enfoca');
    type(w, d.getElementById('m'), 'porque sí'); await tick(w);
    ok(btn.getAttribute('aria-disabled') === null && !btn.classList.contains('jpg-soft-disabled') && hint().hidden, 'todo completo: el botón se enciende y la línea desaparece');
    ok(btn.getAttribute('title') === null, 'se retira el tooltip');
    btn.click();
    ok(clicked === 1, 'con todo completo el clic llega al botón');

    // importes: máximo
    type(w, d.getElementById('p'), '30'); await tick(w);
    ok(btn.getAttribute('aria-disabled') === 'true' && /importe/.test(hint().textContent), 'importe por encima del máximo (25 €) vuelve a apagar el botón');
    type(w, d.getElementById('p'), '25,00'); await tick(w);
    ok(btn.getAttribute('aria-disabled') === null, 'importe dentro del máximo: se enciende');
    type(w, d.getElementById('p'), ''); await tick(w);
    ok(btn.getAttribute('aria-disabled') === null, 'importe opcional vacío: vale');

    // ── exigir cambios (editar) ──
    const dom2 = page('<form id="f" data-guard data-guard-changes><input id="t" name="title" value="Clase" required><button id="b" type="submit">Guardar cambios</button></form>');
    const w2 = dom2.window, d2 = w2.document;
    await tick(w2);
    ok(d2.getElementById('b').getAttribute('aria-disabled') === 'true' && /Aún no has cambiado nada/.test(d2.querySelector('.jpg-hint').textContent), 'editar: sin cambios el botón está apagado y lo explica');
    type(w2, d2.getElementById('t'), 'Clase B'); await tick(w2);
    ok(d2.getElementById('b').getAttribute('aria-disabled') === null, 'editar: al cambiar algo se enciende');
    type(w2, d2.getElementById('t'), 'Clase'); await tick(w2);
    ok(d2.getElementById('b').getAttribute('aria-disabled') === 'true', 'editar: si vuelve al valor original se apaga otra vez');

    // ── API programática: reglas propias, no bloqueante ──
    const dom3 = page('<div id="box"><input id="a"><button id="b" type="button">Crear</button></div>');
    const w3 = dom3.window, d3 = w3.document;
    let n = 0; d3.getElementById('b').addEventListener('click', () => n++);
    const g = w3.JPGuard.attach({ root: d3.getElementById('box'), buttons: [d3.getElementById('b')], rules: [
        { label: 'un entrenador', ok: () => d3.getElementById('a').value.length > 0, focus: () => d3.getElementById('a') },
        { label: 'una fecha', ok: () => false, focus: () => null },
    ] });
    await tick(w3);
    ok(/un entrenador, una fecha/.test(d3.querySelector('.jpg-hint').textContent), 'reglas propias: lista lo que falta');
    d3.getElementById('b').click();
    ok(n === 0, 'reglas propias: el botón apagado no ejecuta su acción (aunque sea un type=button con JS)');
    type(w3, d3.getElementById('a'), 'x'); await tick(w3);
    ok(/una fecha/.test(d3.querySelector('.jpg-hint').textContent) && !/entrenador/.test(d3.querySelector('.jpg-hint').textContent), 'se actualiza al cumplir una regla');

    const dom4 = page('<div id="box"><button id="b" type="button">Guardar y cerrar</button></div>');
    const w4 = dom4.window, d4 = w4.document;
    let n4 = 0; d4.getElementById('b').addEventListener('click', () => n4++);
    w4.JPGuard.attach({ root: d4.getElementById('box'), buttons: [d4.getElementById('b')], rules: [], blocking: false, info: () => '3 sin marcar · se puede cerrar igualmente' });
    await tick(w4);
    d4.getElementById('b').click();
    ok(n4 === 1 && d4.getElementById('b').getAttribute('aria-disabled') === null, 'no bloqueante: solo informa, el botón funciona');
    ok(/3 sin marcar/.test(d4.querySelector('.jpg-hint').textContent), 'no bloqueante: muestra el aviso informativo');

    // observa cambios del DOM (p. ej. se añade un alumno a la lista)
    const dom5 = page('<div id="box"><ul id="list"></ul><button id="b" type="button">Crear</button></div>');
    const w5 = dom5.window, d5 = w5.document;
    w5.JPGuard.attach({ root: d5.getElementById('box'), buttons: [d5.getElementById('b')], rules: [{ label: 'un alumno', ok: () => d5.querySelectorAll('#list li').length > 0, focus: () => null }] });
    await tick(w5);
    ok(d5.getElementById('b').getAttribute('aria-disabled') === 'true', 'DOM dinámico: sin alumnos, apagado');
    d5.getElementById('list').appendChild(d5.createElement('li'));
    await tick(w5, 80);
    ok(d5.getElementById('b').getAttribute('aria-disabled') === null, 'DOM dinámico: al añadir un alumno a la lista se enciende solo');

    // ── cobro: máximo = lo pendiente; precio: mínimo = lo ya cobrado; «ya pagado» exige un precio > 0 ──
    const dom6 = page(
        '<form id="cobro" data-guard data-guard-hint="after"><input id="amt" name="amount" required data-guard-money data-guard-max="2000" aria-label="Importe cobrado"><button id="bc" type="submit">Cobrar</button></form>' +
        '<form id="precio" data-guard><label for="pr">Precio *</label><input id="pr" name="price" required data-guard-money="zero" data-guard-min="1000"><label><input id="pn" type="checkbox" name="paid_now" data-guard-needs="#pr"> Ya lo ha pagado</label><button id="bp" type="submit">Guardar</button></form>' +
        '<form id="anular" data-guard data-guard-hint="none"><input id="rs" name="reason" required aria-label="Motivo"><button id="ba" type="submit">Anular</button></form>');
    const w6 = dom6.window, d6 = w6.document;
    await tick(w6);
    ok(d6.getElementById('cobro').nextElementSibling.classList.contains('jpg-hint'), 'data-guard-hint=after: la línea va debajo del formulario (no dentro de la fila)');
    type(w6, d6.getElementById('amt'), '25'); await tick(w6);
    ok(d6.getElementById('bc').getAttribute('aria-disabled') === 'true', 'cobro: 25 € con 20 € pendientes → apagado');
    type(w6, d6.getElementById('amt'), '20'); await tick(w6);
    ok(d6.getElementById('bc').getAttribute('aria-disabled') === null, 'cobro: 20 € (todo lo pendiente) → enciende');
    type(w6, d6.getElementById('pr'), '5'); await tick(w6);
    ok(d6.getElementById('bp').getAttribute('aria-disabled') === 'true', 'precio: 5 € por debajo de lo ya cobrado (10 €) → apagado');
    type(w6, d6.getElementById('pr'), '10'); await tick(w6);
    ok(d6.getElementById('bp').getAttribute('aria-disabled') === null, 'precio: 10 € → enciende');
    type(w6, d6.getElementById('pr'), '0');
    d6.getElementById('pn').checked = true; d6.getElementById('pn').dispatchEvent(new w6.Event('change', { bubbles: true })); await tick(w6);
    ok(d6.getElementById('bp').getAttribute('aria-disabled') === 'true', 'precio 0 + «ya lo ha pagado» → apagado');
    ok(/mayor que 0/.test(d6.getElementById('bp').getAttribute('title') || ''), 'explica que hace falta un importe mayor que 0: ' + d6.getElementById('bp').getAttribute('title'));
    ok(d6.getElementById('ba').getAttribute('aria-disabled') === 'true' && !d6.querySelector('#anular + .jpg-hint') && /motivo/i.test(d6.getElementById('ba').getAttribute('title')), 'data-guard-hint=none: sin línea, pero con tooltip «Falta: motivo.»');

    console.log(fails ? '\n' + fails + ' FALLO(S)' : '\nTodo OK');
    process.exit(fails ? 1 : 0);
})();
