<?= $this->extend('layouts/base') ?>

<?= $this->section('content') ?>

<div class="login-stage">
    <div class="login-aura-layer login-aura-grid" aria-hidden="true"></div>
    <div class="login-aura-layer login-aura-glow-primary" aria-hidden="true"></div>
    <div class="login-aura-layer login-aura-glow-secondary" aria-hidden="true"></div>

    <div class="login-card">

        <h1 class="login-title">Bienvenido</h1>
        <p class="login-subtitle">Accede a tu plataforma JP Preparation</p>

        <div id="errorBox" class="login-error d-none"></div>

        <form id="loginForm" novalidate>

            <div class="login-field">
                <div class="login-input-wrap">
                    <i class="bi bi-envelope icon-leading"></i>
                    <input type="email" id="login-email" name="email" placeholder="Email" required autocomplete="username">
                </div>
            </div>

            <div class="login-field">
                <div class="login-input-wrap">
                    <i class="bi bi-lock icon-leading"></i>
                    <input type="password" id="login-password" name="password" placeholder="Contraseña" required autocomplete="current-password">
                    <button type="button" class="login-toggle-pw" id="togglePw" aria-label="Mostrar contraseña">
                        <i class="bi bi-eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>

            <div class="login-field-row">
                <label class="login-remember">
                    <input type="checkbox" id="remember" name="remember" value="1">
                    <span class="login-check-box">
                        <svg width="11" height="9" viewBox="0 0 11 9" fill="none"><path d="M1 4.3L4 7.3L10 1.3" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </span>
                    Recuérdame
                </label>
                <a href="<?= base_url('forgot-password') ?>">¿Olvidaste tu contraseña?</a>
            </div>

            <button type="submit" class="login-btn-submit">
                <span class="btn-label">Entrar</span>
                <span class="btn-spinner d-none">
                    <span class="spinner-border spinner-border-sm me-1" style="width:14px;height:14px"></span>Entrando…
                </span>
            </button>
        </form>

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
