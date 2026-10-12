<?= $this->extend('layouts/base') ?>

<?= $this->section('content') ?>
<div style="min-height:100vh;background:#0f172a;padding:24px 16px calc(32px + env(safe-area-inset-bottom))">
    <div style="max-width:560px;margin:0 auto">
        <header style="text-align:center;color:#e2e8f0;margin-bottom:20px">
            <img src="<?= base_url('assets/img/logo-jp-preparation.webp') ?>" alt="JP Preparation" width="96" height="73" style="margin-bottom:10px">
            <h1 style="font-size:1.35rem;font-weight:700;margin:0 0 6px">Instala la app de JP Preparation</h1>
            <p style="margin:0;color:#94a3b8;font-size:14.5px">Acceso directo desde tu pantalla de inicio y avisos al instante de mensajes, clases y bonos.</p>
        </header>

        <?= view('partials/install_guide') ?>

        <p style="text-align:center;margin-top:22px">
            <a href="<?= base_url('login') ?>" style="color:#93c5fd;font-weight:600;text-decoration:none">Ir a la plataforma →</a>
        </p>
    </div>
</div>
<?= $this->endSection() ?>
