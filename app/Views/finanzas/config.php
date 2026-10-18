<?= $this->extend('layouts/app') ?>

<?php
$pageTitle    = 'Finanzas';
$pageSubtitle = 'Configuración: medios de pago, categorías y reglas';
helper(['money', 'finhelp']);
$hours = (int) ($settings['fin_notice_hours'] ?? 24);
$auto  = ($settings['fin_auto_deduct'] ?? '0') === '1';
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
    <!-- Quién ve las finanzas: acceso por persona. Solo el administrador principal lo cambia. -->
    <div class="col-12">
        <div class="card-jp">
            <div class="card-jp-header"><span class="card-jp-title"><i class="bi bi-shield-lock me-2"></i>Quién ve las finanzas</span></div>
            <div class="card-jp-body" style="font-size:13px;line-height:1.55">
                <p style="margin:0 0 10px;color:var(--text-muted)">
                    Las finanzas completas (cobros, gastos, resumen, análisis, historial de alumnos…) son solo para el administrador principal y las personas que marques aquí.
                    El resto de administradores solo ve la <strong>Revisión de clases</strong>: si se dio la clase, si se pasó lista y si se cerró, sin ningún importe.
                </p>
                <?php if (!empty($isMaster)): ?>
                <form action="<?= base_url('finanzas/configuracion/acceso') ?>" method="post">
                    <?= csrf_field() ?>
                    <div class="d-flex flex-column gap-2 mb-3">
                        <div class="d-flex align-items-center gap-2" style="padding:8px 10px;background:var(--bg-input);border:1px solid var(--border);border-radius:8px">
                            <i class="bi bi-patch-check-fill" style="color:var(--success)"></i>
                            <span><strong>Tú</strong> (administrador principal) — acceso siempre</span>
                        </div>
                        <?php foreach ($accessCandidates as $c): ?>
                        <label class="d-flex align-items-center gap-2" style="padding:8px 10px;border:1px solid var(--border);border-radius:8px;cursor:pointer">
                            <input type="checkbox" name="user_ids[]" value="<?= (int) $c['id'] ?>" <?= !empty($c['granted']) ? 'checked' : '' ?>>
                            <span style="flex:1"><strong><?= esc($c['name']) ?></strong>
                                <span style="color:var(--text-muted)"> · <?= esc($c['email']) ?> · <?= $c['role'] === 'superadmin' ? 'Super Admin' : 'Administrador' ?></span></span>
                            <span style="font-size:12px;font-weight:700;color:<?= !empty($c['granted']) ? 'var(--success)' : 'var(--text-muted)' ?>"><?= !empty($c['granted']) ? 'Ve todo' : 'Solo revisión' ?></span>
                        </label>
                        <?php endforeach; ?>
                        <?php if (empty($accessCandidates)): ?>
                        <p style="margin:0;color:var(--text-muted)">No hay más administradores activos.</p>
                        <?php endif; ?>
                    </div>
                    <button type="submit" class="btn-jp btn-jp-primary"><i class="bi bi-check2 me-1"></i>Guardar quién ve las finanzas</button>
                    <span style="font-size:12px;color:var(--text-muted);margin-left:8px">Cada cambio queda en el registro de auditoría.</span>
                </form>
                <?php else: ?>
                <p style="margin:0"><i class="bi bi-info-circle me-1"></i>Solo el administrador principal puede cambiar esta lista.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <!-- Usuarios que no cuentan en Finanzas (cuentas de prueba). Solo el administrador principal lo cambia. -->
    <div class="col-12">
        <div class="card-jp" id="fin-excluidos">
            <div class="card-jp-header"><span class="card-jp-title"><i class="bi bi-person-slash me-2"></i>Usuarios que no cuentan en las finanzas</span></div>
            <div class="card-jp-body" style="font-size:13px;line-height:1.55">
                <p style="margin:0 0 10px;color:var(--text-muted)">
                    Para operar con cuentas de prueba sin ensuciar las cifras. Un usuario marcado aquí <strong>desaparece de Finanzas</strong>
                    (ventas, cobros, bonos, caducados, deuda, alumnos) y también las <strong>clases en las que participe</strong> como alumno o entrenador
                    (servicio prestado, asistencia, análisis, entrenadores y Revisión). En el resto de la plataforma sigue funcionando igual.
                    No depende de que esté de baja. Es reversible: al quitarlo vuelve a contar.
                </p>
                <?php if (!empty($isMaster)): ?>
                <form action="<?= base_url('finanzas/configuracion/excluidos') ?>" method="post">
                    <?= csrf_field() ?>
                    <div class="d-flex flex-column gap-2 mb-3">
                        <?php foreach ($excluded as $u): ?>
                        <label class="d-flex align-items-center gap-2" style="padding:8px 10px;border:1px solid var(--border);border-radius:8px;cursor:pointer">
                            <input type="checkbox" name="user_ids[]" value="<?= (int) $u['id'] ?>" checked>
                            <span style="flex:1"><strong><?= esc($u['name']) ?></strong>
                                <span style="color:var(--text-muted)"> · <?= esc($u['email']) ?> · <?= esc($u['role']) ?><?= $u['status'] !== 'active' ? ' · ' . esc($u['status']) : '' ?></span></span>
                            <span style="font-size:12px;font-weight:700;color:var(--text-muted)">No cuenta · desmarca para quitarlo</span>
                        </label>
                        <?php endforeach; ?>
                        <?php if (empty($excluded)): ?>
                        <p style="margin:0;color:var(--text-muted)">Ningún usuario excluido: todos cuentan.</p>
                        <?php endif; ?>
                    </div>
                    <div class="d-flex flex-wrap align-items-center gap-2 mb-3">
                        <label for="fx-add" class="form-label" style="margin:0">Añadir usuario</label>
                        <select id="fx-add" name="add_user_id" class="form-control-jp" style="max-width:420px">
                            <option value="">— elige a quién —</option>
                            <?php foreach ($exclusionCandidates as $c): ?>
                            <option value="<?= (int) $c['id'] ?>"><?= esc($c['name']) ?> · <?= esc($c['email']) ?> · <?= esc($c['role']) ?><?= $c['status'] !== 'active' ? ' (' . esc($c['status']) . ')' : '' ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <button type="submit" class="btn-jp btn-jp-primary"><i class="bi bi-check2 me-1"></i>Guardar la lista</button>
                    <span style="font-size:12px;color:var(--text-muted);margin-left:8px">Cada cambio queda en el registro de auditoría.</span>
                </form>
                <?php else: ?>
                <p style="margin:0"><i class="bi bi-info-circle me-1"></i>Solo el administrador principal puede cambiar esta lista.</p>
                <?php endif; ?>
            </div>
        </div>
    </div>
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
                <div>
                    <label class="form-label" for="cf-start">Las finanzas cuentan desde<?= fin_help('cfg_desde') ?></label>
                    <input id="cf-start" name="fin_start_date" type="date" value="<?= esc($finStart, 'attr') ?>" max="<?= date('Y-m-d') ?>" class="form-control-jp" style="width:auto" required>
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
