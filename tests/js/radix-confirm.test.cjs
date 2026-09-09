/**
 * Test funcional de public/assets/js/radix-ui.js — el diálogo de confirmación
 * de la plataforma que sustituye a confirm()/alert() nativos en Clases.
 *
 * Requiere Node + jsdom. Se ejecuta:
 *   node tests/js/radix-confirm.test.cjs
 * o desde PHPUnit vía ClasesAlertasTest::testDialogoDeConfirmacionFunciona
 * (que hace markTestSkipped si no hay node/jsdom).
 *
 * Sale con código 0 si todo pasa, 1 si algo falla.
 */
'use strict';

const fs = require('fs');
const path = require('path');

let JSDOM;
try {
    // jsdom puede estar en el propio repo o en un node_modules temporal
    // (NODE_PATH). Si no está, avisamos y salimos con 2 (=> skip en PHPUnit).
    JSDOM = require('jsdom').JSDOM;
} catch (e) {
    console.error('SKIP: jsdom no disponible (' + e.message + ')');
    process.exit(2);
}

const RADIX = fs.readFileSync(
    path.join(__dirname, '..', '..', 'public', 'assets', 'js', 'radix-ui.js'),
    'utf8'
);

let failed = 0;
function assert(cond, label) {
    console.log((cond ? '  OK   ' : '  FAIL ') + label);
    if (!cond) failed++;
}

function makeDom(bodyHtml) {
    const dom = new JSDOM(
        '<!doctype html><html><body>' + (bodyHtml || '') + '</body></html>',
        { runScripts: 'dangerously', pretendToBeVisual: true }
    );
    const { window } = dom;
    // jsdom no calcula layout: offsetParent es null para todo. radix-ui.js
    // filtra los enfocables por offsetParent !== null, así que lo forzamos.
    Object.defineProperty(window.HTMLElement.prototype, 'offsetParent', {
        get() { return this.parentNode; },
        configurable: true,
    });
    window.eval(RADIX);
    // radix-ui.js engancha su init en DOMContentLoaded; ya ha pasado, lo llamamos.
    window.document.dispatchEvent(new window.Event('DOMContentLoaded', { bubbles: true }));
    return window;
}

function tick() { return new Promise((r) => setTimeout(r, 0)); }

async function main() {
    // ── 1. RadixUI.confirm existe y devuelve una promesa ──────────────
    let w = makeDom();
    assert(w.RadixUI && typeof w.RadixUI.confirm === 'function', 'RadixUI.confirm es una función');

    // ── 2. Confirmar → resuelve true; se pinta un diálogo con .btn-jp ─
    w = makeDom();
    let p = w.RadixUI.confirm({ title: '¿Descontar 1 sesión del bono?', description: 'Podrás devolverla.', confirmLabel: 'Descontar' });
    await tick();
    let overlay = w.document.querySelector('.ru-overlay');
    assert(!!overlay, 'se crea .ru-overlay al abrir el confirm');
    assert(overlay.querySelector('h3').textContent === '¿Descontar 1 sesión del bono?', 'el título es el pasado');
    assert(overlay.querySelector('.ru-confirm-desc').textContent === 'Podrás devolverla.', 'la descripción es la pasada');
    assert(overlay.querySelector('[data-role="confirm"]').textContent === 'Descontar', 'el botón usa confirmLabel');
    assert(overlay.querySelector('[data-role="confirm"]').classList.contains('btn-jp'), 'el botón confirmar es .btn-jp (estética plataforma)');
    assert(w.document.body.style.overflow === 'hidden', 'bloquea el scroll del body mientras está abierto');
    overlay.querySelector('[data-role="confirm"]').click();
    assert((await p) === true, 'clic en Confirmar → la promesa resuelve true');
    await tick();
    assert(!w.document.querySelector('.ru-overlay'), 'el diálogo se elimina del DOM al cerrarse');
    assert(w.document.body.style.overflow === '', 'restaura el scroll del body');

    // ── 3. Cancelar → resuelve false ─────────────────────────────────
    w = makeDom();
    p = w.RadixUI.confirm({ title: 'x' });
    await tick();
    w.document.querySelector('[data-role="cancel"]').click();
    assert((await p) === false, 'clic en Cancelar → resuelve false');

    // ── 4. Escape → resuelve false ──────────────────────────────────
    w = makeDom();
    p = w.RadixUI.confirm({ title: 'x' });
    await tick();
    // keydown sobre un elemento real dentro del diálogo (como en un navegador).
    w.document.querySelector('[data-role="confirm"]')
        .dispatchEvent(new w.KeyboardEvent('keydown', { key: 'Escape', bubbles: true }));
    assert((await p) === false, 'Escape → resuelve false');

    // ── 5. danger:true → botón .btn-jp-danger ───────────────────────
    w = makeDom();
    w.RadixUI.confirm({ title: 'x', danger: true });
    await tick();
    assert(w.document.querySelector('[data-role="confirm"]').classList.contains('btn-jp-danger'), 'danger:true → botón .btn-jp-danger');

    // ── 6. Formulario declarativo: cancelar NO envía, confirmar SÍ ──
    w = makeDom(
        '<form id="f" data-ru-confirm="¿Eliminar esta sesión permanentemente?"' +
        ' data-ru-confirm-desc="No se puede deshacer." data-ru-confirm-label="Eliminar" data-ru-confirm-danger>' +
        '  <button type="submit">Eliminar</button>' +
        '</form>'
    );
    let submitted = 0;
    const f = w.document.getElementById('f');
    f.submit = function () { submitted++; };   // jsdom no navega; contamos llamadas

    f.querySelector('button').click();          // dispara submit → interceptado
    await tick();
    let ov = w.document.querySelector('.ru-overlay');
    assert(!!ov, 'submit de <form data-ru-confirm> abre el diálogo');
    assert(ov.querySelector('h3').textContent === '¿Eliminar esta sesión permanentemente?', 'toma el título de data-ru-confirm');
    assert(submitted === 0, 'no se envía el formulario mientras el diálogo está abierto');
    ov.querySelector('[data-role="cancel"]').click();
    await tick();
    assert(submitted === 0, 'Cancelar → el formulario NO se envía');

    f.querySelector('button').click();
    await tick();
    w.document.querySelector('[data-role="confirm"]').click();
    await tick();
    assert(submitted === 1, 'Confirmar → el formulario se envía una vez');

    console.log(failed === 0 ? '\n>>> TODO OK' : '\n>>> ' + failed + ' FALLOS');
    process.exit(failed === 0 ? 0 : 1);
}

main().catch((e) => { console.error(e); process.exit(1); });
