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
    'unjustified' => ['No justificado', '#b91c1c', 'bi-person-x-fill'],
];

$locationDisplay  = $session['location_name'] ?? $session['location_custom'] ?? null;
$isStaffSession   = ($session['session_type'] ?? 'coach') === 'staff';
$responsibleLabel = $isStaffSession ? 'Staff responsable' : 'Entrenadores';
$responsibleIcon  = $isStaffSession ? '#7c3aed'           : '#059669';
$responsibleEmpty = $isStaffSession ? 'Sin staff responsable asignado' : 'Sin entrenadores asignados';
// Máx. 1 responsable por sesión (ver ClasesService::syncCoaches) — el
// modal "Cambiar responsable" (TICKET-011) trabaja con un único valor.
$assignedCoachId = $session['coaches'][0]['user_id'] ?? null;

// El feedback ("Después") se puede escribir cuando la clase ya se ha
// impartido: sesión completada, lista pasada, o al menos un alumno
// marcado como presente. (ver ClasesService::isFeedbackUnlocked)
$feedbackUnlocked = \App\Services\ClasesService::isFeedbackUnlocked($session);

// Estado de la asistencia (para etiquetas coherentes en toda la pantalla).
$listaDone    = !empty($session['lista_pasada_at']) || $session['status'] === 'completed';
$listaLabel   = $listaDone ? 'Revisar asistencia' : 'Pasar lista';
$bonoDeducted = count(array_filter($session['players'] ?? [], fn($p) => !empty($p['bono_deducted_at'])));
$statusHint   = [
    'scheduled' => 'Programada: aún se puede editar y pasar lista.',
    'completed' => 'Cerrada: la asistencia está bloqueada. Puedes reabrirla si necesitas corregir.',
    'cancelled' => 'Cancelada: la clase no se imparte. Puedes reactivarla.',
][$session['status']] ?? '';
?>

<?= $this->section('page_content') ?>

<div class="page-header">

    <a href="/clases" class="btn-jp btn-jp-secondary btn-jp-sm" style="margin-right:8px">
        <i class="bi bi-arrow-left me-1"></i>Clases
    </a>

    <?php if ($canManage): ?>
    <div class="d-flex gap-2 flex-wrap">
        <a href="/clases/<?= $session['id'] ?>/lista" class="btn-jp btn-jp-primary btn-jp-sm">
            <i class="bi bi-clipboard2-check-fill me-1"></i><?= $listaLabel ?>
        </a>
        <?php if (in_array($session['status'], ['scheduled'])): ?>
        <a href="/clases/<?= $session['id'] ?>/editar" class="btn-jp btn-jp-secondary btn-jp-sm">
            <i class="bi bi-pencil-fill me-1"></i>Editar
        </a>
        <form action="/clases/<?= $session['id'] ?>/cancelar" method="POST" style="margin:0"
              data-ru-confirm="¿Cancelar esta sesión?"
              data-ru-confirm-desc="Los alumnos dejarán de verla como activa. Podrás reactivarla más adelante."
              data-ru-confirm-label="Cancelar sesión" data-ru-confirm-danger>
            <?= csrf_field() ?>
            <button type="submit" class="btn-jp btn-jp-danger btn-jp-sm">
                <i class="bi bi-x-circle-fill me-1"></i>Cancelar sesión
            </button>
        </form>
        <?php endif; ?>
        <?php if ($session['status'] === 'completed'): ?>
        <form action="/clases/<?= $session['id'] ?>/reabrir" method="POST" style="margin:0"
              data-ru-confirm="¿Reabrir esta sesión?"
              data-ru-confirm-desc="Volverá a estar programada y podrás editar la asistencia de nuevo. No se pierde nada de lo registrado."
              data-ru-confirm-label="Reabrir">
            <?= csrf_field() ?>
            <button type="submit" class="btn-jp btn-jp-secondary btn-jp-sm">
                <i class="bi bi-unlock-fill me-1"></i>Reabrir sesión
            </button>
        </form>
        <?php elseif ($session['status'] === 'cancelled'): ?>
        <form action="/clases/<?= $session['id'] ?>/reabrir" method="POST" style="margin:0"
              data-ru-confirm="¿Reactivar esta sesión cancelada?"
              data-ru-confirm-desc="Volverá a estar programada."
              data-ru-confirm-label="Reactivar">
            <?= csrf_field() ?>
            <button type="submit" class="btn-jp btn-jp-secondary btn-jp-sm">
                <i class="bi bi-arrow-counterclockwise me-1"></i>Reactivar sesión
            </button>
        </form>
        <?php endif; ?>
        <?php if ($isAdminRole): ?>
        <form action="/clases/<?= $session['id'] ?>/eliminar" method="POST" style="margin:0"
              data-ru-confirm="¿Eliminar esta sesión permanentemente?"
              data-ru-confirm-desc="No se puede deshacer: se borra la sesión con toda su asistencia y observaciones."
              data-ru-confirm-label="Eliminar" data-ru-confirm-danger>
            <?= csrf_field() ?>
            <button type="submit" class="btn-jp btn-jp-danger btn-jp-sm">
                <i class="bi bi-trash3-fill me-1"></i>Eliminar
            </button>
        </form>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<?php if ($flash = session()->getFlashdata('success')): ?>
    <div class="alert-jp success mb-3"><i class="bi bi-check-circle-fill me-2"></i><?= esc($flash) ?></div>
