<?= $this->extend('layouts/base') ?>

<?= $this->section('content') ?>

<div style="height:100vh;display:flex;align-items:center;justify-content:center;background:linear-gradient(135deg,#0f172a,#1e3a8a)">

    <div style="width:380px;padding:40px;border-radius:20px;background:rgba(255,255,255,0.08);backdrop-filter:blur(20px);border:1px solid rgba(255,255,255,0.2);color:white;box-shadow:0 20px 40px rgba(0,0,0,0.3)">

        <h3 style="text-align:center;margin-bottom:8px">Nueva contraseña</h3>
        <p style="text-align:center;color:#93c5fd;font-size:13px;margin-bottom:24px">
            Elige una contraseña segura para tu cuenta.
        </p>

        <div id="errorBox" style="color:#fca5a5;margin-bottom:12px;font-size:13px"></div>

        <form id="resetForm">
            <input type="hidden" name="token" value="<?= esc($token ?? '') ?>">

            <input type="password" name="password" placeholder="Nueva contraseña"
                style="width:100%;padding:12px;margin-bottom:12px;border:none;border-radius:10px;background:rgba(255,255,255,0.1);color:white;font-size:14px">

            <input type="password" name="password_confirm" placeholder="Repite la contraseña"
                style="width:100%;padding:12px;margin-bottom:16px;border:none;border-radius:10px;background:rgba(255,255,255,0.1);color:white;font-size:14px">

            <button type="submit"
                style="width:100%;padding:12px;background:#3b82f6;color:white;border:none;border-radius:10px;font-weight:600;cursor:pointer">
                Guardar contraseña
            </button>
        </form>

        <div style="text-align:center;margin-top:16px">
            <a href="<?= base_url('login') ?>" style="color:#93c5fd;font-size:13px">
                ← Volver al login
            </a>
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
