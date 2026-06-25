<?= $this->extend('layouts/app') ?>
<?= $this->section('page_content') ?>

<?php
helper('avatar');
$userId       = session('id');
$role         = session('role');
$csrfName     = csrf_token();
$csrfHash     = csrf_hash();
$canSendGroup = in_array($role, ['superadmin', 'admin']);
$canSeeSent   = $canSeeSent ?? false;
$sentNotifications = $sentNotifications ?? [];
?>

<div class="d-flex justify-content-between align-items-center mb-4 flex-wrap gap-2">
    <div>
        <h2 class="fw-bold mb-1" style="font-size:1.25rem">Centro de notificaciones</h2>
        <p class="text-muted mb-0" style="font-size:13px">
            <?= $unread ?> sin leer
        </p>
    </div>
    <div class="d-flex gap-2 flex-wrap">
        <?php if ($unread > 0): ?>
        <button class="btn btn-sm btn-outline-secondary" id="btn-mark-all-read">
            <i class="bi bi-check2-all me-1"></i>Marcar todas leídas
        </button>
        <?php endif; ?>
        <?php if ($canSendNotif ?? true): ?>
        <button class="btn btn-sm btn-primary" data-bs-toggle="modal" data-bs-target="#modalNotif">
            <i class="bi bi-bell-fill me-1"></i>Nueva notificación
        </button>
        <?php endif; ?>
    </div>
</div>

<?php if ($canSeeSent): ?>
<!-- Tabs recibidas / enviadas -->
<ul class="nav nav-tabs mb-0" id="notif-tabs" role="tablist" style="border-bottom:none">
    <li class="nav-item" role="presentation">
        <button class="nav-link active" id="tab-recv" data-bs-toggle="tab"
                data-bs-target="#pane-recv" type="button" role="tab">
            <i class="bi bi-inbox me-1"></i>Recibidas
            <?php if ($unread > 0): ?>
            <span class="badge bg-danger ms-1" style="font-size:10px"><?= $unread ?></span>
            <?php endif; ?>
        </button>
    </li>
    <li class="nav-item" role="presentation">
        <button class="nav-link" id="tab-sent" data-bs-toggle="tab"
                data-bs-target="#pane-sent" type="button" role="tab">
            <i class="bi bi-send me-1"></i>Enviadas
            <?php if (!empty($sentNotifications)): ?>
            <span class="badge bg-secondary ms-1" style="font-size:10px"><?= count($sentNotifications) ?></span>
            <?php endif; ?>
        </button>
    </li>
</ul>
<?php endif; ?>

<div class="tab-content">

<!-- ── Recibidas ──────────────────────────────────────────────── -->
<div class="tab-pane fade show active" id="pane-recv" role="tabpanel">
<div class="card border-0 shadow-sm" style="border-radius:<?= $canSeeSent ? '0 var(--radius) var(--radius) var(--radius)' : 'var(--radius)' ?>">
    <div class="card-body p-0">
        <?php if (empty($notifications)): ?>
        <div class="text-center py-5 text-muted">
            <i class="bi bi-bell-slash" style="font-size:2.5rem;opacity:.3"></i>
            <p class="mt-3">No tienes notificaciones recibidas aún.</p>
        </div>
        <?php else: ?>
        <ul class="list-unstyled mb-0" id="notif-list">
            <?php foreach ($notifications as $n): ?>
            <?php
                $isUnread  = empty($n['recipient_read_at']);
                $isGroup   = $n['type'] === 'group';
                $timeAgo   = timeAgo($n['created_at']);
            ?>
            <li class="notif-item <?= $isUnread ? 'notif-unread' : '' ?>"
                data-id="<?= $n['id'] ?>">

                <div class="notif-avatar-wrap">
                    <?= avatar_html($n['sender_avatar'] ?? null, $n['sender_name'] ?? 'Sistema', 'notif-avatar') ?>
                    <?php if ($isGroup): ?>
                    <span class="notif-group-badge"><i class="bi bi-people-fill"></i></span>
                    <?php endif; ?>
                </div>

                <div class="notif-body">
                    <div class="notif-header">
                        <span class="notif-sender"><?= esc($n['sender_name'] ?? 'Sistema') ?></span>
                        <span class="notif-time"><?= esc($timeAgo) ?></span>
                    </div>
                    <div class="notif-title"><?= esc($n['title']) ?></div>
                    <div class="notif-text"><?= nl2br(esc($n['body'])) ?></div>

                    <?php if ($n['file_name']): ?>
                    <a href="<?= base_url('notificaciones/' . $n['id'] . '/download') ?>"
                       class="notif-file-link">
                        <i class="bi bi-paperclip me-1"></i><?= esc($n['file_name']) ?>
                        <?php if ($n['file_size']): ?>
                        <span class="text-muted">(<?= formatBytes((int)$n['file_size']) ?>)</span>
                        <?php endif; ?>
                    </a>
                    <?php endif; ?>
                </div>

                <?php if ($isUnread): ?>
                <button class="notif-read-btn" title="Marcar como leída"
                        data-id="<?= $n['id'] ?>">
                    <i class="bi bi-circle-fill"></i>
                </button>
                <?php endif; ?>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
