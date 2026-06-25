<?= $this->extend('layouts/app') ?>
<?= $this->section('styles') ?>
<link rel="stylesheet" href="<?= base_url('assets/css/tickets.css') ?>">
<?= $this->endSection() ?>
<?= $this->section('page_content') ?>

<?php
helper('avatar');
$userId   = session('id');
$role     = session('role');
$csrfName = csrf_token();
$csrfHash = csrf_hash();

$statusColors = [
    'abierto'     => 'ticket-status--open',
    'en_progreso' => 'ticket-status--progress',
    'resuelto'    => 'ticket-status--resolved',
    'cerrado'     => 'ticket-status--closed',
];
$priorityColors = [
    'baja'    => 'ticket-priority--low',
    'media'   => 'ticket-priority--medium',
    'alta'    => 'ticket-priority--high',
    'urgente' => 'ticket-priority--urgent',
];

$statusCls   = $statusColors[$ticket['status']]   ?? '';
$priorityCls = $priorityColors[$ticket['priority']] ?? '';
$isOwner     = (int)$ticket['user_id'] === (int)$userId;
$canManage   = $isSuperAdmin;
$isClosed    = in_array($ticket['status'], ['resuelto', 'cerrado']);
?>

<!-- Cabecera -->
<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-3">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <a href="<?= base_url($isSuperAdmin ? 'tickets/admin' : 'tickets') ?>"
               class="btn btn-sm btn-outline-secondary py-0 px-2">
                <i class="bi bi-arrow-left"></i>
            </a>
            <span class="ticket-number-lg"><?= esc($ticket['ticket_number']) ?></span>
            <span class="ticket-status <?= $statusCls ?>">
                <?= esc($statuses[$ticket['status']] ?? $ticket['status']) ?>
            </span>
            <span class="ticket-priority <?= $priorityCls ?>">
                <?= esc($priorities[$ticket['priority']] ?? $ticket['priority']) ?>
            </span>
        </div>
        <h2 class="fw-bold mb-0" style="font-size:1.2rem"><?= esc($ticket['title']) ?></h2>
    </div>

    <?php if ($canManage): ?>
    <div class="d-flex gap-2 flex-wrap">
        <!-- Cambiar estado -->
        <div class="dropdown">
            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="bi bi-arrow-repeat me-1"></i>Estado
            </button>
            <ul class="dropdown-menu">
                <?php foreach ($statuses as $key => $label): ?>
                <?php if ($key !== $ticket['status']): ?>
                <li>
                    <button class="dropdown-item btn-change-status" data-status="<?= $key ?>">
                        <?= esc($label) ?>
                    </button>
                </li>
                <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </div>
        <!-- Cambiar prioridad -->
        <div class="dropdown">
            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="bi bi-flag me-1"></i>Prioridad
            </button>
            <ul class="dropdown-menu">
                <?php foreach ($priorities as $key => $label): ?>
                <?php if ($key !== $ticket['priority']): ?>
                <li>
                    <button class="dropdown-item btn-change-priority" data-priority="<?= $key ?>">
                        <?= esc($label) ?>
                    </button>
                </li>
                <?php endif; ?>
                <?php endforeach; ?>
            </ul>
        </div>
    </div>
    <?php elseif ($isOwner && !$isClosed): ?>
    <!-- El creador puede cambiar prioridad si el ticket no está cerrado -->
    <div class="dropdown">
        <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
            <i class="bi bi-flag me-1"></i>Prioridad
        </button>
        <ul class="dropdown-menu">
            <?php foreach ($priorities as $key => $label): ?>
            <?php if ($key !== $ticket['priority']): ?>
            <li>
                <button class="dropdown-item btn-change-priority" data-priority="<?= $key ?>">
                    <?= esc($label) ?>
                </button>
            </li>
            <?php endif; ?>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>
</div>

