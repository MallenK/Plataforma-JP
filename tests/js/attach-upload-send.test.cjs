/**
 * Test de la API AttachUpload (send / bind / bar) de public/assets/js/attach-upload.js,
 * usada por Mensajes, Notificaciones y Tickets (envíos por fetch propio).
 *
 *   node tests/js/attach-upload-send.test.cjs
 *
 * Sale con 0 si todo pasa, 1 si falla, 2 si no hay jsdom.
 */
'use strict';

const fs = require('fs');
const path = require('path');

let JSDOM;
try { JSDOM = require('jsdom').JSDOM; }
catch (e) { console.error('SKIP: jsdom no disponible (' + e.message + ')'); process.exit(2); }

const SRC = fs.readFileSync(path.join(__dirname, '..', '..', 'public', 'assets', 'js', 'attach-upload.js'), 'utf8');

let failed = 0;
function assert(cond, label) {
    console.log((cond ? '  OK   ' : '  FAIL ') + label);
    if (!cond) failed++;
}

// XHR de pega: el test decide cómo termina.
let lastXhr = null;
class FakeXhr {
    constructor() { this.upload = {}; this.headers = {}; lastXhr = this; }
    open(m, u) { this.method = m; this.url = u; }
    setRequestHeader(k, v) { this.headers[k] = v; }
    send(body) { this.body = body; }
}

function makeDom() {
    const dom = new JSDOM('<!doctype html><html><body>' +
        '<div id="box"><input type="file" id="f" accept=".jpg,.pdf,.mp4"></div></body></html>',
        { runScripts: 'outside-only' });
    const w = dom.window;
    w.XMLHttpRequest = FakeXhr;
    w.toasts = [];
    w.showAlert = (m, t) => w.toasts.push({ m, t });
    w.eval(SRC);
    return w;
}

function fakeFile(w, input, name, size) {
    let list = [{ name, size }];
    Object.defineProperty(input, 'files', { get: () => list, configurable: true });
    Object.defineProperty(input, 'value', { get: () => '', set: () => { list = []; }, configurable: true });
}

(async () => {
    console.log('send()');
    let w = makeDom();
    const pcts = [];
    let p = w.AttachUpload.send('/x', { method: 'POST', body: 'b', headers: { 'X-Requested-With': 'XMLHttpRequest' } }, (n) => pcts.push(n));
    lastXhr.upload.onprogress({ lengthComputable: true, loaded: 50, total: 200 });
    lastXhr.upload.onprogress({ lengthComputable: true, loaded: 200, total: 200 });
    lastXhr.status = 200; lastXhr.responseText = '{"ok":true,"csrf":"abc"}'; lastXhr.onload();
    let res = await p;
    assert(res.ok && res.status === 200, 'resuelve con ok/status');
    assert((await res.json()).csrf === 'abc', 'json() devuelve el cuerpo');
    assert(pcts.join(',') === '25,100', 'progreso 25% y 100%');
    assert(lastXhr.headers['X-Requested-With'] === 'XMLHttpRequest' && lastXhr.body === 'b', 'cabeceras y cuerpo enviados');

    p = w.AttachUpload.send('/x', { body: 'b' });
    lastXhr.status = 422; lastXhr.responseText = '{"error":"mal","error_ref":"AB12"}'; lastXhr.onload();
    res = await p;
    assert(!res.ok && res.status === 422 && (await res.json()).error_ref === 'AB12', '4xx: ok=false y JSON con error_ref');

    p = w.AttachUpload.send('/x', { body: 'b' });
    lastXhr.status = 500; lastXhr.responseText = '<html>boom</html>'; lastXhr.onload();
    res = await p;
    let threw = false; try { await res.json(); } catch (_) { threw = true; }
    assert(!res.ok && threw, '5xx no-JSON: json() rechaza (igual que fetch)');

    p = w.AttachUpload.send('/x', { body: 'b' });
    lastXhr.onerror();
    let netErr = null; try { await p; } catch (e) { netErr = e; }
    assert(netErr instanceof w.TypeError, 'corte de red rechaza con TypeError (como fetch)');

    console.log('bind()');
    w = makeDom();
    let input = w.document.getElementById('f');
    let changes = 0; input.addEventListener('change', () => changes++);
    w.AttachUpload.bind(input, { maxVideo: 500 * 1048576, maxOther: 5 * 1048576 });

    fakeFile(w, input, 'virus.exe', 100);
    input.dispatchEvent(new w.Event('change'));
    assert(input.files.length === 0 && w.toasts.length === 1 && /Formato no compatible/.test(w.toasts[0].m), 'formato no admitido: vacía el input y avisa');
    assert(changes === 2, 'tras vaciar se avisa a la vista previa con un 2º change');

    w.toasts.length = 0;
    fakeFile(w, input, 'foto.jpg', 6 * 1048576);
    input.dispatchEvent(new w.Event('change'));
    assert(input.files.length === 0 && /máximo es 5.0 MB/.test(w.toasts[0].m), 'imagen > 5 MB: rechazada');

    w.toasts.length = 0;
    fakeFile(w, input, 'clip.mp4', 100 * 1048576);
    input.dispatchEvent(new w.Event('change'));
    assert(input.files.length === 1 && w.toasts.length === 1 && w.toasts[0].t === 'warning' && /grande/.test(w.toasts[0].m), 'vídeo 100 MB: admitido con aviso de archivo grande');

    w.toasts.length = 0;
    fakeFile(w, input, 'doc.pdf', 1024);
    input.dispatchEvent(new w.Event('change'));
    assert(input.files.length === 1 && w.toasts.length === 0, 'archivo normal: sin avisos');

    console.log('bar()');
    const box = w.document.getElementById('box');
    const bar = w.AttachUpload.bar(box);
    const track = box.querySelector('[role="progressbar"]');
    assert(track && track.style.display === 'none', 'barra oculta al crearse');
    bar.set(40);
    assert(track.style.display === 'block' && track.firstChild.style.width === '40%', 'set(40) la muestra al 40%');
    bar.hide();
    assert(track.style.display === 'none' && track.firstChild.style.width === '0px' || track.firstChild.style.width === '0', 'hide() la oculta y reinicia');
    const bar2 = w.AttachUpload.bar(null, input);
    assert(input.nextSibling.getAttribute('role') === 'progressbar', 'bar(null, after) la coloca tras el elemento');

    console.log(failed ? '\n' + failed + ' FALLO(S)' : '\nTodo OK');
    process.exit(failed ? 1 : 0);
})();
