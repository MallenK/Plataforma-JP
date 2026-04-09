<?= $this->extend('layouts/app') ?>

<?php
$pageTitle    = 'Nuevo entrenador';
$pageSubtitle = 'Añadir miembro al equipo técnico';
?>

<?= $this->section('page_content') ?>

<?php if (session()->getFlashdata('errors')): ?>
<div class="alert-jp danger" style="margin-bottom:16px">
    <i class="bi bi-exclamation-triangle-fill me-2"></i>
    <ul style="margin:0;padding-left:16px">
        <?php foreach ((array) session()->getFlashdata('errors') as $e): ?>
        <li><?= esc($e) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="page-header">
    <div class="page-header-text">
        <h2>Nuevo entrenador</h2>
        <p>Añade un nuevo miembro al equipo técnico</p>
    </div>
    <a href="<?= base_url('entrenadores') ?>" class="btn-jp btn-jp-secondary">
        <i class="bi bi-arrow-left"></i> Volver al listado
    </a>
</div>

<form method="post" action="<?= base_url('entrenadores/nuevo') ?>">
    <?= csrf_field() ?>

    <div class="row g-3 justify-content-center">
        <div class="col-12 col-lg-7">
            <div class="card-jp">
                <div class="card-jp-header">
                    <span class="card-jp-title">
                        <i class="bi bi-person-workspace me-2" style="color:var(--success)"></i>
                        Datos del entrenador
                    </span>
                </div>
                <div class="card-jp-body">
                    <div class="row g-3">

                        <div class="col-12">
                            <div class="form-group">
                                <label class="form-label">Nombre completo <span style="color:var(--danger)">*</span></label>
                                <input type="text" name="name" class="form-control-jp" required
                                    placeholder="Ej: Carlos Pérez"
                                    value="<?= esc(old('name')) ?>">
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="form-group">
                                <label class="form-label">Email <span style="color:var(--danger)">*</span></label>
                                <input type="email" name="email" class="form-control-jp" required
                                    placeholder="entrenador@ejemplo.com"
                                    value="<?= esc(old('email')) ?>">
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="alert-jp" style="background:var(--bg-card);border:1px solid var(--border);border-radius:8px;padding:12px 16px;font-size:13px;color:var(--text-muted)">
                                <i class="bi bi-key-fill me-2" style="color:var(--success)"></i>
                                La contraseña inicial se generará automáticamente y se mostrará una sola vez tras crear el entrenador.
                            </div>
                        </div>

                        <div class="col-12 d-flex gap-2 justify-content-end">
                            <a href="<?= base_url('entrenadores') ?>" class="btn-jp btn-jp-secondary">Cancelar</a>
                            <button type="submit" class="btn-jp btn-jp-primary">
                                <i class="bi bi-person-plus-fill"></i> Crear entrenador
                            </button>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

</form>

<?= $this->endSection() ?>
