$(document).ready(function () {

    function showToast(message, type = "success") {
        Toastify({
            text: message,
            duration: 3000,
            gravity: "top",
            position: "right",
            style: {
                background: type === "success" ? "#28a745" : "#dc3545"
            }
        }).showToast();
    }

    function showErrorBox(messages) {
        let box = $('#errorBox');
        box.html('');

        if (!messages) return;

        if (typeof messages === 'string') {
            box.html(messages);
            return;
        }

        Object.values(messages).forEach(msg => {
            box.append(`<div>${msg}</div>`);
        });
    }

    // LOGIN
    $(document).on('submit', '#loginForm', function (e) {
        e.preventDefault();

        $('#errorBox').html('');

        let data = $(this).serializeArray();
        data.push({ name: CSRF.name, value: CSRF.hash });

        $.ajax({
            url: '/login',
            method: 'POST',
            data: $.param(data),
            dataType: 'json',

            success: function () {
                showToast("Login correcto", "success");
                setTimeout(() => {
                    window.location.href = '/dashboard';
                }, 800);
            },

            error: function (xhr) {
                let res = xhr.responseJSON;

                if (res?.errors) {
                    showErrorBox(res.errors);
                } else if (res?.error) {
                    showErrorBox(res.error);
                } else {
                    showErrorBox("Error en login");
                }

                showToast("Error en login", "error");
            }
        });
    });


    // FORGOT
    $(document).on('submit', '#forgotForm', function (e) {
        e.preventDefault();

        $('#errorBox').html('');

        let data = $(this).serializeArray();
        data.push({ name: CSRF.name, value: CSRF.hash });

        $.post('/forgot-password', $.param(data))
            .done(() => {
                $('#errorBox').html('Si el email existe, recibirás instrucciones');
                showToast("Solicitud enviada", "success");
            })
            .fail(() => {
                $('#errorBox').html('Error al procesar la solicitud');
                showToast("Error", "error");
            });
    });

    // RESET
    $(document).on('submit', '#resetForm', function (e) {
        e.preventDefault();

        console.log("Reset Pasword");

        let data = $(this).serializeArray();
        data.push({ name: CSRF.name, value: CSRF.hash });

        $.post('/reset-password', $.param(data))
            .done(() => {
                showToast("Password actualizada", "success");
                window.location.href = '/login';
            })
            .fail((xhr) => {
                let err = xhr.responseJSON?.error || "Error";
                showErrorBox(err);
            });
    });



    // REGISTER
    $(document).on('submit', '#registerForm', function (e) {
        e.preventDefault();

        $('#errorBox').html('');

        let data = $(this).serializeArray();
        data.push({ name: CSRF.name, value: CSRF.hash });

        $.ajax({
            url: '/register',
            method: 'POST',
            data: $.param(data),
            dataType: 'json',

            success: function () {
                showToast("Registro correcto", "success");

                setTimeout(() => {
                    window.location.href = '/login';
                }, 800);
            },

            error: function (xhr) {
                let res = xhr.responseJSON;

                if (res?.errors) {
                    showErrorBox(res.errors);
                } else if (res?.error) {
                    showErrorBox(res.error);
                } else {
                    showErrorBox("Error en el registro");
                }

                showToast("Error en registro", "error");
            }
        });
    });

});