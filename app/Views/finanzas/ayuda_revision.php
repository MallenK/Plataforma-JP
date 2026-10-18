<?= $this->extend('layouts/app') ?>

<?php
// Ayuda de la «Revisión de clases»: la ven los administradores SIN acceso total
// (y también quien lo tiene). Solo texto, sin importes (ver _manual_revision.php).
$pageTitle    = $finFull ? 'Finanzas' : 'Revisión de clases';
$pageSubtitle = 'Ayuda: cómo usar la Revisión de clases';
?>

<?= $this->section('page_content') ?>
<?= view('finanzas/_tabs', ['tab' => $tab, 'reviewCount' => $reviewCount]) ?>

<div class="card-jp">
    <div class="card-jp-header" style="flex-wrap:wrap;gap:8px">
        <span class="card-jp-title"><i class="bi bi-life-preserver me-2" style="color:var(--accent)"></i>Ayuda de la Revisión de clases</span>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?= base_url('finanzas/revision') ?>" class="btn-jp btn-jp-secondary btn-jp-sm" style="text-decoration:none"><i class="bi bi-clipboard-check me-1"></i>Ir a la Revisión</a>
        </div>
    </div>
    <div class="card-jp-body">
        <?= view('finanzas/_manual_revision') ?>
    </div>
</div>

<?= $this->endSection() ?>
