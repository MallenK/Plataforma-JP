<?= $this->extend('layouts/app') ?>

<?php
$pageTitle    = 'Finanzas';
$pageSubtitle = 'Exportar todos los datos para estudiarlos fuera de la plataforma';
helper(['money', 'finhelp']);
?>

<?= $this->section('page_content') ?>
<?= view('finanzas/_tabs', ['tab' => $tab, 'reviewCount' => $reviewCount]) ?>
<?= view('finanzas/_period', ['period' => $period, 'months' => $months]) ?>

<div class="row g-3 mb-3">
    <div class="col-12 col-xl-7">
        <div class="card-jp h-100">
            <div class="card-jp-header"><span class="card-jp-title">Descargar datos</span></div>
            <div class="card-jp-body">
                <form method="get" action="<?= base_url('finanzas/exportar/descargar') ?>" data-no-soft>
                    <input type="hidden" name="mes" value="<?= esc((string) ($period['month'] ?? ''), 'attr') ?>">
                    <input type="hidden" name="desde" value="<?= empty($period['month']) ? esc($period['from'], 'attr') : '' ?>">
                    <input type="hidden" name="hasta" value="<?= empty($period['month']) ? esc($period['to'], 'attr') : '' ?>">

                    <fieldset style="border:0;padding:0;margin:0 0 14px">
                        <legend style="font-size:13px;font-weight:700;color:var(--text-h);margin-bottom:6px">¿Qué periodo?</legend>
                        <label style="display:flex;gap:8px;align-items:flex-start;font-size:13px;margin-bottom:6px">
                            <input type="radio" name="alcance" value="todo" checked style="margin-top:3px">
                            <span><strong>Todo</strong> · desde el <?= date('d/m/Y', strtotime($finStart)) ?> (inicio de las finanzas) hasta hoy. Es lo mejor para estudiarlo.</span>
                        </label>
                        <label style="display:flex;gap:8px;align-items:flex-start;font-size:13px">
                            <input type="radio" name="alcance" value="periodo" style="margin-top:3px">
                            <span>Solo el periodo elegido arriba: <strong><?= esc($period['label']) ?></strong></span>
                        </label>
                    </fieldset>

                    <fieldset style="border:0;padding:0;margin:0 0 14px">
                        <legend style="font-size:13px;font-weight:700;color:var(--text-h);margin-bottom:6px">Formato</legend>
                        <label style="display:flex;gap:8px;align-items:flex-start;font-size:13px;margin-bottom:6px">
                            <input type="radio" name="formato" value="json" checked style="margin-top:3px">
                            <span><strong>Un solo archivo (JSON)</strong> · todo junto; el más cómodo para analizarlo con Claude.</span>
                        </label>
                        <label style="display:flex;gap:8px;align-items:flex-start;font-size:13px;<?= $canZip ? '' : 'opacity:.55' ?>">
                            <input type="radio" name="formato" value="zip" <?= $canZip ? '' : 'disabled' ?> style="margin-top:3px">
                            <span><strong>Excel (ZIP con un CSV por tabla)</strong><?= $canZip ? '' : ' · no disponible en este servidor' ?></span>
                        </label>
                    </fieldset>

                    <label style="display:flex;gap:8px;align-items:flex-start;font-size:13px;margin-bottom:16px">
                        <input type="checkbox" name="anonimo" value="1" style="margin-top:3px">
                        <span><strong>Ocultar nombres de alumnos</strong> · salen como «Alumno #número» (conviene si vas a compartir el archivo; se conservan los números para cruzar datos).</span>
                    </label>

                    <button type="submit" class="btn-jp btn-jp-primary"><i class="bi bi-download me-1"></i>Descargar</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-12 col-xl-5">
        <div class="card-jp h-100">
            <div class="card-jp-header"><span class="card-jp-title">Qué incluye</span></div>
            <div class="card-jp-body" style="font-size:13px;line-height:1.55">
                <ul style="margin:0 0 10px;padding-left:18px">
                    <li>Resumen por mes (vendido, cobrado, servicio, gastos, resultado)</li>
                    <li>Cargos: lo vendido, con descuento, pagado y pendiente</li>
                    <li>Cobros y a qué cargos se aplicó cada uno</li>
                    <li>Gastos por categoría, entrenador y sede</li>
                    <li>Bonos vendidos, con su precio y sesiones</li>
                    <li>Saldo de cada alumno</li>
                    <li>Clases por alumno: asistencia y descuento de bono</li>
                </ul>
                <p style="margin:0 0 6px;color:var(--text-muted)">Incluye también lo anulado (marcado como tal). No cuenta a los usuarios excluidos de las finanzas.</p>
                <p style="margin:0;color:var(--text-muted)"><i class="bi bi-shield-lock me-1"></i>Solo lo ven quienes tienen acceso total a Finanzas. Cada descarga queda registrada.</p>
            </div>
        </div>
    </div>
</div>
<?= $this->endSection() ?>
