<?= $this->extend('layouts/base') ?>

<?= $this->section('content') ?>

<div class="container d-flex align-items-center justify-content-center vh-100">
    <div class="card auth-card shadow-lg">

        <div class="card-body">
            <h3 class="text-center mb-4">Crear cuenta</h3>

            <form id="registerForm">

                <div class="mb-3">
                    <label>Nombre</label>
                    <input type="text" name="name" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label>Email</label>
                    <input type="email" name="email" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label>Password</label>
                    <input type="password" name="password" class="form-control" required>
                </div>

                <div class="mb-3">
                    <label>Confirmar Password</label>
                    <input type="password" name="confirm_password" class="form-control" required>
                </div>

                <button type="submit" class="btn btn-dark w-100">
                    Registrarse
                </button>
            </form>

            <div class="text-center mt-3">
                <a href="/login">Ya tengo cuenta</a>
            </div>
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