// app.js

/**
 * Muestra notificación toast no bloqueante en lugar de alert() nativo.
 * @param {string} msg    Texto a mostrar
 * @param {string} type   'error' | 'success' | 'warning' | 'info'
 */
function showAlert(msg, type = 'error') {
    const colors = {
        error:   'linear-gradient(135deg, #e74c3c, #c0392b)',
        success: 'linear-gradient(135deg, #27ae60, #1e8449)',
        warning: 'linear-gradient(135deg, #f39c12, #d68910)',
        info:    'linear-gradient(135deg, #2980b9, #1a6fa8)',
    };
    Toastify({
        text: msg,
        duration: 4000,
        close: true,
        gravity: 'top',
        position: 'right',
        style: { background: colors[type] ?? colors.error },
        stopOnFocus: true,
    }).showToast();
}

$(document).ready(function () {
    console.log("App global cargada fouewrhgiuerb");
});

// ── Sidebar toggle para móvil y tablet ─────────────────────────
(function () {
    const btn     = document.getElementById('mobile-menu-btn');
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.getElementById('sidebar-overlay');

    if (!btn || !sidebar) return;

    function openSidebar() {
        sidebar.classList.add('open');
        if (overlay) overlay.classList.add('active');
        document.body.style.overflow = 'hidden';
        btn.querySelector('i').className = 'bi bi-x-lg';
        btn.setAttribute('aria-expanded', 'true');
    }

    function closeSidebar() {
        sidebar.classList.remove('open');
        if (overlay) overlay.classList.remove('active');
        document.body.style.overflow = '';
        btn.querySelector('i').className = 'bi bi-list';
        btn.setAttribute('aria-expanded', 'false');
    }

    btn.addEventListener('click', function () {
        if (sidebar.classList.contains('open')) {
            closeSidebar();
        } else {
            openSidebar();
        }
    });

    if (overlay) overlay.addEventListener('click', closeSidebar);

    window.addEventListener('resize', function () {
        if (window.innerWidth > 1024) closeSidebar();
    });
})();
// Filas clicables: <tr class="row-link" data-href="..."> navega al hacer clic en la fila,
// salvo que el clic sea en un enlace, botón o control de formulario, o se esté seleccionando texto.
document.addEventListener('click', function (e) {
    var tr = e.target.closest && e.target.closest('tr.row-link[data-href]');
    if (!tr) return;
    if (e.target.closest('a, button, input, select, textarea, label, form')) return;
    var sel = window.getSelection && window.getSelection();
    if (sel && String(sel).length > 0) return;
    if (e.ctrlKey || e.metaKey) { window.open(tr.dataset.href, '_blank'); return; }
    window.location.href = tr.dataset.href;
});
