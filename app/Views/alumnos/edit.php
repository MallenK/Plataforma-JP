<?= $this->extend('layouts/app') ?>

<?php
$pageTitle    = 'Editar alumno';
$pageSubtitle = esc($alumno['name'] ?? '');
?>

<?= $this->section('page_content') ?>

<div class="page-header">
    <div class="page-header-text">
        <h2>Editar alumno</h2>
        <p><?= esc($alumno['name']) ?></p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= base_url('alumnos/' . $alumno['id']) ?>" class="btn-jp btn-jp-secondary">
            <i class="bi bi-arrow-left"></i> Ver perfil
        </a>
        <a href="<?= base_url('alumnos') ?>" class="btn-jp btn-jp-secondary">
            <i class="bi bi-people-fill"></i> Listado
        </a>
    </div>
</div>

<form method="post" action="<?= base_url('alumnos/' . $alumno['id'] . '/editar') ?>">
    <?= csrf_field() ?>

    <div class="row g-3">

        <!-- Datos de acceso -->
        <div class="col-12">
            <div class="card-jp">
                <div class="card-jp-header">
                    <span class="card-jp-title">
                        <i class="bi bi-person-fill me-2" style="color:var(--accent)"></i>
                        Datos de acceso
                    </span>
                </div>
                <div class="card-jp-body">
                    <div class="row g-3">

                        <div class="col-12 col-md-6">
                            <div class="form-group">
                                <label class="form-label">Nombre completo <span style="color:var(--danger)">*</span></label>
                                <input type="text" name="name" class="form-control-jp" required
                                    value="<?= esc($alumno['name']) ?>">
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <div class="form-group">
                                <label class="form-label">Email <span style="color:var(--danger)">*</span></label>
                                <input type="email" name="email" class="form-control-jp" required
                                    value="<?= esc($alumno['email']) ?>">
                            </div>
                        </div>

                        <div class="col-12 col-md-4">
                            <div class="form-group">
                                <label class="form-label">Estado</label>
                                <select name="status" class="form-control-jp">
                                    <option value="active"   <?= ($alumno['status'] ?? '') === 'active'   ? 'selected' : '' ?>>Activo</option>
                                    <option value="inactive" <?= ($alumno['status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactivo</option>
                                    <option value="banned"   <?= ($alumno['status'] ?? '') === 'banned'   ? 'selected' : '' ?>>Bloqueado</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="alert-jp" style="background:var(--bg-card);border:1px solid var(--border);border-radius:8px;padding:12px 16px;font-size:13px;color:var(--text-muted)">
                                <i class="bi bi-key-fill me-2" style="color:var(--text-muted)"></i>
                                Para cambiar la contraseña, el alumno puede usar la opción "Recuperar contraseña" desde el login.
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <!-- Ficha técnica -->
        <div class="col-12">
            <div class="card-jp">
                <div class="card-jp-header">
                    <span class="card-jp-title">
                        <i class="bi bi-person-badge-fill me-2" style="color:var(--accent)"></i>
                        Ficha técnica
                    </span>
                </div>
                <div class="card-jp-body">
                    <div class="row g-3">

                        <div class="col-12 col-md-6">
                            <div class="form-group">
                                <label class="form-label">Fecha de nacimiento</label>
                                <input type="date" name="birth_date" class="form-control-jp"
                                    value="<?= esc($alumno['birth_date'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <div class="form-group">
                                <label class="form-label">Posición</label>
                                <input type="text" name="position" class="form-control-jp"
                                    placeholder="Ej: Base, Escolta, Pivot..."
                                    value="<?= esc($alumno['position'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="col-12 col-md-4">
                            <div class="form-group">
                                <label class="form-label">Altura (cm)</label>
                                <input type="number" name="height" class="form-control-jp"
                                    min="100" max="250"
                                    value="<?= esc($alumno['height'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="col-12 col-md-4">
                            <div class="form-group">
                                <label class="form-label">Peso (kg)</label>
                                <input type="number" name="weight" class="form-control-jp"
                                    min="30" max="200"
                                    value="<?= esc($alumno['weight'] ?? '') ?>">
                            </div>
                        </div>

                        <div class="col-12 col-md-4">
                            <div class="form-group">
                                <label class="form-label">Nivel</label>
                                <select name="level" class="form-control-jp">
                                    <option value="">— Sin especificar —</option>
                                    <option value="beginner"     <?= ($alumno['level'] ?? '') === 'beginner'     ? 'selected' : '' ?>>Principiante</option>
                                    <option value="intermediate" <?= ($alumno['level'] ?? '') === 'intermediate' ? 'selected' : '' ?>>Intermedio</option>
                                    <option value="advanced"     <?= ($alumno['level'] ?? '') === 'advanced'     ? 'selected' : '' ?>>Avanzado</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="form-group">
                                <label class="form-label">Notas médicas o lesiones previas</label>
                                <textarea name="medical_notes" class="form-control-jp" rows="3"
                                    placeholder="Alergias, lesiones previas u otras consideraciones..."><?= esc($alumno['medical_notes'] ?? '') ?></textarea>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <!-- Acciones -->
        <div class="col-12 d-flex gap-2 justify-content-end">
            <a href="<?= base_url('alumnos/' . $alumno['id']) ?>" class="btn-jp btn-jp-secondary">Cancelar</a>
            <button type="submit" class="btn-jp btn-jp-primary">
                <i class="bi bi-floppy-fill"></i> Guardar cambios
            </button>
        </div>

    </div>
</form>

<?= $this->endSection() ?>
