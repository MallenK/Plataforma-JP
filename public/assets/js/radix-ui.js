/**
 * radix-ui.js — Primitivas de UI accesibles al estilo Radix, sin React.
 *
 * Reimplementa en JS vanilla los patrones de accesibilidad de Radix
 * Primitives (foco atrapado, Escape, ARIA, navegación por teclado) para
 * los tres widgets que se repiten en la plataforma: Dialog, DropdownMenu
 * y Tabs. Cero dependencias, sin paso de build.
 *
 * Uso — Dialog declarativo:
 *   <button data-ru-dialog-trigger="miDialogo">Abrir</button>
 *   <div class="ru-overlay" data-ru-dialog id="miDialogo" hidden>
 *     <div class="ru-dialog" role="dialog" aria-modal="true" aria-labelledby="miDialogoTitulo">
 *       <div class="ru-dialog-header">
 *         <h3 id="miDialogoTitulo">Título</h3>
 *         <button type="button" data-ru-dialog-close aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
 *       </div>
 *       <div class="ru-dialog-body">...</div>
 *     </div>
 *   </div>
 *
 * Uso — confirmación programática (sustituye a confirm() nativo):
 *   RadixUI.confirm({
 *     title: '¿Eliminar la sede "Camp Nou"?',
 *     description: 'Esta acción no se puede deshacer.',
 *     confirmLabel: 'Eliminar', danger: true
 *   }).then(ok => { if (ok) form.submit(); });
 *
 * Uso — DropdownMenu:
 *   <div class="ru-dropdown">
 *     <button data-ru-dropdown-trigger class="btn-jp btn-jp-secondary btn-jp-sm">Estado</button>
 *     <div class="ru-dropdown-menu" role="menu" hidden>
 *       <button role="menuitem" class="ru-dropdown-item">Opción</button>
 *     </div>
 *   </div>
 *
 * Uso — Tabs:
 *   <div class="ru-tabs" role="tablist">
 *     <button role="tab" id="tab-a" aria-controls="panel-a" data-ru-tab>A</button>
 *   </div>
 *   <div role="tabpanel" id="panel-a" aria-labelledby="tab-a" data-ru-tabpanel>...</div>
 */
