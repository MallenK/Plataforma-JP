<?= $this->extend('layouts/app') ?>

<?= $this->section('styles') ?>
<link href="<?= base_url('assets/css/profile.css') ?>" rel="stylesheet">
<?= $this->endSection() ?>

<?= $this->section('page_content') ?>

<div class="profile-page">

    <!-- HEADER -->
    <div class="profile-header">
        <div class="profile-header-left">
            <div class="avatar-lg">
                <?= strtoupper(substr($user['name'] ?? '?', 0, 1)) ?>
            </div>

            <div>
                <h2><?= esc($user['name']) ?></h2>
                <span class="badge-role"><?= esc($user['role']) ?></span>
            </div>
        </div>

        <div class="profile-header-right">
            <button class="btn btn-outline-light btn-sm">Editar perfil</button>
        </div>
    </div>

    <!-- GRID -->
    <div class="row g-4 mt-1">

        <!-- INFO PRINCIPAL -->
        <div class="col-12 col-lg-8">
            <div class="card-profile-details">

                <h5 class="section-title">Información general</h5>

                <div class="profile-grid">

                    <div class="profile-item">
                        <span>Email</span>
                        <strong><?= esc($user['email']) ?></strong>
                    </div>

                    <div class="profile-item">
                        <span>Rol</span>
                        <strong><?= esc($user['role']) ?></strong>
                    </div>

                    <div class="profile-item">
                        <span>Fecha creación</span>
                        <strong><?= esc($user['created_at']) ?></strong>
                    </div>

                </div>
            </div>
        </div>

        <!-- SIDECARD -->
        <div class="col-12 col-lg-4">
            <div class="card-profile">

                <h6>Estado de cuenta</h6>

                <div class="status-badge active">
                    Activo
                </div>

                <hr>

                <div class="mini-info">
                    <span>ID Usuario</span>
                    <strong>#<?= esc($user['id']) ?></strong>
                </div>

            </div>
        </div>

    </div>

</div>

<?= $this->endSection() ?>