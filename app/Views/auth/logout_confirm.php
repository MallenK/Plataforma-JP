<?= $this->extend('layouts/base') ?>

<?= $this->section('content') ?>

<div class="auth-page">
    <div class="auth-card">

        <div class="auth-logo"><i class="bi bi-box-arrow-right"></i></div>
        <h1 class="auth-title">Cerrar sesión</h1>
        <p class="auth-subtitle">¿Seguro que quieres salir de la plataforma?</p>

        <form method="post" action="<?= base_url('logout') ?>">
            <?= csrf_field() ?>
            <button type="submit" class="btn-jp btn-jp-primary w-100">
                <i class="bi bi-box-arrow-right me-1"></i>Sí, cerrar sesión
            </button>
        </form>

        <div class="auth-links center">
            <a href="<?= base_url('dashboard') ?>"><i class="bi bi-arrow-left me-1"></i>Volver</a>
        </div>

    </div>
</div>

<?= $this->endSection() ?>
