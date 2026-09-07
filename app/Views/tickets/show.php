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
$canManage   = $isManager ?? $isSuperAdmin;
$isClosed    = in_array($ticket['status'], ['resuelto', 'cerrado']);
?>

<!-- Cabecera -->
<div class="d-flex justify-content-between align-items-start mb-4 flex-wrap gap-3">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <a href="<?= base_url($canManage ? 'tickets/admin' : 'dashboard') ?>"
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
        <!-- Asignar -->
        <div class="dropdown">
            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="bi bi-person-check me-1"></i>Asignar
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <li>
                    <button class="dropdown-item btn-assign" data-assign="">
                        <i class="bi bi-dash-circle me-1"></i>Sin asignar
                    </button>
                </li>
                <li><hr class="dropdown-divider"></li>
                <?php foreach ($managers as $m): ?>
                <li>
                    <button class="dropdown-item btn-assign <?= (int) $m['id'] === (int) ($ticket['assigned_to'] ?? 0) ? 'active' : '' ?>"
                            data-assign="<?= (int) $m['id'] ?>">
                        <?= esc($m['name']) ?>
                        <span class="text-muted small">· <?= esc(ucfirst($m['role'])) ?></span>
                    </button>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <!-- Ámbito -->
        <div class="dropdown">
            <button class="btn btn-sm btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown">
                <i class="bi bi-diagram-3 me-1"></i>Ámbito
            </button>
            <ul class="dropdown-menu dropdown-menu-end">
                <?php foreach (($scopes ?? []) as $key => $label): ?>
                <li>
                    <button class="dropdown-item btn-change-scope <?= $key === ($ticket['scope'] ?? 'academia') ? 'active' : '' ?>"
                            data-scope="<?= $key ?>"><?= esc($label) ?></button>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <!-- Archivar -->
        <button class="btn btn-sm btn-outline-secondary" id="btn-archive"
                data-archived="<?= !empty($ticket['archived_at']) ? '1' : '0' ?>">
            <i class="bi bi-archive me-1"></i><?= !empty($ticket['archived_at']) ? 'Desarchivar' : 'Archivar' ?>
        </button>
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

<?php if (!empty($ticket['archived_at'])): ?>
<div class="alert alert-secondary d-flex align-items-center gap-2" style="font-size:13px">
    <i class="bi bi-archive-fill"></i>
    Este ticket está <b>archivado</b> (<?= date('d/m/Y', strtotime($ticket['archived_at'])) ?>).
    No aparece en las bandejas, pero se conserva su historial.
