$(document).ready(function () {

    console.log('Dashboard loaded');


    const container = $('#stats-container');
    if (container.length === 0) return;

    const url = container.data('url');
    let csrfName = container.data('csrf-name');
    let csrfHash = container.data('csrf-hash');

    let data = {};
    data[csrfName] = csrfHash;

    // 🔹 Loading state (mejor UX)
    $('#alumnos-count').text('...');
    $('#entrenadores-count').text('...');

    $.ajax({
        url: url,
        method: 'POST',
        dataType: 'json',
        data: data,

        success: function (res) {

            // 🔹 Actualizar métricas
            $('#alumnos-count').text(res.alumnos ?? 0);
            $('#entrenadores-count').text(res.entrenadores ?? 0);

            // 🔹 Actualizar CSRF si backend lo devuelve (muy importante en CI4)
            if (res.csrfHash) {
                csrfHash = res.csrfHash;
                container.data('csrf-hash', csrfHash);
            }
        },

        error: function (xhr) {

            console.error('Dashboard error:', xhr);

            // 🔹 UI consistente (no usar alert bootstrap cutre)
            container.html(`
                <div class="empty-state">
                    <div class="empty-state-box">
                        <h5>Error cargando datos</h5>
                        <p>Inténtalo de nuevo más tarde</p>
                    </div>
                </div>
            `);
        }
    });

});