<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Login - JP Preparation</title>

    <!-- Bootstrap -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="/public/assets/css/auth.css">
</head>
<body class="auth-bg">

<div class="container d-flex align-items-center justify-content-center vh-100">
    <div class="card auth-card shadow-lg">

        <div class="card-body">
            <h3 class="text-center mb-4">JP Preparation</h3>

            <?php if(session()->getFlashdata('error')): ?>
                <div class="alert alert-danger">
                    <?= session()->getFlashdata('error') ?>
                </div>
            <?php endif; ?>

            <form method="post" action="/login">

                <div class="mb-3">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label>Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>

                <button class="btn btn-dark w-100">Entrar</button>
            </form>

            <div class="text-center mt-3">
                <a href="/register">Crear cuenta</a>
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