<div class="ticket-show-layout">

    <!-- Hilo principal -->
    <div class="ticket-thread">

        <!-- Mensaje original -->
        <div class="ticket-message ticket-message--original">
            <div class="ticket-message-avatar">
                <?= avatar_html($ticket['user_avatar'], $ticket['user_name'], 'ticket-avatar') ?>
            </div>
            <div class="ticket-message-body">
                <div class="ticket-message-header">
                    <span class="ticket-message-author"><?= esc($ticket['user_name']) ?></span>
                    <span class="ticket-message-role-badge ticket-role--<?= esc($ticket['user_role']) ?>">
                        <?= esc(ucfirst($ticket['user_role'])) ?>
                    </span>
                    <span class="ticket-message-time">
                        <?= date('d/m/Y H:i', strtotime($ticket['created_at'])) ?>
                    </span>
                </div>
                <div class="ticket-message-content">
                    <?= nl2br(esc($ticket['description'])) ?>
                </div>

                <!-- Adjuntos del ticket -->
                <?php if (!empty($attachments)): ?>
                <div class="ticket-attachments mt-2">
                    <?php foreach ($attachments as $att): ?>
                    <a href="<?= base_url('tickets/download/' . $att['id']) ?>"
                       class="ticket-attach-chip" target="_blank">
                        <i class="bi bi-paperclip me-1"></i><?= esc($att['file_name']) ?>
                        <span class="ticket-attach-size"><?= round($att['file_size'] / 1024) ?> KB</span>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <!-- Metadatos -->
                <div class="ticket-message-meta mt-2">
                    <span class="ticket-category-badge">
                        <?= esc($categories[$ticket['category']] ?? $ticket['category']) ?>
                    </span>
                </div>
            </div>
        </div>

        <!-- Respuestas -->
        <?php foreach ($replies as $reply): ?>
        <?php $isAdmin = in_array($reply['user_role'], ['superadmin', 'admin']); ?>
        <div class="ticket-message <?= $isAdmin ? 'ticket-message--admin' : '' ?>">
            <div class="ticket-message-avatar">
                <?= avatar_html($reply['user_avatar'], $reply['user_name'], 'ticket-avatar') ?>
            </div>
            <div class="ticket-message-body">
                <div class="ticket-message-header">
                    <span class="ticket-message-author"><?= esc($reply['user_name']) ?></span>
                    <?php if ($isAdmin): ?>
                    <span class="ticket-staff-badge">
                        <i class="bi bi-shield-check me-1"></i>Equipo JP
                    </span>
                    <?php else: ?>
                    <span class="ticket-message-role-badge ticket-role--<?= esc($reply['user_role']) ?>">
                        <?= esc(ucfirst($reply['user_role'])) ?>
                    </span>
                    <?php endif; ?>
                    <span class="ticket-message-time">
                        <?= date('d/m/Y H:i', strtotime($reply['created_at'])) ?>
                    </span>
                </div>
                <div class="ticket-message-content">
                    <?= nl2br(esc($reply['body'])) ?>
                </div>

                <!-- Adjuntos de la respuesta -->
                <?php if (!empty($replyAttachments[$reply['id']])): ?>
                <div class="ticket-attachments mt-2">
                    <?php foreach ($replyAttachments[$reply['id']] as $att): ?>
                    <a href="<?= base_url('tickets/download/' . $att['id']) ?>"
                       class="ticket-attach-chip" target="_blank">
                        <i class="bi bi-paperclip me-1"></i><?= esc($att['file_name']) ?>
                        <span class="ticket-attach-size"><?= round($att['file_size'] / 1024) ?> KB</span>
                    </a>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- Formulario de respuesta (solo superadmin) -->
        <?php if ($canManage && !in_array($ticket['status'], ['cerrado'])): ?>
        <div class="ticket-reply-form" id="reply-form-wrap">
            <div class="ticket-message-avatar">
                <?= avatar_html(session('avatar'), session('name'), 'ticket-avatar') ?>
            </div>
            <div class="ticket-message-body flex-1">
                <form id="reply-form" enctype="multipart/form-data">
                    <input type="hidden" name="<?= $csrfName ?>" value="<?= $csrfHash ?>" id="reply-csrf">
                    <textarea name="body" id="reply-body" class="form-control mb-2" rows="4"
                              placeholder="Escribe tu respuesta..." maxlength="5000"></textarea>

                    <!-- Adjunto en respuesta -->
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <label for="reply-file" class="btn btn-sm btn-outline-secondary">
                            <i class="bi bi-paperclip me-1"></i>Adjuntar
                        </label>
                        <input type="file" name="attachment" id="reply-file" class="d-none"
                               accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.doc,.docx,.xls,.xlsx,.txt,.mp4">
                        <span class="text-muted small d-none" id="reply-file-name"></span>
                        <button type="button" class="btn btn-sm btn-link text-danger p-0 d-none" id="reply-file-remove">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>

                    <div class="d-flex justify-content-end">
                        <button type="submit" class="btn btn-sm btn-primary" id="btn-reply">
                            <span class="btn-label"><i class="bi bi-reply me-1"></i>Enviar respuesta</span>
                            <span class="btn-spinner d-none">
                                <span class="spinner-border spinner-border-sm me-1"></span>Enviando...
                            </span>
                        </button>
                    </div>
                    <div class="alert alert-danger mt-2 d-none" id="reply-error"></div>
                </form>
            </div>
        </div>
        <?php elseif ($isClosed): ?>
        <div class="ticket-closed-notice">
            <i class="bi bi-lock-fill me-2"></i>
            Este ticket está <?= esc($statuses[$ticket['status']]) ?> y no admite más respuestas.
        </div>
        <?php endif; ?>

    </div><!-- /ticket-thread -->

    <!-- Panel lateral -->
    <div class="ticket-sidebar-panel">
        <div class="ticket-info-card">
            <h6 class="ticket-info-title">Información</h6>
            <dl class="ticket-info-dl">
                <dt>Estado</dt>
                <dd><span class="ticket-status <?= $statusCls ?>"><?= esc($statuses[$ticket['status']] ?? $ticket['status']) ?></span></dd>
                <dt>Prioridad</dt>
                <dd><span class="ticket-priority <?= $priorityCls ?>"><?= esc($priorities[$ticket['priority']] ?? $ticket['priority']) ?></span></dd>
                <dt>Categoría</dt>
                <dd><?= esc($categories[$ticket['category']] ?? $ticket['category']) ?></dd>
                <dt>Creado</dt>
                <dd><?= date('d/m/Y H:i', strtotime($ticket['created_at'])) ?></dd>
                <?php if ($ticket['resolved_at']): ?>
                <dt>Resuelto</dt>
                <dd><?= date('d/m/Y H:i', strtotime($ticket['resolved_at'])) ?></dd>
                <?php endif; ?>
                <?php if ($ticket['closed_at']): ?>
                <dt>Cerrado</dt>
                <dd><?= date('d/m/Y H:i', strtotime($ticket['closed_at'])) ?></dd>
                <?php endif; ?>
            </dl>
        </div>

        <?php if ($canManage): ?>
        <div class="ticket-info-card mt-3">
            <h6 class="ticket-info-title">Solicitante</h6>
            <div class="d-flex align-items-center gap-2 mt-2">
                <?= avatar_html($ticket['user_avatar'], $ticket['user_name'], 'ticket-avatar-sm') ?>
                <div>
                    <div class="fw-semibold" style="font-size:13px"><?= esc($ticket['user_name']) ?></div>
                    <div class="text-muted" style="font-size:11px"><?= esc(ucfirst($ticket['user_role'])) ?></div>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>

