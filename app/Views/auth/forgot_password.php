<?= $this->extend('layouts/base') ?>

<?= $this->section('content') ?>

<div class="auth-page">
    <div class="auth-card">

        <div class="auth-logo"><i class="bi bi-key-fill"></i></div>
        <h1 class="auth-title">Recuperar contraseña</h1>
        <p class="auth-subtitle">Introduce tu email y te enviaremos un enlace de recuperación.</p>

        <div id="errorBox" class="alert-jp danger d-none"></div>
        <div id="infoBox" class="alert-jp success d-none"></div>

        <form id="forgotForm" novalidate>

            <div class="form-group mb-3">
                <label class="form-label" for="forgot-email">Email</label>
                <input type="email" id="forgot-email" name="email" class="form-control-jp"
                       placeholder="tucorreo@ejemplo.com" required autocomplete="username">
            </div>

            <button type="submit" class="btn-jp btn-jp-primary w-100">
                <span class="btn-label">Enviar enlace</span>
                <span class="btn-spinner d-none">
                    <span class="spinner-border spinner-border-sm me-1"></span>Enviando...
                </span>
            </button>
        </form>

        <div class="auth-links center">
            <a href="<?= base_url('login') ?>"><i class="bi bi-arrow-left me-1"></i>Volver al login</a>
        </div>

    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
const CSRF = { name: "<?= csrf_token() ?>", hash: "<?= csrf_hash() ?>" };
</script>
<script src="<?= base_url('assets/js/auth.js') ?>"></script>
<?= $this->endSection() ?>