(function (window, document) {
    'use strict';

    const FOCUSABLE = 'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])';

    // ────────────────────────────────────────────────────────────────
    //  DIALOG — motor compartido por diálogos declarativos y confirm()
    // ────────────────────────────────────────────────────────────────

    // Pila de { el, dialogEl, lastFocused, onClose } — soporta diálogos
    // anidados (ej. un confirm() abierto desde dentro de otro diálogo).
    // Un único listener de teclado global actúa siempre sobre el TOPE de
    // la pila, para que Escape/Tab solo afecten al diálogo visible más
    // reciente y no a los que puedan quedar debajo.
    let openStack = [];

    function getFocusable(container) {
        return Array.prototype.slice.call(container.querySelectorAll(FOCUSABLE))
            .filter(el => el.offsetParent !== null);
    }

    function trapFocus(container, e) {
        const focusables = getFocusable(container);
        if (focusables.length === 0) return;
        const first = focusables[0];
        const last  = focusables[focusables.length - 1];

        if (e.shiftKey && document.activeElement === first) {
            e.preventDefault();
            last.focus();
        } else if (!e.shiftKey && document.activeElement === last) {
            e.preventDefault();
            first.focus();
        }
    }

    function onStackKeydown(e) {
        const entry = openStack[openStack.length - 1];
        if (!entry) return;

        if (e.key === 'Escape') {
            e.preventDefault();
            closeDialogEl(entry.el);
        } else if (e.key === 'Tab') {
            trapFocus(entry.dialogEl, e);
        }
    }

    function openDialogEl(overlayEl, onClose) {
        const dialogEl = overlayEl.querySelector('.ru-dialog') || overlayEl;
        const entry = { el: overlayEl, dialogEl: dialogEl, lastFocused: document.activeElement, onClose: onClose };

        overlayEl.hidden = false;
        overlayEl.classList.add('ru-open');

        if (openStack.length === 0) {
            document.addEventListener('keydown', onStackKeydown, true);
            document.body.style.overflow = 'hidden';
        }
        openStack.push(entry);

        // Foco al primer elemento enfocable, o al propio diálogo.
        const focusables = getFocusable(dialogEl);
        (focusables[0] || dialogEl).focus();
        if (!dialogEl.hasAttribute('tabindex') && focusables.length === 0) {
            dialogEl.setAttribute('tabindex', '-1');
        }
    }

    function closeDialogEl(overlayEl) {
        const idx = openStack.findIndex(e => e.el === overlayEl);
        if (idx === -1) return;
        const entry = openStack[idx];

        overlayEl.classList.remove('ru-open');
        overlayEl.hidden = true;
        openStack.splice(idx, 1);

        if (openStack.length === 0) {
            document.removeEventListener('keydown', onStackKeydown, true);
            document.body.style.overflow = '';
        }
        if (entry.lastFocused && typeof entry.lastFocused.focus === 'function') {
            entry.lastFocused.focus();
        }
        if (typeof entry.onClose === 'function') {
            entry.onClose();
        }
        // Evento genérico para que otro código de la página reaccione al
        // cierre (ej. resetear un formulario), sin acoplarse a Bootstrap.
        overlayEl.dispatchEvent(new CustomEvent('ru-dialog-close'));
    }

    function initDialogs() {
        // Triggers declarativos: data-ru-dialog-trigger="idDelDialogo"
        document.addEventListener('click', function (e) {
            const trigger = e.target.closest('[data-ru-dialog-trigger]');
            if (trigger) {
                const target = document.getElementById(trigger.getAttribute('data-ru-dialog-trigger'));
                if (target) openDialogEl(target);
                return;
            }

            const closeBtn = e.target.closest('[data-ru-dialog-close]');
            if (closeBtn) {
                const overlay = closeBtn.closest('[data-ru-dialog]');
                if (overlay) closeDialogEl(overlay);
                return;
            }

            // Click directo en el overlay (fuera de la tarjeta del diálogo) cierra.
            if (e.target.matches('[data-ru-dialog].ru-open')) {
                closeDialogEl(e.target);
            }
        });
    }

    /**
     * Confirmación accesible, no bloqueante — sustituye a confirm() nativo.
     * @return {Promise<boolean>}
     */
    function confirmDialog(opts) {
        opts = Object.assign({
            title: '¿Confirmar la acción?',
            description: '',
            confirmLabel: 'Confirmar',
            cancelLabel: 'Cancelar',
            danger: false,
        }, opts || {});

        return new Promise(function (resolve) {
            const overlay = document.createElement('div');
            overlay.className = 'ru-overlay';
            overlay.setAttribute('data-ru-dialog', '');

            const titleId = 'ru-confirm-title-' + Date.now();
            overlay.innerHTML =
                '<div class="ru-dialog ru-dialog-sm" role="alertdialog" aria-modal="true" aria-labelledby="' + titleId + '">' +
                '  <div class="ru-dialog-header">' +
                '    <h3 id="' + titleId + '"></h3>' +
                '  </div>' +
                '  <div class="ru-dialog-body ru-confirm-desc"></div>' +
                '  <div class="ru-dialog-footer">' +
                '    <button type="button" class="btn-jp btn-jp-secondary" data-role="cancel"></button>' +
                '    <button type="button" class="btn-jp ' + (opts.danger ? 'btn-jp-danger' : 'btn-jp-primary') + '" data-role="confirm"></button>' +
                '  </div>' +
                '</div>';

            overlay.querySelector('h3').textContent = opts.title;
            const descEl = overlay.querySelector('.ru-confirm-desc');
            if (opts.description) {
                descEl.textContent = opts.description;
            } else {
                descEl.remove();
            }
            overlay.querySelector('[data-role="cancel"]').textContent = opts.cancelLabel;
            overlay.querySelector('[data-role="confirm"]').textContent = opts.confirmLabel;

            let settled = false;
            // finish() puede llegar por dos caminos (clic en un botón, que cierra
            // el diálogo, o Escape/overlay, que cierra el diálogo directamente)
            // — se protege para resolver la promesa una única vez.
            function finish(result) {
                if (settled) return;
                settled = true;
                if (openStack.some(e => e.el === overlay)) {
                    closeDialogEl(overlay); // dispara onClose → finish(false) de nuevo, pero settled ya corta ahí
                }
                if (overlay.parentNode) overlay.parentNode.removeChild(overlay);
                resolve(result);
            }

            overlay.querySelector('[data-role="cancel"]').addEventListener('click', function () { finish(false); });
            overlay.querySelector('[data-role="confirm"]').addEventListener('click', function () { finish(true); });

            document.body.appendChild(overlay);
            // onClose cubre Escape y el clic en el overlay (gestionados por initDialogs()/openDialogEl).
            openDialogEl(overlay, function () { finish(false); });
        });
    }

    // ────────────────────────────────────────────────────────────────
    //  DROPDOWN MENU
    // ────────────────────────────────────────────────────────────────

    function closeAllDropdowns(except) {
        document.querySelectorAll('.ru-dropdown-menu').forEach(function (menu) {
            if (menu === except) return;
            menu.hidden = true;
            const trigger = menu.parentElement.querySelector('[data-ru-dropdown-trigger]');
            if (trigger) trigger.setAttribute('aria-expanded', 'false');
        });
    }

    function initDropdowns() {
        document.addEventListener('click', function (e) {
            const trigger = e.target.closest('[data-ru-dropdown-trigger]');
            if (trigger) {
                const wrap = trigger.closest('.ru-dropdown');
                const menu = wrap && wrap.querySelector('.ru-dropdown-menu');
                if (!menu) return;
                const willOpen = menu.hidden;
                closeAllDropdowns();
                menu.hidden = !willOpen;
                trigger.setAttribute('aria-expanded', String(willOpen));
                if (willOpen) {
                    const first = menu.querySelector('[role="menuitem"]');
                    if (first) first.focus();
                }
                e.stopPropagation();
                return;
            }
            // Click fuera de cualquier dropdown → cerrar todos.
            if (!e.target.closest('.ru-dropdown-menu')) {
                closeAllDropdowns();
            }
        });

        document.addEventListener('keydown', function (e) {
            const openMenu = document.querySelector('.ru-dropdown-menu:not([hidden])');
            if (!openMenu) return;

            const items = Array.prototype.slice.call(openMenu.querySelectorAll('[role="menuitem"]'));
            const currentIdx = items.indexOf(document.activeElement);

            if (e.key === 'Escape') {
                e.preventDefault();
                const trigger = openMenu.parentElement.querySelector('[data-ru-dropdown-trigger]');
                closeAllDropdowns();
                if (trigger) trigger.focus();
            } else if (e.key === 'ArrowDown') {
                e.preventDefault();
                (items[currentIdx + 1] || items[0]).focus();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                (items[currentIdx - 1] || items[items.length - 1]).focus();
            }
        });
    }

    // ────────────────────────────────────────────────────────────────
    //  TABS (roving tabindex + flechas)
    // ────────────────────────────────────────────────────────────────

    function activateTab(tab) {
        const tablist = tab.closest('[role="tablist"]');
        const tabs    = Array.prototype.slice.call(tablist.querySelectorAll('[role="tab"]'));

        tabs.forEach(function (t) {
            const selected = t === tab;
            t.setAttribute('aria-selected', String(selected));
            t.tabIndex = selected ? 0 : -1;
            t.classList.toggle('active', selected);

            const panel = document.getElementById(t.getAttribute('aria-controls'));
            if (panel) panel.hidden = !selected;
        });

        tab.focus();
    }

    function initTabs() {
        document.addEventListener('click', function (e) {
            const tab = e.target.closest('[role="tab"]');
            if (tab) activateTab(tab);
        });

        document.addEventListener('keydown', function (e) {
            const tab = e.target.closest('[role="tab"]');
            if (!tab) return;
            const tablist = tab.closest('[role="tablist"]');
            const tabs    = Array.prototype.slice.call(tablist.querySelectorAll('[role="tab"]'));
            const idx     = tabs.indexOf(tab);

            if (e.key === 'ArrowRight' || e.key === 'ArrowDown') {
                e.preventDefault();
                activateTab(tabs[(idx + 1) % tabs.length]);
            } else if (e.key === 'ArrowLeft' || e.key === 'ArrowUp') {
                e.preventDefault();
                activateTab(tabs[(idx - 1 + tabs.length) % tabs.length]);
            } else if (e.key === 'Home') {
                e.preventDefault();
                activateTab(tabs[0]);
            } else if (e.key === 'End') {
                e.preventDefault();
                activateTab(tabs[tabs.length - 1]);
            }
        });
    }

    // ────────────────────────────────────────────────────────────────
    //  FORMULARIOS CON CONFIRMACIÓN DECLARATIVA
    //  <form data-ru-confirm="¿Título?" data-ru-confirm-desc="..."
    //        data-ru-confirm-label="Eliminar" data-ru-confirm-danger>
    // ────────────────────────────────────────────────────────────────

    function initConfirmForms() {
        document.addEventListener('submit', function (e) {
            const form = e.target;
            if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-ru-confirm')) return;

            e.preventDefault();
            confirmDialog({
                title: form.getAttribute('data-ru-confirm'),
                description: form.getAttribute('data-ru-confirm-desc') || '',
                confirmLabel: form.getAttribute('data-ru-confirm-label') || 'Confirmar',
                danger: form.hasAttribute('data-ru-confirm-danger'),
            }).then(function (ok) {
                if (ok) form.submit(); // .submit() no vuelve a disparar 'submit' — sin bucle
            });
        });
    }

    // ────────────────────────────────────────────────────────────────

    document.addEventListener('DOMContentLoaded', function () {
        initDialogs();
        initDropdowns();
        initTabs();
        initConfirmForms();
    });

    window.RadixUI = {
        confirm: confirmDialog,
        openDialog: function (id) { const el = document.getElementById(id); if (el) openDialogEl(el); },
        closeDialog: function (id) { const el = document.getElementById(id); if (el) closeDialogEl(el); },
    };
})(window, document);
