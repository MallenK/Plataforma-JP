<?= $this->extend('layouts/base') ?>

<?php
$policy = $policy ?? ['minLength' => 8, 'requireUpper' => false, 'requireNumbers' => false, 'requireSpecial' => false];

$requirements = ['Al menos ' . $policy['minLength'] . ' caracteres'];
if ($policy['requireUpper'])   $requirements[] = 'Una letra mayúscula';
if ($policy['requireNumbers']) $requirements[] = 'Un número';
if ($policy['requireSpecial']) $requirements[] = 'Un carácter especial (!@#$...)';
?>

<?= $this->section('content') ?>

<div class="auth-page">
    <div class="auth-card">

        <div class="auth-logo"><i class="bi bi-shield-fill-check"></i></div>
        <h1 class="auth-title">Nueva contraseña</h1>
        <p class="auth-subtitle">Elige una contraseña segura para tu cuenta.</p>

        <div id="errorBox" class="alert-jp danger d-none"></div>

        <form id="resetForm" novalidate>
            <input type="hidden" name="token" value="<?= esc($token ?? '') ?>">

            <div class="form-group mb-3">
                <label class="form-label" for="reset-password">Nueva contraseña</label>
                <input type="password" id="reset-password" name="password" class="form-control-jp"
                       placeholder="••••••••" required autocomplete="new-password"
                       minlength="<?= (int)$policy['minLength'] ?>">
            </div>

            <div class="form-group mb-2">
                <label class="form-label" for="reset-password-confirm">Repite la contraseña</label>
                <input type="password" id="reset-password-confirm" name="password_confirm" class="form-control-jp"
                       placeholder="••••••••" required autocomplete="new-password"
                       minlength="<?= (int)$policy['minLength'] ?>">
            </div>

            <ul style="font-size:12px;color:var(--text-muted);margin:0 0 20px;padding-left:18px;line-height:1.7">
                <?php foreach ($requirements as $r): ?>
                <li><?= esc($r) ?></li>
                <?php endforeach; ?>
            </ul>

            <button type="submit" class="btn-jp btn-jp-primary w-100">
                <span class="btn-label">Guardar contraseña</span>
                <span class="btn-spinner d-none">
                    <span class="spinner-border spinner-border-sm me-1"></span>Guardando...
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
