<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title><?= $title ?? 'App' ?></title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
    <link href="<?= base_url('assets/css/app.css') ?>" rel="stylesheet">
    <link href="<?= base_url('assets/css/dashboard.css') ?>" rel="stylesheet">

    <?= $this->renderSection('styles') ?>

</head>
<body>

<?= $this->renderSection('content') ?>

<!-- jQuery SIEMPRE primero -->
<script src="<?= base_url('assets/js/vendor/jquery.min.js') ?>"></script>

<script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>

<!-- Luego app -->
<script src="<?= base_url('assets/js/app.js') ?>"></script>

<!-- Luego scripts por vista -->
<?= $this->renderSection('scripts') ?>
</body>
</html>