</div>

<div id="toast-ticket" class="ticket-toast d-none"></div>

<?= $this->section('scripts') ?>
<script>
(function () {
    const BASE      = '<?= base_url() ?>';
    const TICKET_ID = <?= (int) $ticket['id'] ?>;
    const CSRF_NAME = '<?= $csrfName ?>';
    let   csrfHash  = '<?= $csrfHash ?>';

    // ── Cambiar estado ─────────────────────────────────────
    document.querySelectorAll('.btn-change-status').forEach(btn => {
        btn.addEventListener('click', async () => {
            const status = btn.dataset.status;
            const fd = new FormData();
            fd.append(CSRF_NAME, csrfHash);
            fd.append('status', status);
            try {
                const res  = await fetch(BASE + 'tickets/' + TICKET_ID + '/status', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: fd,
                });
                const data = await res.json();
                if (data.ok) { showToast('Estado actualizado a: ' + data.label); setTimeout(() => location.reload(), 800); }
                if (data.csrf) csrfHash = data.csrf;
            } catch (_) { showToast('Error al actualizar estado', true); }
        });
    });

    // ── Cambiar prioridad ──────────────────────────────────
    document.querySelectorAll('.btn-change-priority').forEach(btn => {
        btn.addEventListener('click', async () => {
            const priority = btn.dataset.priority;
            const fd = new FormData();
            fd.append(CSRF_NAME, csrfHash);
            fd.append('priority', priority);
            try {
                const res  = await fetch(BASE + 'tickets/' + TICKET_ID + '/priority', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: fd,
                });
                const data = await res.json();
                if (data.ok) { showToast('Prioridad actualizada a: ' + data.label); setTimeout(() => location.reload(), 800); }
                if (data.csrf) csrfHash = data.csrf;
            } catch (_) { showToast('Error al actualizar prioridad', true); }
        });
    });

    // ── Adjunto en respuesta ───────────────────────────────
    const replyFile       = document.getElementById('reply-file');
    const replyFileName   = document.getElementById('reply-file-name');
    const replyFileRemove = document.getElementById('reply-file-remove');

    replyFile?.addEventListener('change', () => {
        if (replyFile.files[0]) {
            replyFileName.textContent = replyFile.files[0].name;
            replyFileName.classList.remove('d-none');
            replyFileRemove.classList.remove('d-none');
        }
    });
    replyFileRemove?.addEventListener('click', () => {
        replyFile.value = '';
        replyFileName.classList.add('d-none');
        replyFileRemove.classList.add('d-none');
    });

    // ── Enviar respuesta ───────────────────────────────────
    const replyForm = document.getElementById('reply-form');
    if (replyForm) {
        const btnLbl  = replyForm.querySelector('.btn-label');
        const btnSpin = replyForm.querySelector('.btn-spinner');
        const errBox  = document.getElementById('reply-error');

        replyForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            errBox.classList.add('d-none');
            btnLbl.classList.add('d-none');
            btnSpin.classList.remove('d-none');

            const fd = new FormData(replyForm);
            fd.set(CSRF_NAME, csrfHash);

            try {
                const res  = await fetch(BASE + 'tickets/' + TICKET_ID + '/reply', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: fd,
                });
                const data = await res.json();
                if (data.ok) {
                    showToast('Respuesta enviada');
                    setTimeout(() => location.reload(), 600);
                } else {
                    errBox.textContent = data.error ?? 'Error inesperado.';
                    errBox.classList.remove('d-none');
                }
                if (data.csrf) csrfHash = data.csrf;
            } catch (_) {
                errBox.textContent = 'Error de conexión.';
                errBox.classList.remove('d-none');
            } finally {
                btnLbl.classList.remove('d-none');
                btnSpin.classList.add('d-none');
            }
        });
    }

    function showToast(msg, isError = false) {
        const t = document.getElementById('toast-ticket');
        t.textContent = msg;
        t.className = 'ticket-toast' + (isError ? ' ticket-toast--error' : '');
        t.classList.remove('d-none');
        setTimeout(() => t.classList.add('d-none'), 2500);
    }
})();
</script>
<?= $this->endSection() ?>

<?= $this->endSection() ?>
