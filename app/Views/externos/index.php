<?= $this->extend('layouts/app') ?>

<?php
helper('money');
$statuses = \App\Services\ExternosPersonasService::STATUSES;
?>

<?= $this->section('page_content') ?>

<?php if (session()->getFlashdata('success')): ?>
<div class="alert-jp success mb-3"><i class="bi bi-check-circle-fill me-2"></i><?= esc(session()->getFlashdata('success')) ?></div>
<?php endif; ?>
<?php if (session()->getFlashdata('error')): ?>
<div class="alert-jp error mb-3"><i class="bi bi-x-circle-fill me-2"></i><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif; ?>

<div class="page-header">
    <a href="<?= base_url('alumnos') ?>" class="btn-jp btn-jp-secondary"><i class="bi bi-arrow-left"></i> Volver a alumnos</a>
</div>

<?php if (!$available): ?>
<div class="card-jp"><div class="card-jp-body" style="color:var(--text-muted)">
    <i class="bi bi-exclamation-triangle me-1"></i>Este apartado se activará al aplicar la migración de externos en la base de datos.
</div></div>
<?php else: ?>

<div class="card-jp mb-3">
    <div class="card-jp-body py-3">
        <form method="GET" action="<?= base_url('externos') ?>" class="d-flex flex-wrap gap-2 align-items-center">
            <div class="input-search" style="flex:1;min-width:220px">
                <i class="bi bi-search"></i>
                <input type="text" name="q" value="<?= esc($q) ?>" placeholder="Buscar por nombre, teléfono o email…" aria-label="Buscar persona externa">
            </div>
            <select name="estado" class="form-control-jp" style="max-width:190px" aria-label="Estado" onchange="this.form.requestSubmit()">
                <?php foreach ($statuses as $k => $label): ?>
                    <option value="<?= $k ?>" <?= $status === $k ? 'selected' : '' ?>><?= esc($label) ?></option>
                <?php endforeach; ?>
            </select>
            <button class="btn-jp btn-jp-primary btn-jp-sm" type="submit">Buscar</button>
            <?php if ($q !== '' || $status !== 'activos'): ?>
                <a href="<?= base_url('externos') ?>" class="btn-jp btn-jp-secondary btn-jp-sm">Quitar filtros</a>
            <?php endif; ?>
        </form>
    </div>
</div>

<div class="card-jp">
    <div class="card-jp-header">
        <span class="card-jp-title"><i class="bi bi-person-badge-fill me-2" style="color:#0ea5e9"></i>Personas externas</span>
        <span style="font-size:12px;color:var(--text-muted)"><?= count($guests) ?> persona<?= count($guests) !== 1 ? 's' : '' ?></span>
    </div>

    <?php if (empty($guests)): ?>
    <div class="card-jp-body" style="text-align:center;color:var(--text-muted);padding:32px">
        <i class="bi bi-person-badge" style="font-size:2rem;display:block;margin-bottom:8px"></i>
        <?= $q !== '' ? 'Nadie coincide con «' . esc($q) . '».' : 'Todavía no hay personas externas en este apartado. Se crean al añadir una persona externa a una clase.' ?>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table-jp">
            <thead>
                <tr>
                    <th>Persona</th>
                    <th>Contacto</th>
                    <th style="text-align:center">Clases</th>
                    <th>Última clase</th>
                    <?php if ($canMoney): ?><th>Cobrado</th><?php endif; ?>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($guests as $g): ?>
                <tr>
                    <td>
                        <a href="<?= base_url('externos/' . (int) $g['id']) ?>" class="row-link-anchor" style="font-weight:600"><?= esc($g['name']) ?></a>
                    </td>
                    <td style="font-size:12px;color:var(--text-muted)">
                            <?php if (!empty($g['phone'])): ?><div><i class="bi bi-telephone me-1"></i><?= esc($g['phone']) ?></div><?php endif; ?>
                            <?php if (!empty($g['email'])): ?><div><i class="bi bi-envelope me-1"></i><?= esc($g['email']) ?></div><?php endif; ?>
                            <?php if (empty($g['phone']) && empty($g['email'])): ?>
                                <?= !empty($g['guardian_phone']) ? 'Tutor: ' . esc($g['guardian_phone']) : 'Sin contacto' ?>
                            <?php endif; ?>
                    </td>
                    <td style="text-align:center"><?= (int) $g['n_present'] ?> / <?= (int) $g['n_classes'] ?></td>
                    <td><?= $g['last_date'] ? esc(date('d/m/Y', strtotime($g['last_date']))) : '—' ?></td>
                    <?php if ($canMoney): ?><td><?= eur((int) ($g['paid_cents'] ?? 0)) ?></td><?php endif; ?>
                    <td>
                        <?php if (!empty($g['converted_user_id'])): ?>
                            <a href="<?= base_url('alumnos/' . (int) $g['converted_user_id']) ?>" class="badge-jp" style="color:#059669;text-decoration:none"><i class="bi bi-check-circle me-1"></i>Ya es alumno</a>
                        <?php else: ?>
                            <span style="font-size:12px;color:var(--text-muted)">Persona externa</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <div class="card-jp-body" style="font-size:12px;color:var(--text-muted);border-top:1px solid var(--border)">
        «Clases» = a las que vino / total. Las fichas y su historial se conservan siempre.
    </div>
    <?php endif; ?>
</div>

<?php endif; ?>
<?= $this->endSection() ?>
