<?= $this->extend('layouts/app') ?>

<?php
$pageTitle    = 'Editar entrenador';
$pageSubtitle = esc($coach['name'] ?? '');
?>

<?= $this->section('page_content') ?>

<div class="page-header">
    <div class="page-header-text">
        <h2>Editar entrenador</h2>
        <p><?= esc($coach['name']) ?></p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= base_url('entrenadores/' . $coach['id']) ?>" class="btn-jp btn-jp-secondary">
            <i class="bi bi-arrow-left"></i> Ver perfil
        </a>
        <a href="<?= base_url('entrenadores') ?>" class="btn-jp btn-jp-secondary">
            <i class="bi bi-people-fill"></i> Listado
        </a>
    </div>
</div>

<form method="post" action="<?= base_url('entrenadores/' . $coach['id'] . '/editar') ?>">
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
                                    value="<?= esc($coach['name']) ?>">
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="form-group">
                                <label class="form-label">Email <span style="color:var(--danger)">*</span></label>
                                <input type="email" name="email" class="form-control-jp" required
                                    value="<?= esc($coach['email']) ?>">
                            </div>
                        </div>

                        <div class="col-12 col-md-5">
                            <div class="form-group">
                                <label class="form-label">Estado</label>
                                <select name="status" class="form-control-jp">
                                    <option value="active"   <?= ($coach['status'] ?? '') === 'active'   ? 'selected' : '' ?>>Activo</option>
                                    <option value="inactive" <?= ($coach['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactivo</option>
                                    <option value="banned"   <?= ($coach['status'] ?? '') === 'banned'   ? 'selected' : '' ?>>Bloqueado</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="alert-jp" style="background:var(--bg-card);border:1px solid var(--border);border-radius:8px;padding:12px 16px;font-size:13px;color:var(--text-muted)">
                                <i class="bi bi-key-fill me-2" style="color:var(--text-muted)"></i>
                                Para cambiar la contraseña, el entrenador puede usar la opción "Recuperar contraseña" desde el login.
                            </div>
                        </div>

                        <div class="col-12 d-flex gap-2 justify-content-end">
                            <a href="<?= base_url('entrenadores/' . $coach['id']) ?>" class="btn-jp btn-jp-secondary">Cancelar</a>
                            <button type="submit" class="btn-jp btn-jp-primary">
                                <i class="bi bi-floppy-fill"></i> Guardar cambios
                            </button>
                        </div>

                    </div>
                </div>
            </div>
        </div>
    </div>

</form>

<?= $this->endSection() ?>
