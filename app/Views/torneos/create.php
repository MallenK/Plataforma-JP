<?= $this->extend('layouts/app') ?>

<?php
$isEdit  = !empty($event);
$evType  = $isEdit ? $event['type'] : ($type ?? 'torneo');
$action  = $isEdit ? '/torneos/' . $event['id'] . '/editar' : '/torneos/nuevo';
$btnLabel= $isEdit ? 'Guardar cambios' : ($evType === 'campus' ? 'Crear campus' : 'Crear torneo');

// Shorthand para pre-fill
$v = function(string $key, $default = '') use ($event) {
    if (!empty($event[$key])) return esc($event[$key]);
    $old = old($key);
    return $old !== null ? esc($old) : esc($default);
};
?>

<?= $this->section('page_content') ?>

<div class="page-header">
    <div class="page-header-text">
        <h2><?= $isEdit ? 'Editar ' . ($evType === 'campus' ? 'Campus' : 'Torneo') : ($evType === 'campus' ? 'Nuevo Campus' : 'Nuevo Torneo') ?></h2>
        <p>
            <a href="/torneos" style="color:var(--text-muted);text-decoration:none">
                <i class="bi bi-arrow-left me-1"></i>Volver al listado
            </a>
        </p>
    </div>
</div>

<?php if ($flash = session()->getFlashdata('error')): ?>
    <div class="alert-jp danger mb-3"><i class="bi bi-x-circle-fill me-2"></i><?= esc($flash) ?></div>
<?php endif; ?>

