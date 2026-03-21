<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Registro - JP Preparation</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
        
    <link rel="stylesheet" href="/public/assets/css/auth.css">
</head>
<body class="auth-bg">

<div class="container d-flex align-items-center justify-content-center vh-100">
    <div class="card auth-card shadow-lg">

        <div class="card-body">
            <h3 class="text-center mb-4">Crear cuenta</h3>

            <?php if(session()->getFlashdata('errors')): ?>
                <div class="alert alert-danger">
                    <?php foreach(session()->getFlashdata('errors') as $error): ?>
                        <div><?= $error ?></div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <form method="post" action="/register">

                <div class="mb-3">
                    <label>Nombre</label>
                    <input type="text" name="name" class="form-control" value="<?= old('name') ?>">
                </div>

                <div class="mb-3">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" value="<?= old('email') ?>">
                </div>

                <div class="mb-3">
                    <label>Password</label>
                    <input type="password" name="password" class="form-control">
                </div>

                <button class="btn btn-dark w-100">Registrarse</button>
            </form>

            <div class="text-center mt-3">
                <a href="/login">Ya tengo cuenta</a>
            </div>
        </div>

    </div>
</div>

<?php if(session()->getFlashdata('errors')): ?>
    <div class="alert alert-danger">
        <?php foreach(session()->getFlashdata('errors') as $error): ?>
            <div><?= $error ?></div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<script src="/public/assets/js/auth.js"></script>
</body>
</html>