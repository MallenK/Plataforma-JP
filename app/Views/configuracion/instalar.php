<?= $this->extend('layouts/app') ?>

<?= $this->section('page_content') ?>

<div class="mb-3">
    <h2 class="fw-bold mb-1" style="font-size:1.25rem">Configuración</h2>
    <p class="text-muted mb-0" style="font-size:13px">Instala la app en tu dispositivo para entrar con un toque y recibir avisos al instante.</p>
</div>

<?= view('configuracion/_subnav', ['active' => 'instalar']) ?>

<div style="max-width:860px">
    <?= view('partials/install_guide', ['share' => true]) ?>
</div>

<?= $this->endSection() ?>
