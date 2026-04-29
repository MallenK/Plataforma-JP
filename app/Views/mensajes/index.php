<?= $this->extend('layouts/app') ?>
<?= $this->section('page_content') ?>

<?php
helper('avatar');
$csrfName = csrf_token();
$csrfHash = csrf_hash();
$roleLabels = [
    'superadmin' => 'Super Admin', 'admin' => 'Admin',
    'coach' => 'Entrenador', 'alumno' => 'Jugador',
    'player' => 'Jugador', 'staff' => 'Staff',
];
?>

<div class="chat-layout">

    <!-- ── Panel izquierdo: lista de conversaciones ────────── -->
    <div class="chat-sidebar" id="chat-sidebar">

        <div class="chat-sidebar-header">
            <span class="fw-bold" style="font-size:15px">Mensajes</span>
            <button class="btn btn-sm btn-primary" id="btn-new-chat" title="Nueva conversación">
                <i class="bi bi-pencil-square"></i>
            </button>
        </div>

        <!-- Buscador de conversaciones -->
        <div class="chat-search-wrap">
            <input type="search" id="chat-search" class="chat-search"
                   placeholder="Buscar conversación…">
        </div>

        <!-- Lista -->
        <ul class="conv-list" id="conv-list">
            <?php if (empty($conversations)): ?>
            <li class="conv-empty" id="conv-empty">
                <i class="bi bi-chat-dots" style="font-size:2rem;opacity:.25"></i>
                <p>Sin conversaciones aún.<br>
                   <button class="btn btn-link p-0" id="btn-new-chat-empty">Inicia una nueva</button>
                </p>
            </li>
            <?php else: ?>
            <?php foreach ($conversations as $c): ?>
            <?php
                $otherRole = $roleLabels[$c['other_role']] ?? ucfirst($c['other_role']);
                $lastText  = $c['last_body'] ? mb_strimwidth($c['last_body'], 0, 55, '…') : ($c['last_file'] ? '📎 ' . $c['last_file'] : '');
                $unread    = (int)($c['unread_count'] ?? 0);
            ?>
            <li class="conv-item" data-conv-id="<?= $c['id'] ?>"
                data-other-id="<?= $c['other_user_id'] ?>">
                <div class="conv-avatar-wrap">
                    <?= avatar_html($c['other_avatar'] ?? null, $c['other_name'] ?? '?', 'conv-avatar') ?>
                </div>
                <div class="conv-info">
                    <div class="conv-name-row">
                        <span class="conv-name"><?= esc($c['other_name']) ?></span>
                        <?php if ($c['last_message_at']): ?>
                        <span class="conv-time"><?= timeAgo($c['last_message_at']) ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="conv-preview-row">
                        <span class="conv-preview"><?= esc($lastText) ?></span>
                        <?php if ($unread > 0): ?>
                        <span class="conv-unread-badge"><?= $unread ?></span>
                        <?php endif; ?>
                    </div>
                </div>
            </li>
            <?php endforeach; ?>
            <?php endif; ?>
        </ul>
    </div>

    <!-- ── Panel derecho: ventana de chat ─────────────────── -->
    <div class="chat-main" id="chat-main">

        <!-- Estado vacío -->
        <div class="chat-empty" id="chat-empty">
            <i class="bi bi-chat-text" style="font-size:3rem;opacity:.2"></i>
            <p class="mt-3 text-muted">Selecciona una conversación o<br>inicia una nueva.</p>
            <button class="btn btn-primary mt-2" id="btn-new-chat-main">
                <i class="bi bi-pencil-square me-1"></i>Nueva conversación
            </button>
        </div>

        <!-- Header del chat activo -->
        <div class="chat-header d-none" id="chat-header">
            <button class="btn btn-sm btn-link text-muted d-lg-none me-1" id="btn-back-chat">
                <i class="bi bi-arrow-left"></i>
            </button>
            <div class="chat-header-avatar" id="chat-header-avatar"></div>
            <div class="chat-header-info">
                <div class="chat-header-name" id="chat-header-name">—</div>
                <div class="chat-header-role" id="chat-header-role">—</div>
            </div>
        </div>

        <!-- Mensajes -->
        <div class="chat-messages d-none" id="chat-messages">
            <div class="chat-messages-inner" id="chat-messages-inner">
                <div class="chat-loading" id="chat-loading">
                    <div class="spinner-border spinner-border-sm text-muted"></div>
                </div>
            </div>
        </div>

        <!-- Input -->
        <div class="chat-input-bar d-none" id="chat-input-bar">
            <form id="form-message" enctype="multipart/form-data">
                <input type="hidden" name="conversation_id" id="input-conv-id">
                <input type="hidden" name="<?= $csrfName ?>" id="csrf-msg" value="<?= $csrfHash ?>">

                <!-- Preview adjunto -->
                <div id="msg-file-preview" class="msg-file-preview d-none">
                    <span class="msg-file-badge">
                        <i class="bi bi-paperclip me-1"></i>
                        <span id="msg-file-name-label"></span>
                        <button type="button" class="btn-close btn-close-sm ms-2"
                                id="btn-clear-file" aria-label="Quitar archivo"></button>
                    </span>
                </div>

                <div class="chat-input-row">
                    <label class="chat-attach-btn" title="Adjuntar archivo">
                        <i class="bi bi-paperclip"></i>
                        <input type="file" id="msg-file" name="attachment" class="d-none"
                               accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.doc,.docx,.xls,.xlsx,.txt,.mp4">
                    </label>
                    <textarea class="chat-input" id="msg-body" name="body"
                              rows="1" placeholder="Escribe un mensaje…"
                              maxlength="5000"></textarea>
                    <button type="submit" class="chat-send-btn" id="btn-send-msg" title="Enviar">
                        <i class="bi bi-send-fill"></i>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── Modal: nueva conversación ──────────────────────────── -->
