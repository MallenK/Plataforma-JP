<?php
helper('avatar');
// ── Datos del usuario ─────────────────────────────────────
$name   = session('name')   ?? 'Usuario';
$role   = session('role')   ?? '';
$avatar = session('avatar');

$roleLabel = match($role) {
    'superadmin' => 'Super Admin',
    'admin'      => 'Administrador',
    'coach'      => 'Entrenador',
    'alumno'     => 'Alumno',
    'staff'      => 'Staff',
    default      => ucfirst($role),
};

// ── Título de página derivado de la URI ───────────────────
// Las variables $pageTitle / $pageSubtitle definidas en las vistas
// hijo no se propagan aquí porque son variables PHP locales, no del
// data array del controlador. Derivamos el título de la URI para no
// depender de que cada controlador los pase explícitamente.
$uriSegment = service('uri')->getSegment(1) ?: 'dashboard';

$navTitles = [
    'dashboard'    => ['Dashboard',      strtoupper($roleLabel) . ' · PANEL DE CONTROL'],
    'alumnos'      => ['Alumnos',        'Gestión de alumnos'],
    'alumno'       => ['Mi ficha',       'Perfil de alumno'],
    'entrenadores' => ['Entrenadores',   'Equipo técnico'],
    'organizador'  => ['Organizador',    'Calendario y planificación'],
    'clases'       => ['Clases',         'Sesiones de entrenamiento'],
    'bonos'        => ['Bonos',          'Membresías y bonos'],
    'documentacion'=> ['Documentación',  'Material formativo'],
    'finanzas'     => ['Finanzas',       'Control económico'],
    'configuracion'  => ['Configuración',   'Ajustes de la plataforma'],
    'perfil'         => ['Mi perfil',       'Información de cuenta'],
    'notificaciones' => ['Notificaciones',  'Centro de notificaciones'],
    'mensajes'       => ['Mensajes',        'Chat y conversaciones'],
    'tickets'        => ['Soporte',         'Sistema de tickets'],
];

// Si el controlador pasó $pageTitle/$pageSubtitle explícitamente, se usan esos
if (!isset($pageTitle) || !isset($pageSubtitle)) {
    [$pageTitle, $pageSubtitle] = $navTitles[$uriSegment] ?? ['JP Preparation', ''];
}
?>

<header class="topbar">

    <button class="mobile-menu-btn" id="mobile-menu-btn" aria-label="Menú">
        <i class="bi bi-list"></i>
    </button>

    <div class="topbar-title">
        <h1><?= esc($pageTitle) ?></h1>
        <?php if ($pageSubtitle): ?><p><?= esc($pageSubtitle) ?></p><?php endif; ?>
    </div>

    <div class="topbar-right">

        <!-- Mensajes -->
        <a href="<?= base_url('mensajes') ?>" class="topbar-btn topbar-btn-link" title="Mensajes" id="topbar-msg-btn">
            <i class="bi bi-chat-dots"></i>
            <span class="topbar-badge d-none" id="msg-bell-count">0</span>
        </a>

        <!-- Notificaciones -->
        <div class="topbar-notif-wrap" id="topbar-notif-wrap">
            <button class="topbar-btn" title="Notificaciones" id="topbar-notif-btn"
                    aria-haspopup="true" aria-expanded="false">
                <i class="bi bi-bell"></i>
                <span class="topbar-badge d-none" id="notif-bell-count">0</span>
            </button>

            <!-- Dropdown -->
            <div class="notif-dropdown" id="notif-dropdown" aria-hidden="true">
                <div class="notif-dropdown-header">
                    <span class="fw-semibold">Notificaciones</span>
                    <a href="<?= base_url('notificaciones') ?>" class="notif-dropdown-link">Ver todas</a>
                </div>
                <ul class="notif-dropdown-list" id="notif-dropdown-list">
                    <li class="notif-dropdown-empty">
                        <i class="bi bi-bell-slash"></i> Sin notificaciones
                    </li>
                </ul>
                <div class="notif-dropdown-footer">
                    <button class="btn btn-sm w-100" id="notif-mark-all-btn"
                            style="font-size:12px;color:var(--accent)">
                        Marcar todas como leídas
                    </button>
                </div>
            </div>
        </div>

        <!-- Perfil -->
        <a href="<?= base_url('perfil') ?>" class="topbar-user" style="text-decoration:none">
            <div class="topbar-user-info">
                <div class="topbar-user-name"><?= esc($name) ?></div>
                <div class="topbar-user-role"><?= esc($roleLabel) ?></div>
            </div>
            <?= avatar_html($avatar, $name, 'topbar-avatar') ?>
        </a>

    </div>

