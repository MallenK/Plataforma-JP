/**
 * attach-upload.js — subida de adjuntos con aviso previo y progreso.
 *
 * Formularios con [data-attach-upload]:
 *   data-max-video  límite en bytes para .mp4/.mov
 *   data-max-other  límite en bytes para el resto
 *
 * Antes de subir comprueba el tamaño (un vídeo de móvil tarda minutos en
 * subir y antes solo se rechazaba al terminar), y sube por XHR mostrando el
 * porcentaje. El servidor responde JSON si la petición es AJAX; sin JS el
 * formulario sigue funcionando con el POST normal.
 */
(function () {
    'use strict';

    var VIDEO_EXT = ['mp4', 'mov'];

    function toast(msg, type) {
        if (typeof window.showAlert === 'function') {
            window.showAlert(msg, type || 'error');
        } else {
            alert(msg);
        }
    }

    function fmtMb(bytes) {
        var mb = bytes / (1024 * 1024);
        return (mb >= 10 ? Math.round(mb) : mb.toFixed(1)) + ' MB';
    }

    function extOf(name) {
        var i = name.lastIndexOf('.');
        return i < 0 ? '' : name.slice(i + 1).toLowerCase();
    }

    function init(form) {
        var input  = form.querySelector('input[type="file"]');
        var button = form.querySelector('button[type="submit"]');
        if (!input || !button) { return; }

        var maxVideo = parseInt(form.dataset.maxVideo, 10) || 0;
        var maxOther = parseInt(form.dataset.maxOther, 10) || 0;
        var idleHtml = button.innerHTML;

        var bar = document.createElement('div');
        bar.setAttribute('role', 'progressbar');
        bar.style.cssText = 'display:none;flex:1 0 100%;height:6px;border-radius:3px;background:var(--border);overflow:hidden';
        var fill = document.createElement('div');
        fill.style.cssText = 'height:100%;width:0;background:#7c3aed;transition:width .2s';
        bar.appendChild(fill);
        form.appendChild(bar);

        function limitFor(file) {
            var isVideo = VIDEO_EXT.indexOf(extOf(file.name)) !== -1;
            return { isVideo: isVideo, max: isVideo ? maxVideo : maxOther };
        }

        function tooBigMessage(file, lim) {
            return 'El archivo pesa ' + fmtMb(file.size) + ' y el máximo para ' +
                (lim.isVideo ? 'vídeos' : 'imágenes y documentos') + ' es ' + fmtMb(lim.max) +
                '. ' + (lim.isVideo ? 'Recórtalo o grábalo en menor calidad e inténtalo de nuevo.' : '');
        }

        function setBusy(busy, label) {
            button.disabled = busy;
            input.disabled  = busy;
            button.innerHTML = busy ? '<i class="bi bi-hourglass-split me-1"></i>' + label : idleHtml;
            bar.style.display = busy ? 'block' : 'none';
            if (!busy) { fill.style.width = '0'; }
        }

        // Aviso inmediato al elegir el archivo, no tras minutos de subida.
        input.addEventListener('change', function () {
            var file = input.files && input.files[0];
            if (!file) { return; }
            var lim = limitFor(file);
            if (lim.max && file.size > lim.max) {
                toast(tooBigMessage(file, lim), 'warning');
                input.value = '';
            }
        });

        form.addEventListener('submit', function (e) {
            var file = input.files && input.files[0];
            if (!file) { return; }          // deja actuar al "required" nativo

            var lim = limitFor(file);
            if (lim.max && file.size > lim.max) {
                e.preventDefault();
                toast(tooBigMessage(file, lim), 'warning');
                input.value = '';
                return;
            }

            e.preventDefault();
            var payload = new FormData(form);   // antes de setBusy: un input disabled no entra
            var xhr = new XMLHttpRequest();
            xhr.open('POST', form.action);
            xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');
            xhr.setRequestHeader('Accept', 'application/json');

            setBusy(true, 'Subiendo… 0%');

            xhr.upload.onprogress = function (ev) {
                if (!ev.lengthComputable) { return; }
                var pct = Math.min(100, Math.round(ev.loaded / ev.total * 100));
                fill.style.width = pct + '%';
                button.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>' +
                    (pct >= 100 ? 'Procesando…' : 'Subiendo… ' + pct + '%');
            };

            xhr.onload = function () {
                var data = null;
                try { data = JSON.parse(xhr.responseText); } catch (err) { /* no era JSON */ }

                if (xhr.status >= 200 && xhr.status < 300 && data && data.success) {
                    window.location.reload();      // el flash "Adjunto guardado." se muestra al recargar
                    return;
                }

                setBusy(false);
                if (data && data.csrf) {
                    var tok = form.querySelector('input[type="hidden"][name="jp_csrf_token"]');
                    if (tok) { tok.value = data.csrf; }
                }
                var msg = (data && data.error) ||
                    (xhr.status === 413 ? 'El archivo es demasiado grande para el servidor.' :
                     xhr.status === 403 ? 'La sesión ha caducado. Recarga la página e inténtalo de nuevo.' :
                     'No se pudo subir el archivo (error ' + xhr.status + ').');
                toast(msg);
            };

            xhr.onerror = function () {
                setBusy(false);
                toast('Se ha perdido la conexión durante la subida. Comprueba tu cobertura e inténtalo de nuevo.');
            };

            xhr.send(payload);
        });
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('form[data-attach-upload]').forEach(init);
    });
})();