<?php endif; ?>
<?php if ($flash = session()->getFlashdata('error')): ?>
    <div class="alert-jp error mb-3"><i class="bi bi-x-circle-fill me-2"></i><?= esc($flash) ?></div>
<?php endif; ?>

<!-- ── Continuar clase recurrente (serie a punto de terminar / terminada) ── -->
<?php if (!empty($renewalDefaults)): ?>
<div class="card-jp mb-3" style="border-left:3px solid #7c3aed">
    <div class="card-jp-body d-flex align-items-center justify-content-between flex-wrap gap-3">
        <div>
            <div style="font-weight:700;color:var(--text-h);margin-bottom:4px">
                <i class="bi bi-arrow-repeat me-2" style="color:#7c3aed"></i>
                <?= $seriesRenewal['scheduled_remaining'] === 0
                    ? 'Esta clase recurrente ha terminado'
                    : 'Última sesión programada de esta clase recurrente' ?>
            </div>
            <div style="font-size:13px;color:var(--text-muted)">
                ¿Quieres continuar con las mismas sesiones el mes que viene? Podrás editarlo todo antes de confirmar.
            </div>
        </div>
        <button type="button" class="btn-jp btn-jp-primary btn-jp-sm" onclick="openModal('modalRenewSeries')">
            <i class="bi bi-arrow-repeat me-1"></i>Continuar clases recurrentes
        </button>
    </div>
