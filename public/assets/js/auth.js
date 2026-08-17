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
        $('#infoBox').addClass('d-none');

        if (!messages) {
            box.addClass('d-none');
            return;
        }

        if (typeof messages === 'string') {
            box.html(messages);
        } else {
            Object.values(messages).forEach(msg => {
                box.append(`<div>${msg}</div>`);
            });
        }

        box.removeClass('d-none');
    }
    window.showAuthError = showErrorBox;

    function showInfoBox(message) {
        $('#errorBox').addClass('d-none').html('');
        $('#infoBox').removeClass('d-none').html(message);
    }

    // Deshabilita el botón de envío y muestra el spinner mientras dura la
    // petición — evita doble envío y da feedback de que algo está pasando.
    function setFormLoading($form, isLoading) {
        const $btn   = $form.find('button[type="submit"]');
        const $label = $btn.find('.btn-label');
        const $spin  = $btn.find('.btn-spinner');
        $btn.prop('disabled', isLoading);
        $label.toggleClass('d-none', isLoading);
        $spin.toggleClass('d-none', !isLoading);
    }

    // Mostrar/ocultar contraseña (login)
    $(document).on('click', '#togglePw', function () {
        const $input = $('#login-password');
        const isPw   = $input.attr('type') === 'password';
        $input.attr('type', isPw ? 'text' : 'password');
        $('#eyeIcon').toggleClass('bi-eye', !isPw).toggleClass('bi-eye-slash', isPw);
        $(this).attr('aria-label', isPw ? 'Ocultar contraseña' : 'Mostrar contraseña');
    });

    // LOGIN
    $(document).on('submit', '#loginForm', function (e) {
        e.preventDefault();
        const $form = $(this);
        if ($form.find('button[type="submit"]').prop('disabled')) return;

        showErrorBox(null);
        setFormLoading($form, true);

        let data = $form.serializeArray();
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
                setFormLoading($form, false);
            }
        });
    });


    // FORGOT
    $(document).on('submit', '#forgotForm', function (e) {
        e.preventDefault();
        const $form = $(this);
        if ($form.find('button[type="submit"]').prop('disabled')) return;

        showErrorBox(null);
        setFormLoading($form, true);

        let data = $form.serializeArray();
        data.push({ name: CSRF.name, value: CSRF.hash });

        $.post('/forgot-password', $.param(data))
            .done(() => {
                showInfoBox('Si el email existe, recibirás instrucciones para recuperar tu contraseña.');
                showToast("Solicitud enviada", "success");
                $form.trigger('reset');
            })
            .fail(() => {
                showErrorBox('Error al procesar la solicitud.');
                showToast("Error", "error");
            })
            .always(() => setFormLoading($form, false));
    });

    // RESET
    $(document).on('submit', '#resetForm', function (e) {
        e.preventDefault();
        const $form = $(this);
        if ($form.find('button[type="submit"]').prop('disabled')) return;

        showErrorBox(null);

        const password        = $form.find('[name="password"]').val();
        const passwordConfirm = $form.find('[name="password_confirm"]').val();

        if (password !== passwordConfirm) {
            showErrorBox('Las contraseñas no coinciden.');
            return;
        }

        setFormLoading($form, true);

        let data = $form.serializeArray();
        data.push({ name: CSRF.name, value: CSRF.hash });

        $.post('/reset-password', $.param(data))
            .done(() => {
                showToast("Contraseña actualizada", "success");
                setTimeout(() => {
                    window.location.href = '/login';
                }, 800);
            })
            .fail((xhr) => {
                let err = xhr.responseJSON?.error || "Error al restablecer la contraseña.";
                showErrorBox(err);
                setFormLoading($form, false);
            });
    });

});
