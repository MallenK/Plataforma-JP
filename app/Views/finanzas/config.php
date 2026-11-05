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
    <div class="col-12">
        <style>
        .rg-grid { display:grid; grid-template-columns:minmax(0,5fr) minmax(0,7fr); gap:20px; align-items:start; }
        @media (max-width: 991px) { .rg-grid { grid-template-columns:1fr; } }
        .rg-state { display:inline-flex; align-items:center; gap:6px; font-size:12px; font-weight:800; padding:3px 10px; border-radius:999px; }
        .rg-state.on { background:#dcfce7; color:#166534; } .rg-state.off { background:#f1f5f9; color:#475569; }
        .rg-box { border-radius:10px; padding:12px 14px; font-size:13px; line-height:1.55; }
        .rg-box h4 { font-size:13px; font-weight:800; margin:0 0 6px; color:#0f172a; }
        .rg-box ul, .rg-box ol { margin:0; padding-left:18px; } .rg-box li { margin-bottom:3px; }
        .rg-table { width:100%; border-collapse:collapse; font-size:13px; }
        .rg-table th, .rg-table td { border:1px solid #e2e8f0; padding:7px 9px; text-align:left; vertical-align:top; }
        .rg-table th { background:#f1f5f9; font-size:12px; }
        .rg-yes { color:#b45309; font-weight:800; } .rg-no { color:#166534; font-weight:800; }
        </style>
        <div class="card-jp">
            <div class="card-jp-header"><span class="card-jp-title">Reglas de asistencia y bono</span></div>
            <div class="card-jp-body rg-grid">
                <form action="<?= base_url('finanzas/configuracion') ?>" method="post" class="d-flex flex-column gap-3" style="font-size:13px">
                    <?= csrf_field() ?>
                    <label class="d-flex gap-2 align-items-start" style="cursor:pointer">
                        <input type="checkbox" name="fin_auto_deduct" id="cf-auto" value="1" <?= $auto ? 'checked' : '' ?> style="margin-top:3px">
                        <span><strong>Descontar la sesión automáticamente</strong><?= fin_help('cfg_auto') ?>
                            <span class="rg-state <?= $auto ? 'on' : 'off' ?>" id="cf-auto-state" style="margin-left:6px"><?= $auto ? 'Activado' : 'Desactivado' ?></span><br>
                            cuando el alumno <strong>falta sin justificar</strong> o <strong>avisa con poca antelación</strong>. Siempre se puede deshacer desde Pasar lista (entrenador, admin y superadmin).</span>
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

                <div class="d-flex flex-column gap-3">
                    <div class="rg-box" style="background:#eff6ff;border:1px solid #bfdbfe">
                        <h4><i class="bi bi-lightbulb me-1"></i>En una frase</h4>
                        Si un alumno <strong>no viene y no ha avisado con tiempo</strong>, esa clase se le cuenta como dada: la plataforma <strong>gasta 1 sesión de su bono</strong> al guardar la lista, sin que nadie tenga que acordarse.
                    </div>

                    <div>
                        <h4 style="font-size:13px;font-weight:800;margin:0 0 6px">¿Cuándo se gasta una sesión?</h4>
                        <table class="rg-table">
                            <tr><th>Lo que marcas al pasar lista</th><th>¿Gasta sesión?</th></tr>
                            <tr><td>Presente</td><td><span class="rg-yes">Sí</span> (como siempre)</td></tr>
                            <tr><td><strong>Falta sin justificar</strong></td><td><span class="rg-yes">Sí, solo</span> <em>(esta regla)</em></td></tr>
                            <tr><td><strong>Avisó que no viene</strong> con menos de <b class="js-hours"><?= $hours ?></b> h de antelación, o sin hora de aviso</td><td><span class="rg-yes">Sí, solo</span> <em>(esta regla)</em></td></tr>
                            <tr><td>Avisó que no viene con <b class="js-hours"><?= $hours ?></b> h o más</td><td><span class="rg-no">No</span></td></tr>
                            <tr><td>Ausencia justificada</td><td><span class="rg-no">No</span></td></tr>
                        </table>
                    </div>

                    <div class="rg-box" style="background:#f8fafc;border:1px solid #e2e8f0">
                        <h4><i class="bi bi-person-check me-1"></i>Ejemplo</h4>
                        Clase de <strong>17:00</strong>. El alumno avisa a las <strong>10:00</strong> del mismo día que no puede ir: son 7 horas de antelación.
                        <ul style="margin-top:6px">
                            <li>Con el mínimo en <b class="js-hours"><?= $hours ?></b> h: avisó <strong>tarde</strong> → se descuenta 1 sesión.</li>
                            <li>Si hubiera avisado el día anterior a las 16:00 (25 h antes): <strong>a tiempo</strong> → no se descuenta nada.</li>
                        </ul>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <div class="rg-box h-100" style="background:#ecfdf5;border:1px solid #a7f3d0">
                                <h4><i class="bi bi-toggle-on me-1"></i>Si está ACTIVADO</h4>
                                <ul>
                                    <li>Al <strong>guardar la lista</strong> de una clase, el descuento se hace solo y aparece un mensaje diciendo a cuántos alumnos.</li>
                                    <li>Se descuenta del bono <strong>que caduca antes</strong>.</li>
                                    <li>Queda anotado en el historial del bono y del alumno como «Descuento automático» con su motivo.</li>
                                    <li>Cuenta como <strong>servicio prestado</strong> en Finanzas.</li>
                                    <li>Si el bono se queda con 1 o 0 sesiones, salen los avisos de saldo bajo de siempre.</li>
                                </ul>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="rg-box h-100" style="background:#f8fafc;border:1px solid #e2e8f0">
                                <h4><i class="bi bi-toggle-off me-1"></i>Si está DESACTIVADO</h4>
                                <ul>
                                    <li>No se descuenta nada solo por faltas ni avisos tardíos.</li>
                                    <li>Quien pasa lista decide en cada caso y descuenta a mano con <strong>Descontar bono</strong>.</li>
                                    <li>Las clases que consumen y no se descuentan quedan como <strong>clases sin bono</strong> en Finanzas › Revisión.</li>
                                </ul>
                            </div>
                        </div>
                    </div>

                    <div class="rg-box" style="background:#fffbeb;border:1px solid #fde68a">
                        <h4><i class="bi bi-arrow-counterclockwise me-1"></i>¿Y si se descuenta por error?</h4>
                        <ol>
                            <li>Abre la clase y entra en <strong>Pasar lista</strong> (lo puede hacer el entrenador de esa clase, el admin y el superadmin).</li>
                            <li>En la fila del alumno pulsa <strong>Devolver bono</strong>. La sesión vuelve a su bono al instante.</li>
                            <li>Si no quieres que se vuelva a descontar al guardar, cambia su estado a <strong>Ausencia justificada</strong>.</li>
                        </ol>
                    </div>

                    <div class="rg-box" style="background:#faf5ff;border:1px solid #e9d5ff">
                        <h4><i class="bi bi-journal-check me-1"></i>Reglas de uso</h4>
                        <ol>
                            <li><strong>Avisa antes de activarlo.</strong> Esta regla cobra sesiones: comunica a las familias el aviso mínimo (<b class="js-hours"><?= $hours ?></b> h) antes de ponerla en marcha.</li>
                            <li><strong>Marca bien el estado.</strong> «Avisó ausencia» solo si el alumno avisó de verdad; si no avisó, es «Falta sin justificar». Cada estado decide si se cobra o no.</li>
                            <li><strong>Guarda la lista cuando esté completa.</strong> La regla se aplica en cada guardado, así que no la guardes a medias con alumnos mal marcados.</li>
                            <li><strong>Las excepciones se hacen en la clase.</strong> Si hay un motivo para no cobrar, usa <em>Devolver bono</em> o <em>Ausencia justificada</em>; no desactives la regla para todos por un caso.</li>
                            <li><strong>Revisa una vez por semana</strong> Finanzas › Revisión: ahí salen las clases que no se pudieron descontar por falta de bono.</li>
                            <li><strong>Si cambias las horas o la desactivas</strong>, solo afecta a las listas que se guarden a partir de ese momento; lo ya descontado no se toca.</li>
                            <li><strong>Quién puede cambiar esta opción:</strong> solo quien tiene acceso total a Finanzas. Cada cambio queda registrado.</li>
                        </ol>
                    </div>

                    <div class="rg-box" style="background:#f8fafc;border:1px solid #e2e8f0">
                        <h4><i class="bi bi-info-circle me-1"></i>Qué conviene saber</h4>
                        <ul>
                            <li><strong>Solo actúa al guardar la lista.</strong> Las clases ya guardadas antes de activarlo no cambian hasta que se vuelva a guardar su lista.</li>
                            <li>Si el alumno <strong>no tiene bono con saldo</strong>, no se descuenta nada: la clase queda como «sin bono» en Revisión y se salda cuando le des un bono (Bonos › Asignar bono).</li>
                            <li>No se descuenta dos veces la misma clase.</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <script>
        (function () {
            var h = document.getElementById('cf-hours'), a = document.getElementById('cf-auto'), st = document.getElementById('cf-auto-state');
            if (h) h.addEventListener('input', function () {
                var v = parseInt(h.value, 10); if (!(v > 0)) return;
                document.querySelectorAll('.js-hours').forEach(function (e) { e.textContent = v; });
            });
            if (a && st) a.addEventListener('change', function () {
                st.className = 'rg-state ' + (a.checked ? 'on' : 'off'); st.textContent = a.checked ? 'Activado (sin guardar)' : 'Desactivado (sin guardar)';
            });
        })();
        </script>
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
