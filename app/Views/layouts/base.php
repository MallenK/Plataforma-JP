<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title ?? 'JP Preparation') ?></title>

    <!-- Favicon -->
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>⚽</text></svg>">
    <!-- Google Fonts — Montserrat + Oswald (titulares de las pantallas de acceso) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800&family=Oswald:wght@600;700&display=swap" rel="stylesheet">
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    <!-- Toastify (notificaciones auth) -->
    <link href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css" rel="stylesheet">
    <!-- App design system -->
    <link href="<?= base_url('assets/css/app.css') ?>" rel="stylesheet">

    <?= $this->renderSection('styles') ?>
</head>
<body>

<?php $envLabel = trim((string) env('APP_ENV_LABEL', '')); ?>
<?php if ($envLabel !== ''): ?>
    <div style="background:#b91c1c;color:#fff;text-align:center;
                font:700 11px/22px system-ui,sans-serif;letter-spacing:.5px;
                text-transform:uppercase;">
        <?= esc($envLabel) ?> · datos de prueba
    </div>
<?php endif; ?>

<?= $this->renderSection('content') ?>

<!-- jQuery -->
<script src="<?= base_url('assets/js/vendor/jquery.min.js') ?>"></script>
<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- Toastify -->
<script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
<!-- App JS -->
<script src="<?= base_url('assets/js/app.js') ?>"></script>
<!-- Componentes accesibles propios (Dialog / DropdownMenu / Tabs) -->
<script src="<?= base_url('assets/js/radix-ui.js') ?>"></script>

<?= $this->renderSection('scripts') ?>
</body>
</html>