<script>
(function () {
    const BASE      = '<?= base_url() ?>';
    const CSRF_NAME = '<?= csrf_token() ?>';
    let   csrfHash  = '<?= csrf_hash() ?>';
    let   isOpen    = false;

    const btn      = document.getElementById('topbar-notif-btn');
    const dropdown = document.getElementById('notif-dropdown');
    const list     = document.getElementById('notif-dropdown-list');
    const bellBadge = document.getElementById('notif-bell-count');
    const msgBadge  = document.getElementById('msg-bell-count');

    // ── Toggle dropdown ──────────────────────────────────────
    btn?.addEventListener('click', (e) => {
        e.stopPropagation();
        isOpen = !isOpen;
        dropdown.classList.toggle('open', isOpen);
        btn.setAttribute('aria-expanded', isOpen);
        if (isOpen) fetchNotifications();
    });

    document.addEventListener('click', (e) => {
        if (isOpen && !dropdown.contains(e.target) && e.target !== btn) {
            isOpen = false;
            dropdown.classList.remove('open');
            btn.setAttribute('aria-expanded', 'false');
        }
    });

    // ── Cargar notificaciones ────────────────────────────────
    async function fetchNotifications() {
        try {
            const res  = await fetch(BASE + 'notificaciones/latest', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await res.json();
            renderBell(data.unread, data.notifications);
        } catch (_) {}
    }

    function renderBell(unread, notifications) {
        // Badge campana
        if (unread > 0) {
            bellBadge.textContent = unread > 99 ? '99+' : unread;
            bellBadge.classList.remove('d-none');
        } else {
            bellBadge.classList.add('d-none');
        }

        // Lista
        if (!notifications || notifications.length === 0) {
            list.innerHTML = '<li class="notif-dropdown-empty"><i class="bi bi-bell-slash"></i> Sin notificaciones</li>';
            return;
        }

        list.innerHTML = notifications.slice(0, 8).map(n => {
            const unreadCls = !n.recipient_read_at ? 'notif-dd-item--unread' : '';
            const time      = timeAgoJS(n.created_at);
            const icon      = n.type === 'group' ? '<i class="bi bi-people-fill notif-dd-group"></i>' : '';
            return `<li class="notif-dd-item ${unreadCls}" data-id="${n.id}">
                ${icon}
                <div class="notif-dd-body">
                    <div class="notif-dd-title">${escH(n.title)}</div>
                    <div class="notif-dd-sender">${escH(n.sender_name ?? '')}</div>
                </div>
                <span class="notif-dd-time">${time}</span>
            </li>`;
        }).join('');

        // Clic en item → marcar leída
        list.querySelectorAll('.notif-dd-item[data-id]').forEach(item => {
            item.addEventListener('click', () => markRead(parseInt(item.dataset.id), item));
        });
    }

    async function markRead(id, el) {
        el.classList.remove('notif-dd-item--unread');
        const cur = parseInt(bellBadge.textContent) || 0;
        const nxt = Math.max(0, cur - 1);
        bellBadge.textContent = nxt;
        if (nxt === 0) bellBadge.classList.add('d-none');

        await fetch(BASE + 'notificaciones/' + id + '/read', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: CSRF_NAME + '=' + encodeURIComponent(csrfHash)
        });
    }

    // ── Marcar todas leídas desde dropdown ───────────────────
    document.getElementById('notif-mark-all-btn')?.addEventListener('click', async () => {
        list.querySelectorAll('.notif-dd-item--unread').forEach(i => i.classList.remove('notif-dd-item--unread'));
        bellBadge.classList.add('d-none');

        await fetch(BASE + 'notificaciones/read-all', {
            method: 'POST',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/x-www-form-urlencoded'
            },
            body: CSRF_NAME + '=' + encodeURIComponent(csrfHash)
        });
    });

    // ── Polling cada 30 s ────────────────────────────────────
    async function pollAll() {
        try {
            const res  = await fetch(BASE + 'notificaciones/latest', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await res.json();
            // Solo actualizar badge, no re-renderizar lista si está abierta
            if (!isOpen && data.unread !== undefined) {
                if (data.unread > 0) {
                    bellBadge.textContent = data.unread > 99 ? '99+' : data.unread;
                    bellBadge.classList.remove('d-none');
                } else {
                    bellBadge.classList.add('d-none');
                }
            }
        } catch (_) {}

        // Mensajes no leídos
        try {
            const res  = await fetch(BASE + 'mensajes/conversations', {
                headers: { 'X-Requested-With': 'XMLHttpRequest' }
            });
            const data = await res.json();
            if (data.conversations) {
                const totalUnread = data.conversations.reduce((sum, c) => sum + parseInt(c.unread_count ?? 0), 0);
                const sidebarBadge = document.getElementById('sidebar-msg-badge');
                if (totalUnread > 0) {
                    const txt = totalUnread > 99 ? '99+' : totalUnread;
                    msgBadge.textContent = txt;
                    msgBadge.classList.remove('d-none');
                    if (sidebarBadge) { sidebarBadge.textContent = txt; sidebarBadge.classList.remove('d-none'); }
                } else {
                    msgBadge.classList.add('d-none');
                    if (sidebarBadge) sidebarBadge.classList.add('d-none');
                }
            }
        } catch (_) {}
    }

    // Carga inicial y polling
    pollAll();
    setInterval(pollAll, 30000);

    // ── Helpers ──────────────────────────────────────────────
    function timeAgoJS(dt) {
        if (!dt) return '';
        const d   = new Date(dt.replace(' ', 'T'));
        const sec = Math.floor((Date.now() - d) / 1000);
        if (sec < 60)    return 'ahora';
        if (sec < 3600)  return Math.floor(sec/60) + ' min';
        if (sec < 86400) return Math.floor(sec/3600) + ' h';
        return Math.floor(sec/86400) + ' d';
    }

    function escH(str) {
        return String(str ?? '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
    }
})();
</script>

</header>
