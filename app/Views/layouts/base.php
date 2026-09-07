<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title ?? "Mallen'k · Academy Software") ?></title>

    <!-- Favicon — monograma Mk (identidad Mallen'k) -->
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><rect width='100' height='100' rx='22' fill='%23111111'/><text x='50' y='50' dy='.34em' text-anchor='middle' font-family='Montserrat,Arial,sans-serif' font-size='52' font-weight='800' fill='%23FFC300'>Mk</text></svg>">
    <!-- Google Fonts — Montserrat (única familia de la identidad) -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
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
<!-- Reporte de problemas desde alertas de error / permiso -->
<script src="<?= base_url('assets/js/error-report.js') ?>"></script>
<!-- Componentes accesibles propios (Dialog / DropdownMenu / Tabs) -->
<script src="<?= base_url('assets/js/radix-ui.js') ?>"></script>

<?= $this->renderSection('scripts') ?>
</body>
</html>
