<?= $this->extend('layouts/app') ?>

<?php
helper('money');
helper('attendance');
$converted = !empty($guest['converted_user_id']);
$gid       = (int) $guest['id'];
$fmt       = static fn(?string $d) => $d ? date('d/m/Y', strtotime($d)) : '—';
$stateLabel = ['sin_precio' => 'Sin precio', 'gratis' => 'Gratis', 'pagado' => 'Pagado', 'parcial' => 'Cobro parcial', 'pendiente' => 'Pendiente'];
$stateColor = ['sin_precio' => '#b45309', 'gratis' => '#64748b', 'pagado' => '#059669', 'parcial' => '#b45309', 'pendiente' => '#dc2626'];
?>

<?= $this->section('page_content') ?>

<?php if (session()->getFlashdata('success')): ?>
<div class="alert-jp success mb-3"><i class="bi bi-check-circle-fill me-2"></i><?= esc(session()->getFlashdata('success')) ?></div>
<?php endif; ?>
<?php if (session()->getFlashdata('error')): ?>
<div class="alert-jp error mb-3"><i class="bi bi-x-circle-fill me-2"></i><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif; ?>
<?php if (session()->getFlashdata('created_password')): ?>
<div class="alert-jp warning mb-3" style="display:block">
    <b><i class="bi bi-key-fill me-2"></i>Contraseña temporal del nuevo alumno:</b>
    <code style="font-size:15px;margin-left:6px"><?= esc(session()->getFlashdata('created_password')) ?></code>
    <div style="font-size:12px;margin-top:4px">Solo se muestra ahora. Tendrá que cambiarla al entrar por primera vez.
        <?php if (session()->getFlashdata('created_user_id')): ?>
            <a href="<?= base_url('alumnos/' . (int) session()->getFlashdata('created_user_id')) ?>">Ver ficha del alumno</a>
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<div class="page-header">
    <a href="<?= base_url('externos') ?>" class="btn-jp btn-jp-secondary"><i class="bi bi-arrow-left"></i> Personas externas</a>
</div>

<?php if ($converted): ?>
<div class="alert-jp success mb-3" style="display:block">
    <i class="bi bi-check-circle-fill me-2"></i><b>Ya es alumno.</b>
    <a href="<?= base_url('alumnos/' . (int) $guest['converted_user_id']) ?>">Ver su ficha de alumno</a>.
    Las clases de abajo, que recibió antes como externo, se conservan como histórico.
</div>
<?php endif; ?>