</div>
<?php endif; ?>

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
                    <span class="ticket-category-badge ticket-scope-badge ticket-scope--<?= esc($ticket['scope'] ?? 'academia') ?>">
                        <i class="bi bi-<?= ($ticket['scope'] ?? 'academia') === 'plataforma' ? 'hdd-network' : 'mortarboard' ?>"></i>
                        <?= esc(($scopes[$ticket['scope'] ?? 'academia'] ?? $ticket['scope']) ?? 'Academia') ?>
                    </span>
                    <?php if (($ticket['origin'] ?? 'manual') !== 'manual'): ?>
                    <span class="ticket-category-badge" style="background:#fef3c7;color:#92400e">
                        <i class="bi bi-<?= $ticket['origin'] === 'permiso' ? 'shield-lock' : 'bug' ?>"></i>
                        <?= $ticket['origin'] === 'permiso' ? 'Reporte de permiso' : 'Reporte de error' ?>
                    </span>
                    <?php endif; ?>
                </div>

                <?php
                // Contexto técnico — solo para gestores, solo si viene de una alerta.
                if ($canManage && ($ticket['origin'] ?? 'manual') !== 'manual'):
                    $ctx = json_decode((string) ($ticket['context'] ?? ''), true) ?: [];
                ?>
                <details class="mt-3" style="border:1px solid var(--border-color,#e5e7eb);border-radius:8px;padding:8px 12px;font-size:12.5px">
                    <summary style="cursor:pointer;font-weight:600;color:var(--text-muted,#6b7280)">
                        <i class="bi bi-terminal me-1"></i>Contexto técnico
                    </summary>
                    <div style="margin-top:8px;line-height:1.7;color:var(--text-secondary,#4b5563)">
                        <?php if (!empty($ticket['error_ref'])): ?>
                        <div><b>Referencia:</b> <code><?= esc($ticket['error_ref']) ?></code>
                            <span class="text-muted">— busca <code>TICKET-REF <?= esc($ticket['error_ref']) ?></code> en <code>writable/logs/</code></span>
                        </div>
                        <?php endif; ?>
                        <?php if (!empty($ctx['url'])): ?><div><b>Página:</b> <?= esc($ctx['url']) ?></div><?php endif; ?>
                        <?php if (!empty($ctx['endpoint'])): ?><div><b>Endpoint:</b> <?= esc($ctx['endpoint']) ?></div><?php endif; ?>
                        <?php if (!empty($ctx['message'])): ?><div><b>Mensaje:</b> <?= esc($ctx['message']) ?></div><?php endif; ?>
                        <?php if (!empty($ctx['user_agent'])): ?><div><b>Navegador:</b> <?= esc($ctx['user_agent']) ?></div><?php endif; ?>
                        <?php if (!empty($ctx['client_ts'])): ?><div><b>Hora (cliente):</b> <?= esc($ctx['client_ts']) ?></div><?php endif; ?>
                    </div>
                </details>
                <?php endif; ?>
            </div>
        </div>

        <!-- Respuestas -->
        <?php foreach ($replies as $reply): ?>
        <?php
            $isAdmin    = in_array($reply['user_role'], ['superadmin', 'admin']);
            $isInternal = !empty($reply['is_internal']);
        ?>
        <div class="ticket-message <?= $isAdmin ? 'ticket-message--admin' : '' ?> <?= $isInternal ? 'ticket-message--internal' : '' ?>">
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
                    <?php if ($isInternal): ?>
                    <span class="ticket-internal-badge">
                        <i class="bi bi-eye-slash me-1"></i>Nota interna
                    </span>
                    <?php endif; ?>
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

                    <div class="form-check form-switch mb-2">
                        <input class="form-check-input" type="checkbox" role="switch"
                               name="is_internal" value="1" id="reply-internal">
                        <label class="form-check-label small" for="reply-internal">
                            <i class="bi bi-eye-slash me-1"></i>Nota interna
                            <span class="text-muted">— solo la ven los gestores, no el solicitante</span>
                        </label>
                    </div>

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
                <dt>Ámbito</dt>
                <dd><?= esc($scopes[$ticket['scope'] ?? 'academia'] ?? 'Academia') ?></dd>
                <dt>Creado</dt>
                <dd><?= date('d/m/Y H:i', strtotime($ticket['created_at'])) ?></dd>
                <?php if ($canManage): ?>
                <dt>1ª respuesta</dt>
                <dd>
                    <?php if (!empty($ticket['first_response_at'])): ?>
                        <?= date('d/m/Y H:i', strtotime($ticket['first_response_at'])) ?>
                        <span class="text-muted">(<?= round((strtotime($ticket['first_response_at']) - strtotime($ticket['created_at'])) / 3600, 1) ?> h)</span>
                    <?php else: ?>
                        <span class="text-danger">Sin responder</span>
                    <?php endif; ?>
                </dd>
                <?php endif; ?>
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

        <div class="ticket-info-card mt-3">
            <h6 class="ticket-info-title">Asignado a</h6>
            <?php if (!empty($ticket['assignee_name'])): ?>
            <div class="d-flex align-items-center gap-2 mt-2">
                <?= avatar_html($ticket['assignee_avatar'] ?? null, $ticket['assignee_name'], 'ticket-avatar-sm') ?>
                <div>
                    <div class="fw-semibold" style="font-size:13px"><?= esc($ticket['assignee_name']) ?></div>
                    <div class="text-muted" style="font-size:11px"><?= esc(ucfirst($ticket['assignee_role'] ?? '')) ?></div>
                </div>
            </div>
            <?php else: ?>
            <p class="text-muted mb-0 mt-2" style="font-size:12px">Sin asignar</p>
            <?php endif; ?>
        </div>

        <?php if (!empty($events)): ?>
        <div class="ticket-info-card mt-3">
            <h6 class="ticket-info-title">Historial</h6>
            <ul class="ticket-timeline mt-2">
                <?php
                $evLabels = [
                    'created'         => fn($e) => 'creó el ticket',
                    'reply'           => fn($e) => 'respondió',
                    'internal_note'   => fn($e) => 'añadió una nota interna',
                    'status_changed'  => fn($e) => 'cambió el estado' . ($e['to_value'] ? ' a «' . ($statuses[$e['to_value']] ?? $e['to_value']) . '»' : ''),
                    'reopened'        => fn($e) => 'reabrió el ticket',
                    'closed'          => fn($e) => 'cerró el ticket',
                    'priority_changed'=> fn($e) => 'cambió la prioridad' . ($e['to_value'] ? ' a «' . ($priorities[$e['to_value']] ?? $e['to_value']) . '»' : ''),
                    'assigned'        => fn($e) => 'asignó el ticket' . ($e['to_value'] ? ' a ' . $e['to_value'] : ''),
                    'unassigned'      => fn($e) => 'quitó la asignación',
                    'scope_changed'   => fn($e) => 'cambió el ámbito' . ($e['to_value'] ? ' a «' . ($scopes[$e['to_value']] ?? $e['to_value']) . '»' : ''),
                    'archived'        => fn($e) => 'archivó el ticket',
                    'unarchived'      => fn($e) => 'desarchivó el ticket',
                ];
                ?>
                <?php foreach ($events as $e): ?>
                <li class="ticket-timeline-item">
                    <span class="ticket-timeline-dot"></span>
                    <div>
                        <span class="fw-semibold"><?= esc($e['actor_name'] ?? 'Sistema') ?></span>
                        <?= esc(isset($evLabels[$e['event_type']]) ? $evLabels[$e['event_type']]($e) : $e['event_type']) ?>
                        <div class="text-muted" style="font-size:11px"><?= date('d/m/Y H:i', strtotime($e['created_at'])) ?></div>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
        </div>
        <?php endif; ?>
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
                if (data.csrf) csrfHash = data.csrf;
                if (data.ok) { showToast('Estado actualizado a: ' + data.label); setTimeout(() => location.reload(), 800); }
                else if (!(window.handleApiError && window.handleApiError(data, 'tickets.status'))) {
                    showToast(data.error || 'No se pudo actualizar el estado', true);
                }
            } catch (_) { showToast('Error de conexión al actualizar el estado', true); }
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
                if (data.csrf) csrfHash = data.csrf;
                if (data.ok) { showToast('Prioridad actualizada a: ' + data.label); setTimeout(() => location.reload(), 800); }
                else if (!(window.handleApiError && window.handleApiError(data, 'tickets.priority'))) {
                    showToast(data.error || 'No se pudo actualizar la prioridad', true);
                }
            } catch (_) { showToast('Error de conexión al actualizar la prioridad', true); }
        });
    });

    // ── Asignar ────────────────────────────────────────────
    document.querySelectorAll('.btn-assign').forEach(btn => {
        btn.addEventListener('click', async () => {
            const fd = new FormData();
            fd.append(CSRF_NAME, csrfHash);
            fd.append('assigned_to', btn.dataset.assign || '');
            try {
                const res  = await fetch(BASE + 'tickets/' + TICKET_ID + '/asignar', {
                    method: 'POST',
                    headers: { 'X-Requested-With': 'XMLHttpRequest' },
                    body: fd,
                });
                const data = await res.json();
                if (data.csrf) csrfHash = data.csrf;
                if (data.ok) {
                    showToast(data.assignee_name ? ('Asignado a ' + data.assignee_name) : 'Asignación retirada');
                    setTimeout(() => location.reload(), 800);
                } else if (!(window.handleApiError && window.handleApiError(data, 'tickets.asignar'))) {
                    showToast(data.error || 'No se pudo asignar', true);
                }
            } catch (_) { showToast('Error de conexión al asignar', true); }
        });
    });

    // ── Cambiar ámbito ─────────────────────────────────────
    document.querySelectorAll('.btn-change-scope').forEach(btn => {
        btn.addEventListener('click', async () => {
            const fd = new FormData();
            fd.append(CSRF_NAME, csrfHash);
            fd.append('scope', btn.dataset.scope);
            try {
                const res  = await fetch(BASE + 'tickets/' + TICKET_ID + '/ambito', {
                    method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd,
                });
                const data = await res.json();
                if (data.csrf) csrfHash = data.csrf;
                if (data.ok) { showToast('Ámbito: ' + data.label); setTimeout(() => location.reload(), 800); }
                else if (!(window.handleApiError && window.handleApiError(data, 'tickets.ambito'))) {
                    showToast(data.error || 'No se pudo cambiar el ámbito', true);
                }
            } catch (_) { showToast('Error de conexión al cambiar el ámbito', true); }
        });
    });

    // ── Archivar / desarchivar ─────────────────────────────
    const btnArchive = document.getElementById('btn-archive');
    btnArchive?.addEventListener('click', async () => {
        const target = btnArchive.dataset.archived === '1' ? '0' : '1';
        const fd = new FormData();
        fd.append(CSRF_NAME, csrfHash);
        fd.append('archived', target);
        try {
            const res  = await fetch(BASE + 'tickets/' + TICKET_ID + '/archivar', {
                method: 'POST', headers: { 'X-Requested-With': 'XMLHttpRequest' }, body: fd,
            });
            const data = await res.json();
            if (data.csrf) csrfHash = data.csrf;
            if (data.ok) { showToast(target === '1' ? 'Ticket archivado' : 'Ticket desarchivado'); setTimeout(() => location.reload(), 800); }
            else if (!(window.handleApiError && window.handleApiError(data, 'tickets.archivar'))) {
                showToast(data.error || 'No se pudo archivar', true);
            }
        } catch (_) { showToast('Error de conexión al archivar', true); }
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
                if (data.csrf) csrfHash = data.csrf;
                if (data.ok) {
                    showToast('Respuesta enviada');
                    setTimeout(() => location.reload(), 600);
                } else if (!(window.handleApiError && window.handleApiError(data, 'tickets.reply'))) {
                    errBox.textContent = data.error ?? 'Error inesperado.';
                    errBox.classList.remove('d-none');
                }
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
