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