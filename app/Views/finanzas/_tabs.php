<?php
/**
 * Pestañas de Finanzas. $tab = clave de la pestaña activa.
 * Las que aún no existen se muestran desactivadas («Próximamente») para que
 * se vea hacia dónde va la sección (plan: docs/finanzas/PLAN-finanzas-v2.md).
 * Para ocultarlas en vez de mostrarlas, poner $finShowUpcoming a false.
 */
$finShowUpcoming = true;

$finTabs = [
    'resumen'      => ['Resumen',       'bi-speedometer2',      null],
    'movimientos'  => ['Movimientos',   'bi-journal-text',      null],
    'cobros'       => ['Cobros',        'bi-cash-coin',         null],
    'gastos'       => ['Gastos',        'bi-receipt-cutoff',    null],
    'alumnos'      => ['Alumnos',       'bi-person-vcard',      null],
    'entrenadores' => ['Entrenadores',  'bi-person-badge',      null],
    'analisis'     => ['Análisis',      'bi-graph-up',          null],
    'revision'     => ['Revisión',      'bi-clipboard-check',   'finanzas/revision'],
    'config'       => ['Configuración', 'bi-sliders',           null],
];
$tab = $tab ?? '';
?>
<nav class="calendar-view-tabs fin-tabs mb-3" aria-label="Secciones de Finanzas" style="flex-wrap:wrap;gap:4px">
    <?php foreach ($finTabs as $key => [$label, $icon, $url]): ?>
        <?php if ($url): ?>
        <a href="<?= base_url($url) ?>" class="calendar-view-tab <?= $tab === $key ? 'active' : '' ?>"
           style="text-decoration:none" <?= $tab === $key ? 'aria-current="page"' : '' ?>>
            <i class="bi <?= $icon ?> me-1"></i><?= $label ?>
        </a>
        <?php elseif ($finShowUpcoming): ?>
        <span class="calendar-view-tab" aria-disabled="true" title="Próximamente (Finanzas 2.0)"
              style="opacity:.45;cursor:not-allowed">
            <i class="bi <?= $icon ?> me-1"></i><?= $label ?>
            <span style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:.3px;margin-left:3px">pronto</span>
        </span>
        <?php endif; ?>
    <?php endforeach; ?>
</nav>
