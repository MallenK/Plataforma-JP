<?= $this->extend('layouts/app') ?>

<?php
$statusMap = [
    'scheduled' => ['Programada',  '#2563eb', 'bi-calendar-event-fill'],
    'completed' => ['Completada',  '#059669', 'bi-check-circle-fill'],
    'cancelled' => ['Cancelada',   '#dc2626', 'bi-x-circle-fill'],
];
[$statusLabel, $statusColor, $statusIcon] = $statusMap[$session['status']] ?? ['—', '#6b7280', 'bi-dash'];

$attendanceMap = [
    'pending'   => ['Pendiente',  '#d97706', 'bi-clock-fill'],
    'confirmed' => ['Confirmado', '#059669', 'bi-check-circle-fill'],
    'declined'  => ['Declinado',  '#dc2626', 'bi-x-circle-fill'],
    'present'   => ['Presente',   '#059669', 'bi-person-check-fill'],
    'absent'    => ['Ausente',    '#dc2626', 'bi-person-x-fill'],
];

$locationDisplay = $session['location_name'] ?? $session['location_custom'] ?? null;
?>

<?= $this->section('page_content') ?>

<div class="page-header">

    <a href="/clases" class="btn-jp btn-jp-secondary btn-jp-sm" style="margin-right:8px">
        <i class="bi bi-arrow-left me-1"></i>Clases
    </a>

    <?php if ($canManage): ?>
    <div class="d-flex gap-2 flex-wrap">
        <?php if (in_array(session('role'), ['admin', 'superadmin'])): ?>
        <a href="/clases/<?= $session['id'] ?>/lista"
           class="btn-jp btn-jp-sm"
           style="background:#ede9fe;color:#5b21b6;border:1px solid #c4b5fd">
            <i class="bi bi-clipboard2-check-fill me-1"></i>Pasar Lista
        </a>
        <?php endif; ?>
        <?php if ($session['status'] === 'scheduled'): ?>
        <form action="/clases/<?= $session['id'] ?>/completar" method="POST" style="margin:0">
            <?= csrf_field() ?>
            <button type="submit" class="btn-jp btn-jp-sm" style="background:#d1fae5;color:#065f46">
                <i class="bi bi-check-circle-fill me-1"></i>Marcar completada
            </button>
        </form>
        <?php endif; ?>
        <?php if (in_array($session['status'], ['scheduled'])): ?>
        <a href="/clases/<?= $session['id'] ?>/editar" class="btn-jp btn-jp-secondary btn-jp-sm">
            <i class="bi bi-pencil-fill me-1"></i>Editar
        </a>
        <form action="/clases/<?= $session['id'] ?>/cancelar" method="POST" style="margin:0">
            <?= csrf_field() ?>
            <button type="submit" class="btn-jp btn-jp-danger btn-jp-sm"
                    onclick="return confirm('¿Cancelar esta sesión?')">
                <i class="bi bi-x-circle-fill me-1"></i>Cancelar sesión
            </button>
        </form>
        <?php endif; ?>
        <form action="/clases/<?= $session['id'] ?>/eliminar" method="POST" style="margin:0">
            <?= csrf_field() ?>
            <button type="submit" class="btn-jp btn-jp-danger btn-jp-sm"
                    onclick="return confirm('¿Eliminar esta sesión permanentemente?')">
                <i class="bi bi-trash3-fill me-1"></i>Eliminar
            </button>
        </form>
    </div>
    <?php endif; ?>
</div>

<?php if ($flash = session()->getFlashdata('success')): ?>
    <div class="alert-jp success mb-3"><i class="bi bi-check-circle-fill me-2"></i><?= esc($flash) ?></div>
<?php endif; ?>
<?php if ($flash = session()->getFlashdata('error')): ?>
    <div class="alert-jp error mb-3"><i class="bi bi-x-circle-fill me-2"></i><?= esc($flash) ?></div>
<?php endif; ?>

