<?= $this->extend('layouts/base') ?>

<?php
$policy = $policy ?? ['minLength' => 8, 'requireUpper' => false, 'requireNumbers' => false, 'requireSpecial' => false];
$forced = $forced ?? false;

$requirements = ['Al menos ' . (int) $policy['minLength'] . ' caracteres'];
if ($policy['requireUpper'])   $requirements[] = 'Una letra mayúscula';
if ($policy['requireNumbers']) $requirements[] = 'Un número';
if ($policy['requireSpecial']) $requirements[] = 'Un carácter especial (!@#$...)';
$requirements[] = 'Distinta de la actual';
?>

<?= $this->section('content') ?>

<div class="auth-page">
    <div class="auth-card">

        <div class="auth-logo"><i class="bi bi-shield-lock-fill"></i></div>
        <h1 class="auth-title"><?= $forced ? 'Define tu contraseña' : 'Cambiar contraseña' ?></h1>
        <p class="auth-subtitle">
            <?= $forced
                ? 'Tu cuenta usa una contraseña temporal. Elige una propia para continuar.'
                : 'Necesitarás tu contraseña actual para confirmar el cambio.' ?>
        </p>

        <?php if ($msg = session()->getFlashdata('error')): ?>
        <div class="alert-jp danger" style="margin-bottom:16px">
            <i class="bi bi-exclamation-triangle-fill me-2"></i><?= esc($msg) ?>
        </div>
        <?php endif; ?>

        <form method="post" action="<?= base_url('perfil/password') ?>" novalidate>
            <?= csrf_field() ?>

            <div class="form-group mb-3">
                <label class="form-label" for="current_password">Contraseña actual</label>
                <input type="password" id="current_password" name="current_password" class="form-control-jp"
                       placeholder="••••••••" required autocomplete="current-password">
            </div>

            <div class="form-group mb-3">
                <label class="form-label" for="new_password">Nueva contraseña</label>
                <input type="password" id="new_password" name="new_password" class="form-control-jp"
                       placeholder="••••••••" required autocomplete="new-password"
                       minlength="<?= (int) $policy['minLength'] ?>">
            </div>

            <div class="form-group mb-2">
                <label class="form-label" for="new_password_confirm">Repite la nueva contraseña</label>
                <input type="password" id="new_password_confirm" name="new_password_confirm" class="form-control-jp"
                       placeholder="••••••••" required autocomplete="new-password"
                       minlength="<?= (int) $policy['minLength'] ?>">
            </div>

            <ul style="font-size:12px;color:var(--text-muted);margin:0 0 20px;padding-left:18px;line-height:1.7">
                <?php foreach ($requirements as $r): ?>
                <li><?= esc($r) ?></li>
                <?php endforeach; ?>
            </ul>

            <button type="submit" class="btn-jp btn-jp-primary w-100">Guardar contraseña</button>
        </form>

        <div class="auth-links center">
            <?php if ($forced): ?>
            <form method="post" action="<?= base_url('logout') ?>" style="display:inline">
                <?= csrf_field() ?>
                <button type="submit" style="background:none;border:none;color:var(--accent);cursor:pointer;font:inherit;padding:0">
                    <i class="bi bi-box-arrow-right me-1"></i>Cerrar sesión
                </button>
            </form>
            <?php else: ?>
            <a href="<?= base_url('perfil') ?>"><i class="bi bi-arrow-left me-1"></i>Volver al perfil</a>
            <?php endif; ?>
        </div>

    </div>
</div>

<?= $this->endSection() ?>
