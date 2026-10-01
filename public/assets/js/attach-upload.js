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

        // [data-attach-reveal] en el botón: solo aparece cuando el archivo ya
        // está procesado (leído y validado). Sin JS el botón se ve siempre.
        var reveal = button.hasAttribute('data-attach-reveal');
        function setReady(ok) { if (reveal) { button.style.display = ok ? '' : 'none'; } }
        setReady(false);

        // La barra se puede colocar a mano con [data-attach-progress]; si no, se añade al final.
        var bar = form.querySelector('[data-attach-progress]');
        var ownBar = !bar;
        if (ownBar) {
            bar = document.createElement('div');
            bar.style.cssText = 'display:none;flex:1 0 100%;height:6px;border-radius:3px;background:var(--border);overflow:hidden';
            form.appendChild(bar);
        }
        bar.setAttribute('role', 'progressbar');
        var fill = document.createElement('div');
        fill.style.cssText = 'height:100%;width:0;background:var(--accent);transition:width .2s';
        bar.appendChild(fill);

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

        // Extensiones admitidas = las del atributo accept del input.
        var allowedExt = (input.getAttribute('accept') || '')
            .split(',')
            .map(function (s) { return s.trim().replace(/^\./, '').toLowerCase(); })
            .filter(Boolean);

        // Línea de información bajo el selector (propiedades del archivo elegido).
        var info = form.querySelector('[data-attach-info]');
        if (!info) {
            info = document.createElement('div');
            info.style.cssText = 'display:none;flex:1 0 100%;font-size:12px;color:var(--text-muted)';
            form.insertBefore(info, bar);
        }

        function showInfo(html, tone) {
            info.style.display = html ? 'block' : 'none';
            info.style.color = tone === 'warn' ? '#b45309' : 'var(--text-muted)';
            info.innerHTML = html;
        }

        function fmtDuration(sec) {
            sec = Math.round(sec);
            return Math.floor(sec / 60) + ':' + ('0' + (sec % 60)).slice(-2);
        }

        // Lee duración y resolución de un vídeo sin subirlo. Resuelve null si
        // el navegador no puede decodificarlo (p. ej. HEVC en Chrome de escritorio).
        function readVideoMeta(file) {
            return new Promise(function (resolve) {
                var url = URL.createObjectURL(file);
                var v = document.createElement('video');
                var done = false;
                function finish(val) {
                    if (done) { return; }
                    done = true;
                    URL.revokeObjectURL(url);
                    v.removeAttribute('src');
                    resolve(val);
                }
                v.preload = 'metadata';
                v.muted = true;
                v.onloadedmetadata = function () {
                    finish({ duration: v.duration, w: v.videoWidth, h: v.videoHeight });
                };
                v.onerror = function () { finish(null); };
                setTimeout(function () { finish(null); }, 4000);
                v.src = url;
            });
        }

        var inspectToken = 0;

        // Revisa el archivo elegido ANTES de subir: tipo, tamaño y, en vídeo,
        // sus propiedades. Devuelve true si se puede subir.
        function inspect(file) {
            var token = ++inspectToken;
            var ext = extOf(file.name);
            setReady(false);

            if (allowedExt.length && allowedExt.indexOf(ext) === -1) {
                toast('Formato no compatible (.' + (ext || '?') + '). Se admiten: ' +
                    allowedExt.join(', ').toUpperCase() + '.', 'warning');
                input.value = '';
                showInfo('');
                return false;
            }

            var lim = limitFor(file);
            if (lim.max && file.size > lim.max) {
                toast(tooBigMessage(file, lim), 'warning');
                input.value = '';
                showInfo('');
                return false;
            }

            var base = '<i class="bi bi-file-earmark me-1"></i>' + file.name.replace(/[<>&]/g, '') +
                ' · ' + fmtMb(file.size);
            var big = file.size >= 30 * 1024 * 1024;
            var bigNote = big
                ? ' — archivo grande: la subida puede tardar varios minutos. No cierres ni bloquees la pantalla.'
                : '';

            if (!lim.isVideo) {
                showInfo(base + bigNote, big ? 'warn' : '');
                setReady(true);
                return true;
            }

            showInfo(base + ' · leyendo vídeo…');
            readVideoMeta(file).then(function (m) {
                if (token !== inspectToken) { return; }   // el usuario eligió otro archivo
                setReady(true);                            // procesado: ya se puede subir
                if (m) {
                    showInfo(base + ' · ' + fmtDuration(m.duration) + ' · ' + m.w + '×' + m.h + bigNote,
                        big ? 'warn' : '');
                } else {
                    showInfo(base + ' — no se pueden leer las propiedades del vídeo en este navegador ' +
                        '(códec poco común). Se subirá igualmente, pero puede no reproducirse en todos los dispositivos.' +
                        bigNote, 'warn');
                }
            });
            return true;
        }

        // Aviso inmediato al elegir el archivo, no tras minutos de subida.
        input.addEventListener('change', function () {
            var file = input.files && input.files[0];
            if (!file) { inspectToken++; showInfo(''); setReady(false); return; }
            inspect(file);
        });

        // form.reset() (p. ej. al abrir otra carpeta) deja el formulario como nuevo.
        form.addEventListener('reset', function () {
            inspectToken++;
            showInfo('');
            setReady(false);
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

    // ── API para pantallas que envían por fetch propio (Mensajes, Notificaciones, Tickets) ──
    //
    //   AttachUpload.send(url, {method, body, headers}, onProgress)
    //       Sustituto de fetch() con progreso de subida. Resuelve con un objeto
    //       parecido a Response ({ok, status, json()}); ante un corte de red
    //       rechaza con TypeError, igual que fetch.
    //   AttachUpload.bind(input, {maxVideo, maxOther})
    //       Aviso previo al elegir el archivo: formato (atributo accept) y tamaño.
    //       Si no vale, vacía el input y avisa con un toast.
    //   AttachUpload.bar(parent[, after])
    //       Barra de progreso fina dentro de `parent` (o justo tras `after`): {set(pct), hide()}.
    window.AttachUpload = {
        send: function (url, options, onProgress) {
            options = options || {};
            return new Promise(function (resolve, reject) {
                var xhr = new XMLHttpRequest();
                xhr.open(options.method || 'POST', url);
                var headers = options.headers || {};
                Object.keys(headers).forEach(function (k) { xhr.setRequestHeader(k, headers[k]); });
                xhr.upload.onprogress = function (ev) {
                    if (onProgress && ev.lengthComputable) {
                        onProgress(Math.min(100, Math.round(ev.loaded / ev.total * 100)));
                    }
                };
                xhr.onload = function () {
                    var text = xhr.responseText;
                    resolve({
                        ok: xhr.status >= 200 && xhr.status < 300,
                        status: xhr.status,
                        json: function () {
                            try { return Promise.resolve(JSON.parse(text)); }
                            catch (err) { return Promise.reject(err); }
                        }
                    });
                };
                xhr.onerror = xhr.onabort = xhr.ontimeout = function () {
                    reject(new TypeError('Network request failed'));
                };
                xhr.send(options.body);
            });
        },

        bind: function (input, limits) {
            if (!input) { return; }
            limits = limits || {};
            var allowed = (input.getAttribute('accept') || '')
                .split(',')
                .map(function (s) { return s.trim().replace(/^\./, '').toLowerCase(); })
                .filter(Boolean);

            // En captura: corre antes que los manejadores de cada pantalla (vista previa del archivo).
            input.addEventListener('change', function () {
                var file = input.files && input.files[0];
                if (!file) { return; }
                var ext = extOf(file.name);
                var isVideo = VIDEO_EXT.indexOf(ext) !== -1;
                var max = isVideo ? (limits.maxVideo || limits.maxOther) : limits.maxOther;
                var msg = null;

                if (allowed.length && allowed.indexOf(ext) === -1) {
                    msg = 'Formato no compatible (.' + (ext || '?') + '). Se admiten: ' +
                        allowed.join(', ').toUpperCase() + '.';
                } else if (max && file.size > max) {
                    msg = 'El archivo pesa ' + fmtMb(file.size) + ' y el máximo es ' + fmtMb(max) + '.';
                }
                if (msg) {
                    input.value = '';
                    toast(msg, 'warning');
                    input.dispatchEvent(new Event('change'));   // avisa a la vista previa de que ya no hay archivo
                } else if (file.size >= 30 * 1024 * 1024) {
                    toast('Archivo grande (' + fmtMb(file.size) + '): la subida puede tardar. No cierres la pantalla.', 'warning');
                }
            }, true);
        },

        bar: function (parent, after) {
            var track = document.createElement('div');
            track.setAttribute('role', 'progressbar');
            track.style.cssText = 'display:none;height:6px;border-radius:3px;background:var(--border);overflow:hidden;margin-top:6px;width:100%';
            var fill = document.createElement('div');
            fill.style.cssText = 'height:100%;width:0;background:var(--accent);transition:width .2s';
            track.appendChild(fill);
            if (after) { after.parentNode.insertBefore(track, after.nextSibling); }
            else { parent.appendChild(track); }
            return {
                set: function (pct) {
                    track.style.display = 'block';
                    fill.style.width = pct + '%';
                },
                hide: function () {
                    track.style.display = 'none';
                    fill.style.width = '0';
                }
            };
        }
    };

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('form[data-attach-upload]').forEach(init);
    });
})();
