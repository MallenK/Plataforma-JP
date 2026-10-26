<?= $this->extend('layouts/app') ?>

<?php
$pageTitle    = 'Alumnos inactivos';
$pageSubtitle = 'Alumnos dados de baja que puedes volver a habilitar';
?>

<?= $this->section('page_content') ?>

<?php if (session()->getFlashdata('success')): ?>
<div class="alert-jp success" style="display:flex;align-items:center;gap:10px;margin-bottom:16px">
    <i class="bi bi-check-circle-fill"></i>
    <?= esc(session()->getFlashdata('success')) ?>
</div>
<?php endif; ?>

<?php if (session()->getFlashdata('error')): ?>
<div class="alert-jp error" style="display:flex;align-items:center;gap:10px;margin-bottom:16px">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <?= esc(session()->getFlashdata('error')) ?>
</div>
<?php endif; ?>

<div class="page-header">
    <a href="<?= base_url('alumnos') ?>" class="btn-jp btn-jp-secondary">
        <i class="bi bi-arrow-left"></i> Volver a alumnos
    </a>
</div>

<?php if (!empty($players)): ?>
<div class="card-jp mb-3">
    <div class="card-jp-body py-3">
        <div class="search-bar">
            <div class="input-search">
                <i class="bi bi-search"></i>
                <input type="text" id="search-input" placeholder="Buscar por nombre o email...">
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card-jp">
    <div class="card-jp-header">
        <span class="card-jp-title">
            <i class="bi bi-person-x-fill me-2" style="color:var(--accent)"></i>
            Alumnos dados de baja
        </span>
        <span style="font-size:12px;color:var(--text-muted)" id="total-count">
            <?= count($players ?? []) ?> alumnos
        </span>
    </div>

    <?php if (!empty($players)): ?>
    <div class="table-responsive">
        <table class="table-jp" id="inactivos-table">
            <thead>
                <tr>
                    <th>Alumno</th>
                    <th>Email</th>
                    <th>Baja desde</th>
                    <th class="no-sort" style="text-align:right">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($players as $p): ?>
                <tr data-name="<?= strtolower(esc($p['name'])) ?>"
                    data-email="<?= strtolower(esc($p['email'] ?? '')) ?>">
                    <td>
                        <div class="td-user">
                            <div class="td-avatar"><?= strtoupper(substr($p['name'], 0, 1)) ?></div>
                            <div class="td-name"><?= esc($p['name']) ?></div>
                        </div>
                    </td>
                    <td style="color:var(--text-muted)"><?= esc($p['email'] ?? '—') ?></td>
                    <td style="color:var(--text-muted)" data-order="<?= esc($p['updated_at'] ?? '') ?>">
                        <?= !empty($p['updated_at']) ? esc(date('d/m/Y', strtotime($p['updated_at']))) : '—' ?>
                    </td>
                    <td>
                        <div class="d-flex gap-1 justify-content-end">
                            <a href="<?= base_url('alumnos/' . (int) $p['id']) ?>" class="btn-jp btn-jp-secondary btn-jp-sm btn-jp-icon" title="Ver perfil">
                                <i class="bi bi-eye"></i>
                            </a>
                            <form method="post" action="<?= base_url('alumnos/' . (int) $p['id'] . '/reactivar') ?>" style="display:inline">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn-jp btn-jp-primary btn-jp-sm" title="Volver a habilitar">
                                    <i class="bi bi-person-check-fill"></i> Reactivar
                                </button>
                            </form>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div class="empty-state">
        <i class="bi bi-person-check"></i>
        <h3>No hay alumnos inactivos</h3>
        <p>Todos los alumnos están habilitados.</p>
    </div>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>

<?php if (!empty($players)): ?>
<?= $this->section('scripts') ?>
<script>
JPList.init({
    table: '#inactivos-table', key: 'alumnos-inactivos', search: '#search-input', searchAttrs: ['name', 'email'],
    count: '#total-count', noun: 'alumnos',
    pageLength: 10, defaultView: 'list',
});
</script>
<?= $this->endSection() ?>
<?php endif; ?>