<!-- ── Mi convocatoria (solo jugadores) ────────────────────── -->
<?php if ($myPlayer): ?>
<?php
$myAttendance = $myPlayer['attendance'] ?? 'pending';
[$aLabel, $aColor, $aIcon] = $attendanceMap[$myAttendance] ?? $attendanceMap['pending'];
$hasStudentNote = !empty($myPlayer['student_note']);
$sessionDate    = $session['session_date'] ?? '';
$todayStr       = date('Y-m-d');
$isToday        = $sessionDate === $todayStr;
$pastCutoff     = $isToday && date('H:i') > '10:00';
?>
<div class="card-jp mb-3" style="border-left:3px solid <?= $statusColor ?>">
    <div class="card-jp-body">
        <div class="d-flex align-items-start justify-content-between flex-wrap gap-3">
            <div>
                <div style="font-weight:700;color:var(--text-h);margin-bottom:6px">Mi asistencia</div>
                <span class="badge-status" style="background:<?= $aColor ?>22;color:<?= $aColor ?>;border:1px solid <?= $aColor ?>44">
                    <i class="bi <?= $aIcon ?> me-1"></i><?= $aLabel ?>
                </span>
                <?php if ($myPlayer['coach_name']): ?>
                    <span style="font-size:12px;color:var(--text-muted);margin-left:10px">
                        <i class="bi bi-person-workspace me-1"></i>Entrenador: <?= esc($myPlayer['coach_name']) ?>
                    </span>
                <?php endif; ?>
                <?php if (!empty($myPlayer['absence_reason'])): ?>
                <div style="margin-top:8px;font-size:12.5px;color:var(--text-muted)">
                    <i class="bi bi-chat-left-text me-1" style="color:var(--danger)"></i>
                    <strong style="color:var(--danger)">Motivo (admin):</strong> <?= esc($myPlayer['absence_reason']) ?>
                </div>
                <?php endif; ?>
                <?php if ($hasStudentNote): ?>
                <div style="margin-top:6px;font-size:12.5px;color:var(--text-muted)">
                    <i class="bi bi-check-circle-fill me-1" style="color:#059669"></i>
                    <strong style="color:#059669">Tu aviso enviado:</strong> <?= esc($myPlayer['student_note']) ?>
                    <span style="font-size:11px;margin-left:4px">(<?= !empty($myPlayer['student_noted_at']) ? date('d/m H:i', strtotime($myPlayer['student_noted_at'])) : '' ?>)</span>
                </div>
                <?php endif; ?>
            </div>

            <?php if ($session['status'] === 'scheduled' && !$hasStudentNote): ?>
            <div style="min-width:240px">
                <?php if ($pastCutoff): ?>
                <div class="alert-jp" style="background:rgba(245,158,11,.1);border:1px solid rgba(245,158,11,.3);border-radius:8px;padding:8px 12px;font-size:12px;color:#92400e;margin-bottom:8px">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                    <strong>Aviso tardío:</strong> Los avisos deben enviarse antes de las 10:00 del día de la clase. Tu aviso se registrará igualmente.
                </div>
                <?php elseif ($isToday): ?>
                <div class="alert-jp" style="background:rgba(245,158,11,.1);border:1px solid rgba(245,158,11,.3);border-radius:8px;padding:8px 12px;font-size:12px;color:#92400e;margin-bottom:8px">
                    <i class="bi bi-clock-fill me-1"></i>
                    Recuerda: los avisos deben enviarse <strong>antes de las 10:00</strong> del día de la clase.
                </div>
                <?php endif; ?>
                <form action="/clases/<?= $session['id'] ?>/ausencia" method="POST" style="margin:0">
                    <?= csrf_field() ?>
                    <textarea name="student_note" class="form-control-jp mb-2" rows="2"
                              placeholder="Motivo (opcional)…"
                              style="resize:none;font-size:13px"></textarea>
                    <button type="submit" class="btn-jp btn-jp-danger btn-jp-sm w-100">
                        <i class="bi bi-calendar-x-fill me-1"></i>Avisar que no puedo asistir
                    </button>
                </form>
            </div>
            <?php elseif ($session['status'] === 'scheduled' && $hasStudentNote): ?>
            <span style="font-size:12px;color:#059669">
                <i class="bi bi-check-circle-fill me-1"></i>Aviso enviado
            </span>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="row g-3">

    <!-- ── Columna principal ──────────────────────────────── -->
    <div class="col-12 col-lg-8">

        <!-- Info de la sesión -->
        <div class="card-jp mb-3">
            <div class="card-jp-header">
                <span class="card-jp-title">
                    <i class="bi bi-info-circle-fill me-2" style="color:var(--accent)"></i>
                    Detalles de la sesión
                </span>
            </div>
            <div class="card-jp-body">
                <div class="row g-3">
                    <div class="col-12 mb-1">
                        <span class="badge-status" style="background:<?= $statusColor ?>22;color:<?= $statusColor ?>;border:1px solid <?= $statusColor ?>44">
                            <i class="bi <?= $statusIcon ?> me-1"></i><?= $statusLabel ?>
                        </span>
                    </div>
                    <div class="col-6 col-md-4">
                        <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--text-muted);letter-spacing:.5px;margin-bottom:4px">Fecha</div>
                        <div style="font-weight:600;color:var(--text-h)">
                            <?= date('d/m/Y', strtotime($session['session_date'])) ?>
                        </div>
                        <div style="font-size:12px;color:var(--text-muted)">
                            <?= ['','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo'][(int)date('N', strtotime($session['session_date']))] ?>
                        </div>
                    </div>
                    <div class="col-6 col-md-4">
                        <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--text-muted);letter-spacing:.5px;margin-bottom:4px">Horario</div>
                        <div style="font-weight:600;color:var(--text-h)">
                            <?= substr($session['start_time'], 0, 5) ?> – <?= substr($session['end_time'], 0, 5) ?>
                        </div>
                        <?php
                        $mins = (strtotime($session['end_time']) - strtotime($session['start_time'])) / 60;
                        if ($mins > 0): ?>
                        <div style="font-size:12px;color:var(--text-muted)"><?= $mins ?> min.</div>
                        <?php endif; ?>
                    </div>
                    <div class="col-12 col-md-4">
                        <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--text-muted);letter-spacing:.5px;margin-bottom:4px">Lugar</div>
                        <div style="font-weight:600;color:var(--text-h)">
                            <?= $locationDisplay ? esc($locationDisplay) : '<span style="color:var(--text-muted);font-weight:400">No especificado</span>' ?>
                        </div>
                    </div>
                    <?php if (!empty($session['focus'])): ?>
                    <div class="col-12">
                        <div style="font-size:11px;font-weight:700;text-transform:uppercase;color:var(--text-muted);letter-spacing:.5px;margin-bottom:4px">Objetivo del entrenamiento</div>
                        <div style="font-weight:500;color:var(--text-h)"><?= esc($session['focus']) ?></div>
                    </div>
                    <?php endif; ?>
                    <?php if (!empty($session['class_info'])): ?>
                    <div class="col-12">
                        <div style="font-size:12px;color:var(--text-muted);display:flex;align-items:center;gap:6px">
                            <i class="bi bi-arrow-repeat" style="color:#7c3aed"></i>
                            Parte de la clase recurrente:
                            <strong style="color:var(--text-h)"><?= esc($session['class_info']['title']) ?></strong>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Observaciones (planificación + feedback) -->
        <?php if ($canManage): ?>
        <form action="/clases/<?= $session['id'] ?>/observaciones" method="POST">
            <?= csrf_field() ?>
            <div class="card-jp mb-3">
                <div class="card-jp-header">
                    <span class="card-jp-title">
                        <i class="bi bi-clipboard-fill me-2" style="color:#7c3aed"></i>
                        Observaciones de la sesión
                    </span>
                </div>
                <div class="card-jp-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-6">
                            <label class="form-label">
                                <i class="bi bi-arrow-right-circle me-1" style="color:#7c3aed"></i>
                                Antes — Planificación
                            </label>
                            <textarea name="pre_notes" class="form-control-jp" rows="4"
                                      placeholder="Objetivos, ejercicios planificados…"><?= esc($session['pre_notes'] ?? '') ?></textarea>
                        </div>
                        <div class="col-12 col-md-6">
                            <label class="form-label">
                                <i class="bi bi-check-circle me-1" style="color:#059669"></i>
                                Después — Feedback
                                <?php if ($session['status'] !== 'completed'): ?>
                                <small style="color:var(--text-muted)">(disponible tras completar)</small>
                                <?php endif; ?>
                            </label>
                            <textarea name="post_notes" class="form-control-jp" rows="4"
                                      placeholder="Qué salió bien, puntos de mejora, incidencias…"
                                      <?= $session['status'] !== 'completed' ? 'disabled' : '' ?>><?= esc($session['post_notes'] ?? '') ?></textarea>
                        </div>
                    </div>
                    <div class="text-end mt-3">
                        <button type="submit" class="btn-jp btn-jp-primary btn-jp-sm">
                            <i class="bi bi-floppy-fill me-1"></i>Guardar observaciones
                        </button>
                    </div>
                </div>
            </div>
        </form>
        <?php elseif (!empty($session['pre_notes']) || !empty($session['post_notes'])): ?>
        <!-- Vista read-only para jugadores -->
        <div class="card-jp mb-3">
            <div class="card-jp-header">
                <span class="card-jp-title"><i class="bi bi-clipboard-fill me-2" style="color:#7c3aed"></i>Observaciones</span>
            </div>
            <div class="card-jp-body">
                <?php if (!empty($session['pre_notes'])): ?>
                <div class="mb-3">
                    <div class="form-label"><i class="bi bi-arrow-right-circle me-1" style="color:#7c3aed"></i>Planificación</div>
                    <div style="background:var(--bg-app);padding:12px;border-radius:var(--radius-sm);font-size:13.5px;white-space:pre-wrap"><?= esc($session['pre_notes']) ?></div>
                </div>
                <?php endif; ?>
                <?php if (!empty($session['post_notes'])): ?>
                <div>
                    <div class="form-label"><i class="bi bi-check-circle me-1" style="color:#059669"></i>Feedback</div>
                    <div style="background:var(--bg-app);padding:12px;border-radius:var(--radius-sm);font-size:13.5px;white-space:pre-wrap"><?= esc($session['post_notes']) ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Jugadores: observaciones individuales y asistencia -->
        <?php if (!empty($session['players'])): ?>
        <div class="card-jp mb-3">
            <div class="card-jp-header">
                <span class="card-jp-title">
                    <i class="bi bi-people-fill me-2" style="color:var(--accent)"></i>
                    Jugadores (<?= count($session['players']) ?>)
                </span>
            </div>

            <?php if ($canManage): ?>
            <!-- Admin/coach: formularios de asistencia y obs por jugador -->
            <form action="/clases/<?= $session['id'] ?>/asistencia" method="POST">
                <?= csrf_field() ?>
            <div class="table-responsive">
                <table class="table-jp">
                    <thead>
                        <tr>
                            <th>Jugador</th>
                            <th>Aviso alumno</th>
                            <th>Asistencia</th>
                            <th>Motivo ausencia</th>
                            <th>Obs.</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($session['players'] as $p):
                        [$aLabel, $aColor, $aIcon] = $attendanceMap[$p['attendance']] ?? $attendanceMap['pending'];
                        $hasNote = !empty($p['student_note']);
                    ?>
                        <tr>
                            <td>
                                <div class="td-user">
                                    <div class="td-avatar"><?= strtoupper(substr($p['name'], 0, 1)) ?></div>
                                    <div>
                                        <div class="td-name"><?= esc($p['name']) ?></div>
                                        <span class="badge-status" style="background:<?= $aColor ?>22;color:<?= $aColor ?>;border:1px solid <?= $aColor ?>44;font-size:10px">
                                            <i class="bi <?= $aIcon ?> me-1"></i><?= $aLabel ?>
                                        </span>
                                    </div>
                                </div>
                            </td>
                            <td style="font-size:12px;max-width:160px">
                                <?php if ($hasNote): ?>
                                <span title="<?= esc($p['student_note']) ?>"
                                      style="display:inline-flex;align-items:center;gap:4px;color:#d97706;font-size:12px">
                                    <i class="bi bi-exclamation-triangle-fill"></i>
                                    <span style="max-width:130px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"><?= esc($p['student_note']) ?></span>
                                </span>
                                <div style="font-size:10px;color:var(--text-muted)">
                                    <?= !empty($p['student_noted_at']) ? date('d/m H:i', strtotime($p['student_noted_at'])) : '' ?>
                                </div>
                                <?php else: ?>
                                <span style="color:var(--text-muted)">—</span>
                                <?php endif; ?>
                            </td>
                            <td>
                                <select name="attendance[<?= $p['user_id'] ?>]"
                                        class="form-control-jp attendance-select"
                                        data-uid="<?= $p['user_id'] ?>"
                                        style="font-size:12px;padding:4px 8px;width:auto">
                                    <?php foreach (['pending' => 'Pendiente', 'present' => 'Presente', 'absent' => 'Ausente'] as $val => $lbl): ?>
                                        <option value="<?= $val ?>" <?= $p['attendance'] === $val ? 'selected' : '' ?>><?= $lbl ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                            <td style="min-width:160px">
                                <input type="text"
                                       name="absence_reason[<?= $p['user_id'] ?>]"
                                       class="form-control-jp absence-reason-field"
                                       id="abs-reason-<?= $p['user_id'] ?>"
                                       placeholder="Motivo…"
                                       value="<?= esc($p['absence_reason'] ?? '') ?>"
                                       style="font-size:12px;padding:4px 8px;<?= ($p['attendance'] !== 'absent') ? 'display:none' : '' ?>">
                            </td>
                            <td>
                                <button type="button" onclick="openObsModal(<?= $p['user_id'] ?>, '<?= esc($p['name'], 'js') ?>', '<?= esc($p['pre_obs'] ?? '', 'js') ?>', '<?= esc($p['post_obs'] ?? '', 'js') ?>')"
                                        class="btn-jp btn-jp-secondary btn-jp-sm">
                                    <i class="bi bi-pencil-fill"></i>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <div style="padding:14px 20px;border-top:1px solid var(--border);text-align:right">
                <button type="submit" class="btn-jp btn-jp-primary btn-jp-sm">
                    <i class="bi bi-floppy-fill me-1"></i>Guardar asistencia
                </button>
            </div>
            </form>

            <?php else: ?>
            <!-- Vista jugador: solo ve a sus compañeros y su obs -->
            <div class="card-jp-body">
                <?php foreach ($session['players'] as $p):
                    [$aLabel, $aColor, $aIcon] = $attendanceMap[$p['attendance']] ?? $attendanceMap['pending'];
                ?>
                <div class="d-flex align-items-start gap-3 mb-3" style="padding-bottom:12px;border-bottom:1px solid var(--border)">
                    <div class="td-avatar"><?= strtoupper(substr($p['name'], 0, 1)) ?></div>
                    <div style="flex:1">
                        <div class="td-name"><?= esc($p['name']) ?>
                            <?php if ((int)$p['user_id'] === $currentUserId): ?>
                                <span style="font-size:11px;color:var(--text-muted)">(tú)</span>
                            <?php endif; ?>
                        </div>
                        <span class="badge-status" style="background:<?= $aColor ?>22;color:<?= $aColor ?>;border:1px solid <?= $aColor ?>44;font-size:10px">
                            <i class="bi <?= $aIcon ?> me-1"></i><?= $aLabel ?>
                        </span>
                        <?php if ((int)$p['user_id'] === $currentUserId && (!empty($p['pre_obs']) || !empty($p['post_obs']))): ?>
                        <div class="mt-2">
                            <?php if (!empty($p['pre_obs'])): ?>
                                <div style="font-size:12px;color:var(--text-muted);margin-bottom:2px"><i class="bi bi-arrow-right-circle me-1" style="color:#7c3aed"></i>Pre: <?= esc($p['pre_obs']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($p['post_obs'])): ?>
                                <div style="font-size:12px;color:var(--text-muted)"><i class="bi bi-check-circle me-1" style="color:#059669"></i>Post: <?= esc($p['post_obs']) ?></div>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

    </div><!-- /col-lg-8 -->

    <!-- ── Sidebar ──────────────────────────────────────────── -->
    <div class="col-12 col-lg-4">

        <!-- Entrenadores -->
        <div class="card-jp mb-3">
            <div class="card-jp-header">
                <span class="card-jp-title" style="font-size:13px">
                    <i class="bi bi-person-workspace me-2" style="color:#059669"></i>
                    Entrenadores (<?= count($session['coaches']) ?>)
                </span>
                <?php if ($canManage && $session['status'] === 'scheduled'): ?>
                <button class="btn-jp btn-jp-secondary btn-jp-sm" onclick="openModal('modalAddCoach')">
                    <i class="bi bi-plus-lg"></i>
                </button>
                <?php endif; ?>
            </div>
            <div class="card-jp-body py-2">
                <?php if (empty($session['coaches'])): ?>
                    <div style="font-size:13px;color:var(--text-muted);text-align:center;padding:10px">Sin entrenadores asignados</div>
                <?php else: ?>
                <?php foreach ($session['coaches'] as $c): ?>
                <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border)">
                    <div class="d-flex align-items-center gap-2">
                        <div class="td-avatar" style="background:#d1fae5;color:#059669"><?= strtoupper(substr($c['name'], 0, 1)) ?></div>
                        <div>
                            <div style="font-size:13px;font-weight:600;color:var(--text-h)"><?= esc($c['name']) ?></div>
                            <div style="font-size:11px;color:var(--text-muted)"><?= esc($c['email']) ?></div>
                        </div>
                    </div>
                    <?php if ($canManage && $session['status'] === 'scheduled'): ?>
                    <form action="/clases/<?= $session['id'] ?>/coaches/<?= $c['user_id'] ?>/remove" method="POST" style="margin:0">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn-jp btn-jp-danger btn-jp-icon btn-jp-sm"
                                onclick="return confirm('¿Eliminar entrenador?')" title="Eliminar">
                            <i class="bi bi-trash3"></i>
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Resumen de asistencia -->
        <?php if (!empty($session['players'])): ?>
        <?php
        $totalP      = count($session['players']);
        $confirmed   = count(array_filter($session['players'], fn($p) => $p['attendance'] === 'confirmed'));
        $present     = count(array_filter($session['players'], fn($p) => $p['attendance'] === 'present'));
        $absent      = count(array_filter($session['players'], fn($p) => $p['attendance'] === 'absent'));
        $declined    = count(array_filter($session['players'], fn($p) => $p['attendance'] === 'declined'));
        $pending     = count(array_filter($session['players'], fn($p) => $p['attendance'] === 'pending'));
        $withNote    = count(array_filter($session['players'], fn($p) => !empty($p['student_note'])));
        ?>
        <div class="card-jp mb-3">
            <div class="card-jp-header">
                <span class="card-jp-title" style="font-size:13px">
                    <i class="bi bi-bar-chart-fill me-2" style="color:var(--accent)"></i>
                    Resumen asistencia
                </span>
            </div>
            <div class="card-jp-body">
                <div style="display:flex;flex-direction:column;gap:8px;font-size:13px">
                    <div style="display:flex;justify-content:space-between;align-items:center">
                        <span style="color:var(--text-muted)"><i class="bi bi-people-fill me-1"></i>Total convocados</span>
                        <strong style="color:var(--text-h)"><?= $totalP ?></strong>
                    </div>
                    <?php if ($confirmed): ?>
                    <div style="display:flex;justify-content:space-between;align-items:center">
                        <span style="color:#059669"><i class="bi bi-check-circle-fill me-1"></i>Confirmados</span>
                        <strong><?= $confirmed ?></strong>
                    </div>
                    <?php endif; ?>
                    <?php if ($present): ?>
                    <div style="display:flex;justify-content:space-between;align-items:center">
                        <span style="color:#059669"><i class="bi bi-person-check-fill me-1"></i>Presentes</span>
                        <strong><?= $present ?></strong>
                    </div>
                    <?php endif; ?>
                    <?php if ($absent): ?>
                    <div style="display:flex;justify-content:space-between;align-items:center">
                        <span style="color:var(--danger)"><i class="bi bi-person-x-fill me-1"></i>Ausentes</span>
                        <strong><?= $absent ?></strong>
                    </div>
                    <?php endif; ?>
                    <?php if ($declined): ?>
                    <div style="display:flex;justify-content:space-between;align-items:center">
                        <span style="color:var(--danger)"><i class="bi bi-x-circle-fill me-1"></i>Avisaron ausencia</span>
                        <strong><?= $declined ?></strong>
                    </div>
                    <?php endif; ?>
                    <?php if ($withNote): ?>
                    <div style="display:flex;justify-content:space-between;align-items:center">
                        <span style="color:#d97706"><i class="bi bi-exclamation-triangle-fill me-1"></i>Con aviso alumno</span>
                        <strong><?= $withNote ?></strong>
                    </div>
                    <?php endif; ?>
                    <?php if ($pending): ?>
                    <div style="display:flex;justify-content:space-between;align-items:center">
                        <span style="color:#d97706"><i class="bi bi-clock-fill me-1"></i>Sin registrar</span>
                        <strong><?= $pending ?></strong>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Añadir alumno (admin/coach) — límite según class_format -->
        <?php if ($canManage && $session['status'] === 'scheduled'): ?>
        <?php
            $playerCount = count($session['players']);
            $fmt = $session['class_format'] ?? 'individual';
            $maxPlayers = $fmt === 'pareja' ? 2 : 1;
            $fmtLabel   = $fmt === 'pareja' ? 'Pareja' : 'Individual';
        ?>
        <div class="card-jp">
            <div class="card-jp-header">
                <span class="card-jp-title" style="font-size:13px">
                    <i class="bi bi-person-plus-fill me-2" style="color:var(--accent)"></i>
                    Añadir alumno
                    <span style="font-size:11px;color:var(--text-muted);margin-left:6px">(<?= $playerCount ?>/<?= $maxPlayers ?> · <?= $fmtLabel ?>)</span>
                </span>
            </div>
            <div class="card-jp-body">
                <?php if ($playerCount >= $maxPlayers): ?>
                <p style="font-size:13px;color:var(--text-muted);margin:0;text-align:center">
                    <i class="bi bi-lock-fill me-1"></i>Sesión completa — máximo <?= $maxPlayers ?> alumno<?= $maxPlayers > 1 ? 's' : '' ?> (<?= $fmtLabel ?>).
                </p>
                <?php else: ?>
                <form action="/clases/<?= $session['id'] ?>/jugadores/add" method="POST">
                    <?= csrf_field() ?>
                    <select name="user_id" class="form-control-jp mb-2" required>
                        <option value="">Seleccionar jugador…</option>
                        <?php foreach ($playerOptions as $p): ?>
                            <?php $isAssigned = false;
                            foreach ($session['players'] as $sp) {
                                if ((int)$sp['user_id'] === (int)$p['id']) { $isAssigned = true; break; }
                            } ?>
                            <?php if (!$isAssigned): ?>
                            <option value="<?= $p['id'] ?>"><?= esc($p['name']) ?></option>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </select>
                    <select name="coach_id" class="form-control-jp mb-2">
                        <option value="">Sin entrenador asignado</option>
                        <?php foreach ($coachOptions as $c): ?>
                            <option value="<?= $c['id'] ?>"><?= esc($c['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <button type="submit" class="btn-jp btn-jp-primary btn-jp-sm w-100">
                        <i class="bi bi-plus-lg me-1"></i>Añadir alumno
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

    </div><!-- /sidebar -->

</div>

<!-- ── Modal: añadir entrenador ──────────────────────────────── -->
<?php if ($canManage): ?>
<div id="modalAddCoach" class="cs-modal-overlay d-none">
    <div class="cs-modal">
        <div class="cs-modal-header">
            <span>Añadir entrenador</span>
            <button onclick="closeModal('modalAddCoach')"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="cs-modal-body">
            <form action="/clases/<?= $session['id'] ?>/coaches/add" method="POST">
                <?= csrf_field() ?>
                <label class="form-label">Entrenador</label>
                <select name="user_id" class="form-control-jp mb-3" required>
                    <option value="">Seleccionar…</option>
                    <?php foreach ($coachOptions as $c):
                        $isAssigned = false;
                        foreach ($session['coaches'] as $sc) {
                            if ((int)$sc['user_id'] === (int)$c['id']) { $isAssigned = true; break; }
                        } ?>
                        <?php if (!$isAssigned): ?>
                        <option value="<?= $c['id'] ?>"><?= esc($c['name']) ?></option>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </select>
                <div class="d-flex gap-2 justify-content-end">
                    <button type="button" class="btn-jp btn-jp-secondary" onclick="closeModal('modalAddCoach')">Cancelar</button>
                    <button type="submit" class="btn-jp btn-jp-primary"><i class="bi bi-check-lg me-1"></i>Añadir</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ── Modal: observaciones por jugador ─────────────────────── -->
<div id="modalObs" class="cs-modal-overlay d-none">
    <div class="cs-modal" style="max-width:540px">
        <div class="cs-modal-header">
            <span id="obsModalTitle">Observaciones</span>
            <button onclick="closeModal('modalObs')"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="cs-modal-body">
            <form action="/clases/<?= $session['id'] ?>/observaciones" method="POST" id="obsForm">
                <?= csrf_field() ?>
                <input type="hidden" id="obsUserId" name="_player_uid">
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <label class="form-label"><i class="bi bi-arrow-right-circle me-1" style="color:#7c3aed"></i>Antes (planificación)</label>
                        <textarea id="obsPreInput" class="form-control-jp" rows="4" placeholder="Objetivos para este jugador…"></textarea>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label"><i class="bi bi-check-circle me-1" style="color:#059669"></i>Después (feedback)</label>
                        <textarea id="obsPostInput" class="form-control-jp" rows="4"
                                  placeholder="Notas post-sesión…"
                                  <?= $session['status'] !== 'completed' ? 'disabled' : '' ?>></textarea>
                    </div>
                </div>
                <div id="obsHiddenFields"></div>
                <div class="d-flex justify-content-end gap-2 mt-3">
                    <button type="button" class="btn-jp btn-jp-secondary" onclick="closeModal('modalObs')">Cancelar</button>
                    <button type="submit" class="btn-jp btn-jp-primary"><i class="bi bi-floppy-fill me-1"></i>Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?= $this->section('scripts') ?>
<style>
.cs-modal-overlay {
    position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:1050;
    display:flex;align-items:center;justify-content:center;padding:16px;
}
.cs-modal {
    background:var(--bg-card);border:1px solid var(--border);border-radius:12px;
    width:100%;max-width:480px;max-height:90vh;display:flex;flex-direction:column;
    box-shadow:0 20px 60px rgba(0,0,0,.3);
}
.cs-modal-header {
    display:flex;align-items:center;justify-content:space-between;
    padding:16px 20px;border-bottom:1px solid var(--border);
    font-size:15px;font-weight:700;color:var(--text-h);
}
.cs-modal-header button {
    background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:18px;
    width:32px;height:32px;display:flex;align-items:center;justify-content:center;border-radius:6px;
    transition:background .15s;
}
.cs-modal-header button:hover { background:var(--bg-app);color:var(--text-h); }
.cs-modal-body { padding:20px;overflow-y:auto;flex:1; }
</style>
<script>
function openModal(id) {
    document.getElementById(id)?.classList.remove('d-none');
    document.body.style.overflow = 'hidden';
}
function closeModal(id) {
    document.getElementById(id)?.classList.add('d-none');
    document.body.style.overflow = '';
}
document.addEventListener('keydown', e => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.cs-modal-overlay:not(.d-none)').forEach(m => closeModal(m.id));
    }
});
document.querySelectorAll('.cs-modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', e => { if (e.target === overlay) closeModal(overlay.id); });
});

