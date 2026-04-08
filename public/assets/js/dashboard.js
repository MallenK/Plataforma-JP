$(document).ready(function () {

    const container = $('#stats-container');
    if (container.length === 0) return;

    const url      = container.data('url');
    let csrfName   = container.data('csrf-name');
    let csrfHash   = container.data('csrf-hash');

    const data = {};
    data[csrfName] = csrfHash;

    $.ajax({
        url:      url,
        method:   'POST',
        dataType: 'json',
        data:     data,

        success: function (res) {
            // Métricas numéricas
            $('#alumnos-count').text(res.alumnos ?? 0);
            $('#entrenadores-count').text(res.entrenadores ?? 0);

            // Métricas opcionales (cuando el backend las devuelva)
            if (res.ingresos !== undefined) {
                $('#ingresos-count').text(res.ingresos + '€');
                const pct = Math.min(Math.round((res.ingresos / 5000) * 100), 100);
                $('#ingresos-bar').css('width', pct + '%');
            }

            if (res.alertas !== undefined) {
                $('#alertas-count').text(res.alertas);
            }

            // Progreso de alumnos (sobre una meta de 150)
            const alumnosPct = Math.min(Math.round(((res.alumnos ?? 0) / 150) * 100), 100);
            $('#alumnos-bar').css('width', alumnosPct + '%');

            // Estado de pagos
            if (res.pagos_pct !== undefined) {
                $('#pagos-pct').text(res.pagos_pct + '%');
                $('#pagos-bar').css('width', res.pagos_pct + '%');
                $('#pagos-desc').text(res.pagos_desc ?? '');
            }

            // Renovar CSRF
            if (res.csrfHash) {
                csrfHash = res.csrfHash;
                container.data('csrf-hash', csrfHash);
            }
        },

        error: function () {
            ['alumnos-count', 'entrenadores-count', 'ingresos-count', 'alertas-count'].forEach(function (id) {
                $('#' + id).text('—');
            });
        }
    });

});
