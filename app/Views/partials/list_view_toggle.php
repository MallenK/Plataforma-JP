<?php
/**
 * Botones Lista / Cuadrícula para un listado paginado con JPList.
 * Uso: <?= view('partials/list_view_toggle', ['key' => 'alumnos']) ?>
 */
$key = $key ?? '';
?>
<div class="jp-view-toggle" role="group" aria-label="Cambiar vista">
    <button type="button" data-jp-view="list" data-jp-view-for="<?= esc($key, 'attr') ?>" aria-pressed="true" title="Vista de lista" aria-label="Vista de lista"><i class="bi bi-list-ul"></i></button>
    <button type="button" data-jp-view="grid" data-jp-view-for="<?= esc($key, 'attr') ?>" aria-pressed="false" title="Vista de cuadrícula" aria-label="Vista de cuadrícula"><i class="bi bi-grid-3x3-gap"></i></button>
</div>
