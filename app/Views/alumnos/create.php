<?= $this->extend('layouts/app') ?>

<?php
$pageTitle    = 'Nuevo alumno';
$pageSubtitle = 'Crear cuenta y ficha del alumno';
?>

<?= $this->section('page_content') ?>

<?php if (session()->getFlashdata('errors')): ?>
<div class="alert-jp danger" style="margin-bottom:16px">
    <i class="bi bi-exclamation-triangle-fill me-2"></i>
    <ul style="margin:0;padding-left:16px">
        <?php foreach ((array) session()->getFlashdata('errors') as $e): ?>
        <li><?= esc($e) ?></li>
        <?php endforeach; ?>
    </ul>
</div>
<?php endif; ?>

<div class="page-header">
    <div class="page-header-text">
        <h2>Nuevo alumno</h2>
        <p>Crea la cuenta y ficha técnica del alumno</p>
    </div>
    <a href="<?= base_url('alumnos') ?>" class="btn-jp btn-jp-secondary">
        <i class="bi bi-arrow-left"></i> Volver al listado
    </a>
</div>

<form method="post" action="<?= base_url('alumnos/nuevo') ?>">
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
                                    placeholder="Ej: Carlos Martínez"
                                    value="<?= esc(old('name')) ?>">
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <div class="form-group">
                                <label class="form-label">Email <span style="color:var(--danger)">*</span></label>
                                <input type="email" name="email" class="form-control-jp" required
                                    placeholder="alumno@ejemplo.com"
                                    value="<?= esc(old('email')) ?>">
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="alert-jp" style="background:var(--bg-card);border:1px solid var(--border);border-radius:8px;padding:12px 16px;font-size:13px;color:var(--text-muted)">
                                <i class="bi bi-key-fill me-2" style="color:var(--accent)"></i>
                                La contraseña inicial se generará automáticamente y se mostrará una sola vez tras crear el alumno.
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <!-- Ficha técnica (opcional) -->
        <div class="col-12">
            <div class="card-jp">
                <div class="card-jp-header">
                    <span class="card-jp-title">
                        <i class="bi bi-person-badge-fill me-2" style="color:var(--accent)"></i>
                        Ficha técnica <span style="font-weight:400;font-size:12px;color:var(--text-muted);margin-left:6px">(opcional)</span>
                    </span>
                </div>
                <div class="card-jp-body">
                    <div class="row g-3">

                        <div class="col-12 col-md-6">
                            <div class="form-group">
                                <label class="form-label">Fecha de nacimiento</label>
                                <input type="date" name="birth_date" class="form-control-jp"
                                    value="<?= esc(old('birth_date')) ?>">
                            </div>
                        </div>

                        <div class="col-12 col-md-6">
                            <div class="form-group">
                                <label class="form-label">Posición</label>
                                <input type="text" name="position" class="form-control-jp"
                                    placeholder="Ej: Base, Escolta, Pivot..."
                                    value="<?= esc(old('position')) ?>">
                            </div>
                        </div>

                        <div class="col-12 col-md-4">
                            <div class="form-group">
                                <label class="form-label">Altura (cm)</label>
                                <input type="number" name="height" class="form-control-jp"
                                    placeholder="Ej: 185" min="100" max="250"
                                    value="<?= esc(old('height')) ?>">
                            </div>
                        </div>

                        <div class="col-12 col-md-4">
                            <div class="form-group">
                                <label class="form-label">Peso (kg)</label>
                                <input type="number" name="weight" class="form-control-jp"
                                    placeholder="Ej: 80" min="30" max="200"
                                    value="<?= esc(old('weight')) ?>">
                            </div>
                        </div>

                        <div class="col-12 col-md-4">
                            <div class="form-group">
                                <label class="form-label">Nivel</label>
                                <select name="level" class="form-control-jp">
                                    <option value="">— Sin especificar —</option>
                                    <option value="beginner"     <?= old('level') === 'beginner'     ? 'selected' : '' ?>>Principiante</option>
                                    <option value="intermediate" <?= old('level') === 'intermediate' ? 'selected' : '' ?>>Intermedio</option>
                                    <option value="advanced"     <?= old('level') === 'advanced'     ? 'selected' : '' ?>>Avanzado</option>
                                </select>
                            </div>
                        </div>

                        <div class="col-12">
                            <div class="form-group">
                                <label class="form-label">Notas médicas o lesiones previas</label>
                                <textarea name="medical_notes" class="form-control-jp" rows="3"
                                    placeholder="Alergias, lesiones previas u otras consideraciones..."><?= esc(old('medical_notes')) ?></textarea>
                            </div>
                        </div>

                    </div>
                </div>
            </div>
        </div>

        <!-- Acciones -->
        <div class="col-12 d-flex gap-2 justify-content-end">
            <a href="<?= base_url('alumnos') ?>" class="btn-jp btn-jp-secondary">Cancelar</a>
            <button type="submit" class="btn-jp btn-jp-primary">
                <i class="bi bi-person-plus-fill"></i> Crear alumno
            </button>
        </div>

    </div>
</form>

<?= $this->endSection() ?>
