<?= $this->extend('layouts/app') ?>
<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/tickets.css') ?>">
<?= $this->endSection() ?>
<?= $this->section('page_content') ?>

<?php
$csrfName = csrf_token();
$csrfHash = csrf_hash();
$pf = $prefill ?? null;

$isMgr = in_array(session('role'), ['admin', 'superadmin'], true);

// Los no-gestores no tienen bandeja de tickets: se vuelve al dashboard.
$backUrl = $isMgr ? base_url('tickets') : base_url('dashboard');

$pfTitle = '';
$pfDesc  = '';
$pfCat   = '';
if ($pf) {
    $pfCat   = $pf['category'];
    $secc    = $pf['url'] ? parse_url($pf['url'], PHP_URL_PATH) : '';
    $pfTitle = $pf['origin'] === 'permiso'
        ? 'No puedo acceder a ' . ($secc ?: 'una sección')
        : 'Error en ' . ($secc ?: 'la plataforma');
    $pfDesc  = "— Descríbenos qué estabas haciendo cuando ocurrió —\n\n"
             . "———————————————\n"
             . ($pf['url']     ? "Página: " . $pf['url'] . "\n" : '')
             . ($pf['message'] ? "Mensaje: " . $pf['message'] . "\n" : '')
             . ($pf['ref']     ? "Referencia: " . $pf['ref'] . "\n" : '');
}
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="fw-bold mb-1" style="font-size:1.25rem"><?= $pf ? 'Reportar un problema' : 'Nuevo Ticket' ?></h2>
        <p class="text-muted mb-0" style="font-size:13px">Describe el problema o sugerencia con el mayor detalle posible</p>
    </div>
    <a href="<?= $backUrl ?>" class="btn btn-sm btn-outline-secondary">
        <i class="bi bi-arrow-left me-1"></i>Volver
    </a>
</div>

<?php if ($pf): ?>
<div class="alert alert-info d-flex align-items-start gap-2" style="font-size:13px">
    <i class="bi bi-info-circle-fill mt-1"></i>
    <div>
        Estás reportando <?= $pf['origin'] === 'permiso' ? 'un problema de permisos' : 'un error' ?>.
        Hemos guardado automáticamente la página, la hora y la referencia técnica.
        Solo tienes que contarnos qué hacías y, si quieres, adjuntar una captura.
    </div>
</div>
<?php endif; ?>

<div class="ticket-form-wrap">
    <form id="ticket-create-form" enctype="multipart/form-data">
        <input type="hidden" name="<?= $csrfName ?>" value="<?= $csrfHash ?>" id="csrf-input">
        <input type="hidden" name="origin" id="tk-origin" value="<?= esc($pf['origin'] ?? 'manual', 'attr') ?>">
        <input type="hidden" name="error_ref" id="tk-error-ref" value="<?= esc($pf['ref'] ?? '', 'attr') ?>">
        <input type="hidden" name="context" id="tk-context" value="">

        <!-- Título -->
        <div class="mb-3">
            <label class="form-label fw-semibold">Título <span class="text-danger">*</span></label>
            <input type="text" name="title" id="ticket-title" class="form-control"
                   value="<?= esc($pfTitle, 'attr') ?>"
                   placeholder="Ej: Error al cargar la sección de clases" maxlength="255" required>
            <div class="form-text">Resume el problema en una frase corta y clara.</div>
        </div>

        <!-- Categoría + Prioridad -->
        <div class="row g-3 mb-3">
            <div class="col-sm-6">
                <label class="form-label fw-semibold">Categoría <span class="text-danger">*</span></label>
                <select name="category" class="form-select" required>
                    <option value="">Selecciona una categoría</option>
                    <?php foreach ($categories as $key => $label): ?>
                    <option value="<?= $key ?>" <?= $key === $pfCat ? 'selected' : '' ?>><?= esc($label) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-sm-6">
                <label class="form-label fw-semibold">
                    <?= $isMgr ? 'Prioridad' : '¿Qué urgencia tiene para ti?' ?>
                    <span class="text-danger">*</span>
                </label>
                <select name="priority" class="form-select" required>
                    <?php foreach ($priorities as $key => $label): ?>
                    <option value="<?= $key ?>" <?= $key === 'media' ? 'selected' : '' ?>>
                        <?= esc($label) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <div class="form-text">
                    <?= $isMgr
                        ? 'Selecciona "Urgente" solo si bloquea el uso de la plataforma.'
                        : 'Es orientativo: el equipo revisará y asignará la prioridad real.' ?>
                </div>
            </div>
        </div>

        <?php if (!empty($canChooseScope)): ?>
        <!-- Ámbito -->
        <div class="mb-3">
            <label class="form-label fw-semibold">¿A qué se refiere?</label>
            <div class="d-flex gap-3 flex-wrap">
                <?php foreach (($scopes ?? []) as $key => $label): ?>
                <label class="ticket-scope-option">
                    <input type="radio" name="scope" value="<?= $key ?>" <?= $key === 'academia' ? 'checked' : '' ?>>
                    <span><?= esc($label) ?></span>
                </label>
                <?php endforeach; ?>
            </div>
            <div class="form-text">
                <b>Academia</b>: entrenamientos, clases, bonos, alumnos.
                <b>Plataforma</b>: fallos técnicos o mejoras de esta web.
            </div>
        </div>
        <?php else: ?>
        <input type="hidden" name="scope" value="academia">
        <?php endif; ?>

        <!-- Descripción -->
        <div class="mb-3">
            <label class="form-label fw-semibold">Descripción <span class="text-danger">*</span></label>
            <textarea name="description" id="ticket-desc" class="form-control" rows="6"
                      placeholder="Explica el problema con detalle: ¿qué ocurrió?, ¿qué esperabas que ocurriera?, ¿en qué sección?"
                      maxlength="5000" required><?= esc($pfDesc) ?></textarea>
            <div class="d-flex justify-content-between mt-1">
                <div class="form-text">Incluye pasos para reproducir el problema si es posible.</div>
                <span class="ticket-char-count" id="desc-count">0 / 5000</span>
            </div>
        </div>

        <!-- Adjunto -->
        <div class="mb-4">
            <label class="form-label fw-semibold">Adjunto <span class="text-muted fw-normal">(opcional)</span></label>
            <div class="ticket-dropzone" id="ticket-dropzone">
                <input type="file" name="attachment" id="ticket-file" class="ticket-dropzone-input"
                       accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.doc,.docx,.xls,.xlsx,.txt,.mp4">
                <label for="ticket-file" class="ticket-dropzone-label">
                    <i class="bi bi-cloud-upload ticket-dropzone-icon"></i>
                    <span class="ticket-dropzone-text">Arrastra un archivo o <span class="text-primary">haz clic aquí</span></span>
                    <span class="ticket-dropzone-hint">JPG, PNG, PDF, DOCX, MP4 · Máx. 10 MB</span>
                </label>
                <div class="ticket-dropzone-preview d-none" id="file-preview">
                    <i class="bi bi-paperclip me-1"></i>
                    <span id="file-preview-name"></span>
                    <button type="button" class="ticket-dropzone-remove" id="file-remove" aria-label="Quitar archivo">
                        <i class="bi bi-x-lg"></i>
                    </button>
                </div>
            </div>
        </div>

        <div class="d-flex justify-content-end gap-2">
            <a href="<?= $backUrl ?>" class="btn btn-outline-secondary">Cancelar</a>
            <button type="submit" class="btn btn-primary" id="btn-submit-ticket">
                <span class="btn-label"><i class="bi bi-send me-1"></i>Enviar ticket</span>
                <span class="btn-spinner d-none">
                    <span class="spinner-border spinner-border-sm me-1"></span>Enviando...
                </span>
            </button>
        </div>

        <div class="alert alert-danger mt-3 d-none" id="ticket-error"></div>
    </form>