<form action="<?= $action ?>" method="POST">
    <?= csrf_field() ?>
    <input type="hidden" name="type" value="<?= esc($evType) ?>">

    <div class="row g-3">

        <!-- ── Columna principal ──────────────────────────────────── -->
        <div class="col-12 col-lg-8">

            <!-- Información básica -->
            <div class="card-jp mb-3">
                <div class="card-jp-header">
                    <span class="card-jp-title">
                        <i class="bi <?= $evType === 'campus' ? 'bi-mortarboard-fill' : 'bi-trophy-fill' ?> me-2"
                           style="color:<?= $evType === 'campus' ? '#7c3aed' : '#d97706' ?>"></i>
                        Información general
                    </span>
                </div>
                <div class="card-jp-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-8">
                            <label class="form-label">Nombre del <?= $evType === 'campus' ? 'campus' : 'torneo' ?> <span style="color:var(--danger)">*</span></label>
                            <input type="text" name="name" class="form-control-jp" required
                                   value="<?= $v('name') ?>"
                                   placeholder="Ej: <?= $evType === 'campus' ? 'Campus Verano 2026' : 'Torneo Ciudad de Murcia 2026' ?>">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label">Categoría</label>
                            <input type="text" name="category" class="form-control-jp"
                                   value="<?= $v('category') ?>"
                                   placeholder="Sub-12, Sub-14, Absoluto…">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Descripción</label>
                            <textarea name="description" class="form-control-jp" rows="3"
                                      placeholder="Descripción breve del evento…"><?= $v('description') ?></textarea>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Fechas y lugar -->
            <div class="card-jp mb-3">
                <div class="card-jp-header">
                    <span class="card-jp-title">
                        <i class="bi bi-calendar3 me-2" style="color:var(--accent)"></i>
                        Fechas y ubicación
                    </span>
                </div>
                <div class="card-jp-body">
                    <div class="row g-3">
                        <div class="col-6 col-md-4">
                            <label class="form-label">Fecha de inicio <span style="color:var(--danger)">*</span></label>
                            <input type="date" name="start_date" class="form-control-jp" required
                                   value="<?= $v('start_date') ?>">
                        </div>
                        <div class="col-6 col-md-4">
                            <label class="form-label">Fecha de fin <span style="color:var(--danger)">*</span></label>
                            <input type="date" name="end_date" class="form-control-jp" required
                                   value="<?= $v('end_date') ?>">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label">Hora de concentración</label>
                            <input type="time" name="concentration_time" class="form-control-jp"
                                   value="<?= $v('concentration_time') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Lugar (ciudad, estadio, dirección…)</label>
                            <input type="text" name="location" class="form-control-jp"
                                   value="<?= $v('location') ?>"
                                   placeholder="Ej: Polideportivo Norte, Murcia / Ciudad de Valencia">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Lugar de concentración <small style="color:var(--text-muted)">(si es diferente al lugar del evento)</small></label>
                            <input type="text" name="concentration_place" class="form-control-jp"
                                   value="<?= $v('concentration_place') ?>"
                                   placeholder="Ej: Parking del polideportivo, Calle Mayor 1">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Equipamiento -->
            <div class="card-jp mb-3">
                <div class="card-jp-header">
                    <span class="card-jp-title">
                        <i class="bi bi-bag-fill me-2" style="color:#0891b2"></i>
                        Equipamiento y material
                    </span>
                </div>
                <div class="card-jp-body">
                    <label class="form-label">Notas de equipamiento</label>
                    <textarea name="equipment_notes" class="form-control-jp" rows="3"
                              placeholder="Ej: Equipación azul + calentador, botas de tacos, botellas de agua…"><?= $v('equipment_notes') ?></textarea>
                </div>
            </div>

            <!-- Sección exclusiva Campus -->
            <?php if ($evType === 'campus'): ?>
            <div class="card-jp mb-3" id="campusFields">
                <div class="card-jp-header">
                    <span class="card-jp-title">
                        <i class="bi bi-house-fill me-2" style="color:#7c3aed"></i>
                        Información del campus
                    </span>
                </div>
                <div class="card-jp-body">
                    <div class="row g-3">
                        <div class="col-12">
                            <label class="form-label">Alojamiento</label>
                            <textarea name="accommodation_info" class="form-control-jp" rows="3"
                                      placeholder="Ej: Hotel Sport Inn, habitaciones dobles incluidas. Check-in 12h del día 1."><?= $v('accommodation_info') ?></textarea>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Programa / horario de actividades</label>
                            <textarea name="schedule_info" class="form-control-jp" rows="5"
                                      placeholder="Ej: Día 1 — Llegada 14:00 / Entreno 16:00 / Cena 20:00&#10;Día 2 — Desayuno 8:30 / Partido 10:00 / Tarde libre"><?= $v('schedule_info') ?></textarea>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

        </div><!-- /col-8 -->

        <!-- ── Sidebar ────────────────────────────────────────────── -->
        <div class="col-12 col-lg-4">

            <!-- Acciones -->
            <div class="card-jp mb-3">
                <div class="card-jp-body">
                    <button type="submit" class="btn-jp btn-jp-primary w-100 mb-2">
                        <i class="bi bi-check-lg me-1"></i><?= $btnLabel ?>
                    </button>
                    <a href="<?= $isEdit ? '/torneos/' . $event['id'] : '/torneos' ?>"
                       class="btn-jp btn-jp-secondary w-100" style="text-align:center;display:block">
                        Cancelar
                    </a>
                </div>
            </div>

            <!-- Info tipo -->
            <div class="card-jp">
                <div class="card-jp-body">
                    <div class="d-flex align-items-center gap-2 mb-2">
                        <?php if ($evType === 'campus'): ?>
                            <span style="font-size:24px;color:#7c3aed"><i class="bi bi-mortarboard-fill"></i></span>
                            <div>
                                <div style="font-weight:700;color:var(--text-h)">Campus formativo</div>
                                <div style="font-size:12px;color:var(--text-muted)">Evento de varios días con programa</div>
                            </div>
                        <?php else: ?>
                            <span style="font-size:24px;color:#d97706"><i class="bi bi-trophy-fill"></i></span>
                            <div>
                                <div style="font-weight:700;color:var(--text-h)">Torneo / Competición</div>
                                <div style="font-size:12px;color:var(--text-muted)">Competición oficial o amistosa</div>
                            </div>
                        <?php endif; ?>
                    </div>
                    <hr style="border-color:var(--border);margin:10px 0">
                    <ul style="font-size:12.5px;color:var(--text-muted);list-style:none;padding:0;margin:0;display:flex;flex-direction:column;gap:6px">
                        <li><i class="bi bi-check2 me-1 text-success"></i>Tras crear, añade equipos y miembros</li>
                        <li><i class="bi bi-check2 me-1 text-success"></i>Envía la convocatoria con un clic</li>
                        <li><i class="bi bi-check2 me-1 text-success"></i>Los convocados confirman desde la app</li>
                    </ul>
                </div>
            </div>

        </div><!-- /sidebar -->
    </div>
</form>

<?= $this->endSection() ?>
