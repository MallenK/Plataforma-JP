/**
 * Privacidad de importes — solo se carga para admin y superadmin.
 *
 * Busca en la página cifras de dinero («1.234,50 €», «225.00€», «−35 €») y
 * las difumina. Hay dos formas de mostrarlas:
 *  - Botón general (barra superior, #money-toggle-all): todas las de la página.
 *  - Ojo junto a cada cifra suelta (resúmenes, totales, tarjetas). Dentro de
 *    las tablas no hay un ojo por celda: se hace clic en la propia cifra.
 *
 * Al cargar cada página las cifras empiezan SIEMPRE difuminadas (no se
 * recuerda el estado). Es privacidad visual: el texto sigue en el HTML.
 * Las tablas paginadas (DataTables) se redibujan; un MutationObserver
 * procesa las celdas nuevas.
 */
(function (root, factory) {
    var api = factory(root);
    if (typeof module === 'object' && module.exports) { module.exports = api; }
    root.JPMoney = api;
    if (root.document) { api.boot(); }
})(typeof window !== 'undefined' ? window : globalThis, function (window) {
    'use strict';

    // Signo opcional + número con separadores + «€» (con o sin espacio, normal o de no separación).
    var MONEY = /[−-]?\d[\d.,]*[\s ]*€/g;
    var SKIP_TAGS = { SCRIPT: 1, STYLE: 1, TEXTAREA: 1, OPTION: 1, SELECT: 1, INPUT: 1, NOSCRIPT: 1, BUTTON: 1 };

    function hasMoney(text) {
        MONEY.lastIndex = 0;
        return MONEY.test(text);
    }

    function skip(node) {
        for (var el = node.parentNode; el && el.nodeType === 1; el = el.parentNode) {
            if (SKIP_TAGS[el.tagName]) return true;
            if (el.classList && (el.classList.contains('jp-money') || el.classList.contains('no-money'))) return true;
        }
        return false;
    }

    function inTable(el) { return !!(el.closest && el.closest('table')); }

    function isHiddenNow(span) {
        var doc = span.ownerDocument.documentElement;
        var globalHidden = doc.getAttribute('data-money-hidden') === '1';
        if (span.classList.contains('is-masked')) return true;
        return globalHidden && !span.classList.contains('is-revealed');
    }

    function syncEye(span) {
        var eye = span.nextSibling;
        if (!eye || !eye.classList || !eye.classList.contains('jp-money-eye')) return;
        var hidden = isHiddenNow(span);
        eye.setAttribute('aria-pressed', hidden ? 'false' : 'true');
        eye.setAttribute('aria-label', hidden ? 'Mostrar este importe' : 'Ocultar este importe');
        eye.title = hidden ? 'Mostrar este importe' : 'Ocultar este importe';
        eye.firstChild.className = 'bi ' + (hidden ? 'bi-eye-slash' : 'bi-eye');
    }

    function toggleOne(span) {
        var show = isHiddenNow(span);
        span.classList.toggle('is-revealed', show);
        span.classList.toggle('is-masked', !show);
        syncEye(span);
    }

    /** Envuelve cada cifra de un nodo de texto en <span class="jp-money">. */
    function wrapTextNode(node) {
        var text = node.nodeValue;
        if (!text || !hasMoney(text) || skip(node)) return 0;
        var doc = node.ownerDocument, frag = doc.createDocumentFragment(), last = 0, n = 0, m;
        MONEY.lastIndex = 0;
        while ((m = MONEY.exec(text))) {
            if (m.index > last) frag.appendChild(doc.createTextNode(text.slice(last, m.index)));
            var span = doc.createElement('span');
            span.className = 'jp-money';
            span.textContent = m[0];
            frag.appendChild(span);
            last = m.index + m[0].length;
            n++;
        }
        if (last < text.length) frag.appendChild(doc.createTextNode(text.slice(last)));
        node.parentNode.replaceChild(frag, node);
        return n;
    }

    /** Ojo junto a las cifras sueltas; en tablas, la cifra se pulsa directamente. */
    function decorate(span) {
        if (span.getAttribute('data-money-ready')) return;
        span.setAttribute('data-money-ready', '1');
        var doc = span.ownerDocument;
        if (inTable(span)) {
            span.setAttribute('tabindex', '0');
            span.setAttribute('role', 'button');
            span.title = 'Clic para mostrar u ocultar este importe';
            var act = function (e) {
                if (e.type === 'keydown' && e.key !== 'Enter' && e.key !== ' ') return;
                e.preventDefault();
                e.stopPropagation();
                toggleOne(span);
            };
            span.addEventListener('click', act);
            span.addEventListener('keydown', act);
            return;
        }
        var eye = doc.createElement('button');
        eye.type = 'button';
        eye.className = 'jp-money-eye';
        eye.appendChild(doc.createElement('i'));
        eye.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); toggleOne(span); });
        span.parentNode.insertBefore(eye, span.nextSibling);
        syncEye(span);
    }

    function scan(rootNode) {
        var doc = rootNode.ownerDocument || rootNode;
        var walker = doc.createTreeWalker(rootNode, 4 /* NodeFilter.SHOW_TEXT */, null);
        var nodes = [], n;
        while ((n = walker.nextNode())) nodes.push(n);
        var found = 0;
        nodes.forEach(function (t) { found += wrapTextNode(t); });
        Array.prototype.forEach.call(rootNode.querySelectorAll ? rootNode.querySelectorAll('.jp-money:not([data-money-ready])') : [], decorate);
        return found;
    }

    function setGlobal(doc, hidden) {
        doc.documentElement.setAttribute('data-money-hidden', hidden ? '1' : '0');
        Array.prototype.forEach.call(doc.querySelectorAll('.jp-money'), function (s) {
            s.classList.remove('is-revealed', 'is-masked');
            syncEye(s);
        });
        // Los «title» con importes (p. ej. las barras del gráfico) también se ocultan.
        Array.prototype.forEach.call(doc.querySelectorAll('[title],[data-money-title]'), function (el) {
            if (el.classList.contains('jp-money') || el.classList.contains('jp-money-eye') || el.id === 'money-toggle-all') return;
            if (hidden && el.hasAttribute('title') && hasMoney(el.getAttribute('title'))) {
                el.setAttribute('data-money-title', el.getAttribute('title'));
                el.removeAttribute('title');
            } else if (!hidden && el.hasAttribute('data-money-title')) {
                el.setAttribute('title', el.getAttribute('data-money-title'));
                el.removeAttribute('data-money-title');
            }
        });
        var btn = doc.getElementById('money-toggle-all');
        if (btn) {
            btn.setAttribute('aria-pressed', hidden ? 'false' : 'true');
            btn.title = hidden ? 'Mostrar los importes de esta página' : 'Ocultar los importes de esta página';
            btn.setAttribute('aria-label', btn.title);
            var i = btn.querySelector('i');
            if (i) i.className = 'bi ' + (hidden ? 'bi-eye-slash' : 'bi-eye');
        }
    }

    function boot() {
        var doc = window.document;
        // Oculto desde el primer momento: nunca se ve un importe antes de difuminarlo.
        doc.documentElement.setAttribute('data-money-hidden', '1');

        function init() {
            var body = doc.querySelector('.page-body') || doc.body;
            var found = scan(body);
            setGlobal(doc, true);
            var btn = doc.getElementById('money-toggle-all');
            if (btn) {
                btn.hidden = !doc.querySelector('.jp-money');
                btn.addEventListener('click', function () {
                    setGlobal(doc, doc.documentElement.getAttribute('data-money-hidden') !== '1');
                });
            }
            var pending = false;
            var mo = new window.MutationObserver(function (muts) {
                if (pending) return;
                pending = true;
                window.requestAnimationFrame(function () {
                    pending = false;
                    mo.disconnect();
                    muts.forEach(function (m) {
                        Array.prototype.forEach.call(m.addedNodes, function (nd) {
                            if (nd.nodeType === 3 && nd.parentNode) wrapTextNode(nd);
                            else if (nd.nodeType === 1 && !(nd.classList && nd.classList.contains('jp-money-eye'))) scan(nd);
                        });
                    });
                    if (btn) btn.hidden = !doc.querySelector('.jp-money');
                    mo.observe(body, { childList: true, subtree: true });
                });
            });
            mo.observe(body, { childList: true, subtree: true });
            return found;
        }

        if (doc.readyState === 'loading') doc.addEventListener('DOMContentLoaded', init);
        else init();
    }

    return { boot: boot, scan: scan, hasMoney: hasMoney, setGlobal: setGlobal, toggleOne: toggleOne, isHiddenNow: isHiddenNow };
});