</div>
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
                        <i class="bi bi-person-workspace me-1"></i><?= $isStaffSession ? 'Staff' : 'Entrenador' ?>: <?= esc($myPlayer['coach_name']) ?>
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
            <div style="width:100%;max-width:320px">
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
                        <?php if ($canManage && $statusHint): ?>
                        <div style="font-size:12px;color:var(--text-muted);margin-top:6px">
                            <i class="bi bi-info-circle me-1"></i><?= $statusHint ?>
                        </div>
                        <?php endif; ?>
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
                <div class="card-jp-header" style="flex-direction:column;align-items:flex-start;gap:2px">
                    <span class="card-jp-title">
                        <i class="bi bi-clipboard-fill me-2" style="color:#7c3aed"></i>
                        Observaciones generales de la sesión
                    </span>
                    <span style="font-size:12px;color:var(--text-muted);font-weight:400">
                        Sobre <strong>toda la clase</strong> — para notas de un jugador en concreto usa el lápiz de su fila más abajo.
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
                                <?php if (!$feedbackUnlocked): ?>
                                <small style="color:var(--text-muted)">(disponible tras impartir la clase o marcar asistencia)</small>
                                <?php endif; ?>
                            </label>
                            <textarea name="post_notes" class="form-control-jp" rows="4"
                                      placeholder="Qué salió bien, puntos de mejora, incidencias…"
                                      <?= $feedbackUnlocked ? '' : 'disabled' ?>><?= esc($session['post_notes'] ?? '') ?></textarea>
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

        <!-- Adjuntos generales de la sesión -->
        <div class="card-jp mb-3">
            <div class="card-jp-header">
                <span class="card-jp-title">
                    <i class="bi bi-paperclip me-2" style="color:#7c3aed"></i>
                    Adjuntos de la sesión
                </span>
            </div>
            <div class="card-jp-body">
                <p style="font-size:12px;color:var(--text-muted);margin:0 0 12px">
                    Fotos, vídeos o documentos de <strong>toda la clase</strong>. Quedan siempre vinculados a esta sesión
                    (<?= date('d/m/Y', strtotime($session['session_date'])) ?>) y visibles desde aquí — para material de un jugador concreto, súbelo desde su ficha de observaciones (lápiz en la tabla).
                </p>
                <div class="cs-attach-list mb-3">
                    <?php if (empty($session['attachments'])): ?>
                    <span style="font-size:12px;color:var(--text-muted)">Sin adjuntos todavía.</span>
                    <?php else: foreach ($session['attachments'] as $att): ?>
                        <?= view('clases/_attachment_chip', ['att' => $att, 'canDelete' => true]) ?>
                    <?php endforeach; endif; ?>
                </div>
                <form action="/clases/<?= $session['id'] ?>/observaciones/adjuntos" method="POST" enctype="multipart/form-data" class="d-flex gap-2 flex-wrap align-items-center">
                    <?= csrf_field() ?>
                    <input type="file" name="attachment" class="form-control-jp" style="max-width:320px" accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.doc,.docx,.mp4,.mov" required>
                    <button type="submit" class="btn-jp btn-jp-secondary btn-jp-sm">
                        <i class="bi bi-upload me-1"></i>Subir adjunto
                    </button>
                </form>
            </div>
        </div>
        <?php elseif (!empty($session['pre_notes']) || !empty($session['post_notes']) || !empty($session['attachments'])): ?>
        <!-- Vista read-only para jugadores -->
        <div class="card-jp mb-3">
            <div class="card-jp-header" style="flex-direction:column;align-items:flex-start;gap:2px">
                <span class="card-jp-title"><i class="bi bi-clipboard-fill me-2" style="color:#7c3aed"></i>Observaciones generales de la sesión</span>
                <span style="font-size:12px;color:var(--text-muted);font-weight:400">Sobre toda la clase, no solo sobre ti.</span>
            </div>
            <div class="card-jp-body">
                <?php if (!empty($session['pre_notes'])): ?>
                <div class="mb-3">
                    <div class="form-label"><i class="bi bi-arrow-right-circle me-1" style="color:#7c3aed"></i>Planificación</div>
                    <div style="background:var(--bg-app);padding:12px;border-radius:var(--radius-sm);font-size:13.5px;white-space:pre-wrap"><?= esc($session['pre_notes']) ?></div>
                </div>
                <?php endif; ?>
                <?php if (!empty($session['post_notes'])): ?>
                <div class="mb-3">
                    <div class="form-label"><i class="bi bi-check-circle me-1" style="color:#059669"></i>Feedback</div>
                    <div style="background:var(--bg-app);padding:12px;border-radius:var(--radius-sm);font-size:13.5px;white-space:pre-wrap"><?= esc($session['post_notes']) ?></div>
                </div>
                <?php endif; ?>
                <?php if (!empty($session['attachments'])): ?>
                <div>
                    <div class="form-label"><i class="bi bi-paperclip me-1" style="color:#7c3aed"></i>Adjuntos</div>
                    <div class="cs-attach-list">
                        <?php foreach ($session['attachments'] as $att): ?>
                            <?= view('clases/_attachment_chip', ['att' => $att, 'canDelete' => false]) ?>
                        <?php endforeach; ?>
                    </div>
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
            <!-- Admin/coach: vista de estado + link a pasar lista -->
            <div class="table-responsive">
                <table class="table-jp">
                    <thead>
                        <tr>
                            <th>Jugador</th>
                            <th>Aviso alumno</th>
                            <th>Asistencia</th>
                            <th title="Observaciones individuales de este jugador (no las generales de la sesión)">Obs. individuales</th>
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
                                <span class="badge-status" style="background:<?= $aColor ?>22;color:<?= $aColor ?>;border:1px solid <?= $aColor ?>44;font-size:11px">
                                    <i class="bi <?= $aIcon ?> me-1"></i><?= $aLabel ?>
                                </span>
                            </td>
                            <td>
                                <button type="button" onclick="openObsModal(<?= $p['user_id'] ?>, '<?= esc($p['name'], 'js') ?>', '<?= esc($p['pre_obs'] ?? '', 'js') ?>', '<?= esc($p['post_obs'] ?? '', 'js') ?>')"
                                        class="btn-jp btn-jp-secondary btn-jp-sm"
                                        title="Observaciones y adjuntos individuales de <?= esc($p['name'], 'attr') ?> (distinto de las observaciones generales de arriba)">
                                    <i class="bi bi-pencil-fill"></i>
                                    <?php if (!empty($p['attachments'])): ?>
                                    <span class="badge-status" style="background:#7c3aed22;color:#7c3aed;border:1px solid #7c3aed44;font-size:10px;margin-left:4px;padding:1px 5px">
                                        <i class="bi bi-paperclip"></i> <?= count($p['attachments']) ?>
                                    </span>
                                    <?php endif; ?>
                                </button>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <!-- Plantillas ocultas con los adjuntos individuales de cada jugador,
                 leídas por openObsModal() al abrir el modal de ese jugador. -->
            <?php foreach ($session['players'] as $p): ?>
            <template id="attachTpl-<?= $p['user_id'] ?>"><?php if (!empty($p['attachments'])): foreach ($p['attachments'] as $att): ?><?= view('clases/_attachment_chip', ['att' => $att, 'canDelete' => true]) ?><?php endforeach; else: ?><span style="font-size:12px;color:var(--text-muted)">Sin adjuntos todavía.</span><?php endif; ?></template>
            <?php endforeach; ?>
            <div style="padding:14px 20px;border-top:1px solid var(--border);text-align:right">
                <a href="/clases/<?= $session['id'] ?>/lista" class="btn-jp btn-jp-primary btn-jp-sm">
                    <i class="bi bi-clipboard2-check-fill me-1"></i><?= $listaLabel ?>
                </a>
            </div>

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
                        <?php if ((int)$p['user_id'] === $currentUserId && (!empty($p['pre_obs']) || !empty($p['post_obs']) || !empty($p['attachments']))): ?>
                        <div class="mt-2">
                            <div style="font-size:10px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.03em;margin-bottom:2px">Tus observaciones individuales</div>
                            <?php if (!empty($p['pre_obs'])): ?>
                                <div style="font-size:12px;color:var(--text-muted);margin-bottom:2px"><i class="bi bi-arrow-right-circle me-1" style="color:#7c3aed"></i>Pre: <?= esc($p['pre_obs']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($p['post_obs'])): ?>
                                <div style="font-size:12px;color:var(--text-muted)"><i class="bi bi-check-circle me-1" style="color:#059669"></i>Post: <?= esc($p['post_obs']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($p['attachments'])): ?>
                            <div class="cs-attach-list mt-2">
                                <?php foreach ($p['attachments'] as $att): ?>
                                    <?= view('clases/_attachment_chip', ['att' => $att, 'canDelete' => false]) ?>
                                <?php endforeach; ?>
                            </div>
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

        <!-- Responsable (entrenador o staff) -->
        <?php
        $avatarBg    = $isStaffSession ? '#ede9fe' : '#d1fae5';
        $avatarColor = $isStaffSession ? '#7c3aed' : '#059669';
        $removeLabel = $isStaffSession ? '¿Quitar al staff responsable?' : '¿Quitar a este entrenador?';
        ?>
        <div class="card-jp mb-3">
            <div class="card-jp-header">
                <span class="card-jp-title" style="font-size:13px">
                    <i class="bi bi-person-workspace me-2" style="color:<?= $responsibleIcon ?>"></i>
                    <?= $responsibleLabel ?> (<?= count($session['coaches']) ?>)
                </span>
                <?php if ($isAdminRole && $session['status'] === 'scheduled'): ?>
                <button class="btn-jp btn-jp-secondary btn-jp-sm" onclick="openModal('modalChangeResponsible')">
                    <i class="bi bi-arrow-repeat me-1"></i>Cambiar
                </button>
                <?php endif; ?>
            </div>
            <div class="card-jp-body py-2">
                <?php if (empty($session['coaches'])): ?>
                    <div style="font-size:13px;color:var(--text-muted);text-align:center;padding:10px"><?= $responsibleEmpty ?></div>
                <?php else: ?>
                <?php foreach ($session['coaches'] as $c): ?>
                <div style="display:flex;align-items:center;justify-content:space-between;padding:8px 0;border-bottom:1px solid var(--border)">
                    <div class="d-flex align-items-center gap-2">
                        <div class="td-avatar" style="background:<?= $avatarBg ?>;color:<?= $avatarColor ?>"><?= strtoupper(substr($c['name'], 0, 1)) ?></div>
                        <div>
                            <div style="font-size:13px;font-weight:600;color:var(--text-h)"><?= esc($c['name']) ?></div>
                            <div style="font-size:11px;color:var(--text-muted)"><?= esc($c['email']) ?></div>
                        </div>
                    </div>
                    <?php if ($isAdminRole && $session['status'] === 'scheduled'): ?>
                    <form action="/clases/<?= $session['id'] ?>/coaches/<?= $c['user_id'] ?>/remove" method="POST" style="margin:0"
                          data-ru-confirm="<?= esc($removeLabel, 'attr') ?>"
                          data-ru-confirm-desc="Se quitará de esta sesión. Puedes volver a añadirlo cuando quieras."
                          data-ru-confirm-label="Quitar" data-ru-confirm-danger>
                        <?= csrf_field() ?>
                        <button type="submit" class="btn-jp btn-jp-danger btn-jp-icon btn-jp-sm" title="Quitar">
                            <i class="bi bi-trash3"></i>
                        </button>
                    </form>
                    <?php endif; ?>
                </div>
                <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <!-- Campo / instalación -->
        <div class="card-jp mb-3">
            <div class="card-jp-header">
                <span class="card-jp-title" style="font-size:13px">
                    <i class="bi bi-geo-alt-fill me-2" style="color:#059669"></i>
                    Campo
                </span>
                <?php if ($isAdminRole && $session['status'] === 'scheduled'): ?>
                <button class="btn-jp btn-jp-secondary btn-jp-sm" onclick="openModal('modalChangeLocation')">
                    <i class="bi bi-arrow-repeat me-1"></i>Cambiar
                </button>
                <?php endif; ?>
            </div>
            <div class="card-jp-body py-2">
                <div style="font-size:13px;font-weight:600;color:var(--text-h)">
                    <?= $locationDisplay ? esc($locationDisplay) : '<span style="color:var(--text-muted);font-weight:400">No especificado</span>' ?>
                </div>
            </div>
        </div>

        <!-- Resumen de asistencia -->
        <?php if (!empty($session['players'])): ?>
        <?php
        $totalP      = count($session['players']);
        $confirmed   = count(array_filter($session['players'], fn($p) => $p['attendance'] === 'confirmed'));
        $present     = count(array_filter($session['players'], fn($p) => $p['attendance'] === 'present'));
        $absent      = count(array_filter($session['players'], fn($p) => $p['attendance'] === 'absent'));
        $unjustified = count(array_filter($session['players'], fn($p) => $p['attendance'] === 'unjustified'));
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
                    <?php if ($unjustified): ?>
                    <div style="display:flex;justify-content:space-between;align-items:center">
                        <span style="color:#b91c1c"><i class="bi bi-person-x-fill me-1"></i>No justificadas</span>
                        <strong><?= $unjustified ?></strong>
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
                    <?php if ($bonoDeducted): ?>
                    <div style="display:flex;justify-content:space-between;align-items:center;border-top:1px solid var(--border);padding-top:8px;margin-top:2px">
                        <span style="color:var(--text-muted)"><i class="bi bi-ticket-perforated-fill me-1"></i>Bono descontado</span>
                        <strong style="color:var(--text-h)"><?= $bonoDeducted ?></strong>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Añadir alumno (solo admin/staff) — límite según class_format -->
        <?php if ($isAdminRole && $session['status'] === 'scheduled'): ?>
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
                        <option value=""><?= $isStaffSession ? 'Sin staff asignado' : 'Sin entrenador asignado' ?></option>
                        <?php
                        $responsiblePool = $isStaffSession ? $staffOptions : $coachOptions;
                        foreach ($responsiblePool as $c): ?>
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