</div>

<?= $this->section('scripts') ?>
<script>
(function () {
    const BASE      = '<?= base_url() ?>';
    const CSRF_NAME = '<?= $csrfName ?>';
    let   csrfHash  = '<?= $csrfHash ?>';

    // Contexto técnico (solo si el ticket nace de un error/permiso)
    const originEl = document.getElementById('tk-origin');
    if (originEl && originEl.value !== 'manual') {
        const ctxEl = document.getElementById('tk-context');
        ctxEl.value = JSON.stringify({
            url:       <?= json_encode($pf['url'] ?? '') ?> || document.referrer || '',
            endpoint:  '',
            message:   <?= json_encode($pf['message'] ?? '') ?>,
            client_ts: new Date().toISOString(),
        });
    }

    // Contador de caracteres
    const desc      = document.getElementById('ticket-desc');
    const descCount = document.getElementById('desc-count');
    desc?.addEventListener('input', () => {
        descCount.textContent = desc.value.length + ' / 5000';
    });

    // Dropzone
    const fileInput   = document.getElementById('ticket-file');
    const preview     = document.getElementById('file-preview');
    const previewName = document.getElementById('file-preview-name');
    const removeBtn   = document.getElementById('file-remove');
    const dropzone    = document.getElementById('ticket-dropzone');

    fileInput?.addEventListener('change', () => {
        if (fileInput.files[0]) showPreview(fileInput.files[0].name);
    });
    removeBtn?.addEventListener('click', () => {
        fileInput.value = '';
        preview.classList.add('d-none');
        dropzone.querySelector('.ticket-dropzone-label').classList.remove('d-none');
    });
    dropzone?.addEventListener('dragover', e => { e.preventDefault(); dropzone.classList.add('dragover'); });
    dropzone?.addEventListener('dragleave', () => dropzone.classList.remove('dragover'));
    dropzone?.addEventListener('drop', e => {
        e.preventDefault();
        dropzone.classList.remove('dragover');
        if (e.dataTransfer.files[0]) {
            fileInput.files = e.dataTransfer.files;
            showPreview(e.dataTransfer.files[0].name);
        }
    });
    function showPreview(name) {
        previewName.textContent = name;
        preview.classList.remove('d-none');
        dropzone.querySelector('.ticket-dropzone-label').classList.add('d-none');
    }

    // Submit
    const form    = document.getElementById('ticket-create-form');
    const btnLbl  = form.querySelector('.btn-label');
    const btnSpin = form.querySelector('.btn-spinner');
    const errBox  = document.getElementById('ticket-error');

    form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        errBox.classList.add('d-none');
        btnLbl.classList.add('d-none');
        btnSpin.classList.remove('d-none');

        const fd = new FormData(form);
        fd.set(CSRF_NAME, csrfHash);

        try {
            const res  = await fetch(BASE + 'tickets', {
                method: 'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body: fd,
            });
            const data = await res.json();
            if (data.ok) {
                window.location.href = data.redirect;
            } else {
                showError(data.error ?? 'Error inesperado.');
            }
            if (data.csrf) csrfHash = data.csrf;
        } catch (_) {
            showError('Error de conexión. Inténtalo de nuevo.');
        } finally {
            btnLbl.classList.remove('d-none');
            btnSpin.classList.add('d-none');
        }
    });

    function showError(msg) {
        errBox.textContent = msg;
        errBox.classList.remove('d-none');
    }
})();
</script>
<?= $this->endSection() ?>

<?= $this->endSection() ?>
