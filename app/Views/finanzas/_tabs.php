<?php
/**
 * Cabecera común de Finanzas: título, pestañas y avisos flash.
 * $tab = pestaña activa · $reviewCount = tareas pendientes en Revisión.
 * Las pestañas con periodo conservan el ?mes / ?desde / ?hasta elegido.
 */
helper(['money', 'finhelp']);
$finTabs = [
    'resumen'      => ['Resumen',       'bi-speedometer2',    'finanzas/resumen',      true],
    'movimientos'  => ['Movimientos',   'bi-journal-text',    'finanzas/movimientos',  true],
    'cobros'       => ['Cobros',        'bi-cash-coin',       'finanzas/cobros',       true],
    'gastos'       => ['Gastos',        'bi-receipt-cutoff',  'finanzas/gastos',       true],
    'alumnos'      => ['Alumnos',       'bi-person-vcard',    'finanzas/alumnos',      false],
    'entrenadores' => ['Entrenadores',  'bi-person-badge',    'finanzas/entrenadores', true],
    'analisis'     => ['Análisis',      'bi-graph-up',        'finanzas/analisis',     true],
    'revision'     => ['Revisión',      'bi-clipboard-check', 'finanzas/revision',     false],
    'config'       => ['Configuración', 'bi-sliders',         'finanzas/configuracion', false],
    'ayuda'        => ['Ayuda',         'bi-life-preserver',  'finanzas/ayuda',        false],
];
$tab = $tab ?? '';
// Sin acceso total a Finanzas solo existe la «Revisión de clases» (FinanceAccessService).
$finFullAccess = \App\Services\FinanceAccessService::canFull();
if (!$finFullAccess) {
    $finTabs = ['revision' => ['Revisión de clases', 'bi-clipboard-check', 'finanzas/revision', false]];
}
$keep = array_filter([
    'mes'   => $_GET['mes']   ?? null,
    'desde' => $_GET['desde'] ?? null,
    'hasta' => $_GET['hasta'] ?? null,
], fn($v) => is_string($v) && preg_match('/^[0-9-]{7,10}$/', $v));
$qs = $keep ? '?' . http_build_query($keep) : '';
?>
<nav class="calendar-view-tabs fin-tabs mb-3" aria-label="<?= $finFullAccess ? 'Secciones de Finanzas' : 'Revisión de clases' ?>" style="flex-wrap:wrap;gap:4px">
    <?php foreach ($finTabs as $key => [$label, $icon, $url, $periodic]): ?>
    <a href="<?= base_url($url) . ($periodic ? $qs : '') ?>" class="calendar-view-tab <?= $tab === $key ? 'active' : '' ?>"
       style="text-decoration:none" <?= $tab === $key ? 'aria-current="page"' : '' ?>>
        <i class="bi <?= $icon ?> me-1"></i><?= $label ?>
        <?php if ($key === 'revision' && !empty($reviewCount)): ?>
        <span style="display:inline-block;min-width:18px;padding:0 6px;margin-left:4px;border-radius:9px;background:#fef3c7;color:#92400e;font-size:11px;font-weight:800;text-align:center"><?= (int) $reviewCount ?></span>
        <?php endif; ?>
    </a>
    <?php endforeach; ?>
</nav>

<?php if (!$finFullAccess): ?>
<p class="mb-3" style="font-size:13px;color:var(--text-muted)">
    <i class="bi bi-shield-lock me-1"></i>Aquí ves el estado de las clases (si se dieron, si se pasó lista, si se cerraron). Las finanzas completas son solo para las personas autorizadas.
</p>
<?php elseif (in_array($tab, ['alumnos', 'revision'], true)): // las pestañas con periodo ya lo dicen en su selector ?>
<p class="mb-3" style="font-size:13px;color:var(--text-muted)">
    <i class="bi bi-info-circle me-1"></i>Las finanzas cuentan desde el <?= date('d/m/Y', strtotime(\App\Services\FinanceService::startDate())) ?>: lo anterior no se incluye.
</p>
<?php endif; ?>

<?php if (session()->getFlashdata('success')): ?>
<div class="alert-jp success mb-3" data-flash><i class="bi bi-check-circle-fill me-2"></i><?= esc(session()->getFlashdata('success')) ?></div>
<?php endif; ?>
<?php if (session()->getFlashdata('error')): ?>
<div class="alert-jp error mb-3" data-flash><i class="bi bi-exclamation-triangle-fill me-2"></i><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif; ?>
<script src="<?= base_url('assets/js/fin-ui.js') ?>?v=<?= @filemtime(FCPATH . 'assets/js/fin-ui.js') ?: time() ?>"></script>