<!-- ── Modal: cambiar responsable (entrenador/staff) — TICKET-011 ── -->
<?php if ($canManage): ?>
<?php $modalPool = $isStaffSession ? $staffOptions : $coachOptions; ?>
<div id="modalChangeResponsible" class="cs-modal-overlay d-none">
    <div class="cs-modal">
        <div class="cs-modal-header">
            <span><?= $isStaffSession ? 'Cambiar staff responsable' : 'Cambiar entrenador' ?></span>
            <button onclick="closeModal('modalChangeResponsible')"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="cs-modal-body">
            <form action="/clases/<?= $session['id'] ?>/responsable" method="POST">
                <?= csrf_field() ?>
                <label class="form-label"><?= $isStaffSession ? 'Staff' : 'Entrenador' ?></label>
                <select name="user_id" class="form-control-jp mb-3">
                    <option value="">Sin responsable asignado</option>
                    <?php foreach ($modalPool as $c): ?>
                    <option value="<?= $c['id'] ?>" <?= ((int) $assignedCoachId === (int) $c['id']) ? 'selected' : '' ?>>
                        <?= esc($c['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>

                <?php if ($seriesFutureCount > 1): ?>
                <!-- Clase recurrente: preguntar el alcance (como Google Calendar),
                     nunca aplicar a la serie entera sin que se elija a propósito. -->
                <div class="mb-3" style="display:flex;flex-direction:column;gap:8px">
                    <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer">
                        <input type="radio" name="scope" value="single" checked>
                        Solo esta sesión
                    </label>
                    <label style="display:flex;align-items:center;gap:8px;font-size:13px;cursor:pointer">
                        <input type="radio" name="scope" value="series">
                        Esta y las siguientes (<?= $seriesFutureCount ?> sesiones de la serie)
                    </label>
                </div>
                <?php else: ?>
                <input type="hidden" name="scope" value="single">
                <?php endif; ?>

                <div class="d-flex gap-2 justify-content-end">
                    <button type="button" class="btn-jp btn-jp-secondary" onclick="closeModal('modalChangeResponsible')">Cancelar</button>
                    <button type="submit" class="btn-jp btn-jp-primary"><i class="bi bi-check-lg me-1"></i>Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ── Modal: cambiar campo/instalación ─────────────────────── -->
<?php if ($isAdminRole): ?>
<div id="modalChangeLocation" class="cs-modal-overlay d-none">
    <div class="cs-modal">
        <div class="cs-modal-header">
            <span>Cambiar campo</span>
            <button onclick="closeModal('modalChangeLocation')"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="cs-modal-body">
            <form action="/clases/<?= $session['id'] ?>/campo" method="POST">
                <?= csrf_field() ?>
                <label class="form-label">Instalación (de la lista)</label>
                <select name="location_id" class="form-control-jp mb-3">
                    <option value="">— Ninguna —</option>
                    <?php foreach ($locationOptions as $loc): ?>
                    <option value="<?= $loc['id'] ?>" <?= ((int) ($session['location_id'] ?? 0) === (int) $loc['id']) ? 'selected' : '' ?>>
                        <?= esc($loc['name']) ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <label class="form-label">O lugar personalizado</label>
                <input type="text" name="location_custom" class="form-control-jp mb-3"
                       value="<?= esc($session['location_custom'] ?? '', 'attr') ?>"
                       placeholder="Ej: Estadio Municipal, Campo 3">
                <div class="d-flex gap-2 justify-content-end">
                    <button type="button" class="btn-jp btn-jp-secondary" onclick="closeModal('modalChangeLocation')">Cancelar</button>
                    <button type="submit" class="btn-jp btn-jp-primary"><i class="bi bi-check-lg me-1"></i>Guardar</button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ── Modal: observaciones por jugador ─────────────────────── -->
<div id="modalObs" class="cs-modal-overlay d-none">
    <div class="cs-modal" style="max-width:540px">
        <div class="cs-modal-header" style="flex-direction:column;align-items:flex-start;gap:2px">
            <span id="obsModalTitle">Observaciones</span>
            <span style="font-size:12px;color:var(--text-muted);font-weight:400">
                Solo sobre este jugador — no se comparte con el resto del grupo ni con la observación general de la sesión.
            </span>
            <button onclick="closeModal('modalObs')" style="position:absolute;top:14px;right:14px"><i class="bi bi-x-lg"></i></button>
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
                                  <?= $feedbackUnlocked ? '' : 'disabled' ?>></textarea>
                    </div>
                </div>
                <div id="obsHiddenFields"></div>
                <div class="d-flex justify-content-end gap-2 mt-3">
                    <button type="button" class="btn-jp btn-jp-secondary" onclick="closeModal('modalObs')">Cancelar</button>
                    <button type="submit" class="btn-jp btn-jp-primary"><i class="bi bi-floppy-fill me-1"></i>Guardar</button>
                </div>
            </form>

            <hr style="border-color:var(--border);margin:18px 0">

            <div class="form-label mb-2"><i class="bi bi-paperclip me-1" style="color:#7c3aed"></i>Adjuntos de este jugador</div>
            <div id="obsAttachList" class="cs-attach-list mb-3"></div>
            <form id="obsAttachForm" action="/clases/<?= $session['id'] ?>/observaciones/adjuntos" method="POST" enctype="multipart/form-data" class="d-flex gap-2 flex-wrap align-items-center">
                <?= csrf_field() ?>
                <input type="hidden" id="obsAttachPlayerUid" name="player_uid">
                <input type="file" name="attachment" class="form-control-jp" style="max-width:260px" accept=".jpg,.jpeg,.png,.webp,.gif,.pdf,.doc,.docx,.mp4,.mov" required>
                <button type="submit" class="btn-jp btn-jp-secondary btn-jp-sm">
                    <i class="bi bi-upload me-1"></i>Subir
                </button>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ── Modal: continuar clase recurrente ──────────────────────── -->
<?php if (!empty($renewalDefaults)): ?>
<div id="modalRenewSeries" class="cs-modal-overlay d-none">
    <div class="cs-modal" style="max-width:640px">
        <div class="cs-modal-header">
            <span><i class="bi bi-arrow-repeat me-2" style="color:#7c3aed"></i>Continuar clases recurrentes</span>
            <button onclick="closeModal('modalRenewSeries')"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="cs-modal-body">
            <p style="font-size:12.5px;color:var(--text-muted);margin-top:0">
                Se generará una nueva serie de clases a partir de estos datos (por defecto: mismos días de la semana,
                un mes después). Revisa y edita lo que necesites antes de confirmar.
            </p>
            <form action="/clases/plantilla/<?= (int) $session['class_id'] ?>/renovar" method="POST" id="formRenewSeries">
                <?= csrf_field() ?>
                <div class="row g-3">
                    <div class="col-12">
                        <label class="form-label">Título</label>
                        <input type="text" name="title" class="form-control-jp" value="<?= esc($renewalDefaults['title']) ?>" required>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label">Formato</label>
                        <div style="display:flex;gap:10px">
                            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;padding:6px 12px;border:2px solid var(--border);border-radius:8px;flex:1">
                                <input type="radio" name="class_format" value="individual" <?= $renewalDefaults['class_format'] === 'individual' ? 'checked' : '' ?>>
                                Individual
                            </label>
                            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;padding:6px 12px;border:2px solid var(--border);border-radius:8px;flex:1">
                                <input type="radio" name="class_format" value="pareja" <?= $renewalDefaults['class_format'] === 'pareja' ? 'checked' : '' ?>>
                                Pareja
                            </label>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label">Tipo de responsable</label>
                        <div style="display:flex;gap:10px">
                            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;padding:6px 12px;border:2px solid var(--border);border-radius:8px;flex:1">
                                <input type="radio" name="session_type" value="coach" id="rn-type-coach" <?= $renewalDefaults['session_type'] === 'coach' ? 'checked' : '' ?>>
                                Entrenador
                            </label>
                            <label style="display:flex;align-items:center;gap:6px;cursor:pointer;padding:6px 12px;border:2px solid var(--border);border-radius:8px;flex:1">
                                <input type="radio" name="session_type" value="staff" id="rn-type-staff" <?= $renewalDefaults['session_type'] === 'staff' ? 'checked' : '' ?>>
                                Staff
                            </label>
                        </div>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Días de la semana</label>
                        <div class="cm-day-row">
                            <?php foreach ([1=>'Lun',2=>'Mar',3=>'Mié',4=>'Jue',5=>'Vie',6=>'Sáb',7=>'Dom'] as $n=>$d): ?>
                            <label class="cm-day-pill <?= in_array($n, $renewalDefaults['recurrence_days'], true) ? 'active' : '' ?>">
                                <input type="checkbox" class="rn-day-check" name="recurrence_days[]" value="<?= $n ?>"
                                       <?= in_array($n, $renewalDefaults['recurrence_days'], true) ? 'checked' : '' ?>>
                                <span><?= $d ?></span>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Desde</label>
                        <input type="date" name="recurrence_start" class="form-control-jp" value="<?= esc((string) $renewalDefaults['recurrence_start']) ?>" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Hasta</label>
                        <input type="date" name="recurrence_end" class="form-control-jp" value="<?= esc((string) $renewalDefaults['recurrence_end']) ?>" required>
                    </div>

                    <div class="col-6">
                        <label class="form-label">Hora inicio</label>
                        <input type="time" name="start_time" class="form-control-jp" value="<?= esc((string) $renewalDefaults['start_time']) ?>" required>
                    </div>
                    <div class="col-6">
                        <label class="form-label">Hora fin</label>
                        <input type="time" name="end_time" class="form-control-jp" value="<?= esc((string) $renewalDefaults['end_time']) ?>">
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label">Instalación</label>
                        <select name="location_id" class="form-control-jp">
                            <option value="">— Seleccionar —</option>
                            <?php foreach ($locationOptions as $loc): ?>
                            <option value="<?= $loc['id'] ?>" <?= (int) $renewalDefaults['location_id'] === (int) $loc['id'] ? 'selected' : '' ?>>
                                <?= esc($loc['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label">O lugar personalizado</label>
                        <input type="text" name="location_custom" class="form-control-jp" value="<?= esc((string) $renewalDefaults['location_custom']) ?>">
                    </div>

                    <div class="col-12">
                        <label class="form-label">Objetivo del entrenamiento</label>
                        <input type="text" name="focus" class="form-control-jp" value="<?= esc((string) $renewalDefaults['focus']) ?>">
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label" id="rn-coach-label"><?= $renewalDefaults['session_type'] === 'staff' ? 'Staff' : 'Entrenador' ?></label>
                        <select name="coach_ids[]" id="rn-coach-select" class="form-control-jp">
                            <option value="">Sin responsable asignado</option>
                            <?php foreach ($coachOptions as $c): ?>
                            <option value="<?= $c['id'] ?>" data-pool="coach" <?= in_array((int) $c['id'], $renewalDefaults['coach_ids'], true) ? 'selected' : '' ?>>
                                <?= esc($c['name']) ?>
                            </option>
                            <?php endforeach; ?>
                            <?php foreach ($staffOptions as $c): ?>
                            <option value="<?= $c['id'] ?>" data-pool="staff" <?= in_array((int) $c['id'], $renewalDefaults['coach_ids'], true) ? 'selected' : '' ?>>
                                <?= esc($c['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <div id="rn-coach-error" class="cm-error d-none" style="margin-top:8px"></div>
                    </div>
                    <div class="col-12 col-md-6">
                        <label class="form-label">Alumnos</label>
                        <?php
                        $playerNameById = array_column($playerOptions, 'name', 'id');
                        ?>
                        <div class="cm-tags" style="border:1px solid var(--border);border-radius:8px;padding:10px;min-height:24px">
                            <?php if (empty($renewalDefaults['player_ids'])): ?>
                            <span style="font-size:12.5px;color:var(--text-muted)">Sin alumnos asignados</span>
                            <?php else: ?>
                                <?php foreach ($renewalDefaults['player_ids'] as $pid): ?>
                                <span class="cm-tag"><?= esc($playerNameById[$pid] ?? ('Alumno #' . $pid)) ?></span>
                                <input type="hidden" name="player_ids[]" value="<?= (int) $pid ?>">
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                        <div style="font-size:11px;color:var(--text-muted);margin-top:4px">
                            Los alumnos se mantienen: una clase recurrente continúa siempre con el mismo alumno/alumnos.
                        </div>
                    </div>
                </div>

                <div class="d-flex gap-2 justify-content-end mt-3">
                    <button type="button" class="btn-jp btn-jp-secondary" onclick="closeModal('modalRenewSeries')">Cancelar</button>
                    <button type="submit" class="btn-jp btn-jp-primary"><i class="bi bi-check-lg me-1"></i>Continuar clases</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
// Filtra el select de responsable según Entrenador/Staff elegido, para no
// dejar seleccionable a alguien del pool equivocado (mismas reglas que
// ClasesService::RESPONSABLE_TECNICO_ROLES / RESPONSABLE_STAFF_ROLES).
(function () {
    var sel = document.getElementById('rn-coach-select');
    var label = document.getElementById('rn-coach-label');
    if (!sel) return;
    function applyPool() {
        var isStaff = document.getElementById('rn-type-staff')?.checked;
        label.textContent = isStaff ? 'Staff' : 'Entrenador';
        Array.prototype.forEach.call(sel.options, function (opt) {
            if (!opt.value) return;
            var show = !opt.dataset.pool || opt.dataset.pool === (isStaff ? 'staff' : 'coach');
            opt.hidden = !show;
            if (!show && opt.selected) opt.selected = false;
        });
    }
    document.getElementById('rn-type-coach')?.addEventListener('change', applyPool);
    document.getElementById('rn-type-staff')?.addEventListener('change', applyPool);
    applyPool();

    document.querySelectorAll('.rn-day-check').forEach(function (chk) {
        chk.addEventListener('change', function () {
            chk.closest('.cm-day-pill')?.classList.toggle('active', chk.checked);
        });
    });

    // El responsable es obligatorio al continuar una serie: dejarlo en
    // "Sin responsable asignado" y guardar no puede colar en silencio.
    var form = document.getElementById('formRenewSeries');
    var errEl = document.getElementById('rn-coach-error');
    form?.addEventListener('submit', function (e) {
        if (!sel.value) {
            e.preventDefault();
            errEl.textContent = 'Selecciona ' + (document.getElementById('rn-type-staff')?.checked ? 'un miembro del staff' : 'un entrenador') + ' antes de continuar la serie.';
            errEl.classList.remove('d-none');
            sel.focus();
        } else {
            errEl.classList.add('d-none');
        }
    });
})();
// Auto-abrir el modal si venimos del aviso "última sesión" tras cerrar.
// Se espera a DOMContentLoaded porque openModal() se define más abajo, en
// la sección "scripts" del layout (renderizada después de este bloque).
document.addEventListener('DOMContentLoaded', function () {
    if (new URLSearchParams(window.location.search).get('renovar') === '1') {
        openModal('modalRenewSeries');
    }
});
</script>
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
    display:flex;align-items:center;justify-content:space-between;position:relative;
    padding:16px 44px 16px 20px;border-bottom:1px solid var(--border);
    font-size:15px;font-weight:700;color:var(--text-h);
}
.cs-modal-header button {
    background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:18px;
    width:32px;height:32px;display:flex;align-items:center;justify-content:center;border-radius:6px;
    transition:background .15s;
}
.cs-modal-header button:hover { background:var(--bg-app);color:var(--text-h); }
.cs-modal-body { padding:20px;overflow-y:auto;flex:1; }

.cs-attach-list { display:flex;flex-wrap:wrap;gap:8px; }
.cs-attach-chip {
    display:inline-flex;align-items:center;gap:6px;background:var(--bg-app);
    border:1px solid var(--border);border-radius:20px;padding:5px 6px 5px 12px;font-size:12px;
}
.cs-attach-chip a { display:inline-flex;align-items:center;gap:6px;color:var(--text-h);text-decoration:none;max-width:220px; }
.cs-attach-name { overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:150px; }
.cs-attach-size { color:var(--text-muted);font-size:10.5px; }
.cs-attach-del {
    background:none;border:none;color:var(--text-muted);cursor:pointer;
    width:22px;height:22px;border-radius:50%;display:flex;align-items:center;justify-content:center;
    font-size:11px;transition:background .15s,color .15s;
}
.cs-attach-del:hover { background:#dc262622;color:#dc2626; }

/* Días de la semana — modal "Continuar clases recurrentes" */
.cm-day-row { display:flex; flex-wrap:wrap; gap:6px; }
.cm-day-pill {
    cursor:pointer; padding:6px 12px; border:1px solid var(--border);
    border-radius:20px; font-size:12.5px; font-weight:600; color:var(--text-body);
    transition:all .15s; user-select:none;
}
.cm-day-pill input { display:none; }
.cm-day-pill.active { background:var(--accent-light); border-color:var(--accent); color:var(--accent); }

/* Alumnos (solo lectura) — modal "Continuar clases recurrentes" */
.cm-tags { display:flex; flex-wrap:wrap; gap:6px; align-items:center; }
.cm-tag {
    display:inline-flex; align-items:center; gap:4px;
    padding:4px 10px; border-radius:20px; font-size:12px; font-weight:600;
    background:var(--accent-light); color:var(--accent);
}
.cm-error {
    margin-top:6px; padding:8px 12px; border-radius:6px;
    background:rgba(239,68,68,.10); color:var(--danger); font-size:12.5px;
    border:1px solid rgba(239,68,68,.25);
}
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


function openObsModal(userId, name, preObs, postObs) {
    document.getElementById('obsModalTitle').textContent = 'Observaciones individuales — ' + name;
    document.getElementById('obsUserId').value = userId;
    document.getElementById('obsPreInput').value = preObs;
    document.getElementById('obsPostInput').value = postObs;

    // Adjuntos de este jugador (plantilla oculta renderizada por el servidor).
    document.getElementById('obsAttachPlayerUid').value = userId;
    const tpl = document.getElementById('attachTpl-' + userId);
    document.getElementById('obsAttachList').innerHTML = tpl ? tpl.innerHTML : '';

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
