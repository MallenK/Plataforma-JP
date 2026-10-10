<?= $this->extend('layouts/app') ?>

<?php
$pageTitle    = 'Finanzas';
$pageSubtitle = 'Configuración: medios de pago, categorías y reglas';
helper(['money', 'finhelp']);
$hours = (int) ($settings['fin_notice_hours'] ?? 24);
$auto  = ($settings['fin_auto_deduct'] ?? '1') === '1';
$catalog = static function (string $kind, array $items, string $title, ?string $catKind = null) {
    ob_start(); ?>
    <div class="card-jp h-100">
        <div class="card-jp-header"><span class="card-jp-title"><?= $title ?></span></div>
        <div class="card-jp-body d-flex flex-column gap-2" style="font-size:13px">
            <?php foreach ($items as $it): if ($catKind !== null && $it['kind'] !== $catKind) continue; ?>
            <div class="d-flex gap-2 align-items-center">
                <form action="<?= base_url('finanzas/configuracion/' . $kind) ?>" method="post" class="d-flex gap-2 flex-grow-1" style="margin:0">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $it['id'] ?>">
                    <input type="text" name="name" value="<?= esc($it['name'], 'attr') ?>" class="form-control-jp" style="padding:5px 8px" maxlength="80" aria-label="Nombre" <?= ($it['code'] ?? '') === 'sin_especificar' ? 'readonly' : '' ?>>
                    <?php if (($it['code'] ?? '') !== 'sin_especificar'): ?><button type="submit" class="btn-jp btn-jp-secondary btn-jp-sm">Guardar</button><?php endif; ?>
                </form>
                <?php if (($it['code'] ?? '') !== 'sin_especificar'): ?>
                <form action="<?= base_url('finanzas/configuracion/' . $kind) ?>" method="post" style="margin:0"
                      data-ru-confirm="¿Archivar «<?= esc($it['name'], 'attr') ?>»?" data-ru-confirm-desc="Deja de ofrecerse. Lo ya registrado lo conserva." data-ru-confirm-label="Archivar">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $it['id'] ?>">
                    <input type="hidden" name="action" value="archive">
                    <button type="submit" class="btn-jp btn-jp-secondary btn-jp-sm" aria-label="Archivar"><i class="bi bi-archive"></i></button>
                </form>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <form action="<?= base_url('finanzas/configuracion/' . $kind) ?>" method="post" class="d-flex gap-2 mt-2" style="margin:0">
                <?= csrf_field() ?>
                <?php if ($catKind): ?><input type="hidden" name="kind" value="<?= $catKind ?>"><?php endif; ?>
                <input type="text" name="name" class="form-control-jp" style="padding:5px 8px" maxlength="80" placeholder="Añadir…" aria-label="Nuevo" required>
                <button type="submit" class="btn-jp btn-jp-primary btn-jp-sm">Añadir</button>
            </form>
        </div>
    </div>
    <?php return ob_get_clean();
};
?>

<?= $this->section('page_content') ?>
<?= view('finanzas/_tabs', ['tab' => $tab, 'reviewCount' => $reviewCount]) ?>

<div class="row g-3">
    <div class="col-12 col-xl-6">
        <div class="card-jp h-100">
            <div class="card-jp-header"><span class="card-jp-title">Reglas de asistencia y bono</span></div>
            <form action="<?= base_url('finanzas/configuracion') ?>" method="post" class="card-jp-body d-flex flex-column gap-3" style="font-size:13px">
                <?= csrf_field() ?>
                <label class="d-flex gap-2 align-items-start" style="cursor:pointer">
                    <input type="checkbox" name="fin_auto_deduct" value="1" <?= $auto ? 'checked' : '' ?> style="margin-top:3px">
                    <span><strong>Descontar la sesión automáticamente</strong><?= fin_help('cfg_auto') ?> cuando el alumno falta sin justificar o avisa con poca antelación. Siempre se puede deshacer desde Pasar lista (entrenador, admin y superadmin).</span>
                </label>
                <div>
                    <label class="form-label" for="cf-hours">Horas mínimas de aviso para no perder la sesión<?= fin_help('cfg_horas') ?></label>
                    <div class="d-flex align-items-center gap-2"><input id="cf-hours" name="fin_notice_hours" type="number" min="1" max="168" value="<?= $hours ?>" class="form-control-jp" style="width:100px"> horas</div>
                </div>
                <div><button type="submit" class="btn-jp btn-jp-primary">Guardar reglas</button></div>
            </form>
        </div>
    </div>
    <div class="col-12 col-xl-6">
        <div class="card-jp h-100">
            <div class="card-jp-header"><span class="card-jp-title">IVA y facturas</span></div>
            <div class="card-jp-body" style="font-size:13px;line-height:1.55">
                <p style="margin:0 0 8px"><strong>Desactivado.</strong> La plataforma registra cargos, cobros y estados de cuenta (recibos internos), sin IVA.</p>
                <p style="margin:0;color:var(--text-muted)">Se activará cuando el gestor de la academia confirme el régimen de IVA. Emitir facturas oficiales desde un programa propio está sujeto a Veri*factu.</p>
            </div>
        </div>
    </div>
    <div class="col-12 col-md-6 col-xl-4"><?= $catalog('metodo', $methods, 'Medios de pago') ?></div>
    <div class="col-12 col-md-6 col-xl-4"><?= $catalog('categoria', $categories, 'Categorías de gasto', 'expense') ?></div>
    <div class="col-12 col-md-6 col-xl-4"><?= $catalog('categoria', $categories, 'Categorías de ingreso', 'income') ?></div>
</div>

<?= $this->endSection() ?>
