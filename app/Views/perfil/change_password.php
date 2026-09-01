<?= $this->extend('layouts/base') ?>

<?php
$policy = $policy ?? ['minLength' => 8, 'requireUpper' => false, 'requireNumbers' => false, 'requireSpecial' => false];
$forced = $forced ?? false;

$requirements = ['Al menos ' . (int) $policy['minLength'] . ' caracteres'];
if ($policy['requireUpper'])   $requirements[] = 'Una letra mayúscula';
if ($policy['requireNumbers']) $requirements[] = 'Un número';
if ($policy['requireSpecial']) $requirements[] = 'Un carácter especial (!@#$...)';
$requirements[] = 'Distinta de la actual';

$inputStyle = 'width:100%;padding:12px;margin-bottom:12px;border:none;border-radius:10px;background:rgba(255,255,255,0.1);color:white;font-size:14px';
?>

<?= $this->section('content') ?>

<div style="min-height:100vh;display:flex;align-items:center;justify-content:center;padding:16px;background:linear-gradient(135deg,#0f172a,#1e3a8a)">

    <div style="width:100%;max-width:400px;padding:32px 28px;border-radius:20px;background:rgba(255,255,255,0.08);backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,0.2);color:white;box-shadow:0 20px 40px rgba(0,0,0,0.3)">

        <h3 style="text-align:center;margin-bottom:8px">
            <?= $forced ? 'Define tu contraseña' : 'Cambiar contraseña' ?>
        </h3>
        <p style="text-align:center;color:#93c5fd;font-size:13px;margin-bottom:20px">
            <?= $forced
                ? 'Tu cuenta usa una contraseña temporal. Elige una propia para continuar.'
                : 'Necesitarás tu contraseña actual para confirmar el cambio.' ?>
        </p>

        <?php if ($msg = session()->getFlashdata('error')): ?>
        <div style="background:rgba(220,38,38,.25);border:1px solid rgba(248,113,113,.5);color:#fecaca;padding:10px 12px;border-radius:10px;font-size:13px;margin-bottom:14px">
            <?= esc($msg) ?>
        </div>
        <?php endif; ?>

        <form method="post" action="<?= base_url('perfil/password') ?>">
            <?= csrf_field() ?>

            <input type="password" name="current_password" placeholder="Contraseña actual"
                   required autocomplete="current-password" style="<?= $inputStyle ?>">

            <input type="password" name="new_password" placeholder="Nueva contraseña"
                   required autocomplete="new-password" minlength="<?= (int) $policy['minLength'] ?>"
                   style="<?= $inputStyle ?>">

            <input type="password" name="new_password_confirm" placeholder="Repite la nueva contraseña"
                   required autocomplete="new-password" minlength="<?= (int) $policy['minLength'] ?>"
                   style="<?= $inputStyle ?>;margin-bottom:16px">

            <ul style="font-size:12px;color:#cbd5e1;margin:0 0 18px;padding-left:18px;line-height:1.7">
                <?php foreach ($requirements as $r): ?>
                <li><?= esc($r) ?></li>
                <?php endforeach; ?>
            </ul>

            <button type="submit"
                    style="width:100%;padding:12px;background:#3b82f6;color:white;border:none;border-radius:10px;font-weight:600;cursor:pointer">
                Guardar contraseña
            </button>
        </form>

        <div style="text-align:center;margin-top:16px">
            <?php if ($forced): ?>
            <form method="post" action="<?= base_url('logout') ?>" style="display:inline">
                <?= csrf_field() ?>
                <button type="submit" style="background:none;border:none;color:#93c5fd;font-size:13px;cursor:pointer">
                    Cerrar sesión
                </button>
            </form>
            <?php else: ?>
            <a href="<?= base_url('perfil') ?>" style="color:#93c5fd;font-size:13px">← Volver al perfil</a>
            <?php endif; ?>
        </div>

    </div>
</div>

<?= $this->endSection() ?>
