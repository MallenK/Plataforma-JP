<?= $this->extend('layouts/app') ?>

<?php
helper('avatar');
$pageTitle    = esc($alumno['name'] ?? 'Alumno');
$pageSubtitle = 'Perfil completo del alumno';

$name        = $alumno['name']   ?? '?';
$userAvatar  = $alumno['avatar'] ?? null;
$isAdminUser = in_array(session('role'), ['superadmin', 'admin']);

$levelLabel = match($alumno['level'] ?? '') {
    'beginner'     => 'Principiante',
    'intermediate' => 'Intermedio',
    'advanced'     => 'Avanzado',
    default        => '—',
};

$statusLabel = match($alumno['status'] ?? 'active') {
    'active'   => 'Activo',
    'inactive' => 'Inactivo',
    'banned'   => 'Bloqueado',
    default    => ucfirst($alumno['status'] ?? ''),
};

$classesCount  = (int)($alumno['classes_count']  ?? 0);
$upcomingCount = (int)($alumno['upcoming_count'] ?? 0);
$activeBonos   = (int)($alumno['active_bonos']   ?? 0);

$today = date('Y-m-d');

$formatTime = static function (?string $hms): string {
    if (!$hms) return '';
    return substr($hms, 0, 5);
};
?>

<?= $this->section('page_content') ?>

<?php if (session()->getFlashdata('success')): ?>
<div class="alert-jp success" style="display:flex;align-items:center;gap:10px;margin-bottom:16px">
    <i class="bi bi-check-circle-fill"></i>
    <?= esc(session()->getFlashdata('success')) ?>
</div>
<?php endif; ?>
<?php if (session()->getFlashdata('annotation_success')): ?>
<div class="alert-jp success" style="display:flex;align-items:center;gap:10px;margin-bottom:16px">
    <i class="bi bi-check-circle-fill"></i>
    <?= esc(session()->getFlashdata('annotation_success')) ?>
</div>
<?php endif; ?>
<?php if (session()->getFlashdata('annotation_error')): ?>
<div class="alert-jp danger" style="display:flex;align-items:center;gap:10px;margin-bottom:16px">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <?= esc(session()->getFlashdata('annotation_error')) ?>
</div>
<?php endif; ?>

<!-- Cabecera -->
<div class="page-header">
    <div class="page-header-text">
        <h2><?= esc($name) ?></h2>
        <p>Perfil completo del alumno</p>
    </div>
    <?php if (in_array(session('role'), ['superadmin', 'admin'])): ?>
    <div class="d-flex gap-2">
        <a href="<?= base_url('alumnos') ?>" class="btn-jp btn-jp-secondary">
            <i class="bi bi-arrow-left"></i> Listado
        </a>
        <a href="<?= base_url('alumnos/' . $alumno['id'] . '/editar') ?>" class="btn-jp btn-jp-primary">
            <i class="bi bi-pencil"></i> Editar
        </a>
    </div>
    <?php endif; ?>
</div>