// Mostrar/ocultar campo motivo ausencia según selección
document.querySelectorAll('.attendance-select').forEach(function(sel) {
    sel.addEventListener('change', function() {
        const uid    = this.dataset.uid;
        const field  = document.getElementById('abs-reason-' + uid);
        if (field) {
            field.style.display = this.value === 'absent' ? '' : 'none';
            if (this.value !== 'absent') field.value = '';
        }
    });
});

function openObsModal(userId, name, preObs, postObs) {
    document.getElementById('obsModalTitle').textContent = 'Observaciones — ' + name;
    document.getElementById('obsUserId').value = userId;
    document.getElementById('obsPreInput').value = preObs;
    document.getElementById('obsPostInput').value = postObs;

    // Build hidden fields dynamically to submit player_obs[userId][pre/post]
    const obsForm = document.getElementById('obsForm');
    // Remove old hidden fields
    document.getElementById('obsHiddenFields').innerHTML = '';

    obsForm.onsubmit = function(e) {
        e.preventDefault();
        const uid  = document.getElementById('obsUserId').value;
        const pre  = document.getElementById('obsPreInput').value;
        const post = document.getElementById('obsPostInput').value;
        const hf   = document.getElementById('obsHiddenFields');
        hf.innerHTML = `<input type="hidden" name="player_obs[${uid}][pre]" value="${pre.replace(/"/g,'&quot;')}">
                        <input type="hidden" name="player_obs[${uid}][post]" value="${post.replace(/"/g,'&quot;')}">`;
        obsForm.submit();
    };
    openModal('modalObs');
}
</script>
<?= $this->endSection() ?>

<?= $this->endSection() ?>
