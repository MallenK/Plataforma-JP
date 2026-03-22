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


    
    // LOGIN
    $(document).on('submit', '#loginForm', function (e) {
        e.preventDefault();

        let data = $(this).serializeArray();
        data.push({ name: CSRF.name, value: CSRF.hash });

        $.ajax({
            url: '/login',
            method: 'POST',
            data: $.param(data),
            dataType: 'json',

            success: function (res) {
                showToast("Login correcto", "success");
                setTimeout(() => {
                    window.location.href = '/dashboard';
                }, 800);
            },

            error: function (xhr) {
                let error = xhr.responseJSON?.error || "Error en login";
                showToast(error, "error");
            }
        });
    });

    // REGISTER
    $(document).on('submit', '#registerForm', function (e) {
        e.preventDefault();

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
                let errors = xhr.responseJSON?.errors;

                if (errors) {
                    Object.values(errors).forEach(err => {
                        showToast(err, "error");
                    });
                } else {
                    showToast("Error en el registro", "error");
                }
            }
        });
    });

});