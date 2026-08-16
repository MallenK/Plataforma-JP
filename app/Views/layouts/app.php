<?= $this->extend('layouts/base') ?>

<?= $this->section('content') ?>

<link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/doc-preview.css') ?>">

<script>window.APP_BASE = '<?= rtrim(base_url(), '/') ?>';</script>

<div class="app-layout">

    <?= view('components/sidebar') ?>
    <div class="sidebar-overlay" id="sidebar-overlay"></div>

    <div class="main-wrap">

        <?= view('components/navbar') ?>

        <div class="page-body">
            <?= $this->renderSection('page_content') ?>
        </div>

    </div>

</div>

<?= view('partials/tutorial_init') ?>

<script src="<?= base_url('assets/js/doc-preview.js') ?>"></script>

<?php if (!in_array(session('role'), ['player', 'alumno'])): ?>
<!-- ── Diálogo ticket rápido — fuera de cualquier contenedor posicionado ── -->
<div class="ru-overlay" data-ru-dialog id="modalTicketRapido" hidden>
    <div class="ru-dialog ru-dialog-lg" role="dialog" aria-modal="true" aria-labelledby="modalTicketRapidoLabel">
        <div class="ru-dialog-header">
            <h3 id="modalTicketRapidoLabel">
                <i class="bi bi-ticket-perforated me-2" style="color:var(--accent)"></i>Reportar problema o sugerencia
            </h3>
            <button type="button" data-ru-dialog-close aria-label="Cerrar"><i class="bi bi-x-lg"></i></button>
        </div>
        <form id="form-ticket-rapido" enctype="multipart/form-data">
            <div class="ru-dialog-body">
                <input type="hidden" name="<?= csrf_token() ?>" value="<?= csrf_hash() ?>" id="ticket-rapido-csrf">

                <div class="form-group mb-3">
                    <label class="form-label">Título <span style="color:var(--danger)">*</span></label>
                    <input type="text" name="title" class="form-control-jp"
                           placeholder="Ej: Error al cargar la sección de clases" maxlength="255" required>
                </div>

                <div class="row g-3 mb-3">
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label class="form-label">Categoría <span style="color:var(--danger)">*</span></label>
                            <select name="category" class="form-control-jp" required>
                                <option value="">Selecciona una categoría</option>
                                <option value="bug">Error / Bug</option>
                                <option value="mejora">Sugerencia / Mejora</option>
                                <option value="consulta">Consulta general</option>
                                <option value="tecnico">Problema técnico</option>
                                <option value="otro">Otro</option>
                            </select>
                        </div>
                    </div>
                    <div class="col-sm-6">
                        <div class="form-group">
                            <label class="form-label">Prioridad <span style="color:var(--danger)">*</span></label>
                            <select name="priority" class="form-control-jp" required>
                                <option value="baja">Baja</option>
                                <option value="media" selected>Media</option>
                                <option value="alta">Alta</option>
                                <option value="urgente">Urgente</option>
                            </select>
                        </div>
                    </div>
                </div>

                <div class="form-group mb-3">
                    <label class="form-label">Descripción <span style="color:var(--danger)">*</span></label>
                    <textarea name="description" class="form-control-jp" rows="5"
                              placeholder="Describe el problema con detalle: ¿qué ocurrió?, ¿dónde?, ¿qué esperabas que pasara?"
                              maxlength="5000" required></textarea>
                </div>

                <div class="form-group mb-1">
                    <label class="form-label">
                        Adjunto <span style="color:var(--text-muted);font-weight:400">(opcional · máx. 10 MB)</span>
                    </label>
                    <input type="file" name="attachment" class="form-control-jp"
                           accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.doc,.docx,.xls,.xlsx,.txt,.mp4">
                    <div style="font-size:11.5px;color:var(--text-muted);margin-top:4px">JPG, PNG, PDF, DOCX, MP4</div>
                </div>

                <div class="alert-jp danger mt-3 d-none" id="ticket-rapido-error"></div>
            </div>
            <div class="ru-dialog-footer">
                <button type="button" class="btn-jp btn-jp-secondary" data-ru-dialog-close>Cancelar</button>
                <button type="submit" class="btn-jp btn-jp-primary" id="btn-ticket-rapido-submit">
                    <span class="btn-label"><i class="bi bi-send me-1"></i>Enviar ticket</span>
                    <span class="btn-spinner d-none">
                        <span class="spinner-border spinner-border-sm me-1"></span>Enviando...
                    </span>
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    const BASE      = '<?= base_url() ?>';
    const CSRF_NAME = '<?= csrf_token() ?>';
    let   csrfHash  = '<?= csrf_hash() ?>';

    const form    = document.getElementById('form-ticket-rapido');
    const errBox  = document.getElementById('ticket-rapido-error');
    const btnLbl  = document.querySelector('#btn-ticket-rapido-submit .btn-label');
    const btnSpin = document.querySelector('#btn-ticket-rapido-submit .btn-spinner');
    const modal   = document.getElementById('modalTicketRapido');

    modal?.addEventListener('ru-dialog-close', () => {
        form.reset();
        errBox.classList.add('d-none');
        btnLbl.classList.remove('d-none');
        btnSpin.classList.add('d-none');
    });

    form?.addEventListener('submit', async (e) => {
        e.preventDefault();
        errBox.classList.add('d-none');
        btnLbl.classList.add('d-none');
        btnSpin.classList.remove('d-none');

        const fd = new FormData(form);
        fd.set(CSRF_NAME, csrfHash);

        try {
            const res  = await fetch(BASE + 'tickets', {
                method:  'POST',
                headers: { 'X-Requested-With': 'XMLHttpRequest' },
                body:    fd,
            });
            const data = await res.json();

            if (data.csrf) csrfHash = data.csrf;
            document.getElementById('ticket-rapido-csrf').value = csrfHash;

            if (data.ok) {
                RadixUI.closeDialog('modalTicketRapido');
                window.location.href = data.redirect;
            } else {
                errBox.textContent = data.error ?? 'Error inesperado. Inténtalo de nuevo.';
                errBox.classList.remove('d-none');
                btnLbl.classList.remove('d-none');
                btnSpin.classList.add('d-none');
            }
        } catch (_) {
            errBox.textContent = 'Error de conexión. Inténtalo de nuevo.';
            errBox.classList.remove('d-none');
            btnLbl.classList.remove('d-none');
            btnSpin.classList.add('d-none');
        }
    });
})();
</script>
<?php endif; ?>

<?= $this->endSection() ?>
