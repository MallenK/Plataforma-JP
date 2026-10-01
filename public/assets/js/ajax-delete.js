/**
 * ajax-delete.js — borrado dinámico: el elemento desaparece con una animación
 * sutil, sin recargar la página.
 *
 * <form action="…/eliminar" method="POST" data-ajax-delete data-remove-target=".cs-attach-chip">
 *   (opcional) data-ru-confirm / -desc / -label / -danger → diálogo de confirmación
 *
 * El servidor responde JSON {success:true} si la petición es AJAX. Si el
 * contenedor (.cs-attach-list u otro con data-empty-text) se queda vacío, se
 * pinta ese texto. Sin JS el formulario sigue funcionando (POST + redirect).
 */
(function () {
    'use strict';

    function toast(msg, type) {
        if (typeof window.showAlert === 'function') { window.showAlert(msg, type || 'error'); }
        else { alert(msg); }
    }

    /** Desvanece y desliza el elemento y lo quita del DOM al terminar. */
    function removeAnimated(el, done) {
        function finish() {
            var parent = el.parentNode;
            el.remove();
            if (done) { done(parent); }
        }
        if (window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches) { finish(); return; }
        el.style.transition = 'opacity .26s ease, transform .26s ease';
        el.style.opacity = '0';
        el.style.transform = 'translateX(10px) scale(.96)';
        setTimeout(finish, 270);
    }
    window.jpRemoveAnimated = removeAnimated;

    function showEmptyIfNeeded(container, itemSelector) {
        if (!container || !container.dataset.emptyText) { return; }
        if (container.querySelector(itemSelector)) { return; }
        var span = document.createElement('span');
        span.style.cssText = 'font-size:12px;color:var(--text-muted)';
        span.textContent = container.dataset.emptyText;
        container.appendChild(span);
    }

    function doDelete(form) {
        var selector  = form.dataset.removeTarget;
        var target    = selector ? form.closest(selector) : null;
        var container = target ? target.parentElement : null;
        var action    = form.getAttribute('action');
        var busyBtn   = form.querySelector('button');

        if (busyBtn) { busyBtn.disabled = true; }
        if (target)  { target.style.opacity = '.5'; target.style.pointerEvents = 'none'; }

        function restore() {
            if (busyBtn) { busyBtn.disabled = false; }
            if (target)  { target.style.opacity = ''; target.style.pointerEvents = ''; }
        }

        fetch(action, {
            method: 'POST',
            body: new FormData(form),
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            credentials: 'same-origin'
        })
        .then(function (r) {
            return r.text().then(function (t) {
                var data = null;
                try { data = JSON.parse(t); } catch (e) { /* no era JSON */ }
                return { ok: r.ok, status: r.status, data: data };
            });
        })
        .then(function (res) {
            if (res.ok && res.data && res.data.success) {
                if (target) {
                    // Si el elemento también vive dentro de un <template> (p. ej. el
                    // modal de adjuntos de un alumno), se quita para que no reaparezca.
                    document.querySelectorAll('template').forEach(function (tpl) {
                        tpl.content.querySelectorAll('form[action="' + action + '"]').forEach(function (f) {
                            var t = f.closest(selector);
                            if (t) { t.remove(); }
                        });
                    });
                    removeAnimated(target, function (parent) { showEmptyIfNeeded(parent || container, selector); });
                }
                toast(res.data.message || 'Eliminado correctamente.', 'success');
                return;
            }
            restore();
            toast((res.data && res.data.error) ||
                (res.status === 403 ? 'No tienes permiso para eliminarlo, o la sesión ha caducado.' :
                 'No se pudo eliminar (error ' + res.status + ').'));
        })
        .catch(function () {
            restore();
            toast('Error de red al eliminar. Inténtalo de nuevo.');
        });
    }

    // Captura: se ejecuta antes que el manejador de data-ru-confirm de radix-ui.js
    // (que haría form.submit() nativo y recargaría la página).
    document.addEventListener('submit', function (e) {
        var form = e.target;
        if (!(form instanceof HTMLFormElement) || !form.hasAttribute('data-ajax-delete')) { return; }

        e.preventDefault();
        e.stopPropagation();

        if (form.hasAttribute('data-ru-confirm') && window.RadixUI && typeof window.RadixUI.confirm === 'function') {
            window.RadixUI.confirm({
                title:        form.getAttribute('data-ru-confirm'),
                description:  form.getAttribute('data-ru-confirm-desc') || '',
                confirmLabel: form.getAttribute('data-ru-confirm-label') || 'Confirmar',
                danger:       form.hasAttribute('data-ru-confirm-danger')
            }).then(function (ok) { if (ok) { doDelete(form); } });
            return;
        }
        doDelete(form);
    }, true);
})();
