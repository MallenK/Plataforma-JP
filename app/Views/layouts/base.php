<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $title ?? 'JP Preparation' ?></title>

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

<?= $this->renderSection('content') ?>

<!-- jQuery -->
<script src="<?= base_url('assets/js/vendor/jquery.min.js') ?>"></script>
<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- Toastify -->
<script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
<!-- App JS -->
<script src="<?= base_url('assets/js/app.js') ?>"></script>

<?= $this->renderSection('scripts') ?>
</body>
</html>
