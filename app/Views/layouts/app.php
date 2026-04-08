<?= $this->extend('layouts/base') ?>

<?= $this->section('content') ?>

<link rel="stylesheet" href="<?= base_url('assets/css/app.css') ?>">

<div class="app-layout">

    <?= view('components/sidebar') ?>

    <div class="main-wrap">

        <?= view('components/navbar') ?>

        <div class="page-body">
            <?= $this->renderSection('page_content') ?>
        </div>

    </div>

</div>

<?= $this->endSection() ?>
