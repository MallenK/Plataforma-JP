<?php
/**
 * Externos de la clase: personas SIN cuenta de alumno (no usan bono).
 * Variables: $session, $externos (array), $canSeePersonal (bool), $externosAvailable (bool),
 *            $canMoney (bool), $payMethods (array).
 * Solo se incluye para quien gestiona la clase (admin/superadmin/staff/coach asignado).
 *
 * La asistencia se marca en «Pasar lista» igual que la de los alumnos (aquí solo se ve).
 * El dinero (precio y cobros) siempre a la vista para quien tiene acceso total a Finanzas.
 */
helper('attendance');
helper('money');
$sid        = (int) $session['id'];
$cancelled  = ($session['status'] ?? '') === 'cancelled';
$canMoney   = !empty($canMoney);
$payMethods = $payMethods ?? [];
$stateLabel = ['sin_precio' => 'Sin precio', 'gratis' => 'Gratis', 'pagado' => 'Pagado', 'parcial' => 'Cobro parcial', 'pendiente' => 'Pendiente de cobro'];
$stateColor = ['sin_precio' => '#b45309', 'gratis' => '#64748b', 'pagado' => '#059669', 'parcial' => '#b45309', 'pendiente' => '#dc2626'];
$openAdd    = !empty($_GET['ext_add']);
// Sin migrar no hay nada que mostrar; sin externos, solo un botón discreto (sin la tarjeta «Externos (0)»).
if (empty($externosAvailable)) { return; }
$bare = empty($externos);
if ($bare && $cancelled) { return; }
?>
<?php if ($bare): ?>
<div id="externos" class="mb-3">
<?php else: ?>
<div class="card-jp mb-3" id="externos">
    <div class="card-jp-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
        <span class="card-jp-title">
            <i class="bi bi-person-badge-fill me-2" style="color:#0ea5e9"></i>
            Externos (<?= count($externos) ?>)
            <span style="font-size:11px;font-weight:400;color:var(--text-muted);margin-left:6px">sin cuenta de alumno · no usan bono</span>
        </span>
        <?php if (!empty($externos) && !$cancelled): ?>
        <a href="/clases/<?= $sid ?>/lista" class="btn-jp btn-jp-secondary btn-jp-sm"><i class="bi bi-clipboard2-check-fill me-1"></i>Pasar lista</a>
        <?php endif; ?>
    </div>
    <?php endif; /* !bare */ ?>

    <?php if (!$externosAvailable): ?>
        <div class="card-jp-body">
            <p style="font-size:13px;color:var(--text-muted);margin:0">
                <i class="bi bi-exclamation-triangle me-1"></i>Este apartado se activará al aplicar la migración de externos en la base de datos.
            </p>
        </div>
    <?php else: ?>

    <?php foreach ($externos as $e): $m = attendance_meta($e['attendance']); ?>
    <div style="padding:14px 20px;border-bottom:1px solid var(--border)">
        <div class="d-flex flex-wrap align-items-start justify-content-between gap-2">
            <div>
                <div style="font-weight:600;color:var(--text-h)">
                    <?php if ($canSeePersonal): ?>
                        <a href="<?= base_url('externos/' . (int) $e['guest_id']) ?>" class="row-link-anchor" title="Ver su ficha e historial"><?= esc($e['name']) ?></a>
                    <?php else: ?><?= esc($e['name']) ?><?php endif; ?>
                </div>
                <?php if ($canSeePersonal && !empty($e['is_minor'])): ?>
                    <span style="font-size:11px;color:#b45309"><i class="bi bi-exclamation-circle me-1"></i>Menor (<?= (int) $e['age'] ?> años)</span>
                <?php elseif ($canSeePersonal && $e['age'] !== null): ?>
                    <span style="font-size:11px;color:var(--text-muted)"><?= (int) $e['age'] ?> años</span>
                <?php endif; ?>
                <?php if (!empty($e['converted_user_id'])): ?>
                    <span style="font-size:11px;color:#059669"><i class="bi bi-check-circle me-1"></i>Ya es alumno</span>
                <?php endif; ?>
                <?php if ($canSeePersonal): ?>
                <div style="font-size:12px;color:var(--text-muted);margin-top:2px">
                    <?php if (!empty($e['phone'])): ?><span class="me-2"><i class="bi bi-telephone me-1"></i><?= esc($e['phone']) ?></span><?php endif; ?>
                    <?php if (!empty($e['email'])): ?><span class="me-2"><i class="bi bi-envelope me-1"></i><?= esc($e['email']) ?></span><?php endif; ?>
                    <?php if (!empty($e['guardian_name'])): ?><span class="me-2">Tutor: <?= esc($e['guardian_name']) ?><?= !empty($e['guardian_phone']) ? ' · ' . esc($e['guardian_phone']) : '' ?></span><?php endif; ?>
                    <span>Imagen: <?= !empty($e['image_rights']) ? 'autorizada' : 'sin autorizar' ?></span>
                </div>
                <?php endif; ?>
            </div>
            <div class="d-flex align-items-center gap-2">
                <span class="badge-status" style="background:<?= $m['color'] ?>22;color:<?= $m['color'] ?>;border:1px solid <?= $m['color'] ?>44;font-size:11px">
                    <i class="bi bi-<?= $m['icon'] ?> me-1"></i><?= esc($m['label']) ?>
                </span>
                <?php if (!$cancelled): ?>
                <button type="button" class="btn-jp btn-jp-secondary btn-jp-icon btn-jp-sm" title="Editar los datos de esta persona"
                        onclick="var f=document.getElementById('ex-edit-<?= (int) $e['link_id'] ?>');f.hidden=!f.hidden;if(!f.hidden)f.querySelector('input').focus()">
                    <i class="bi bi-pencil-fill"></i>
                </button>
                <?php endif; ?>
                <?php if (empty($e['has_payments'])): ?>
                <form action="/clases/<?= $sid ?>/externos/<?= (int) $e['link_id'] ?>/quitar" method="POST" style="margin:0"
                      data-ru-confirm="¿Quitar a esta persona de la clase?"
                      data-ru-confirm-desc="<?= $e['attendance'] === 'pending' ? 'Solo se quita de esta clase. Su ficha se conserva.' : 'Ya tiene la asistencia marcada: se quitará de la clase (queda anotado en el registro de cambios). Su ficha se conserva.' ?>"
                      data-ru-confirm-label="Quitar" data-ru-confirm-danger>
                    <?= csrf_field() ?>
                    <button type="submit" class="btn-jp btn-jp-danger btn-jp-icon btn-jp-sm" title="Quitar de la clase">
                        <i class="bi bi-trash3"></i>
                    </button>
                </form>
                <?php else: ?>
                <span title="Tiene cobros registrados (se conservan como histórico): no se puede quitar de la clase. Si no vino, márcalo como Ausente en Pasar lista." style="color:var(--text-muted);font-size:15px"><i class="bi bi-lock-fill"></i></span>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!$cancelled): ?>
        <form id="ex-edit-<?= (int) $e['link_id'] ?>" action="/clases/<?= $sid ?>/externos/<?= (int) $e['link_id'] ?>/editar" method="POST" hidden
              style="margin-top:10px;padding:12px;border:1px solid var(--border);border-radius:var(--radius-sm)" autocomplete="off">
            <?= csrf_field() ?>
            <div class="row g-2">
                <div class="col-12 col-md-6">
                    <label class="form-label">Nombre *</label>
                    <input type="text" name="first_name" class="form-control-jp" maxlength="100" required value="<?= esc($e['first_name']) ?>">
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label">Apellidos</label>
                    <input type="text" name="last_name" class="form-control-jp" maxlength="100" value="<?= esc($e['last_name'] ?? '') ?>">
                </div>
                <?php if ($canSeePersonal): ?>
                <div class="col-12 col-md-6">
                    <label class="form-label">Fecha de nacimiento <span style="font-weight:400;color:var(--text-muted)">(opcional)</span></label>
                    <input type="date" name="birth_date" class="form-control-jp" max="<?= date('Y-m-d') ?>" value="<?= esc($e['birth_date'] ?? '') ?>">
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label">Teléfono</label>
                    <input type="tel" name="phone" class="form-control-jp" maxlength="30" value="<?= esc($e['phone'] ?? '') ?>">
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label">Email</label>
                    <input type="email" name="email" class="form-control-jp" maxlength="150" value="<?= esc($e['email'] ?? '') ?>">
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label">Madre / padre / tutor</label>
                    <input type="text" name="guardian_name" class="form-control-jp" maxlength="150" value="<?= esc($e['guardian_name'] ?? '') ?>">
                </div>
                <div class="col-12 col-md-6">
                    <label class="form-label">Teléfono del tutor</label>
                    <input type="tel" name="guardian_phone" class="form-control-jp" maxlength="30" value="<?= esc($e['guardian_phone'] ?? '') ?>">
                </div>
                <div class="col-12">
                    <label style="display:flex;gap:8px;align-items:flex-start;font-size:13px;cursor:pointer">
                        <input type="checkbox" name="image_rights" value="1" style="margin-top:3px" <?= !empty($e['image_rights']) ? 'checked' : '' ?>>
                        <span>Autoriza el uso de su imagen (foto/vídeo).</span>
                    </label>
                </div>
                <?php endif; ?>
                <div class="col-12 d-flex flex-wrap align-items-center gap-2">
                    <button type="submit" class="btn-jp btn-jp-primary btn-jp-sm"><i class="bi bi-check-lg me-1"></i>Guardar cambios</button>
                    <button type="button" class="btn-jp btn-jp-secondary btn-jp-sm" onclick="this.closest('form').hidden=true">Cancelar</button>
                    <span style="font-size:11px;color:var(--text-muted)">Se corrige la ficha de la persona (vale para todas sus clases).</span>
                </div>
            </div>
        </form>
        <?php endif; ?>

        <?php if ($canMoney && isset($e['money_state'])): ?>
        <div style="margin-top:10px;padding:10px 12px;background:var(--bg-app);border-radius:var(--radius-sm)">
            <div class="d-flex flex-wrap align-items-center gap-3" style="font-size:13px">
                <span style="font-weight:700;color:<?= $stateColor[$e['money_state']] ?>"><i class="bi bi-cash-coin me-1"></i><?= $stateLabel[$e['money_state']] ?></span>
                <?php if ($e['price_cents'] !== null): ?>
                <span>Precio: <b><?= eur($e['price_cents']) ?></b></span>
                <span>Cobrado: <b><?= eur($e['paid_cents']) ?></b></span>
                <?php endif; ?>
                <?php if ($e['pending_cents'] !== null && $e['pending_cents'] > 0): ?>
                    <span style="color:#dc2626">Pendiente: <b><?= eur($e['pending_cents']) ?></b></span>
                <?php endif; ?>
            </div>

            <div class="row g-2 mt-1">
                <?php if (true): /* el precio se puede corregir siempre (no puede quedar por debajo de lo cobrado) */ ?>
                <!-- Fijar o cambiar el precio (y, si aún no hay precio, cobrarlo de una vez) -->
                <div class="col-12 col-lg-<?= ($e['pending_cents'] !== null && $e['pending_cents'] > 0) ? '5' : '12' ?>">
                    <form action="/clases/<?= $sid ?>/externos/<?= (int) $e['link_id'] ?>/precio" method="POST">
                        <?= csrf_field() ?>
                        <label class="form-label" for="pr-<?= (int) $e['link_id'] ?>">Precio de la clase (€)</label>
                        <div class="d-flex flex-wrap gap-2 align-items-center">
                            <input id="pr-<?= (int) $e['link_id'] ?>" type="text" inputmode="decimal" name="price" class="form-control-jp" style="max-width:110px"
                                   value="<?= esc(eur_input($e['price_cents'])) ?>" placeholder="0 = gratis" required>
                            <?php if ($e['price_cents'] === null): ?>
                            <select name="method_id" class="form-control-jp" style="max-width:150px" aria-label="Medio de pago">
                                <?php foreach ($payMethods as $pm): ?>
                                    <option value="<?= (int) $pm['id'] ?>" <?= $pm['code'] === 'efectivo' ? 'selected' : '' ?>><?= esc($pm['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <label style="display:flex;gap:6px;align-items:center;font-size:13px;cursor:pointer;margin:0">
                                <input type="checkbox" name="paid_now" value="1"><span>Ya lo ha pagado</span>
                            </label>
                            <?php endif; ?>
                            <button class="btn-jp btn-jp-secondary btn-jp-sm" type="submit">Guardar</button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>

                <?php if ($e['pending_cents'] !== null && $e['pending_cents'] > 0): ?>
                <!-- Registrar un cobro (completo o parcial) -->
                <div class="col-12 col-lg-7">
                    <form action="/clases/<?= $sid ?>/externos/<?= (int) $e['link_id'] ?>/cobro" method="POST">
                        <?= csrf_field() ?>
                        <label class="form-label">Registrar cobro</label>
                        <div class="d-flex flex-wrap gap-2">
                            <input type="text" inputmode="decimal" name="amount" class="form-control-jp" style="max-width:110px"
                                   value="<?= esc(eur_input($e['pending_cents'])) ?>" aria-label="Importe cobrado" required>
                            <select name="method_id" class="form-control-jp" style="max-width:160px" aria-label="Medio de pago">
                                <?php foreach ($payMethods as $pm): ?>
                                    <option value="<?= (int) $pm['id'] ?>" <?= $pm['code'] === 'efectivo' ? 'selected' : '' ?>><?= esc($pm['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                            <input type="date" name="paid_at" class="form-control-jp" style="max-width:150px" value="<?= date('Y-m-d') ?>" max="<?= date('Y-m-d') ?>" aria-label="Fecha del cobro">
                            <button class="btn-jp btn-jp-primary btn-jp-sm" type="submit"><i class="bi bi-check-lg me-1"></i>Cobrar</button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($e['payments'])): ?>
            <details class="mt-2">
                <summary style="cursor:pointer;font-size:12px;color:var(--accent);font-weight:600">Historial de cobros (<?= count($e['payments']) ?>)</summary>
                <table class="table-jp mt-2" style="font-size:12px">
                    <thead><tr><th>Fecha</th><th>Medio</th><th>Importe</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($e['payments'] as $p): $voided = !empty($p['voided_at']); ?>
                        <tr style="<?= $voided ? 'opacity:.55;text-decoration:line-through' : '' ?>">
                            <td><?= esc(date('d/m/Y', strtotime($p['paid_at']))) ?></td>
                            <td><?= esc($p['method'] ?? '—') ?></td>
                            <td><?= eur((int) $p['amount_cents']) ?></td>
                            <td style="text-decoration:none">
                                <?php if ($voided): ?>
                                    <span style="font-size:11px;text-decoration:none">Anulado: <?= esc($p['void_reason'] ?? '') ?></span>
                                <?php else: ?>
                                    <form action="/clases/<?= $sid ?>/externos/cobros/<?= (int) $p['id'] ?>/anular" method="POST" class="d-flex gap-1" style="margin:0">
                                        <?= csrf_field() ?>
                                        <input type="text" name="reason" class="form-control-jp" style="font-size:12px;padding:3px 6px" placeholder="Motivo" required maxlength="255" aria-label="Motivo de la anulación">
                                        <button class="btn-jp btn-jp-danger btn-jp-sm" type="submit">Anular</button>
                                    </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </details>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>

    <?php if (!$cancelled): ?>
    <div class="<?= $bare ? '' : 'card-jp-body' ?>">
        <details <?= $openAdd ? 'open' : '' ?>>
            <summary class="btn-jp btn-jp-secondary btn-jp-sm" style="display:inline-flex;cursor:pointer">
                <i class="bi bi-person-plus me-1"></i>Añadir persona externa
            </summary>
            <form action="/clases/<?= $sid ?>/externos/add" method="POST" class="mt-3" autocomplete="off">
                <?= csrf_field() ?>
                <div class="row g-2">
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="ex-first">Nombre *</label>
                        <input id="ex-first" type="text" name="first_name" class="form-control-jp" maxlength="100" required>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="ex-last">Apellidos</label>
                        <input id="ex-last" type="text" name="last_name" class="form-control-jp" maxlength="100">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="ex-birth">Fecha de nacimiento <span style="font-weight:400;color:var(--text-muted)">(opcional)</span></label>
                        <input id="ex-birth" type="date" name="birth_date" class="form-control-jp" max="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="ex-phone">Teléfono <span style="font-weight:400;color:var(--text-muted)">(opcional)</span></label>
                        <input id="ex-phone" type="tel" name="phone" class="form-control-jp" maxlength="30">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="ex-email">Email <span style="font-weight:400;color:var(--text-muted)">(opcional)</span></label>
                        <input id="ex-email" type="email" name="email" class="form-control-jp" maxlength="150">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="ex-gname">Madre / padre / tutor <span style="font-weight:400;color:var(--text-muted)">(si es menor)</span></label>
                        <input id="ex-gname" type="text" name="guardian_name" class="form-control-jp" maxlength="150">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="ex-gphone">Teléfono del tutor</label>
                        <input id="ex-gphone" type="tel" name="guardian_phone" class="form-control-jp" maxlength="30">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="ex-notes">Notas</label>
                        <textarea id="ex-notes" name="notes" rows="2" class="form-control-jp"></textarea>
                    </div>
                    <?php if ($canMoney): ?>
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="ex-price">Precio (€)</label>
                        <input id="ex-price" type="text" inputmode="decimal" name="price" class="form-control-jp" placeholder="0 = gratis">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label" for="ex-method">Medio de pago</label>
                        <select id="ex-method" name="method_id" class="form-control-jp">
                            <?php foreach ($payMethods as $pm): ?>
                                <option value="<?= (int) $pm['id'] ?>" <?= $pm['code'] === 'efectivo' ? 'selected' : '' ?>><?= esc($pm['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-4 d-flex align-items-end">
                        <label style="display:flex;gap:8px;align-items:center;font-size:13px;cursor:pointer;margin-bottom:10px">
                            <input type="checkbox" name="paid_now" value="1"><span>Cobrado ahora</span>
                        </label>
                    </div>
                    <?php endif; ?>
                    <div class="col-12">
                        <label style="display:flex;gap:8px;align-items:flex-start;font-size:13px;cursor:pointer">
                            <input type="checkbox" name="image_rights" value="1" style="margin-top:3px">
                            <span>Autoriza el uso de su imagen (foto/vídeo) en las comunicaciones de la academia.</span>
                        </label>
                    </div>
                    <div class="col-12">
                        <p style="font-size:12px;color:var(--text-muted);margin:0 0 8px">
                            Solo el nombre es obligatorio. Si es menor de edad (y has indicado su fecha de nacimiento), indica también nombre y teléfono del tutor.
                            Los datos personales solo los ve administración.
                        </p>
                        <div class="d-flex flex-wrap gap-2">
                            <button type="submit" class="btn-jp btn-jp-primary btn-jp-sm">
                                <i class="bi bi-plus-lg me-1"></i>Guardar persona
                            </button>
                            <button type="submit" name="another" value="1" class="btn-jp btn-jp-secondary btn-jp-sm">
                                <i class="bi bi-person-plus-fill me-1"></i>Guardar y añadir otra
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </details>
    </div>
    <?php endif; ?>

    <?php endif; /* externosAvailable */ ?>
</div>
