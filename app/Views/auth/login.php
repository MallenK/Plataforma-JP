<?= $this->extend('layouts/base') ?>

<?= $this->section('content') ?>

<div class="auth-page">
    <div class="auth-card">

        <div class="auth-logo"><i class="bi bi-shield-lock-fill"></i></div>
        <h1 class="auth-title">JP Preparation</h1>
        <p class="auth-subtitle">Accede a tu plataforma</p>

        <div id="errorBox" class="alert-jp danger d-none"></div>

        <form id="loginForm" novalidate>

            <div class="form-group mb-3">
                <label class="form-label" for="login-email">Email</label>
                <input type="email" id="login-email" name="email" class="form-control-jp"
                       placeholder="tucorreo@ejemplo.com" required autocomplete="username">
            </div>

            <div class="form-group mb-3">
                <label class="form-label" for="login-password">Contraseña</label>
                <input type="password" id="login-password" name="password" class="form-control-jp"
                       placeholder="••••••••" required autocomplete="current-password">
            </div>

            <button type="submit" class="btn-jp btn-jp-primary w-100">
                <span class="btn-label">Entrar</span>
                <span class="btn-spinner d-none">
                    <span class="spinner-border spinner-border-sm me-1"></span>Entrando...
                </span>
            </button>
        </form>

        <div class="auth-links center">
            <a href="<?= base_url('forgot-password') ?>">¿Olvidaste tu contraseña?</a>
        </div>

    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>

<script>
    const CSRF = {
        name: "<?= csrf_token() ?>",
        hash: "<?= csrf_hash() ?>"
    };
</script>

<script src="<?= base_url('assets/js/auth.js') ?>"></script>
<?php if (service('request')->getGet('expired')): ?>
<script>window.showAuthError('Tu sesión ha expirado por inactividad. Inicia sesión de nuevo.');</script>
<?php endif; ?>

<?= $this->endSection() ?>
