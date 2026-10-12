<?php
/** Pestañas de los ajustes personales (páginas /configuracion/notificaciones e /instalar). $active: 'notificaciones'|'instalar' */
$active = $active ?? 'notificaciones';
?>
<div class="d-flex gap-2 flex-wrap mb-3" role="tablist" aria-label="Ajustes">
    <a href="<?= base_url('configuracion/notificaciones') ?>" class="btn-jp <?= $active === 'notificaciones' ? 'btn-jp-primary' : 'btn-jp-secondary' ?>">
        <i class="bi bi-bell-fill me-1"></i>Notificaciones
    </a>
    <a href="<?= base_url('configuracion/instalar') ?>" class="btn-jp <?= $active === 'instalar' ? 'btn-jp-primary' : 'btn-jp-secondary' ?>">
        <i class="bi bi-download me-1"></i>Instalar app
    </a>
</div>