<div class="row g-3">

    <!-- ── Datos ──────────────────────────────────────────── -->
    <div class="col-12 col-lg-5">
        <div class="card-jp mb-3">
            <div class="card-jp-header"><span class="card-jp-title"><i class="bi bi-person-lines-fill me-2" style="color:#0ea5e9"></i>Datos</span></div>
            <form action="<?= base_url('externos/' . $gid . '/editar') ?>" method="POST" class="card-jp-body" autocomplete="off">
                <?= csrf_field() ?>
                <div class="row g-2">
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="g-first">Nombre *</label>
                        <input id="g-first" type="text" name="first_name" class="form-control-jp" maxlength="100" required value="<?= esc($guest['first_name']) ?>">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="g-last">Apellidos</label>
                        <input id="g-last" type="text" name="last_name" class="form-control-jp" maxlength="100" value="<?= esc($guest['last_name'] ?? '') ?>">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="g-birth">Fecha de nacimiento <span style="font-weight:400;color:var(--text-muted)">(opcional)</span></label>
                        <input id="g-birth" type="date" name="birth_date" class="form-control-jp" max="<?= date('Y-m-d') ?>" value="<?= esc($guest['birth_date'] ?? '') ?>">
                        <?php if ($guest['age'] !== null): ?>
                            <div style="font-size:11px;color:<?= $guest['age'] < 18 ? '#b45309' : 'var(--text-muted)' ?>"><?= (int) $guest['age'] ?> años<?= $guest['age'] < 18 ? ' · menor de edad' : '' ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="g-phone">Teléfono</label>
                        <input id="g-phone" type="tel" name="phone" class="form-control-jp" maxlength="30" value="<?= esc($guest['phone'] ?? '') ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="g-email">Email</label>
                        <input id="g-email" type="email" name="email" class="form-control-jp" maxlength="150" value="<?= esc($guest['email'] ?? '') ?>">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="g-gname">Madre / padre / tutor</label>
                        <input id="g-gname" type="text" name="guardian_name" class="form-control-jp" maxlength="150" value="<?= esc($guest['guardian_name'] ?? '') ?>">
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label" for="g-gphone">Teléfono del tutor</label>
                        <input id="g-gphone" type="tel" name="guardian_phone" class="form-control-jp" maxlength="30" value="<?= esc($guest['guardian_phone'] ?? '') ?>">
                    </div>
                    <div class="col-12">
                        <label class="form-label" for="g-notes">Notas</label>
                        <textarea id="g-notes" name="notes" rows="2" class="form-control-jp"><?= esc($guest['notes'] ?? '') ?></textarea>
                    </div>
                    <div class="col-12">
                        <label style="display:flex;gap:8px;align-items:flex-start;font-size:13px;cursor:pointer">
                            <input type="checkbox" name="image_rights" value="1" style="margin-top:3px" <?= !empty($guest['image_rights']) ? 'checked' : '' ?>>
                            <span>Autoriza el uso de su imagen (foto/vídeo).</span>
                        </label>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn-jp btn-jp-primary btn-jp-sm"><i class="bi bi-check-lg me-1"></i>Guardar cambios</button>
                    </div>
                </div>
            </form>
        </div>

        <?php if (!$converted): ?>
        <!-- ── Acciones: nada se hace solo; cada una pide confirmación ── -->
        <div class="card-jp mb-3">
            <div class="card-jp-header"><span class="card-jp-title"><i class="bi bi-lightning-charge-fill me-2" style="color:var(--accent)"></i>Acciones</span></div>
            <div class="card-jp-body">

                <?php if (!$converted): ?>
                <details class="mb-3">
                    <summary class="btn-jp btn-jp-primary btn-jp-sm" style="display:inline-flex;cursor:pointer"><i class="bi bi-person-check-fill me-1"></i>Convertir en alumno</summary>
                    <form action="<?= base_url('externos/' . $gid . '/convertir') ?>" method="POST" class="mt-3" autocomplete="off"
                          data-ru-confirm="¿Crear la cuenta de alumno de <?= esc($guest['name'], 'attr') ?>?"
                          data-ru-confirm-desc="Se creará su usuario, se le enviará un email de bienvenida con una contraseña temporal y esta ficha quedará enlazada. Sus clases anteriores se conservan como histórico: no se les descuenta ningún bono."
                          data-ru-confirm-label="Crear cuenta">
                        <?= csrf_field() ?>
                        <p style="font-size:12px;color:var(--text-muted)">Comprueba los datos: no se rellena nada por suposición. El email es obligatorio y será su usuario.</p>
                        <label class="form-label" for="c-name">Nombre completo</label>
                        <input id="c-name" type="text" name="name" class="form-control-jp mb-2" maxlength="150" required value="<?= esc($guest['name']) ?>">
                        <label class="form-label" for="c-email">Email *</label>
                        <input id="c-email" type="email" name="email" class="form-control-jp mb-2" maxlength="150" required value="<?= esc($guest['email'] ?? '') ?>" placeholder="alumno@correo.com">
                        <label class="form-label" for="c-birth">Fecha de nacimiento <span style="font-weight:400;color:var(--text-muted)">(opcional)</span></label>
                        <input id="c-birth" type="date" name="birth_date" class="form-control-jp mb-2" max="<?= date('Y-m-d') ?>" value="<?= esc($guest['birth_date'] ?? '') ?>">
                        <?php if ($guest['age'] !== null && $guest['age'] < 18): ?>
                        <p style="font-size:12px;color:#b45309"><i class="bi bi-exclamation-circle me-1"></i>Es menor de edad: pon un email al que tenga acceso su madre, padre o tutor<?= !empty($guest['guardian_name']) ? ' (' . esc($guest['guardian_name']) . ')' : '' ?>.</p>
                        <?php endif; ?>
                        <button type="submit" class="btn-jp btn-jp-primary btn-jp-sm"><i class="bi bi-check-lg me-1"></i>Crear cuenta de alumno</button>
                    </form>
                </details>
                <?php endif; ?>

            </div>
        </div>
        <?php endif; ?>
    </div>

    <!-- ── Historial ──────────────────────────────────────── -->
    <div class="col-12 col-lg-7">
        <div class="card-jp">
            <div class="card-jp-header" style="display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px">
                <span class="card-jp-title"><i class="bi bi-calendar-check-fill me-2" style="color:var(--accent)"></i>Clases (<?= (int) $totals['classes'] ?>)</span>
                <span style="font-size:12px;color:var(--text-muted)">
                    Vino a <b><?= (int) $totals['present'] ?></b> · no vino a <b><?= (int) $totals['absent'] ?></b> · por marcar <b><?= (int) $totals['pending'] ?></b>
                    <?php if ($canMoney): ?> · cobrado <b><?= eur((int) $totals['paid']) ?></b> de <b><?= eur((int) $totals['billed']) ?></b><?php endif; ?>
                </span>
            </div>
            <?php if (empty($history)): ?>
            <div class="card-jp-body" style="color:var(--text-muted);text-align:center;padding:28px">Todavía no tiene ninguna clase.</div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table-jp">
                    <thead>
                        <tr>
                            <th>Fecha</th><th>Clase</th><th>Asistencia</th>
                            <?php if ($canMoney): ?><th>Precio</th><th>Cobrado</th><th>Estado</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($history as $h): $m = attendance_meta($h['attendance']); $cancelled = ($h['status'] ?? '') === 'cancelled'; ?>
                        <tr style="<?= $cancelled ? 'opacity:.55' : '' ?>">
                            <td><?= esc($fmt($h['session_date'])) ?><div style="font-size:11px;color:var(--text-muted)"><?= esc(substr($h['start_time'], 0, 5)) ?></div></td>
                            <td><a href="<?= base_url('clases/' . (int) $h['session_id']) ?>" class="row-link-anchor"><?= esc($h['title']) ?></a><?= $cancelled ? ' <span style="font-size:11px;color:#dc2626">· cancelada</span>' : '' ?></td>
                            <td>
                                <span class="badge-status" style="background:<?= $m['color'] ?>22;color:<?= $m['color'] ?>;border:1px solid <?= $m['color'] ?>44;font-size:11px">
                                    <i class="bi bi-<?= $m['icon'] ?> me-1"></i><?= esc($m['label']) ?>
                                </span>
                            </td>
                            <?php if ($canMoney): ?>
                            <td><?= $h['price_cents'] === null ? '—' : eur((int) $h['price_cents']) ?></td>
                            <td><?= eur((int) ($h['paid_cents'] ?? 0)) ?></td>
                            <td><span style="font-size:12px;font-weight:600;color:<?= $stateColor[$h['money_state']] ?? '#64748b' ?>"><?= esc($stateLabel[$h['money_state']] ?? '') ?></span></td>
                            <?php endif; ?>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<?= $this->endSection() ?>
