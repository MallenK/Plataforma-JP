<?= $this->extend('layouts/app') ?>

<?= $this->section('page_content') ?>

<?php if ($flash = session()->getFlashdata('success')): ?>
    <div class="alert-jp success mb-3"><i class="bi bi-check-circle-fill me-2"></i><?= esc($flash) ?></div>
<?php endif; ?>

<div class="mb-4">
    <h2 class="fw-bold mb-1" style="font-size:1.25rem">Configuración de notificaciones</h2>
    <p class="text-muted mb-0" style="font-size:13px">Elige qué avisos quieres recibir y dónde.</p>
</div>

<?= view('configuracion/_subnav', ['active' => 'notificaciones']) ?>

<div style="max-width:860px">
    <?= view('configuracion/_notificaciones', ['prefs' => $prefs, 'categories' => $categories, 'returnTo' => 'page']) ?>
</div>

<?= $this->endSection() ?>
