<?php
/**
 * Selector de periodo (mes o rango). $period de FinanceReportService::period(),
 * $months = lista YYYY-MM. $extra = parámetros GET a conservar (p. ej. tipo).
 */
$mNames = ['', 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];
$extra  = $extra ?? [];
$isRange = empty($period['month']);
?>
<form method="get" class="d-flex flex-wrap gap-2 align-items-end mb-3" style="font-size:13px">
    <?php foreach ($extra as $k => $v): if ($v === '' || $v === null) continue; ?>
    <input type="hidden" name="<?= esc($k, 'attr') ?>" value="<?= esc($v, 'attr') ?>">
    <?php endforeach; ?>
    <div>
        <label for="fp-mes" class="form-label" style="margin-bottom:2px">Mes</label>
        <select id="fp-mes" name="mes" class="form-control-jp" style="width:auto;min-width:180px" onchange="this.form.desde.value='';this.form.hasta.value='';this.form.submit()">
            <?php foreach ($months as $m): ?>
            <option value="<?= $m ?>" <?= !$isRange && $period['month'] === $m ? 'selected' : '' ?>><?= ucfirst($mNames[(int) substr($m, 5, 2)]) . ' ' . substr($m, 0, 4) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div>
        <label for="fp-desde" class="form-label" style="margin-bottom:2px">o desde</label>
        <input type="date" id="fp-desde" name="desde" class="form-control-jp" style="width:auto" value="<?= $isRange ? esc($period['from']) : '' ?>">
    </div>
    <div>
        <label for="fp-hasta" class="form-label" style="margin-bottom:2px">hasta</label>
        <input type="date" id="fp-hasta" name="hasta" class="form-control-jp" style="width:auto" value="<?= $isRange ? esc($period['to']) : '' ?>">
    </div>
    <button type="submit" class="btn-jp btn-jp-secondary">Ver</button>
    <span style="color:var(--text-muted);margin-left:4px;align-self:center">Periodo: <strong style="color:var(--text-h)"><?= esc($period['label']) ?></strong></span>
</form>
