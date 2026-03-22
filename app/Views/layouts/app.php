<?= $this->extend('layouts/base') ?>

<?= $this->section('content') ?>

<div class="app">

    <?= view('components/sidebar') ?>

    <div class="main">

        <?= view('components/navbar') ?>

        <div class="content">
            <?= $this->renderSection('page_content') ?>
        </div>

    </div>

</div>

<?= $this->endSection() ?>