</div>
</div>

<?php if ($canSeeSent): ?>
<!-- ── Enviadas ───────────────────────────────────────────────── -->
<div class="tab-pane fade" id="pane-sent" role="tabpanel">
<div class="card border-0 shadow-sm" style="border-radius:0 var(--radius) var(--radius) var(--radius)">
    <div class="card-body p-0">
        <?php if (empty($sentNotifications)): ?>
        <div class="text-center py-5 text-muted">
            <i class="bi bi-send" style="font-size:2.5rem;opacity:.3"></i>
            <p class="mt-3">No has enviado ninguna notificación aún.</p>
        </div>
        <?php else: ?>
        <ul class="list-unstyled mb-0">
            <?php foreach ($sentNotifications as $n): ?>
            <?php
                $isGroup  = $n['type'] === 'group';
                $timeAgo  = timeAgo($n['created_at']);
                $rcpTotal = (int)($n['recipient_count'] ?? 0);
                $rcpRead  = (int)($n['read_count'] ?? 0);
            ?>
            <li class="notif-item" data-id="<?= $n['id'] ?>">

                <div class="notif-avatar-wrap">
                    <span class="notif-avatar d-flex align-items-center justify-content-center bg-primary text-white"
                          style="font-size:1rem">
                        <i class="bi bi-<?= $isGroup ? 'people-fill' : 'person-fill' ?>"></i>
                    </span>
                </div>

                <div class="notif-body">
                    <div class="notif-header">
                        <span class="notif-sender">
                            <?= $isGroup ? 'Grupal' : 'Individual' ?>
                            <span class="badge bg-light text-secondary ms-1 fw-normal" style="font-size:10px">
                                <?= $rcpRead ?>/<?= $rcpTotal ?> leídas
                            </span>
                        </span>
                        <span class="notif-time"><?= esc($timeAgo) ?></span>
                    </div>
                    <div class="notif-title"><?= esc($n['title']) ?></div>
                    <div class="notif-text"><?= nl2br(esc($n['body'])) ?></div>

                    <?php if ($n['file_name']): ?>
                    <a href="<?= base_url('notificaciones/' . $n['id'] . '/download') ?>"
                       class="notif-file-link">
                        <i class="bi bi-paperclip me-1"></i><?= esc($n['file_name']) ?>
                        <?php if ($n['file_size']): ?>
                        <span class="text-muted">(<?= formatBytes((int)$n['file_size']) ?>)</span>
                        <?php endif; ?>
                    </a>
                    <?php endif; ?>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
</div>
</div>
<?php endif; ?>

</div><!-- /.tab-content -->

<!-- ── Modal: ver notificación completa ───────────────────────── -->
<div class="modal fade" id="modalViewNotif" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content border-0 shadow-lg">
            <div class="modal-header border-0 pb-0" style="align-items:flex-start">
                <div class="d-flex align-items-center gap-3 flex-grow-1 me-3">
                    <div id="vn-avatar-wrap" style="flex-shrink:0"></div>
                    <div>
                        <div class="fw-bold" id="vn-sender" style="font-size:15px;color:var(--text-h)"></div>
                        <div class="text-muted" id="vn-time" style="font-size:12px"></div>
                    </div>
                    <span id="vn-type-badge" class="badge ms-1" style="font-size:11px;font-weight:600"></span>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body pt-2">
                <h5 class="fw-bold mb-3" id="vn-title" style="color:var(--text-h)"></h5>
                <div id="vn-body" style="white-space:pre-wrap;color:var(--text-body);font-size:14px;line-height:1.75"></div>
                <div id="vn-file-wrap" class="mt-3 d-none">
                    <a id="vn-file-link" href="#" class="notif-file-link">
                        <i class="bi bi-paperclip me-1"></i>
                        <span id="vn-file-name"></span>
                    </a>
                </div>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cerrar</button>
            </div>
        </div>
    </div>
