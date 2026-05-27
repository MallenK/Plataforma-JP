/**
 * JP Platform — Document Preview Modal
 *
 * API pública:
 *   openDocPreview(fileId, fileName, ext)
 *   closeDocPreview()
 *
 * Requiere que window.APP_BASE esté definido (se inyecta desde layouts/app.php).
 */
(function () {
    'use strict';

    /* ── Constantes ─────────────────────────────────────────────────── */
    const IMAGE_EXTS = new Set(['jpg', 'jpeg', 'png', 'gif', 'webp']);
    const VIDEO_EXTS = new Set(['mp4', 'webm', 'mov', 'avi']);
    const PDF_EXTS   = new Set(['pdf']);

    const ICON_MAP = {
        pdf:  'bi-file-earmark-pdf-fill',
        jpg:  'bi-file-earmark-image-fill',
        jpeg: 'bi-file-earmark-image-fill',
        png:  'bi-file-earmark-image-fill',
        gif:  'bi-file-earmark-image-fill',
        webp: 'bi-file-earmark-image-fill',
        mp4:  'bi-file-earmark-play-fill',
        webm: 'bi-file-earmark-play-fill',
        mov:  'bi-file-earmark-play-fill',
        avi:  'bi-file-earmark-play-fill',
        doc:  'bi-file-earmark-word-fill',
        docx: 'bi-file-earmark-word-fill',
        xls:  'bi-file-earmark-excel-fill',
        xlsx: 'bi-file-earmark-excel-fill',
        ppt:  'bi-file-earmark-ppt-fill',
        pptx: 'bi-file-earmark-ppt-fill',
    };

    /* ── Helpers ─────────────────────────────────────────────────────── */
    function baseUrl() {
        return (window.APP_BASE || '').replace(/\/$/, '');
    }

    function previewUrl(id) {
        return baseUrl() + '/documentacion/file/' + id + '/preview';
    }

    function downloadUrl(id) {
        return baseUrl() + '/documentacion/file/' + id + '/download';
    }

    function renderer(ext) {
        if (PDF_EXTS.has(ext))   return 'pdf';
        if (IMAGE_EXTS.has(ext)) return 'image';
        if (VIDEO_EXTS.has(ext)) return 'video';
        return 'none';
    }

    /* ── Construcción del DOM (se crea una sola vez) ─────────────────── */
    function buildOverlay() {
        const el = document.createElement('div');
        el.id = 'dp-overlay';
        el.setAttribute('role', 'dialog');
        el.setAttribute('aria-modal', 'true');
        el.setAttribute('aria-label', 'Vista previa del documento');
        el.innerHTML = `
            <div id="dp-modal">
                <div id="dp-header">
                    <div id="dp-title">
                        <i id="dp-icon" class="bi bi-file-earmark-fill"></i>
                        <span id="dp-name"></span>
                    </div>
                    <div id="dp-toolbar">
                        <a id="dp-dl" class="btn-jp btn-jp-secondary btn-jp-sm" href="#" title="Descargar">
                            <i class="bi bi-download"></i>
                            <span class="dp-btn-label">Descargar</span>
                        </a>
                        <button id="dp-close" class="btn-jp btn-jp-secondary btn-jp-sm btn-jp-icon" title="Cerrar (Esc)">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>
                <div id="dp-body">
                    <div id="dp-loader">
                        <div class="dp-spinner"></div>
                        <span>Cargando…</span>
                    </div>
                    <div id="dp-content"></div>
                    <div id="dp-error" style="display:none">
                        <i class="bi bi-file-earmark-x dp-err-icon"></i>
                        <p class="dp-err-msg"></p>
                        <a id="dp-err-dl" class="btn-jp btn-jp-primary btn-jp-sm" href="#">
                            <i class="bi bi-download me-1"></i>Descargar archivo
                        </a>
                    </div>
                </div>
            </div>`;
        document.body.appendChild(el);

        /* — Eventos de cierre — */
        el.addEventListener('click', function (e) {
            if (e.target === el) closeDocPreview();
        });
        document.getElementById('dp-close').addEventListener('click', closeDocPreview);

        return el;
    }

    function getOverlay() {
        return document.getElementById('dp-overlay') || buildOverlay();
    }

    /* ── Estados del modal ───────────────────────────────────────────── */
    function showLoader() {
        document.getElementById('dp-loader').style.display  = 'flex';
        document.getElementById('dp-content').style.display = 'none';
        document.getElementById('dp-error').style.display   = 'none';
    }

    function showContent() {
        document.getElementById('dp-loader').style.display  = 'none';
        document.getElementById('dp-content').style.display = 'flex';
        document.getElementById('dp-error').style.display   = 'none';
    }

    function showError(msg, dlUrl) {
        document.getElementById('dp-loader').style.display  = 'none';
        document.getElementById('dp-content').style.display = 'none';
        const errEl  = document.getElementById('dp-error');
        errEl.style.display = 'flex';
        errEl.querySelector('.dp-err-msg').textContent = msg;
        document.getElementById('dp-err-dl').href = dlUrl;
    }

    /* ── Renderizadores ──────────────────────────────────────────────── */
    function renderImage(src, alt, dlUrl) {
        const content = document.getElementById('dp-content');
        content.innerHTML = '';
        const img = document.createElement('img');
        img.id  = 'dp-img';
        img.alt = alt;
        img.addEventListener('load',  showContent);
        img.addEventListener('error', function () {
            showError('No se pudo cargar la imagen.', dlUrl);
        });
        img.src = src;
        content.appendChild(img);
    }

    function renderVideo(src, ext, dlUrl) {
        const content = document.getElementById('dp-content');
        content.innerHTML = '';
        const video  = document.createElement('video');
        video.id       = 'dp-video';
        video.controls = true;
        video.preload  = 'metadata';
        const source   = document.createElement('source');
        source.src  = src;
        source.type = ext === 'webm' ? 'video/webm' : 'video/mp4';
        video.appendChild(source);
        video.addEventListener('loadedmetadata', showContent);
        video.addEventListener('error', function () {
            showError('No se pudo cargar el vídeo.', dlUrl);
        });
        content.appendChild(video);
    }

    function renderPdf(src, title) {
        const content = document.getElementById('dp-content');
        content.innerHTML = '';
        const iframe = document.createElement('iframe');
        iframe.id    = 'dp-iframe';
        iframe.src   = src;
        iframe.title = title;
        /* PDF iframes no disparan error fiablemente; mostramos contenido cuando carga */
        iframe.addEventListener('load', showContent);
        content.appendChild(iframe);
    }

    /* ── API pública ─────────────────────────────────────────────────── */
    window.openDocPreview = function (fileId, fileName, ext) {
        ext = String(ext || '').toLowerCase();
        const pUrl = previewUrl(fileId);
        const dUrl = downloadUrl(fileId);
        const type = renderer(ext);

        const overlay = getOverlay();

        /* Cabecera */
        document.getElementById('dp-name').textContent = fileName;
        document.getElementById('dp-icon').className   = 'bi ' + (ICON_MAP[ext] || 'bi-file-earmark-fill');
        const dlBtn = document.getElementById('dp-dl');
        dlBtn.href = dUrl;
        dlBtn.setAttribute('download', fileName);

        /* Resetear área de contenido */
        document.getElementById('dp-content').innerHTML = '';
        showLoader();

        /* Abrir overlay */
        overlay.classList.add('open');
        document.body.style.overflow = 'hidden';

        /* Renderizar */
        if (type === 'image') {
            renderImage(pUrl, fileName, dUrl);
        } else if (type === 'video') {
            renderVideo(pUrl, ext, dUrl);
        } else if (type === 'pdf') {
            renderPdf(pUrl, fileName);
        } else {
            showError('Este tipo de archivo no admite vista previa.', dUrl);
        }
    };

    window.closeDocPreview = function () {
        const overlay = document.getElementById('dp-overlay');
        if (!overlay || !overlay.classList.contains('open')) return;

        overlay.classList.remove('open');
        document.body.style.overflow = '';

        /* Detener reproducción y liberar recursos tras la animación */
        setTimeout(function () {
            const content = document.getElementById('dp-content');
            if (content) content.innerHTML = '';
        }, 250);
    };

    /* ── Escape global ───────────────────────────────────────────────── */
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') window.closeDocPreview();
    });

})();