<div class="modal fade" id="modalNewChat" tabindex="-1" aria-labelledby="modalNewChatLabel" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0">
                <h5 class="modal-title fw-bold" id="modalNewChatLabel">
                    <i class="bi bi-chat-dots text-primary me-2"></i>Nueva conversación
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="search" class="form-control mb-3" id="contact-search"
                       placeholder="Buscar por nombre…">
                <ul class="contact-list" id="contact-list">
                    <?php foreach ($contactables as $c): ?>
                    <?php $rl = $roleLabels[$c['role']] ?? ucfirst($c['role']); ?>
                    <li class="contact-item" data-user-id="<?= $c['id'] ?>"
                        data-name="<?= esc($c['name']) ?>">
                        <?= avatar_html($c['avatar'] ?? null, $c['name'], 'contact-avatar') ?>
                        <div class="contact-info">
                            <div class="contact-name"><?= esc($c['name']) ?></div>
                            <div class="contact-role"><?= esc($rl) ?></div>
                        </div>
                    </li>
                    <?php endforeach; ?>
                    <?php if (empty($contactables)): ?>
                    <li class="text-center text-muted py-3">No hay usuarios disponibles.</li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
    const BASE        = '<?= base_url() ?>';
    const MY_ID       = <?= (int) $currentUserId ?>;
    const CSRF_NAME   = '<?= $csrfName ?>';
    let   activeConvId  = null;
    let   lastMsgId     = 0;
    let   pollTimer     = null;
    let   pollConvTimer = null;

    // ── CSRF helpers ────────────────────────────────────────
    function csrfVal() {
        return document.getElementById('csrf-msg')?.value ?? '';
    }
    function refreshCsrf(token) {
        if (token) {
            document.getElementById('csrf-msg').value = token;
            document.getElementById('csrf-notif')?.setAttribute('value', token);
        }
    }
    function csrfBody() {
        return CSRF_NAME + '=' + encodeURIComponent(csrfVal());
    }

    // ── Abrir modal nueva conversación ───────────────────────
    function openNewChatModal() {
        const el = document.getElementById('modalNewChat');
        bootstrap.Modal.getOrCreateInstance(el).show();
        document.getElementById('contact-search').value = '';
        filterContacts('');
    }

    ['btn-new-chat', 'btn-new-chat-empty', 'btn-new-chat-main'].forEach(id => {
        document.getElementById(id)?.addEventListener('click', openNewChatModal);
    });

    // ── Filtrar contactos en modal ───────────────────────────
    document.getElementById('contact-search')?.addEventListener('input', function () {
        filterContacts(this.value.toLowerCase());
    });

    function filterContacts(query) {
        document.querySelectorAll('#contact-list .contact-item').forEach(item => {
            const name = item.dataset.name.toLowerCase();
            item.style.display = name.includes(query) ? '' : 'none';
        });
    }

    // ── Seleccionar contacto → abrir conversación ────────────
    document.getElementById('contact-list')?.addEventListener('click', async function (e) {
        const item = e.target.closest('.contact-item');
        if (!item) return;
        const otherId = parseInt(item.dataset.userId);
        bootstrap.Modal.getInstance(document.getElementById('modalNewChat')).hide();
        await openConversation(null, otherId);
    });

    // ── Clic en conversación existente ───────────────────────
    document.getElementById('conv-list')?.addEventListener('click', async function (e) {
        const item = e.target.closest('.conv-item');
        if (!item) return;
        const convId  = parseInt(item.dataset.convId);
        const otherId = parseInt(item.dataset.otherId);
        setActiveConvItem(convId);
        await openConversation(convId, otherId);
    });

    // ── Botón volver (móvil) ─────────────────────────────────
    document.getElementById('btn-back-chat')?.addEventListener('click', () => {
        document.getElementById('chat-sidebar').classList.remove('chat-sidebar-hidden');
        document.getElementById('chat-main').classList.remove('chat-main-active');
    });

    // ── Buscador de conversaciones ───────────────────────────
    document.getElementById('chat-search')?.addEventListener('input', function () {
        const q = this.value.toLowerCase();
        document.querySelectorAll('#conv-list .conv-item').forEach(item => {
            const name = item.querySelector('.conv-name')?.textContent.toLowerCase() ?? '';
            item.style.display = name.includes(q) ? '' : 'none';
        });
    });

    // ── Abrir conversación ───────────────────────────────────
    async function openConversation(convId, otherId) {
        stopPolling();
        showChatLoading();

        const formData = new FormData();
        formData.append('other_user_id', otherId);
        formData.append(CSRF_NAME, csrfVal());

        try {
            const res  = await fetch(BASE + 'mensajes/open', {
                method: 'POST', body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await res.json();
            if (!res.ok) { showChatError(data.error ?? 'Error'); return; }

            if (data.csrf) refreshCsrf(data.csrf);
            activeConvId = data.conversation_id;
            document.getElementById('input-conv-id').value = activeConvId;

            // Cabecera
            const ou = data.other_user;
            document.getElementById('chat-header-name').textContent = ou.name;
            document.getElementById('chat-header-role').textContent =
                {'superadmin':'Super Admin','admin':'Admin','coach':'Entrenador',
                 'alumno':'Jugador','player':'Jugador','staff':'Staff'}[ou.role] ?? ou.role;

            const avatarWrap = document.getElementById('chat-header-avatar');
            avatarWrap.innerHTML = buildAvatarHtml(ou.avatar, ou.name, 'chat-header-avatar-img');

            // Mensajes
            lastMsgId = 0;
            renderMessages(data.messages, true);

            // Limpiar badge de la conversación
            clearConvBadge(activeConvId);

            // Mostrar UI
            document.getElementById('chat-empty').classList.add('d-none');
            document.getElementById('chat-header').classList.remove('d-none');
            document.getElementById('chat-messages').classList.remove('d-none');
            document.getElementById('chat-input-bar').classList.remove('d-none');

            // Móvil
            document.getElementById('chat-sidebar').classList.add('chat-sidebar-hidden');
            document.getElementById('chat-main').classList.add('chat-main-active');

            scrollToBottom();
            document.getElementById('msg-body').focus();
            startPolling();

        } catch (err) {
            showChatError('Error de red.');
        }
    }

    // ── Enviar mensaje ───────────────────────────────────────
    document.getElementById('form-message')?.addEventListener('submit', async function (e) {
        e.preventDefault();
        if (!activeConvId) return;

        const bodyInput = document.getElementById('msg-body');
        const fileInput = document.getElementById('msg-file');
        const body      = bodyInput.value.trim();

        if (!body && !fileInput.files[0]) return;

        const formData = new FormData(this);
        formData.set(CSRF_NAME, csrfVal());

        // Deshabilitar envío mientras procesa
        const btn = document.getElementById('btn-send-msg');
        btn.disabled = true;

        try {
            const res  = await fetch(BASE + 'mensajes/send', {
                method: 'POST', body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await res.json();

            if (res.ok && data.ok) {
                bodyInput.value = '';
                bodyInput.style.height = 'auto';
                clearFileInput();
                appendMessage(data.message);
                scrollToBottom();
                lastMsgId = data.message.id;
                if (data.csrf) refreshCsrf(data.csrf);
            } else {
                showToast(data.error ?? 'Error al enviar.', 'error');
            }
        } catch (err) {
            showToast('Error de red.', 'error');
        }

        btn.disabled = false;
    });

    // Enter para enviar (Shift+Enter = nueva línea)
    document.getElementById('msg-body')?.addEventListener('keydown', function (e) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            document.getElementById('form-message').dispatchEvent(new Event('submit'));
        }
    });

    // Auto-resize del textarea
    document.getElementById('msg-body')?.addEventListener('input', function () {
        this.style.height = 'auto';
        this.style.height = Math.min(this.scrollHeight, 120) + 'px';
    });

    // ── Archivo adjunto ──────────────────────────────────────
    document.getElementById('msg-file')?.addEventListener('change', function () {
        if (this.files[0]) {
            document.getElementById('msg-file-name-label').textContent = this.files[0].name;
            document.getElementById('msg-file-preview').classList.remove('d-none');
        }
    });

    document.getElementById('btn-clear-file')?.addEventListener('click', clearFileInput);

    function clearFileInput() {
        document.getElementById('msg-file').value = '';
        document.getElementById('msg-file-preview').classList.add('d-none');
    }

    // ── Polling de mensajes nuevos ───────────────────────────
    function startPolling() {
        pollTimer = setInterval(pollMessages, 3000);
        pollConvTimer = setInterval(pollConversations, 10000);
    }
    function stopPolling() {
        clearInterval(pollTimer);
        clearInterval(pollConvTimer);
    }

    async function pollMessages() {
        if (!activeConvId) return;
        try {
            const res  = await fetch(BASE + 'mensajes/' + activeConvId + '/poll?since=' + lastMsgId, {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await res.json();
            if (data.messages && data.messages.length > 0) {
                data.messages.forEach(msg => {
                    if (parseInt(msg.sender_id) !== MY_ID) {
                        appendMessage(msg);
                    }
                    if (parseInt(msg.id) > lastMsgId) lastMsgId = parseInt(msg.id);
                });
                scrollToBottom();
                clearConvBadge(activeConvId);
            }
        } catch (_) {}
    }

    async function pollConversations() {
        try {
            const res  = await fetch(BASE + 'mensajes/conversations', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await res.json();
            if (data.conversations) updateConvList(data.conversations);
        } catch (_) {}
    }

    // ── Renderizado de mensajes ──────────────────────────────
    function renderMessages(msgs, reset = false) {
        const inner = document.getElementById('chat-messages-inner');
        if (reset) inner.innerHTML = '';

        const loadEl = document.getElementById('chat-loading');
        if (loadEl) loadEl.style.display = 'none';

        if (!msgs || msgs.length === 0) {
            if (reset) inner.innerHTML = '<div class="chat-no-msgs text-muted text-center py-4" style="font-size:13px">No hay mensajes aún. ¡Di hola!</div>';
            return;
        }

        msgs.forEach(msg => appendMessage(msg, false));
        if (msgs.length > 0) lastMsgId = Math.max(...msgs.map(m => parseInt(m.id)));
    }

    function appendMessage(msg, scroll = false) {
        const inner  = document.getElementById('chat-messages-inner');
        const isMine = parseInt(msg.sender_id) === MY_ID;
        const el     = document.createElement('div');
        el.className = 'chat-msg-wrap ' + (isMine ? 'mine' : 'theirs');
        el.dataset.msgId = msg.id;

        let content = '';

        if (msg.body) {
            content += '<div class="chat-bubble">' + escHtml(msg.body).replace(/\n/g, '<br>') + '</div>';
        }

        if (msg.file_name) {
            const url  = BASE + 'mensajes/download/' + msg.id;
            const icon = fileIcon(msg.file_mime ?? '');
            const size = msg.file_size ? ' <span class="msg-file-size">(' + formatBytes(parseInt(msg.file_size)) + ')</span>' : '';
            const isImage = (msg.file_mime ?? '').startsWith('image/');

            if (isImage) {
                content += '<div class="chat-bubble chat-bubble-file">'
                    + '<a href="' + url + '" target="_blank"><img src="' + url + '" class="chat-img-preview" alt="' + escHtml(msg.file_name) + '"></a>'
                    + '</div>';
            } else {
                content += '<div class="chat-bubble chat-bubble-file">'
                    + '<a href="' + url + '" class="chat-file-link">'
                    + icon + ' ' + escHtml(msg.file_name) + size
                    + '</a></div>';
            }
        }

        const time = formatTime(msg.created_at);
        el.innerHTML = '<div class="chat-msg-inner">' + content
            + '<div class="chat-msg-time">' + time + '</div>'
            + '</div>';

        // Quitar el mensaje de "sin mensajes" si existe
        const noMsgs = inner.querySelector('.chat-no-msgs');
        if (noMsgs) noMsgs.remove();

        inner.appendChild(el);
        if (scroll) scrollToBottom();
    }

    function updateConvList(conversations) {
        const roleMap = {'superadmin':'Super Admin','admin':'Admin','coach':'Entrenador',
                         'alumno':'Jugador','player':'Jugador','staff':'Staff'};
        const list  = document.getElementById('conv-list');
        const empty = document.getElementById('conv-empty');

        conversations.forEach(conv => {
            let item = list.querySelector('.conv-item[data-conv-id="' + conv.id + '"]');

            if (!item) {
                if (empty) empty.style.display = 'none';
                item = document.createElement('li');
                item.className = 'conv-item';
                item.dataset.convId  = conv.id;
                item.dataset.otherId = conv.other_user_id;
                if (parseInt(conv.id) === activeConvId) item.classList.add('active');
                const avatarHtml = buildAvatarHtml(conv.other_avatar ?? null, conv.other_name ?? '?', 'conv-avatar');
                const preview    = conv.last_body ? escHtml(conv.last_body.substring(0, 55)) : (conv.last_file ? '📎 ' + escHtml(conv.last_file) : '');
                const timeStr    = conv.last_message_at ? timeAgoJS(conv.last_message_at) : '';
                item.innerHTML =
                    '<div class="conv-avatar-wrap">' + avatarHtml + '</div>' +
                    '<div class="conv-info">' +
                      '<div class="conv-name-row">' +
                        '<span class="conv-name">' + escHtml(conv.other_name ?? '') + '</span>' +
                        (timeStr ? '<span class="conv-time">' + timeStr + '</span>' : '') +
                      '</div>' +
                      '<div class="conv-preview-row">' +
                        '<span class="conv-preview">' + preview + '</span>' +
                      '</div>' +
                    '</div>';
                list.insertBefore(item, list.firstChild);
                return;
            }

            const previewEl = item.querySelector('.conv-preview');
            const timeEl    = item.querySelector('.conv-time');
            const badgeEl   = item.querySelector('.conv-unread-badge');

            if (previewEl && conv.last_body) previewEl.textContent = conv.last_body.substring(0, 55);
            if (timeEl && conv.last_message_at) timeEl.textContent = timeAgoJS(conv.last_message_at);

            const unread = parseInt(conv.unread_count ?? 0);
            if (unread > 0 && parseInt(conv.id) !== activeConvId) {
                if (badgeEl) {
                    badgeEl.textContent = unread;
                    badgeEl.style.display = '';
                } else {
                    const badge = document.createElement('span');
                    badge.className = 'conv-unread-badge';
                    badge.textContent = unread;
                    item.querySelector('.conv-preview-row')?.appendChild(badge);
                }
            }
        });
    }

    // ── Helpers UI ───────────────────────────────────────────
    function setActiveConvItem(convId) {
        document.querySelectorAll('.conv-item').forEach(i => i.classList.remove('active'));
        document.querySelector('.conv-item[data-conv-id="' + convId + '"]')?.classList.add('active');
    }

    function clearConvBadge(convId) {
        const item  = document.querySelector('.conv-item[data-conv-id="' + convId + '"]');
        const badge = item?.querySelector('.conv-unread-badge');
        if (badge) badge.style.display = 'none';
    }

    function showChatLoading() {
        document.getElementById('chat-messages-inner').innerHTML =
            '<div class="chat-loading" id="chat-loading" style="display:flex"><div class="spinner-border spinner-border-sm text-muted"></div></div>';
    }

    function showChatError(msg) {
        document.getElementById('chat-messages-inner').innerHTML =
            '<div class="text-center text-danger py-4"><i class="bi bi-exclamation-circle me-1"></i>' + escHtml(msg) + '</div>';
    }

    function scrollToBottom() {
        const el = document.getElementById('chat-messages');
        if (el) el.scrollTop = el.scrollHeight;
    }

    function buildAvatarHtml(avatarPath, name, cssClass) {
        const initials = name.split(' ').slice(0,2).map(p => p[0]).join('').toUpperCase();
        if (avatarPath) {
            return '<img src="' + BASE + avatarPath + '" alt="' + escHtml(initials) + '" class="' + cssClass + '" style="object-fit:cover;border-radius:50%;">';
        }
        return '<div class="' + cssClass + '">' + initials + '</div>';
    }

    function fileIcon(mime) {
        if (mime.startsWith('image/'))       return '<i class="bi bi-file-image me-1"></i>';
        if (mime === 'application/pdf')       return '<i class="bi bi-file-pdf me-1"></i>';
        if (mime.includes('word'))            return '<i class="bi bi-file-word me-1"></i>';
        if (mime.includes('excel') || mime.includes('spreadsheet')) return '<i class="bi bi-file-excel me-1"></i>';
        if (mime === 'video/mp4')             return '<i class="bi bi-file-play me-1"></i>';
        return '<i class="bi bi-file-earmark me-1"></i>';
    }

    function formatBytes(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1048576).toFixed(1) + ' MB';
    }

    function formatTime(dt) {
        if (!dt) return '';
        const d = new Date(dt.replace(' ', 'T'));
        const now = new Date();
        const sameDay = d.toDateString() === now.toDateString();
        if (sameDay) return d.toLocaleTimeString('es-ES', {hour:'2-digit', minute:'2-digit'});
        return d.toLocaleDateString('es-ES', {day:'2-digit', month:'short'}) + ' ' +
               d.toLocaleTimeString('es-ES', {hour:'2-digit', minute:'2-digit'});
    }

    function timeAgoJS(dt) {
        if (!dt) return '';
        const d   = new Date(dt.replace(' ', 'T'));
        const now = new Date();
        const sec = Math.floor((now - d) / 1000);
        if (sec < 60)    return 'ahora';
        if (sec < 3600)  return Math.floor(sec/60) + 'm';
        if (sec < 86400) return Math.floor(sec/3600) + 'h';
        return Math.floor(sec/86400) + 'd';
    }

    function escHtml(str) {
        return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function showToast(msg, type = 'success') {
        if (typeof Toastify === 'undefined') return;
        Toastify({
            text: msg, duration: 3500, gravity: 'top', position: 'right',
            style: { background: type === 'success' ? 'var(--success)' : 'var(--danger)', borderRadius: '8px' }
        }).showToast();
    }

    // Limpiar polling al salir
    window.addEventListener('beforeunload', stopPolling);
})();
</script>
<?= $this->endSection() ?>
