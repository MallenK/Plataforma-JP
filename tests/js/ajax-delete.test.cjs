/**
 * Test funcional de public/assets/js/ajax-delete.js — borrado dinámico con
 * animación (adjuntos de clase, y los que vengan después).
 *
 *   node tests/js/ajax-delete.test.cjs
 *
 * Sale con 0 si todo pasa, 1 si falla, 2 si no hay jsdom.
 */
'use strict';

const fs = require('fs');
const path = require('path');

let JSDOM;
try { JSDOM = require('jsdom').JSDOM; }
catch (e) { console.error('SKIP: jsdom no disponible (' + e.message + ')'); process.exit(2); }

const JS = (f) => fs.readFileSync(path.join(__dirname, '..', '..', 'public', 'assets', 'js', f), 'utf8');
const RADIX = JS('radix-ui.js');
const AJAXDEL = JS('ajax-delete.js');

let failed = 0;
function assert(cond, label) {
    console.log((cond ? '  OK   ' : '  FAIL ') + label);
    if (!cond) failed++;
}
const wait = (ms) => new Promise((r) => setTimeout(r, ms));

const CHIP = (id) =>
    '<span class="cs-attach-chip" id="chip' + id + '"><a href="#">f' + id + '</a>' +
    '<form id="f' + id + '" action="/clases/adjuntos/' + id + '/eliminar" method="POST" data-ajax-delete ' +
    'data-remove-target=".cs-attach-chip" data-ru-confirm="¿Eliminar?" data-ru-confirm-label="Eliminar" data-ru-confirm-danger>' +
    '<button type="submit">x</button></form></span>';

function makeDom(body, fetchImpl) {
    const dom = new JSDOM('<!doctype html><html><body>' + body + '</body></html>',
        { runScripts: 'dangerously', pretendToBeVisual: true });
    const w = dom.window;
    Object.defineProperty(w.HTMLElement.prototype, 'offsetParent', { get() { return this.parentNode; }, configurable: true });
    w.matchMedia = () => ({ matches: true });          // sin animación: borrado inmediato
    w.fetch = fetchImpl;
    w.toasts = [];
    w.showAlert = (m, t) => w.toasts.push([m, t]);
    w.eval(RADIX);
    w.eval(AJAXDEL);
    w.document.dispatchEvent(new w.Event('DOMContentLoaded', { bubbles: true }));
    return w;
}

const okFetch = (calls) => (url, opts) => {
    calls.push({ url, opts });
    return Promise.resolve({ ok: true, status: 200, text: () => Promise.resolve('{"success":true,"message":"Adjunto eliminado."}') });
};

async function main() {
    // 1. Cancelar el diálogo: no hay petición y el chip sigue.
    let calls = [];
    let w = makeDom('<div class="cs-attach-list" data-empty-text="Sin adjuntos todavía.">' + CHIP(1) + CHIP(2) + '</div>', okFetch(calls));
    w.document.querySelector('#f1 button').click();
    await wait(5);
    w.document.querySelector('[data-role="cancel"]').click();
    await wait(5);
    assert(calls.length === 0, 'cancelar → no se hace ninguna petición');
    assert(!!w.document.getElementById('chip1'), 'cancelar → el chip sigue en pantalla');

    // 2. Confirmar: una petición AJAX por fetch, el chip desaparece sin recargar.
    w.document.querySelector('#f1 button').click();
    await wait(5);
    w.document.querySelector('[data-role="confirm"]').click();
    await wait(20);
    assert(calls.length === 1 && calls[0].url === '/clases/adjuntos/1/eliminar', 'confirmar → POST a la URL del formulario');
    assert(calls[0].opts.headers['X-Requested-With'] === 'XMLHttpRequest', 'la petición va marcada como AJAX');
    assert(!w.document.getElementById('chip1'), 'éxito → el chip se quita del DOM');
    assert(!!w.document.getElementById('chip2'), 'el otro chip no se toca');
    assert(w.toasts.some((t) => t[1] === 'success'), 'se avisa con un toast de éxito');

    // 3. Borrar el último muestra el texto vacío.
    w.document.querySelector('#f2 button').click();
    await wait(5);
    w.document.querySelector('[data-role="confirm"]').click();
    await wait(20);
    const list = w.document.querySelector('.cs-attach-list');
    assert(list.textContent.trim() === 'Sin adjuntos todavía.', 'lista vacía → «Sin adjuntos todavía.»');

    // 4. Error del servidor: el chip se queda y se avisa.
    calls = [];
    w = makeDom('<div class="cs-attach-list">' + CHIP(7) + '</div>', (u, o) => {
        calls.push(u);
        return Promise.resolve({ ok: false, status: 403, text: () => Promise.resolve('{"success":false,"error":"No tienes permiso para borrar este adjunto."}') });
    });
    w.document.querySelector('#f7 button').click();
    await wait(5);
    w.document.querySelector('[data-role="confirm"]').click();
    await wait(20);
    assert(!!w.document.getElementById('chip7'), 'error 403 → el chip NO desaparece');
    assert(w.toasts.some((t) => t[0].includes('permiso') && t[1] === 'error'), 'error 403 → toast con el mensaje del servidor');
    assert(!w.document.getElementById('f7').querySelector('button').disabled, 'tras el error el botón vuelve a estar activo');

    // 5. El borrado también limpia el <template> que reutiliza el modal de alumnos.
    calls = [];
    w = makeDom('<div class="cs-attach-list" id="live">' + CHIP(9) + '</div><template id="tpl">' + CHIP(9) + '</template>', okFetch(calls));
    w.document.querySelector('#live #f9 button').click();
    await wait(5);
    w.document.querySelector('[data-role="confirm"]').click();
    await wait(20);
    assert(w.document.getElementById('tpl').content.querySelector('form') === null, 'el <template> pierde también el adjunto borrado');

    // 6. Sin RadixUI/data-ru-confirm: borra directamente.
    calls = [];
    w = makeDom('<div class="cs-attach-list"><span class="cs-attach-chip" id="c5"><form id="f5" action="/x/5/eliminar" method="POST" data-ajax-delete data-remove-target=".cs-attach-chip"><button>x</button></form></span></div>', okFetch(calls));
    w.document.querySelector('#f5 button').click();
    await wait(20);
    assert(calls.length === 1 && !w.document.getElementById('c5'), 'sin data-ru-confirm → borra sin diálogo');

    console.log(failed ? '\n' + failed + ' FALLO(S)' : '\nTodo OK');
    process.exit(failed ? 1 : 0);
}
main();
