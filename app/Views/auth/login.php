<?= $this->extend('layouts/base') ?>

<?= $this->section('content') ?>

<div style="height:100vh;display:flex;align-items:center;justify-content:center; background:linear-gradient(135deg,#0f172a,#1e3a8a);">

    <div style="width:380px;padding:40px;border-radius:20px;background:rgba(255,255,255,0.08);backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,0.2);box-shadow:0 20px 40px rgba(0,0,0,0.3);color:white;">
        <h2 style="text-align:center;margin-bottom:30px;">JP Preparation</h2>

        <div id="errorBox" style="color:#fca5a5;margin-bottom:15px;"></div>

        <form id="loginForm">

            <input type="email" name="email" placeholder="Email"
                style="width:100%;padding:12px;margin-bottom:15px;border:none;border-radius:10px;background:rgba(255,255,255,0.1);color:white;">

            <input type="password" name="password" placeholder="Password"
                style="width:100%;padding:12px;margin-bottom:20px;border:none;border-radius:10px;background:rgba(255,255,255,0.1);color:white;">

            <button type="submit"
                style="width:100%;padding:12px;border:none;border-radius:10px;background:#3b82f6;color:white;font-weight:bold;">
                Entrar
            </button>
        </form>

        <div style="text-align:center;margin-top:15px;">
            <a href="/register" style="color:#93c5fd;">Crear cuenta</a>
        </div>

        <div style="text-align:center;margin-top:10px;">
            <a href="/forgot-password" style="color:#93c5fd;">¿Olvidaste tu contraseña?</a>
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

<?= $this->endSection() ?>