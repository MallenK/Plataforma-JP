<?= $this->extend('layouts/app') ?>

<?= $this->section('page_content') ?>

<?php
$canEdit   = in_array(session('role'), ['admin', 'superadmin', 'player']);
$isEditing = !empty($profile);
?>

<div class="page-header">
    <div class="page-header-text">
        <h2><?= $isEditing ? 'Editar ficha' : 'Crear ficha técnica' ?></h2>
        <p><?= $isEditing ? 'Actualiza tus datos deportivos' : 'Completa tu perfil para acceder a la plataforma' ?></p>
    </div>
</div>

<?php if (!$canEdit): ?>
<div class="alert-jp warning">
    <i class="bi bi-exclamation-triangle-fill"></i>
    No tienes permisos para editar este perfil.
</div>
<?php else: ?>

<div class="row g-3 justify-content-center">
    <div class="col-12 col-lg-8">
        <div class="card-jp">
            <div class="card-jp-header">
                <span class="card-jp-title">
                    <i class="bi bi-person-badge-fill me-2" style="color:var(--accent)"></i>
                    Datos deportivos
                </span>
            </div>
            <div class="card-jp-body">

                <form method="post" action="<?= base_url('alumno/save') ?>">
                    <?= csrf_field() ?>

                    <div class="row g-3">

                        <div class="col-12 col-md-6">
                            <div class="form-group">
                                <label class="form-label">Fecha de nacimiento <span style="color:var(--danger)">*</span></label>
                                <input type="date" name="birth_date" class="form-control-jp" required
                                    value="<?= esc($profile['birth_date'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <div class="form-group">
                                <label class="form-label">Posición</label>
                                <input type="text" name="position" class="form-control-jp"
                                    placeholder="Ej: Base, Escolta, Pivot..."
                                    value="<?= esc($profile['position'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="col-12 col-md-4">
                            <div class="form-group">
                                <label class="form-label">Altura (cm)</label>
                                <input type="number" name="height" class="form-control-jp"
                                    placeholder="Ej: 185" step="0.1" min="100" max="250"
                                    value="<?= esc($profile['height'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="col-12 col-md-4">
                            <div class="form-group">
                                <label class="form-label">Peso (kg)</label>
                                <input type="number" name="weight" class="form-control-jp"
                                    placeholder="Ej: 80" step="0.1" min="30" max="200"
                                    value="<?= esc($profile['weight'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="col-12 col-md-4">
                            <div class="form-group">
                                <label class="form-label">Categoría</label>
                                <select name="category" class="form-control-jp">
                                    <option value="">— Sin especificar —</option>
                                    <option value="prebenjamin" <?= ($profile['category'] ?? '') === 'prebenjamin' ? 'selected' : '' ?>>Prebenjamín</option>
                                    <option value="benjamin"    <?= ($profile['category'] ?? '') === 'benjamin'    ? 'selected' : '' ?>>Benjamín</option>
                                    <option value="alevin"      <?= ($profile['category'] ?? '') === 'alevin'      ? 'selected' : '' ?>>Alevín</option>
                                    <option value="infantil"    <?= ($profile['category'] ?? '') === 'infantil'    ? 'selected' : '' ?>>Infantil</option>
                                    <option value="cadete"      <?= ($profile['category'] ?? '') === 'cadete'      ? 'selected' : '' ?>>Cadete</option>
                                    <option value="juvenil"     <?= ($profile['category'] ?? '') === 'juvenil'     ? 'selected' : '' ?>>Juvenil</option>
                                    <option value="junior"      <?= ($profile['category'] ?? '') === 'junior'      ? 'selected' : '' ?>>Júnior</option>
                                    <option value="senior"      <?= ($profile['category'] ?? '') === 'senior'      ? 'selected' : '' ?>>Sénior</option>
                                    <option value="veterano"    <?= ($profile['category'] ?? '') === 'veterano'    ? 'selected' : '' ?>>Veterano</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-12 col-md-4">
                            <div class="form-group">
                                <label class="form-label">Equipo</label>
                                <input type="text" name="team" class="form-control-jp"
                                    placeholder="Ej: CD Juvenil A"
                                    value="<?= esc($profile['team'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="col-12 col-md-4">
                            <div class="form-group">
                                <label class="form-label">Liga</label>
                                <input type="text" name="league" class="form-control-jp"
                                    placeholder="Ej: Liga Autonómica"
                                    value="<?= esc($profile['league'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="form-group">
                                <label class="form-label">Notas médicas o lesiones previas</label>
                                <textarea name="medical_notes" class="form-control-jp"
                                    placeholder="Indica alergias, lesiones previas u otras consideraciones médicas relevantes..."><?= esc($profile['medical_notes'] ?? '') ?></textarea>
                            </div>
                        </div>

                        <div class="col-12 d-flex gap-2 justify-content-end">
                            <?php if ($isEditing): ?>
                            <a href="<?= base_url('alumno') ?>" class="btn-jp btn-jp-secondary">
                                Cancelar
                            </a>
                            <?php endif; ?>
                            <button type="submit" class="btn-jp btn-jp-primary">
                                <i class="bi bi-floppy-fill"></i>
                                <?= $isEditing ? 'Guardar cambios' : 'Crear ficha' ?>
                            </button>
                        </div>

                    </div>
                </form>

            </div>
        </div>
    </div>
</div>

<?php endif; ?>

<?= $this->endSection() ?>