<div class="row g-3">

    <!-- ── Columna izquierda: identidad ─────────────────── -->
    <div class="col-12 col-lg-4 d-flex flex-column gap-3">

        <!-- Tarjeta de identidad -->
        <div class="card-jp">
            <div class="profile-header">
                <div style="position:relative;display:inline-block">
                    <?= avatar_html($userAvatar, $name, 'profile-avatar-lg') ?>
                    <?php if ($isAdminUser): ?>
                    <button onclick="document.getElementById('avatarInputAlumno').click()"
                            title="Cambiar foto"
                            style="position:absolute;bottom:2px;right:2px;width:28px;height:28px;border-radius:50%;background:var(--accent);border:2px solid #fff;color:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer;font-size:13px;padding:0;">
                        <i class="bi bi-camera-fill"></i>
                    </button>
                    <?php endif; ?>
                </div>
                <div>
                    <div class="profile-name"><?= esc($name) ?></div>
                    <div class="profile-email"><?= esc($alumno['email'] ?? '') ?></div>
                    <span class="badge-status <?= esc($alumno['status'] ?? 'active') ?> mt-2 d-inline-block">
                        <?= esc($statusLabel) ?>
                    </span>
                </div>
            </div>
            <?php if ($isAdminUser): ?>
            <div class="card-jp-body pt-0 pb-2 text-center">
                <form id="avatarFormAlumno" action="<?= base_url('avatar/upload/' . $alumno['id']) ?>" method="post" enctype="multipart/form-data">
                    <?= csrf_field() ?>
                    <input type="file" id="avatarInputAlumno" name="avatar"
                           accept="image/jpeg,image/png,image/webp,image/gif"
                           style="display:none"
                           onchange="this.form.submit()">
                </form>
                <?php if ($userAvatar): ?>
                <form action="<?= base_url('avatar/delete/' . $alumno['id']) ?>" method="post">
                    <?= csrf_field() ?>
                    <button type="submit"
                            onclick="return confirm('¿Eliminar el avatar?')"
                            style="background:none;border:none;font-size:12px;color:var(--danger);cursor:pointer;padding:0;text-decoration:underline">
                        <i class="bi bi-trash"></i> Eliminar foto
                    </button>
                </form>
                <?php endif; ?>
            </div>
            <?php endif; ?>
            <div class="card-jp-body">
                <div class="d-flex flex-column gap-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <span style="font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px">ID</span>
                        <span style="font-size:13px;font-weight:600;color:var(--text-h)">#<?= esc($alumno['id']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span style="font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px">Miembro desde</span>
                        <span style="font-size:13px;font-weight:600;color:var(--text-h)">
                            <?= isset($alumno['created_at']) ? date('d M Y', strtotime($alumno['created_at'])) : '—' ?>
                        </span>
                    </div>
                    <?php if (!empty($alumno['birth_date'])): ?>
                    <div class="d-flex justify-content-between align-items-center">
                        <span style="font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px">Fecha nac.</span>
                        <span style="font-size:13px;font-weight:600;color:var(--text-h)">
                            <?= date('d M Y', strtotime($alumno['birth_date'])) ?>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Stats de actividad -->
        <div class="card-jp">
            <div class="card-jp-header">
                <span class="card-jp-title">
                    <i class="bi bi-bar-chart-fill me-2" style="color:var(--accent)"></i>
                    Actividad
                </span>
            </div>
            <div class="card-jp-body">
                <div class="d-flex flex-column gap-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <span style="font-size:13px;color:var(--text-muted)"><i class="bi bi-calendar-check me-2"></i>Clases asistidas</span>
                        <span style="font-size:20px;font-weight:700;color:var(--text-h)"><?= $classesCount ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span style="font-size:13px;color:var(--text-muted)"><i class="bi bi-calendar3 me-2"></i>Próximas clases</span>
                        <span style="font-size:20px;font-weight:700;color:var(--text-h)"><?= $upcomingCount ?></span>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span style="font-size:13px;color:var(--text-muted)"><i class="bi bi-ticket-perforated-fill me-2"></i>Bonos activos</span>
                        <span style="font-size:20px;font-weight:700;color:var(--text-h)"><?= $activeBonos ?></span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Notas médicas -->
        <div class="card-jp">
            <div class="card-jp-header">
                <span class="card-jp-title">
                    <i class="bi bi-clipboard2-pulse-fill me-2" style="color:var(--danger)"></i>
                    Notas médicas
                </span>
            </div>
            <div class="card-jp-body">
                <?php if (!empty($alumno['medical_notes'])): ?>
                    <p style="font-size:13.5px;color:var(--text-body);margin:0;line-height:1.6">
                        <?= nl2br(esc($alumno['medical_notes'])) ?>
                    </p>
                <?php else: ?>
                    <p style="font-size:13px;color:var(--text-muted);margin:0">Sin notas médicas registradas.</p>
                <?php endif; ?>
            </div>
        </div>

    </div>

    <!-- ── Columna derecha: datos + historial ───────────── -->
    <div class="col-12 col-lg-8 d-flex flex-column gap-3">

        <!-- Stats rápidas -->
        <div class="row g-3">
            <div class="col-6 col-md-3">
                <div class="metric-card">
                    <div class="metric-card-header">
                        <span class="metric-label">Posición</span>
                        <div class="metric-icon blue"><i class="bi bi-geo-alt-fill"></i></div>
                    </div>
                    <div class="metric-value" style="font-size:18px"><?= esc($alumno['position'] ?? '—') ?></div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="metric-card">
                    <div class="metric-card-header">
                        <span class="metric-label">Nivel</span>
                        <div class="metric-icon green"><i class="bi bi-bar-chart-fill"></i></div>
                    </div>
                    <div class="metric-value" style="font-size:18px"><?= esc($levelLabel) ?></div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="metric-card">
                    <div class="metric-card-header">
                        <span class="metric-label">Altura</span>
                        <div class="metric-icon orange"><i class="bi bi-rulers"></i></div>
                    </div>
                    <div class="metric-value" style="font-size:18px">
                        <?= !empty($alumno['height']) ? esc($alumno['height']) . ' cm' : '—' ?>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3">
                <div class="metric-card">
                    <div class="metric-card-header">
                        <span class="metric-label">Peso</span>
                        <div class="metric-icon purple"><i class="bi bi-activity"></i></div>
                    </div>
                    <div class="metric-value" style="font-size:18px">
                        <?= !empty($alumno['weight']) ? esc($alumno['weight']) . ' kg' : '—' ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Planes / Bonos -->
        <div class="card-jp">
            <div class="card-jp-header">
                <span class="card-jp-title">
                    <i class="bi bi-ticket-perforated-fill me-2" style="color:var(--accent)"></i>
                    Planes / Bonos
                </span>
                <span style="font-size:12px;color:var(--text-muted)"><?= count($alumno['plans'] ?? []) ?> registrado(s)</span>
            </div>
            <?php if (!empty($alumno['plans'])): ?>
            <div class="table-responsive">
                <table class="table-jp">
                    <thead>
                        <tr>
                            <th>Bono</th>
                            <th>Sesiones</th>
                            <th>Vigencia</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($alumno['plans'] as $bono): ?>
                        <?php
                            $remaining = (int)($bono['sessions_remaining'] ?? 0);
                            $total     = (int)($bono['sessions_total']     ?? 0);
                            $expired   = !empty($bono['expires_at']) && $bono['expires_at'] < $today;
                            $usable    = $remaining > 0 && !$expired;
                            $isActive  = $usable && (int)$bono['id'] === (int)($alumno['active_bono_id'] ?? 0);
                            $isQueued  = $usable && !$isActive;

                            if ($expired)             { $statusLbl = 'Vencido';  $statusCls = 'inactive'; }
                            elseif ($remaining === 0) { $statusLbl = 'Agotado';  $statusCls = 'inactive'; }
                            elseif ($isActive)        { $statusLbl = 'Activo';   $statusCls = 'active'; }
                            elseif ($isQueued)        { $statusLbl = 'En cola';  $statusCls = 'inactive'; }
                            else                      { $statusLbl = '—';        $statusCls = 'inactive'; }

                            $pct = $total > 0 ? max(0, min(100, round(($remaining / $total) * 100))) : 0;
                        ?>
                        <tr>
                            <td>
                                <a href="<?= base_url('bonos/' . (int)$bono['id']) ?>" style="color:inherit;text-decoration:none">
                                    <div style="font-weight:600;color:var(--text-h)"><?= esc($bono['bono_name']) ?></div>
                                    <?php if (!empty($bono['price'])): ?>
                                    <div style="font-size:12px;color:var(--text-muted)"><?= number_format((float)$bono['price'], 2) ?> €</div>
                                    <?php endif; ?>
                                </a>
                            </td>
                            <td style="min-width:140px">
                                <div style="font-size:12px;color:var(--text-muted);margin-bottom:4px">
                                    <strong style="color:var(--text-h)"><?= $remaining ?></strong> / <?= $total ?>
                                </div>
                                <div style="height:6px;background:var(--border);border-radius:3px;overflow:hidden">
                                    <div style="height:100%;width:<?= $pct ?>%;background:<?= $remaining === 0 ? 'var(--danger)' : ($remaining <= 1 ? 'var(--warning,#f59e0b)' : 'var(--accent)') ?>"></div>
                                </div>
                            </td>
                            <td style="font-size:12px;color:var(--text-muted)">
                                <?= !empty($bono['start_date']) ? date('d/m/Y', strtotime($bono['start_date'])) : '—' ?>
                                <?php if (!empty($bono['expires_at'])): ?>
                                → <?= date('d/m/Y', strtotime($bono['expires_at'])) ?>
                                <?php endif; ?>
                            </td>
                            <td>
                                <span class="badge-status <?= $statusCls ?>"><?= $statusLbl ?></span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="card-jp-body">
                <p style="font-size:13px;color:var(--text-muted);margin:0">Sin bonos asignados.</p>
            </div>
            <?php endif; ?>
        </div>

        <!-- Métricas recientes -->
        <div class="card-jp">
            <div class="card-jp-header">
                <span class="card-jp-title">
                    <i class="bi bi-graph-up-arrow me-2" style="color:var(--success)"></i>
                    Últimas métricas
                </span>
                <span style="font-size:12px;color:var(--text-muted)"><?= count($alumno['metrics'] ?? []) ?> registro(s)</span>
            </div>
            <?php if (!empty($alumno['metrics'])): ?>
            <div class="table-responsive">
                <table class="table-jp">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Categoría</th>
                            <th>Valores</th>
                            <th>Evaluación</th>
                            <th>Entrenador</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($alumno['metrics'] as $metric): ?>
                        <?php
                            $payload = [];
                            if (!empty($metric['metrics'])) {
                                $decoded = is_array($metric['metrics'])
                                    ? $metric['metrics']
                                    : json_decode($metric['metrics'], true);
                                if (is_array($decoded)) $payload = $decoded;
                            }
                            $category = $payload['category'] ?? '—';
                            unset($payload['category']);
                        ?>
                        <tr>
                            <td style="white-space:nowrap;color:var(--text-muted);font-size:12px">
                                <?= !empty($metric['date']) ? date('d/m/Y', strtotime($metric['date'])) : '—' ?>
                            </td>
                            <td style="font-size:12px;color:var(--text-h);font-weight:600"><?= esc(ucfirst($category)) ?></td>
                            <td>
                                <?php if (empty($payload)): ?>
                                    <span style="font-size:12px;color:var(--text-muted)">—</span>
                                <?php else: ?>
                                <div style="font-size:12px;color:var(--text-body);line-height:1.5">
                                    <?php foreach ($payload as $k => $v): ?>
                                        <div><strong style="color:var(--text-h);text-transform:capitalize"><?= esc(str_replace('_', ' ', (string)$k)) ?>:</strong> <?= esc(is_scalar($v) ? (string)$v : json_encode($v)) ?></div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            </td>
                            <td><?= esc($metric['evaluation'] ?? '—') ?></td>
                            <td style="font-size:12px;color:var(--text-muted)"><?= esc($metric['coach_name'] ?? '—') ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="card-jp-body">
                <p style="font-size:13px;color:var(--text-muted);margin:0 0 8px 0">
                    <i class="bi bi-info-circle"></i>
                    Sin métricas registradas todavía.
                </p>
                <details style="font-size:12px;color:var(--text-muted)">
                    <summary style="cursor:pointer;font-weight:600;color:var(--text-h)">Ver plantilla de métricas (qué se puede guardar aquí)</summary>
                    <div style="margin-top:10px;padding:10px 12px;background:var(--bg-card-inner,rgba(0,0,0,.04));border-radius:8px;line-height:1.6">
                        En esta sección se pueden registrar valores periódicos del alumno —
                        <strong style="color:var(--text-h)">físicos, técnicos, tácticos</strong> — para llevar
                        un control de su progresión. Cada registro tiene <em>fecha</em>,
                        <em>entrenador</em>, una <em>evaluación global</em>, <em>notas</em>
                        libres y un objeto JSON <em>metrics</em> con pares clave/valor.
                        <br><br>
                        Ejemplo de campos típicos:
                        <ul style="margin:8px 0 0 18px;padding:0">
                            <li><code>weight_kg</code> — peso</li>
                            <li><code>body_fat_pct</code> — % de grasa</li>
                            <li><code>height_cm</code> — altura</li>
                            <li><code>resting_hr</code> — frecuencia cardíaca en reposo</li>
                            <li><code>vo2_max</code> — VO₂ máx estimado</li>
                            <li><code>vertical_jump</code> — salto vertical (cm)</li>
                            <li><code>sprint_30m_s</code> — sprint 30 m (s)</li>
                            <li><code>test_technical</code> — resultado de test técnico</li>
                            <li><code>category</code> — <code>"physical"</code> | <code>"technical"</code> | <code>"tactical"</code></li>
                        </ul>
                    </div>
                </details>
            </div>
            <?php endif; ?>
        </div>

        <!-- Próximas clases -->
        <?php if (!empty($alumno['upcoming'])): ?>
        <div class="card-jp">
            <div class="card-jp-header">
                <span class="card-jp-title">
                    <i class="bi bi-calendar3 me-2" style="color:var(--success)"></i>
                    Próximas clases
                </span>
                <span style="font-size:12px;color:var(--text-muted)"><?= $upcomingCount ?> programada(s)</span>
            </div>
            <div class="table-responsive">
                <table class="table-jp">
                    <thead>
                        <tr>
                            <th>Clase</th>
                            <th>Fecha</th>
                            <th>Horario</th>
                            <th>Sede</th>
                            <th>Confirmación</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($alumno['upcoming'] as $u): ?>
                        <tr>
                            <td style="font-weight:600;color:var(--text-h)">
                                <a href="<?= base_url('clases/' . (int)$u['id']) ?>" style="color:inherit;text-decoration:none">
                                    <?= esc($u['title'] ?? '—') ?>
                                </a>
                            </td>
                            <td style="white-space:nowrap;font-size:12px;color:var(--text-muted)">
                                <?= !empty($u['session_date']) ? date('d/m/Y', strtotime($u['session_date'])) : '—' ?>
                            </td>
                            <td style="white-space:nowrap;font-size:12px;color:var(--text-muted)">
                                <?= esc($formatTime($u['start_time'] ?? null)) ?> – <?= esc($formatTime($u['end_time'] ?? null)) ?>
                            </td>
                            <td style="font-size:12px;color:var(--text-muted)">
                                <?= esc($u['location_name'] ?? $u['location_custom'] ?? '—') ?>
                            </td>
                            <td>
                                <?php $att = $u['attendance'] ?? 'pending'; ?>
                                <span class="badge-status <?= $att === 'confirmed' ? 'active' : ($att === 'declined' ? 'inactive' : 'inactive') ?>">
                                    <?= match($att) {
                                        'confirmed' => 'Confirmada',
                                        'declined'  => 'Rechazada',
                                        default     => 'Pendiente',
                                    } ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Asistencia reciente -->
        <div class="card-jp">
            <div class="card-jp-header">
                <span class="card-jp-title">
                    <i class="bi bi-calendar-check-fill me-2" style="color:var(--accent)"></i>
                    Asistencia reciente
                </span>
                <span style="font-size:12px;color:var(--text-muted)"><?= count($alumno['attendance'] ?? []) ?> registro(s)</span>
            </div>
            <?php if (!empty($alumno['attendance'])): ?>
            <div class="table-responsive">
                <table class="table-jp">
                    <thead>
                        <tr>
                            <th>Clase</th>
                            <th>Fecha</th>
                            <th>Sede</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($alumno['attendance'] as $att): ?>
                        <?php $a = $att['attendance'] ?? ''; ?>
                        <tr>
                            <td style="font-weight:600;color:var(--text-h)">
                                <a href="<?= base_url('clases/' . (int)$att['session_id']) ?>" style="color:inherit;text-decoration:none">
                                    <?= esc($att['session_title'] ?? '—') ?>
                                </a>
                            </td>
                            <td style="white-space:nowrap;font-size:12px;color:var(--text-muted)">
                                <?= !empty($att['session_date']) ? date('d/m/Y', strtotime($att['session_date'])) : '—' ?>
                                <?php if (!empty($att['start_time'])): ?>
                                    · <?= esc($formatTime($att['start_time'])) ?>
                                <?php endif; ?>
                            </td>
                            <td style="font-size:12px;color:var(--text-muted)">
                                <?= esc($att['location_name'] ?? $att['location_custom'] ?? '—') ?>
                            </td>
                            <td>
                                <span class="badge-status <?= $a === 'present' ? 'active' : 'inactive' ?>">
                                    <?= match($a) {
                                        'present'   => 'Asistió',
                                        'absent'    => 'Faltó',
                                        'confirmed' => 'No registrada',
                                        'pending'   => 'Sin respuesta',
                                        'declined'  => 'Rechazada',
                                        default     => ucfirst($a ?: '—'),
                                    } ?>
                                </span>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="card-jp-body">
                <p style="font-size:13px;color:var(--text-muted);margin:0">Sin registros de asistencia.</p>
            </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<!-- ═══════════════════════════════════════════════════════════
     ANOTACIONES
     ═══════════════════════════════════════════════════════ -->
