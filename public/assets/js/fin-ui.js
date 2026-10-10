/**
 * Finanzas — tablas paginadas con buscador y ayudas «?».
 *
 * Tablas: <table data-fin-list="clave" [data-fin-order='[[0,"desc"]]'] [data-fin-page="25"]>
 *   → DataTables (vía JPList de list-view.js): paginación, buscador sobre TODAS
 *     las filas, ordenación por columna y vista lista/cuadrícula recordada.
 *   Para ordenar bien fechas e importes, la celda lleva data-order (AAAA-MM-DD o céntimos).
 *   No usar filas con colspan dentro de tbody (DataTables no las admite).
 *
 * Ayudas: <button class="fin-help" data-bs-toggle="tooltip" data-bs-title="…">
 *   → tooltip de Bootstrap: ratón y teclado en escritorio, toque en móvil.
 */
(function (window, document) {
    'use strict';

    function initTables() {
        if (!window.JPList || !window.jQuery || !window.jQuery.fn.dataTable) return;
        Array.prototype.forEach.call(document.querySelectorAll('table[data-fin-list]'), function (t) {
            var key = 'fin-' + t.getAttribute('data-fin-list');
            var order = [];
            try { order = JSON.parse(t.getAttribute('data-fin-order') || '[]'); } catch (e) { order = []; }
            var anchor = t.closest('.table-responsive') || t;
            var bar = document.createElement('div');
            bar.className = 'jp-files-toolbar';
            bar.innerHTML = window.JPList.toggleHtml(key);
            anchor.parentNode.insertBefore(bar, anchor);
            window.JPList.init({
                table: t,
                key: key,
                order: order,
                ordering: true,
                dtSearch: true,
                pageLength: parseInt(t.getAttribute('data-fin-page'), 10) || 25,
                defaultView: 'list',
            });
        });
    }

    function initTooltips() {
        if (!window.bootstrap || !window.bootstrap.Tooltip) return;
        var touch = window.matchMedia && window.matchMedia('(pointer: coarse)').matches;
        Array.prototype.forEach.call(document.querySelectorAll('.fin-help[data-bs-toggle="tooltip"]'), function (el) {
            if (window.bootstrap.Tooltip.getInstance(el)) return;
            // Un «?» en la cabecera de una columna no debe reordenar la tabla.
            el.addEventListener('click', function (e) { e.stopPropagation(); e.preventDefault(); });
            el.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') e.stopPropagation(); });
            new window.bootstrap.Tooltip(el, {
                trigger: touch ? 'click' : 'hover focus',
                placement: 'top',
                container: 'body',
                customClass: 'fin-tooltip',
            });
        });
        // En móvil, tocar fuera cierra la ayuda abierta.
        if (touch) {
            document.addEventListener('click', function (e) {
                if (e.target.closest('.fin-help')) return;
                Array.prototype.forEach.call(document.querySelectorAll('.fin-help'), function (el) {
                    var tip = window.bootstrap.Tooltip.getInstance(el);
                    if (tip) tip.hide();
                });
            });
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        initTables();
        initTooltips();
    });
})(window, document);
