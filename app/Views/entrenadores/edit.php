<?= $this->extend('layouts/app') ?>

<?php
$pageTitle    = 'Editar entrenador';
$pageSubtitle = esc($coach['name'] ?? '');
$errors       = (array) (session()->getFlashdata('errors') ?? []);
?>

<?= $this->section('page_content') ?>

<?php if (!empty($errors)): ?>
<div class="alert-jp danger" style="margin-bottom:16px">
    <i class="bi bi-exclamation-triangle-fill me-2"></i>
    Revisa los campos marcados en rojo.
</div>
<?php endif; ?>

<div class="page-header">
    <div class="d-flex gap-2">
        <a href="<?= base_url('entrenadores/' . $coach['id']) ?>" class="btn-jp btn-jp-secondary">
            <i class="bi bi-arrow-left"></i> Ver perfil
        </a>
        <a href="<?= base_url('entrenadores') ?>" class="btn-jp btn-jp-secondary">
            <i class="bi bi-people-fill"></i> Listado
        </a>
    </div>
</div>

<form method="post" action="<?= base_url('entrenadores/' . $coach['id'] . '/editar') ?>" id="form-edit-coach">
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
                                <input type="text" name="name" class="form-control-jp<?= !empty($errors['name']) ? ' is-invalid' : '' ?>" required
                                    value="<?= esc(old('name', $coach['name'])) ?>">
                                <?php if (!empty($errors['name'])): ?><span class="field-error-msg"><?= esc($errors['name']) ?></span><?php endif; ?>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="form-group">
                                <label class="form-label">Email <span style="color:var(--danger)">*</span></label>
                                <input type="email" name="email" class="form-control-jp<?= !empty($errors['email']) ? ' is-invalid' : '' ?>" required
                                    value="<?= esc(old('email', $coach['email'])) ?>">
                                <?php if (!empty($errors['email'])): ?><span class="field-error-msg"><?= esc($errors['email']) ?></span><?php endif; ?>
                            </div>
                        </div>

                        <?php $statusVal = old('status', $coach['status'] ?? 'active'); ?>
                        <div class="col-12 col-md-5">
                            <div class="form-group">
                                <label class="form-label">Estado</label>
                                <select name="status" id="coach-status" class="form-control-jp"
                                        data-original="<?= esc($coach['status'] ?? 'active') ?>">
                                    <option value="active"   <?= $statusVal === 'active'   ? 'selected' : '' ?>>Activo</option>
                                    <option value="inactive" <?= $statusVal === 'inactive' ? 'selected' : '' ?>>Inactivo</option>
                                    <option value="banned"   <?= $statusVal === 'banned'   ? 'selected' : '' ?>>Bloqueado</option>
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

<script>
(function () {
    var form   = document.getElementById('form-edit-coach');
    var status = document.getElementById('coach-status');
    if (!form || !status) return;

    var labels = { inactive: 'Inactivo', banned: 'Bloqueado' };

    form.addEventListener('submit', function (e) {
        var risky = ['inactive', 'banned'].includes(status.value) && status.value !== status.dataset.original;
        if (!risky) return; // guardado normal, sin cambios de riesgo en el estado

        e.preventDefault();
        RadixUI.confirm({
            title: '¿Cambiar el estado a "' + labels[status.value] + '"?',
            description: 'El entrenador perderá acceso a la plataforma con este cambio.',
            confirmLabel: 'Guardar cambios',
            danger: true,
        }).then(function (ok) {
            if (ok) form.submit();
        });
    });
})();
</script>

<?= $this->endSection() ?>