<div id="anotaciones" class="d-flex flex-column gap-3 mt-3">

<?php
$currentUserId   = session('id');
$currentRole     = session('role');
$isStaff         = in_array($currentRole, ['superadmin', 'admin', 'coach', 'staff']);
$isAdminOrSuper  = in_array($currentRole, ['superadmin', 'admin']);

$publicAnnotations   = array_filter($annotations ?? [], fn($a) => $a['type'] === 'public');
$internalAnnotations = array_filter($annotations ?? [], fn($a) => $a['type'] === 'internal');
?>

    <!-- ── Anotaciones públicas ────────────────────────────── -->
    <div class="card-jp">
        <div class="card-jp-header">
            <span class="card-jp-title">
                <i class="bi bi-chat-square-text-fill me-2" style="color:var(--accent)"></i>
                Anotaciones
            </span>
            <span style="font-size:12px;color:var(--text-muted)"><?= count($publicAnnotations) ?> anotación(es)</span>
        </div>

        <!-- Lista -->
        <div class="card-jp-body d-flex flex-column gap-2" id="public-annotations-list">
            <?php if (empty($publicAnnotations)): ?>
                <p style="font-size:13px;color:var(--text-muted);margin:0">Sin anotaciones todavía.</p>
            <?php else: ?>
                <?php foreach ($publicAnnotations as $ann): ?>
                <?php
                    $canDelete = (int)$ann['author_id'] === (int)$currentUserId || $isAdminOrSuper;
                ?>
                <div style="background:var(--bg-card-inner,rgba(0,0,0,.04));border-radius:8px;padding:12px 14px;position:relative">
                    <div style="font-size:13.5px;color:var(--text-body);line-height:1.55;white-space:pre-wrap"><?= nl2br(esc($ann['content'])) ?></div>
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-top:8px">
                        <span style="font-size:11px;color:var(--text-muted)">
                            <i class="bi bi-person-fill me-1"></i><?= esc($ann['author_name']) ?>
                            &nbsp;·&nbsp;
                            <?= date('d/m/Y H:i', strtotime($ann['created_at'])) ?>
                        </span>
                        <?php if ($canDelete): ?>
                        <form action="<?= base_url('anotaciones/' . $ann['id'] . '/eliminar') ?>" method="post"
                              onsubmit="return confirm('¿Eliminar esta anotación?')">
                            <?= csrf_field() ?>
                            <button type="submit"
                                    style="background:none;border:none;color:var(--danger);font-size:12px;cursor:pointer;padding:0;line-height:1"
                                    title="Eliminar">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Formulario nueva anotación pública -->
        <div class="card-jp-body" style="border-top:1px solid var(--border)">
            <form action="<?= base_url('alumnos/' . $alumno['id'] . '/anotaciones') ?>" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="type" value="public">
                <div class="form-group mb-2">
                    <textarea name="content" class="form-control-jp" rows="2"
                              placeholder="Añadir anotación..." required
                              style="resize:vertical;min-height:64px"></textarea>
                </div>
                <div class="text-end">
                    <button type="submit" class="btn-jp btn-jp-primary" style="padding:6px 16px;font-size:13px">
                        <i class="bi bi-plus-circle me-1"></i>Añadir anotación
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- ── Anotaciones internas (solo cuerpo técnico) ─────── -->
    <?php if ($canInternal ?? false): ?>
    <div class="card-jp">
        <div class="card-jp-header">
            <span class="card-jp-title">
                <i class="bi bi-shield-lock-fill me-2" style="color:var(--warning,#f59e0b)"></i>
                Notas internas del cuerpo técnico
            </span>
            <span style="font-size:12px;color:var(--text-muted)"><?= count($internalAnnotations) ?> nota(s)</span>
        </div>

        <!-- Lista -->
        <div class="card-jp-body d-flex flex-column gap-2" id="internal-annotations-list">
            <?php if (empty($internalAnnotations)): ?>
                <p style="font-size:13px;color:var(--text-muted);margin:0">Sin notas internas todavía.</p>
            <?php else: ?>
                <?php foreach ($internalAnnotations as $ann): ?>
                <?php
                    $canDelete = (int)$ann['author_id'] === (int)$currentUserId || $isAdminOrSuper;
                ?>
                <div style="background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.2);border-radius:8px;padding:12px 14px;position:relative">
                    <div style="font-size:13.5px;color:var(--text-body);line-height:1.55;white-space:pre-wrap"><?= nl2br(esc($ann['content'])) ?></div>
                    <div style="display:flex;align-items:center;justify-content:space-between;margin-top:8px">
                        <span style="font-size:11px;color:var(--text-muted)">
                            <i class="bi bi-person-fill me-1"></i><?= esc($ann['author_name']) ?>
                            &nbsp;·&nbsp;
                            <?= date('d/m/Y H:i', strtotime($ann['created_at'])) ?>
                        </span>
                        <?php if ($canDelete): ?>
                        <form action="<?= base_url('anotaciones/' . $ann['id'] . '/eliminar') ?>" method="post"
                              onsubmit="return confirm('¿Eliminar esta nota interna?')">
                            <?= csrf_field() ?>
                            <button type="submit"
                                    style="background:none;border:none;color:var(--danger);font-size:12px;cursor:pointer;padding:0;line-height:1"
                                    title="Eliminar">
                                <i class="bi bi-trash"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>

        <!-- Formulario nueva nota interna -->
        <div class="card-jp-body" style="border-top:1px solid var(--border)">
            <form action="<?= base_url('alumnos/' . $alumno['id'] . '/anotaciones') ?>" method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="type" value="internal">
                <div class="form-group mb-2">
                    <textarea name="content" class="form-control-jp" rows="2"
                              placeholder="Añadir nota interna..." required
                              style="resize:vertical;min-height:64px"></textarea>
                </div>
                <div class="text-end">
                    <button type="submit" class="btn-jp btn-jp-primary" style="padding:6px 16px;font-size:13px;background:var(--warning,#f59e0b);border-color:var(--warning,#f59e0b)">
                        <i class="bi bi-shield-plus me-1"></i>Añadir nota interna
                    </button>
                </div>
            </form>
        </div>
    </div>
    <?php endif; ?>

</div>

<?= console_debug('AlumnosController::show #' . ($alumno['id'] ?? '?'), [
    'id'          => $alumno['id'] ?? null,
    'name'        => $alumno['name'] ?? null,
    'email'       => $alumno['email'] ?? null,
    'role'        => $alumno['role'] ?? null,
    'status'      => $alumno['status'] ?? null,
    'profile_id'  => $alumno['profile_id'] ?? null,
    'height'      => $alumno['height'] ?? null,
    'weight'      => $alumno['weight'] ?? null,
    'level'       => $alumno['level'] ?? null,
    'plans_count'      => count($alumno['plans'] ?? []),
    'metrics_count'    => count($alumno['metrics'] ?? []),
    'attendance_count' => count($alumno['attendance'] ?? []),
    'plans'      => $alumno['plans'] ?? [],
    'metrics'    => $alumno['metrics'] ?? [],
    'attendance' => $alumno['attendance'] ?? [],
]) ?>

<?= $this->endSection() ?>
