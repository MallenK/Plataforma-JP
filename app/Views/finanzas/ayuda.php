<?= $this->extend('layouts/app') ?>

<?php
$pageTitle    = 'Finanzas';
$pageSubtitle = 'Ayuda: manual de uso de Finanzas';
?>

<?= $this->section('page_content') ?>
<?= view('finanzas/_tabs', ['tab' => $tab, 'reviewCount' => $reviewCount]) ?>

<div class="card-jp">
    <div class="card-jp-header" style="flex-wrap:wrap;gap:8px">
        <span class="card-jp-title"><i class="bi bi-life-preserver me-2" style="color:var(--accent)"></i>Manual de uso de Finanzas</span>
        <div class="d-flex gap-2 flex-wrap">
            <?php if ($hasPdf): ?>
            <a href="<?= base_url('finanzas/ayuda/manual.pdf') ?>" class="btn-jp btn-jp-primary btn-jp-sm" style="text-decoration:none"><i class="bi bi-file-earmark-pdf me-1"></i>Descargar en PDF</a>
            <?php endif; ?>
            <a href="<?= base_url('finanzas/ayuda/imprimir') ?>" target="_blank" rel="noopener" class="btn-jp btn-jp-secondary btn-jp-sm" style="text-decoration:none"><i class="bi bi-printer me-1"></i>Versión para imprimir</a>
        </div>
    </div>
    <div class="card-jp-body">
        <?= view('finanzas/_manual') ?>
    </div>
</div>

<?= $this->endSection() ?>
