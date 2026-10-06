<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title ?? 'JP Preparation') ?></title>

    <!-- Favicon -->
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>⚽</text></svg>">
    <!-- PWA: manifest, color de la barra y soporte iOS (Añadir a pantalla de inicio) -->
    <link rel="manifest" href="<?= base_url('manifest.webmanifest') ?>">
    <meta name="theme-color" content="#0f172a">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="JP Prep">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <link rel="apple-touch-icon" href="<?= base_url('assets/img/pwa/apple-touch-icon.png') ?>">
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
    <link href="<?= base_url('assets/css/app.css') ?>?v=<?= @filemtime(FCPATH . 'assets/css/app.css') ?: time() ?>" rel="stylesheet">
    <!-- Subidas con aviso previo y progreso: DEBE cargarse antes del contenido (scripts inline lo usan) -->
    <script src="<?= base_url('assets/js/attach-upload.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/attach-upload.js') ?: time() ?>"></script>

    <?= $this->renderSection('styles') ?>
</head>
<body>

<?= $this->renderSection('content') ?>

<!-- jQuery -->
<script src="<?= base_url('assets/js/vendor/jquery.min.js') ?>"></script>
<!-- DataTables (paginación en cliente) + vista lista/cuadrícula -->
<script src="<?= base_url('assets/js/datatables.min.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/datatables.min.js') ?: time() ?>"></script>
<script src="<?= base_url('assets/js/list-view.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/list-view.js') ?: time() ?>"></script>
<!-- Bootstrap JS -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
<!-- Toastify -->
<script src="https://cdn.jsdelivr.net/npm/toastify-js"></script>
<!-- App JS -->
<script src="<?= base_url('assets/js/app.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/app.js') ?: time() ?>"></script>
<!-- PWA: service worker, instalación y notificaciones push -->
<script src="<?= base_url('assets/js/pwa.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/pwa.js') ?: time() ?>" data-base="<?= base_url() ?>"></script>
<!-- Reporte de problemas desde alertas de error / permiso -->
<script src="<?= base_url('assets/js/error-report.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/error-report.js') ?: time() ?>"></script>
<!-- Componentes accesibles propios (Dialog / DropdownMenu / Tabs) -->
<script src="<?= base_url('assets/js/radix-ui.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/radix-ui.js') ?: time() ?>"></script>
<!-- Borrado dinámico con animación (attach-upload.js se carga en <head>) -->
<script src="<?= base_url('assets/js/ajax-delete.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/ajax-delete.js') ?: time() ?>"></script>

<?= $this->renderSection('scripts') ?>
</body>
</html>
