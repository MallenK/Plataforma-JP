/**
 * Test de public/assets/js/money-privacy.js (difuminado de importes, admin/superadmin).
 *   node tests/js/money-privacy.test.cjs     (sale con 2 si no hay jsdom)
 */
'use strict';
const fs = require('fs');
const path = require('path');
let JSDOM;
try { JSDOM = require('jsdom').JSDOM; } catch (e) { console.error('SKIP: jsdom no disponible'); process.exit(2); }

const SRC = fs.readFileSync(path.join(__dirname, '..', '..', 'public', 'assets', 'js', 'money-privacy.js'), 'utf8');
let fails = 0;
const ok = (c, m) => { if (!c) { fails++; console.error('FALLO: ' + m); } else { console.log('ok  ' + m); } };

async function page(html) {
    const dom = new JSDOM('<!doctype html><html><body><button id="money-toggle-all" hidden><i class="bi bi-eye-slash"></i></button><div class="page-body">' + html + '</div></body></html>',
        { runScripts: 'outside-only', pretendToBeVisual: true });
    dom.window.eval(SRC);
    if (dom.window.document.readyState !== 'complete') await new Promise((r) => dom.window.addEventListener('load', r));
    await new Promise((r) => setTimeout(r, 0));
    return dom;
}
const tick = (w) => new Promise((r) => w.requestAnimationFrame(() => setTimeout(r, 0)));

(async () => {
    const dom = await page(
        '<div class="metric-value" id="kpi">Vendido 1.234,50&nbsp;€ y 225.00 €</div>' +
        '<table><tbody><tr><td id="cell">−35,00 €</td><td id="nomoney">12 sesiones · 10:00</td></tr></tbody></table>' +
        '<textarea id="ta">99,00 €</textarea><select><option id="opt">Bono 50,00 €</option></select><span title="vendido 10,00 €" id="bar">x</span>');
    const w = dom.window, d = w.document;
    ok(w.JPMoney.hasMoney('1.234,50 €') && w.JPMoney.hasMoney('225.00€') && !w.JPMoney.hasMoney('12 sesiones'), 'detecta importes y no otros números');

    const spans = d.querySelectorAll('.jp-money');
    ok(spans.length === 3, 'envuelve las 3 cifras (2 en la tarjeta, 1 en la tabla)');
    ok(d.documentElement.getAttribute('data-money-hidden') === '1', 'empiezan ocultas');
    ok(d.querySelector('#kpi').querySelectorAll('.jp-money-eye').length === 2, 'ojo junto a cada cifra suelta');
    ok(d.querySelector('#cell').querySelectorAll('.jp-money-eye').length === 0, 'sin ojo dentro de la tabla');
    ok(d.querySelector('#ta').textContent === '99,00 €' && !d.querySelector('#ta .jp-money'), 'no toca textareas');
    ok(!d.querySelector('#opt .jp-money'), 'no toca opciones de select');
    ok(d.querySelector('#nomoney').textContent === '12 sesiones · 10:00', 'no toca texto sin importes');
    ok(!d.querySelector('#bar').hasAttribute('title') && d.querySelector('#bar').getAttribute('data-money-title'), 'quita el title con importes mientras están ocultos');
    ok(d.getElementById('money-toggle-all').hidden === false, 'el botón general aparece si hay importes');

    const first = spans[0], eye = d.querySelector('#kpi .jp-money-eye');
    ok(w.JPMoney.isHiddenNow(first), 'la primera cifra está difuminada');
    eye.click();
    ok(!w.JPMoney.isHiddenNow(first) && w.JPMoney.isHiddenNow(spans[1]), 'el ojo muestra solo su cifra');
    eye.click();
    ok(w.JPMoney.isHiddenNow(first), 'el ojo vuelve a ocultarla');

    d.querySelector('#cell .jp-money').click();
    ok(!w.JPMoney.isHiddenNow(d.querySelector('#cell .jp-money')), 'clic en una cifra de tabla la muestra');

    d.getElementById('money-toggle-all').click();
    ok(d.documentElement.getAttribute('data-money-hidden') === '0' && [...spans].every((s) => !w.JPMoney.isHiddenNow(s)), 'botón general muestra todas');
    ok(d.querySelector('#bar').getAttribute('title') === 'vendido 10,00 €', 'restaura el title al mostrar');
    eye.click();
    ok(w.JPMoney.isHiddenNow(first) && !w.JPMoney.isHiddenNow(spans[1]), 'con todo visible, el ojo oculta solo una');
    d.getElementById('money-toggle-all').click();
    ok(d.documentElement.getAttribute('data-money-hidden') === '1' && [...spans].every((s) => w.JPMoney.isHiddenNow(s)), 'botón general vuelve a ocultar todas y limpia ajustes individuales');

    // Contenido nuevo (DataTables redibuja)
    d.querySelector('tbody').insertAdjacentHTML('beforeend', '<tr><td id="late">500,00 €</td></tr>');
    await tick(w);
    ok(d.querySelector('#late .jp-money') && w.JPMoney.isHiddenNow(d.querySelector('#late .jp-money')), 'filas añadidas después también se difuminan');

    const empty = await page('<p>Sin importes aquí</p>');
    ok(empty.window.document.getElementById('money-toggle-all').hidden === true, 'sin importes el botón general sigue oculto');

    if (fails) { console.error(fails + ' fallo(s)'); process.exit(1); }
    console.log('Todo OK');
})();