</div>

<!-- ── Modal: nueva notificación ──────────────────────────────── -->
<div class="modal fade" id="modalNotif" tabindex="-1" aria-labelledby="modalNotifLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <form id="form-notif" enctype="multipart/form-data">
                <input type="hidden" name="<?= $csrfName ?>" id="csrf-notif" value="<?= $csrfHash ?>">

                <div class="modal-header border-0 pb-0">
                    <h5 class="modal-title fw-bold" id="modalNotifLabel">
                        <i class="bi bi-bell-fill text-primary me-2"></i>Nueva notificación
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>

                <div class="modal-body">

                    <!-- Tipo -->
                    <div class="mb-3">
                        <label class="form-label fw-semibold">Tipo de envío</label>
                        <div class="d-flex gap-3">
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="type"
                                       id="type-individual" value="individual" checked>
                                <label class="form-check-label" for="type-individual">
                                    <i class="bi bi-person me-1"></i>Individual
                                </label>
                            </div>
                            <?php if ($canSendGroup): ?>
                            <div class="form-check">
                                <input class="form-check-input" type="radio" name="type"
                                       id="type-group" value="group">
                                <label class="form-check-label" for="type-group">
                                    <i class="bi bi-people me-1"></i>Grupal
                                </label>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Destinatario individual -->
                    <div id="field-recipient" class="mb-3">
                        <label for="recipient_id" class="form-label fw-semibold">Destinatario</label>
                        <select class="form-select" name="recipient_id" id="recipient_id">
                            <option value="">— Seleccionar usuario —</option>
                            <?php foreach ($recipients as $r): ?>
                            <?php
                                $roleLabel = match($r['role']) {
                                    'superadmin' => 'Super Admin',
                                    'admin'      => 'Admin',
                                    'coach'      => 'Entrenador',
                                    'alumno','player' => 'Jugador',
                                    'staff'      => 'Staff',
                                    default      => ucfirst($r['role']),
                                };
                            ?>
                            <option value="<?= $r['id'] ?>">
                                <?= esc($r['name']) ?> (<?= $roleLabel ?>)
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>

                    <!-- Grupo -->
                    <?php if ($canSendGroup): ?>
                    <div id="field-group" class="mb-3 d-none">
                        <label for="group" class="form-label fw-semibold">Grupo destinatario</label>
                        <select class="form-select" name="group" id="group">
                            <?php foreach ($groups as $key => $label): ?>
                            <option value="<?= $key ?>"><?= esc($label) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php endif; ?>

                    <!-- Título -->
                    <div class="mb-3">
                        <label for="notif-title" class="form-label fw-semibold">Título</label>
                        <input type="text" class="form-control" id="notif-title" name="title"
                               maxlength="255" placeholder="Ej: Convocatoria especial" required>
                    </div>

                    <!-- Mensaje -->
                    <div class="mb-3">
                        <label for="notif-body" class="form-label fw-semibold">Mensaje</label>
                        <textarea class="form-control" id="notif-body" name="body"
                                  rows="4" maxlength="2000" placeholder="Escribe aquí tu mensaje..."
                                  required></textarea>
                        <div class="text-end text-muted mt-1" style="font-size:11px">
                            <span id="body-counter">0</span>/2000
                        </div>
                    </div>

                    <!-- Adjunto -->
                    <div class="mb-3">
                        <label for="notif-file" class="form-label fw-semibold">
                            Archivo adjunto <span class="text-muted fw-normal">(opcional, máx. 5 MB)</span>
                        </label>
                        <input type="file" class="form-control" id="notif-file" name="attachment"
                               accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.doc,.docx,.xls,.xlsx,.txt,.mp4">
                        <div id="notif-file-preview" class="mt-2 d-none">
                            <span class="badge bg-light text-dark border">
                                <i class="bi bi-paperclip me-1"></i>
                                <span id="notif-file-name"></span>
                            </span>
                        </div>
                    </div>

                    <div id="notif-error" class="alert alert-danger d-none py-2"></div>
                </div>

                <div class="modal-footer border-0">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn btn-primary" id="btn-send-notif">
                        <span class="spinner-border spinner-border-sm d-none me-1" id="notif-spinner"></span>
                        <i class="bi bi-send-fill me-1" id="notif-icon"></i>Enviar
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
    const BASE = '<?= base_url() ?>';

    // ── Helpers ──────────────────────────────────────────────
    function csrf() {
        return {
            name:  '<?= $csrfName ?>',
            value: document.getElementById('csrf-notif')?.value ?? '<?= $csrfHash ?>'
        };
    }
    function refreshCsrf(token) {
        const el = document.getElementById('csrf-notif');
        if (el && token) el.value = token;
    }

    // ── Cambio tipo individual/grupal ────────────────────────
    document.querySelectorAll('input[name="type"]').forEach(r => {
        r.addEventListener('change', () => {
            const isGroup = r.value === 'group' && r.checked;
            document.getElementById('field-recipient')?.classList.toggle('d-none', isGroup);
            document.getElementById('field-group')?.classList.toggle('d-none', !isGroup);
        });
    });

    // ── Contador de caracteres ───────────────────────────────
    const bodyEl = document.getElementById('notif-body');
    const counter = document.getElementById('body-counter');
    if (bodyEl && counter) {
        bodyEl.addEventListener('input', () => { counter.textContent = bodyEl.value.length; });
    }

    // ── Preview del archivo ──────────────────────────────────
    document.getElementById('notif-file')?.addEventListener('change', function () {
        const preview = document.getElementById('notif-file-preview');
        const nameEl  = document.getElementById('notif-file-name');
        if (this.files[0]) {
            nameEl.textContent = this.files[0].name;
            preview.classList.remove('d-none');
        } else {
            preview.classList.add('d-none');
        }
    });

    // ── Enviar notificación ──────────────────────────────────
    document.getElementById('form-notif')?.addEventListener('submit', async function (e) {
        e.preventDefault();
        const btn     = document.getElementById('btn-send-notif');
        const spinner = document.getElementById('notif-spinner');
        const icon    = document.getElementById('notif-icon');
        const errEl   = document.getElementById('notif-error');

        btn.disabled = true;
        spinner.classList.remove('d-none');
        icon.classList.add('d-none');
        errEl.classList.add('d-none');

        const formData = new FormData(this);
        const c = csrf();
        formData.set(c.name, c.value);

        try {
            const res = await fetch(BASE + 'notificaciones/send', {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await res.json();

            if (res.ok && data.ok) {
                if (data.csrf) refreshCsrf(data.csrf);
                bootstrap.Modal.getInstance(document.getElementById('modalNotif')).hide();
                this.reset();
                document.getElementById('notif-file-preview')?.classList.add('d-none');
                if (counter) counter.textContent = '0';
                showToast('Notificación enviada a ' + data.recipients + ' destinatario(s).', 'success');
            } else {
                errEl.textContent = data.error ?? 'Error al enviar.';
                errEl.classList.remove('d-none');
                if (data.csrf) refreshCsrf(data.csrf);
            }
        } catch (err) {
            errEl.textContent = 'Error de red. Inténtalo de nuevo.';
            errEl.classList.remove('d-none');
        }

        btn.disabled = false;
        spinner.classList.add('d-none');
        icon.classList.remove('d-none');
    });

    // ── Marcar una notificación como leída ───────────────────
    document.querySelectorAll('.notif-read-btn').forEach(btn => {
        btn.addEventListener('click', async function () {
            const id   = this.dataset.id;
            const item = this.closest('.notif-item');
            await fetch(BASE + 'notificaciones/' + id + '/read', {
                method: 'POST',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest',
                    'Content-Type': 'application/x-www-form-urlencoded'
                },
                body: '<?= $csrfName ?>=' + encodeURIComponent(csrf().value)
            });
            item.classList.remove('notif-unread');
            this.remove();
            updateBellCount(-1);
        });
    });

    // ── Marcar todas como leídas ─────────────────────────────
    document.getElementById('btn-mark-all-read')?.addEventListener('click', async function () {
        await fetch(BASE + 'notificaciones/read-all', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: '<?= $csrfName ?>=' + encodeURIComponent(csrf().value)
        });
        document.querySelectorAll('.notif-item').forEach(i => i.classList.remove('notif-unread'));
        document.querySelectorAll('.notif-read-btn').forEach(b => b.remove());
        updateBellCount(0, true);
        this.remove();
    });

    // ── Datos de notificaciones recibidas ───────────────────────
    const notifMap = {};
    <?php foreach ($notifications as $n): ?>
    notifMap[<?= (int)$n['id'] ?>] = {
        title:     <?= json_encode($n['title']) ?>,
        body:      <?= json_encode($n['body'] ?? '') ?>,
        sender:    <?= json_encode($n['sender_name'] ?? 'Sistema') ?>,
        avatar:    <?= json_encode($n['sender_avatar'] ?? null) ?>,
        time:      <?= json_encode($n['created_at']) ?>,
        file_name: <?= json_encode($n['file_name'] ?? null) ?>,
        type:      <?= json_encode($n['type']) ?>,
        unread:    <?= empty($n['recipient_read_at']) ? 'true' : 'false' ?>
    };
    <?php endforeach; ?>

    // ── Abrir modal de detalle al hacer click en notificación ────
    document.querySelectorAll('#pane-recv .notif-item[data-id]').forEach(item => {
        item.style.cursor = 'pointer';
        item.addEventListener('click', function (e) {
            if (e.target.closest('.notif-read-btn')) return;

            const id   = parseInt(this.dataset.id);
            const data = notifMap[id];
            if (!data) return;

            document.getElementById('vn-title').textContent  = data.title;
            document.getElementById('vn-sender').textContent = data.sender;
            document.getElementById('vn-body').textContent   = data.body;
            document.getElementById('vn-time').textContent   = formatNotifTime(data.time);

            // Badge tipo
            const badge = document.getElementById('vn-type-badge');
            if (data.type === 'group') {
                badge.textContent = 'Grupal';
                badge.className   = 'badge ms-1';
                badge.style.background = '#7c3aed22';
                badge.style.color = '#7c3aed';
            } else {
                badge.textContent = 'Individual';
                badge.className   = 'badge ms-1';
                badge.style.background = 'var(--accent-light)';
                badge.style.color = 'var(--accent)';
            }

            // Avatar con iniciales
            const wrap    = document.getElementById('vn-avatar-wrap');
            const initials = data.sender.split(' ').map(w => w[0]).slice(0, 2).join('').toUpperCase();
            wrap.innerHTML = `<div class="notif-avatar d-flex align-items-center justify-content-center fw-bold" style="background:var(--accent-light);color:var(--accent);font-size:14px">${initials}</div>`;

            // Adjunto
            const fileWrap = document.getElementById('vn-file-wrap');
            if (data.file_name) {
                document.getElementById('vn-file-link').href        = BASE + 'notificaciones/' + id + '/download';
                document.getElementById('vn-file-name').textContent = data.file_name;
                fileWrap.classList.remove('d-none');
            } else {
                fileWrap.classList.add('d-none');
            }

            bootstrap.Modal.getOrCreateInstance(document.getElementById('modalViewNotif')).show();

            // Marcar leída automáticamente al ver el detalle
            if (data.unread) {
                const readBtn = this.querySelector('.notif-read-btn');
                if (readBtn) { readBtn.click(); data.unread = false; }
            }
        });
    });

    function formatNotifTime(dt) {
        if (!dt) return '';
        const d = new Date(dt.replace(' ', 'T') + 'Z');
        return d.toLocaleDateString('es-ES', {timeZone:'Etc/GMT-1', day:'2-digit', month:'long', year:'numeric'})
             + ' · '
             + d.toLocaleTimeString('es-ES', {timeZone:'Etc/GMT-1', hour:'2-digit', minute:'2-digit'});
    }

    function updateBellCount(delta, reset = false) {
        const badge = document.getElementById('notif-bell-count');
        if (!badge) return;
        if (reset) { badge.textContent = '0'; badge.classList.add('d-none'); return; }
        const cur = parseInt(badge.textContent) || 0;
        const nxt = Math.max(0, cur + delta);
        badge.textContent = nxt;
        nxt === 0 ? badge.classList.add('d-none') : badge.classList.remove('d-none');
    }

    function showToast(msg, type = 'success') {
        if (typeof Toastify === 'undefined') return;
        Toastify({
            text: msg,
            duration: 3500,
            gravity: 'top', position: 'right',
            style: { background: type === 'success' ? 'var(--success)' : 'var(--danger)', borderRadius: '8px' }
        }).showToast();
    }
})();
</script>
<?= $this->endSection() ?>
