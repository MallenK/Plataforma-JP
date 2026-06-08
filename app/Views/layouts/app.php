<?= $this->extend('layouts/base') ?>

<?= $this->section('content') ?>

<link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">
<link rel="stylesheet" href="<?= base_url('assets/css/doc-preview.css') ?>">

<script>window.APP_BASE = '<?= rtrim(base_url(), '/') ?>';</script>

<div class="app-layout">

    <?= view('components/sidebar') ?>
    <div class="sidebar-overlay" id="sidebar-overlay"></div>

    <div class="main-wrap">

        <?= view('components/navbar') ?>

        <div class="page-body">
            <?= $this->renderSection('page_content') ?>
        </div>

    </div>

</div>

<?= view('partials/tutorial_init') ?>

<script src="<?= base_url('assets/js/doc-preview.js') ?>"></script>

<?= $this->endSection() ?>
