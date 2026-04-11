<?= $this->extend('layouts/app') ?>

<?php
// ── Helpers ───────────────────────────────────────────────────────────

function showStatusBadge(string $status): string {
    $map = [
        'planned'   => ['Planificado', '#2563eb', 'bi-calendar-event-fill'],
        'active'    => ['En curso',    '#059669', 'bi-play-circle-fill'],
        'finished'  => ['Finalizado',  '#6b7280', 'bi-check-circle-fill'],
        'cancelled' => ['Cancelado',   '#dc2626', 'bi-x-circle-fill'],
    ];
    [$label, $color, $icon] = $map[$status] ?? ['—', '#6b7280', 'bi-dash'];
    return "<span class=\"badge-status\" style=\"background:{$color}22;color:{$color};border:1px solid {$color}44;font-size:12px\">
              <i class=\"bi {$icon} me-1\"></i>{$label}
            </span>";
}

function confBadge(?string $status): string {
    $map = [
        'confirmed' => ['Confirmado',  '#059669', 'bi-check-circle-fill'],
        'declined'  => ['Declinado',   '#dc2626', 'bi-x-circle-fill'],
        'pending'   => ['Pendiente',   '#d97706', 'bi-clock-fill'],
    ];
    [$label, $color, $icon] = $map[$status ?? 'pending'] ?? $map['pending'];
    return "<span class=\"badge-status\" style=\"background:{$color}22;color:{$color};border:1px solid {$color}44;font-size:11px\">
              <i class=\"bi {$icon} me-1\"></i>{$label}
            </span>";
}

function roleBadgeMember(string $role): string {
    $map = [
        'player' => ['Jugador',    '#2563eb'],
        'coach'  => ['Entrenador', '#059669'],
        'staff'  => ['Staff',      '#0891b2'],
    ];
    [$label, $color] = $map[$role] ?? ['—', '#6b7280'];
    return "<span class=\"badge-status\" style=\"background:{$color}22;color:{$color};border:1px solid {$color}44;font-size:11px\">{$label}</span>";
}
?>

<?= $this->section('page_content') ?>

<div class="page-header">
    <div class="page-header-text">
        <h2><?= esc($event['name']) ?></h2>
        <p>
            <a href="/torneos" style="color:var(--text-muted);text-decoration:none">
                <i class="bi bi-arrow-left me-1"></i>Torneos y Campus
            </a>
        </p>
    </div>
    <?php if ($isAdmin): ?>
    <div class="d-flex gap-2 flex-wrap">
        <!-- Quick-create clase desde Torneos -->
        <button class="btn-jp btn-jp-secondary btn-jp-sm" onclick="openTorneoQuickCreate()" title="Crear clase relacionada con este evento">
            <i class="bi bi-collection-play-fill me-1"></i>Nueva clase
        </button>
        <?php if ($event['status'] !== 'cancelled'): ?>
        <button class="btn-jp btn-jp-primary btn-jp-sm" onclick="openModal('modalNewTeam')">
            <i class="bi bi-plus-lg me-1"></i>Añadir equipo
        </button>
        <form action="/torneos/<?= $event['id'] ?>/notificar" method="POST" class="d-inline">
            <?= csrf_field() ?>
            <button type="submit" class="btn-jp btn-jp-secondary btn-jp-sm"
                    onclick="return confirm('¿Enviar convocatoria a todos los miembros con cuenta?')">
                <i class="bi bi-bell-fill me-1"></i>Enviar convocatoria
            </button>
        </form>
        <?php endif; ?>
        <?php if (!in_array($event['status'], ['cancelled', 'finished'])): ?>
        <a href="/torneos/<?= $event['id'] ?>/editar" class="btn-jp btn-jp-secondary btn-jp-sm">
            <i class="bi bi-pencil-fill me-1"></i>Editar
        </a>
        <form action="/torneos/<?= $event['id'] ?>/cancelar" method="POST" class="d-inline"
              onsubmit="return confirm('¿Cancelar este evento? Esta acción se puede revertir editándolo.')">
            <?= csrf_field() ?>
            <button type="submit" class="btn-jp btn-jp-danger btn-jp-sm">
                <i class="bi bi-x-circle-fill me-1"></i>Cancelar evento
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
    <div class="alert-jp danger mb-3"><i class="bi bi-x-circle-fill me-2"></i><?= esc($flash) ?></div>
<?php endif; ?>

<div class="row g-3">

    <!-- ══════════════════════════════════════════════════════════
         COLUMNA IZQUIERDA — Info del evento
    ═══════════════════════════════════════════════════════════ -->
    <div class="col-12 col-lg-4">

        <!-- Ficha del evento -->
        <div class="card-jp mb-3">
            <div class="card-jp-body">
                <div class="d-flex gap-2 mb-3 flex-wrap">
                    <!-- Tipo -->
                    <?php if ($event['type'] === 'campus'): ?>
                        <span class="badge-status" style="background:#7c3aed22;color:#7c3aed;border:1px solid #7c3aed44">
                            <i class="bi bi-mortarboard-fill me-1"></i>Campus
                        </span>
                    <?php else: ?>
                        <span class="badge-status" style="background:#d9770622;color:#d97706;border:1px solid #d9770644">
                            <i class="bi bi-trophy-fill me-1"></i>Torneo
                        </span>
                    <?php endif; ?>
                    <?= showStatusBadge($event['status']) ?>
                </div>

                <?php if (!empty($event['category'])): ?>
                <div class="mb-3">
                    <div style="font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);margin-bottom:3px">Categoría</div>
                    <div style="font-weight:600;color:var(--text-h)"><?= esc($event['category']) ?></div>
                </div>
                <?php endif; ?>

                <div class="d-flex flex-column gap-2" style="font-size:13px">
                    <div>
                        <i class="bi bi-calendar-range-fill me-2" style="color:var(--accent)"></i>
                        <?= date('d/m/Y', strtotime($event['start_date'])) ?>
                        <?php if ($event['start_date'] !== $event['end_date']): ?>
                            → <?= date('d/m/Y', strtotime($event['end_date'])) ?>
                            <span style="font-size:11px;color:var(--text-muted)">
                                (<?= (new DateTime($event['start_date']))->diff(new DateTime($event['end_date']))->days + 1 ?> días)
                            </span>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($event['location'])): ?>
                    <div><i class="bi bi-geo-alt-fill me-2" style="color:#059669"></i><?= esc($event['location']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($event['concentration_time'])): ?>
                    <div>
                        <i class="bi bi-clock-fill me-2" style="color:#d97706"></i>
                        Concentración: <?= esc(substr($event['concentration_time'], 0, 5)) ?>h
                        <?php if (!empty($event['concentration_place'])): ?>
                            <div style="font-size:12px;color:var(--text-muted);padding-left:22px"><?= esc($event['concentration_place']) ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>

                <?php if (!empty($event['description'])): ?>
                <hr style="border-color:var(--border);margin:12px 0">
                <p style="font-size:13px;color:var(--text-muted);margin:0"><?= nl2br(esc($event['description'])) ?></p>
                <?php endif; ?>

                <?php if (!empty($event['equipment_notes'])): ?>
                <hr style="border-color:var(--border);margin:12px 0">
                <div style="font-size:12px;font-weight:600;color:var(--text-h);margin-bottom:5px">
                    <i class="bi bi-bag-fill me-1" style="color:#0891b2"></i>Equipamiento
                </div>
                <p style="font-size:12.5px;color:var(--text-muted);margin:0"><?= nl2br(esc($event['equipment_notes'])) ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Campus: alojamiento + programa -->
        <?php if ($event['type'] === 'campus' && (!empty($event['accommodation_info']) || !empty($event['schedule_info']))): ?>
        <div class="card-jp mb-3">
            <div class="card-jp-header">
                <span class="card-jp-title" style="color:#7c3aed">
                    <i class="bi bi-mortarboard-fill me-2"></i>Campus
                </span>
            </div>
            <div class="card-jp-body">
                <?php if (!empty($event['accommodation_info'])): ?>
                <div style="font-size:12px;font-weight:600;color:var(--text-h);margin-bottom:5px">
                    <i class="bi bi-house-fill me-1"></i>Alojamiento
                </div>
                <p style="font-size:12.5px;color:var(--text-muted);margin-bottom:12px"><?= nl2br(esc($event['accommodation_info'])) ?></p>
                <?php endif; ?>
                <?php if (!empty($event['schedule_info'])): ?>
                <div style="font-size:12px;font-weight:600;color:var(--text-h);margin-bottom:5px">
                    <i class="bi bi-list-ul me-1"></i>Programa
                </div>
                <pre style="font-size:12px;color:var(--text-muted);white-space:pre-wrap;font-family:inherit;margin:0"><?= esc($event['schedule_info']) ?></pre>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Estadísticas de confirmación -->
        <?php $cs = $event['confirmation_stats']; ?>
        <?php if (array_sum($cs) > 0): ?>
        <div class="card-jp mb-3">
            <div class="card-jp-header">
                <span class="card-jp-title"><i class="bi bi-bar-chart-fill me-2" style="color:var(--accent)"></i>Confirmaciones</span>
            </div>
            <div class="card-jp-body">
                <div class="d-flex gap-3 justify-content-around text-center">
                    <div>
                        <div style="font-size:24px;font-weight:700;color:#059669"><?= $cs['confirmed'] ?></div>
                        <div style="font-size:11px;color:var(--text-muted)">Confirmados</div>
                    </div>
                    <div>
                        <div style="font-size:24px;font-weight:700;color:#d97706"><?= $cs['pending'] ?></div>
                        <div style="font-size:11px;color:var(--text-muted)">Pendientes</div>
                    </div>
                    <div>
                        <div style="font-size:24px;font-weight:700;color:#dc2626"><?= $cs['declined'] ?></div>
                        <div style="font-size:11px;color:var(--text-muted)">Declinados</div>
                    </div>
                </div>
            </div>
        </div>
        <?php endif; ?>

        <!-- Mi convocatoria (si el usuario está convocado) -->
        <?php if (!empty($myMembership)): ?>
        <div class="card-jp mb-3" style="border-left:3px solid #7c3aed">
            <div class="card-jp-header">
                <span class="card-jp-title" style="color:#7c3aed">
                    <i class="bi bi-person-badge-fill me-2"></i>Mi convocatoria
                </span>
            </div>
            <div class="card-jp-body">
                <div class="mb-2" style="font-size:13px">
                    <div><strong>Equipo:</strong> <?= esc($myMembership['team_name']) ?></div>
                    <div><strong>Rol:</strong> <?= roleBadgeMember($myMembership['role']) ?></div>
                    <?php if (!empty($myMembership['dorsal'])): ?>
                        <div><strong>Dorsal:</strong> #<?= $myMembership['dorsal'] ?></div>
                    <?php endif; ?>
                    <?php if (!empty($myMembership['position'])): ?>
                        <div><strong>Posición:</strong> <?= esc($myMembership['position']) ?></div>
                    <?php endif; ?>
                    <?php if (!empty($myMembership['staff_role'])): ?>
                        <div><strong>Función:</strong> <?= esc($myMembership['staff_role']) ?></div>
                    <?php endif; ?>
                </div>
                <div class="mb-3"><?= confBadge($myMembership['conf_status'] ?? 'pending') ?></div>
                <?php if (in_array($event['status'], ['planned', 'active'])): ?>
                <form action="/torneos/<?= $event['id'] ?>/respond" method="POST">
                    <?= csrf_field() ?>
                    <?php if (($myMembership['conf_status'] ?? 'pending') !== 'confirmed'): ?>
                    <button type="submit" name="status" value="confirmed"
                            class="btn-jp btn-jp-primary w-100 mb-2">
                        <i class="bi bi-check-circle-fill me-1"></i>Confirmar asistencia
                    </button>
                    <?php endif; ?>
                    <?php if (($myMembership['conf_status'] ?? 'pending') !== 'declined'): ?>
                    <button type="button" class="btn-jp btn-jp-danger w-100"
                            onclick="openDeclineModal()">
                        <i class="bi bi-x-circle-fill me-1"></i>Declinar asistencia
                    </button>
                    <?php endif; ?>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

        <!-- Resultados -->
        <?php if (!empty($event['results']) || ($isAdmin && $event['status'] === 'finished')): ?>
        <div class="card-jp">
            <div class="card-jp-header d-flex align-items-center justify-content-between">
                <span class="card-jp-title"><i class="bi bi-award-fill me-2" style="color:#d97706"></i>Resultados</span>
                <?php if ($isAdmin): ?>
                <button class="btn-jp btn-jp-secondary btn-jp-sm" onclick="openModal('modalResult')">
                    <i class="bi bi-pencil-fill me-1"></i>Añadir
                </button>
                <?php endif; ?>
            </div>
            <div class="card-jp-body">
                <?php if (empty($event['results'])): ?>
                    <p style="font-size:13px;color:var(--text-muted);margin:0">Sin resultados registrados.</p>
                <?php else: ?>
                    <div class="d-flex flex-column gap-2">
                    <?php foreach ($event['results'] as $res): ?>
                        <div style="padding:10px;background:var(--bg-secondary);border-radius:8px;border:1px solid var(--border)">
                            <?php if (!empty($res['result_text'])): ?>
                            <div style="font-size:18px;font-weight:700;color:var(--text-h)"><?= esc($res['result_text']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($res['notes'])): ?>
                            <div style="font-size:12.5px;color:var(--text-muted);margin-top:4px"><?= nl2br(esc($res['notes'])) ?></div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>

    </div><!-- /col-4 -->


    <!-- ══════════════════════════════════════════════════════════
         COLUMNA DERECHA — Equipos y miembros
    ═══════════════════════════════════════════════════════════ -->
    <div class="col-12 col-lg-8">

        <?php if (empty($event['teams'])): ?>
        <div class="card-jp">
            <div class="empty-state py-5">
                <i class="bi bi-people" style="font-size:2.5rem;color:var(--text-muted)"></i>
                <p class="mt-3 mb-1" style="color:var(--text-h);font-weight:600">Sin equipos</p>
                <p style="color:var(--text-muted);font-size:13px">
                    <?= $isAdmin ? 'Añade el primer equipo con el botón de arriba.' : 'Los equipos aún no han sido configurados.' ?>
                </p>
            </div>
        </div>
        <?php else: ?>

        <?php foreach ($event['teams'] as $team): ?>
        <div class="card-jp mb-3">
            <div class="card-jp-header d-flex align-items-center justify-content-between">
                <span class="card-jp-title">
                    <i class="bi bi-shield-fill me-2" style="color:var(--accent)"></i>
                    <?= esc($team['name']) ?>
                    <?php if (!empty($team['category'])): ?>
                        <span style="font-size:12px;font-weight:400;color:var(--text-muted);margin-left:6px"><?= esc($team['category']) ?></span>
                    <?php endif; ?>
                </span>
                <?php if ($isAdmin && $event['status'] !== 'cancelled'): ?>
                <div class="d-flex gap-2">
                    <button class="btn-jp btn-jp-secondary btn-jp-sm"
                            onclick="openAddMemberModal(<?= $team['id'] ?>, '<?= esc($team['name']) ?>')">
                        <i class="bi bi-person-plus-fill me-1"></i>Añadir
                    </button>
                    <form action="/torneos/<?= $event['id'] ?>/equipos/<?= $team['id'] ?>/delete" method="POST" class="d-inline"
                          onsubmit="return confirm('¿Eliminar el equipo «<?= esc($team['name']) ?>» y todos sus miembros?')">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn-jp btn-jp-danger btn-jp-sm btn-jp-icon" title="Eliminar equipo">
                            <i class="bi bi-trash-fill"></i>
                        </button>
                    </form>
                </div>
                <?php endif; ?>
            </div>

            <?php if (!empty($team['notes'])): ?>
            <div style="padding:8px 16px;font-size:12.5px;color:var(--text-muted);border-bottom:1px solid var(--border)">
                <?= esc($team['notes']) ?>
            </div>
            <?php endif; ?>

            <?php if (empty($team['members'])): ?>
                <div style="padding:20px;text-align:center;color:var(--text-muted);font-size:13px">
                    Sin miembros asignados todavía.
                </div>
            <?php else: ?>
            <div class="card-jp-body p-0">
                <?php
                $groups = ['player' => [], 'coach' => [], 'staff' => []];
                foreach ($team['members'] as $m) $groups[$m['role']][] = $m;
                $groupLabels = ['player' => ['Jugadores', 'bi-person-fill'], 'coach' => ['Entrenadores', 'bi-whistle-fill'], 'staff' => ['Staff', 'bi-briefcase-fill']];
                foreach ($groups as $role => $members):
                    if (empty($members)) continue;
                    [$groupLabel, $groupIcon] = $groupLabels[$role];
                ?>
                <!-- Sub-header por grupo -->
                <div style="padding:8px 16px;background:var(--bg-secondary);border-bottom:1px solid var(--border);font-size:12px;font-weight:700;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px">
                    <i class="bi <?= $groupIcon ?> me-1"></i><?= $groupLabel ?> (<?= count($members) ?>)
                </div>
                <div class="table-responsive">
                    <table class="table-jp">
                        <thead>
                            <tr>
                                <?php if ($role === 'player'): ?>
                                <th style="width:50px">#</th>
                                <?php endif; ?>
                                <th>Nombre</th>
                                <?php if ($role === 'player'): ?>
                                <th>Posición</th>
                                <?php else: ?>
                                <th>Función</th>
                                <?php endif; ?>
                                <th>Tipo</th>
                                <th>Respuesta</th>
                                <?php if ($isAdmin): ?><th></th><?php endif; ?>
                            </tr>
                        </thead>
                        <tbody>
                        <?php foreach ($members as $m): ?>
                            <tr>
                                <?php if ($role === 'player'): ?>
                                <td style="text-align:center;font-weight:700;color:var(--text-muted)">
                                    <?= !empty($m['dorsal']) ? $m['dorsal'] : '—' ?>
                                </td>
                                <?php endif; ?>
                                <td>
                                    <div style="font-weight:600;color:var(--text-h)"><?= esc($m['display_name'] ?? '—') ?></div>
                                    <?php if (!empty($m['display_email'])): ?>
                                    <div style="font-size:11px;color:var(--text-muted)"><?= esc($m['display_email']) ?></div>
                                    <?php endif; ?>
                                </td>
                                <?php if ($role === 'player'): ?>
                                <td style="font-size:12.5px"><?= esc($m['position'] ?? '—') ?></td>
                                <?php else: ?>
                                <td style="font-size:12.5px"><?= esc($m['staff_role'] ?? '—') ?></td>
                                <?php endif; ?>
                                <td>
                                    <?php if ($m['member_type'] === 'external'): ?>
                                        <span class="badge-status" style="background:#6b728022;color:#6b7280;border:1px solid #6b728044;font-size:11px">
                                            <i class="bi bi-person-badge me-1"></i>Externo
                                        </span>
                                    <?php else: ?>
                                        <span class="badge-status" style="background:#2563eb22;color:#2563eb;border:1px solid #2563eb44;font-size:11px">
                                            <i class="bi bi-house-fill me-1"></i>Plataforma
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!empty($m['notified_at'])): ?>
                                        <span class="ms-1" title="Notificado el <?= date('d/m/Y', strtotime($m['notified_at'])) ?>" style="color:#7c3aed">
                                            <i class="bi bi-bell-fill" style="font-size:11px"></i>
                                        </span>
                                    <?php endif; ?>
                                </td>
                                <td><?= confBadge($m['conf_status']) ?></td>
                                <?php if ($isAdmin): ?>
                                <td class="text-end">
                                    <form action="/torneos/<?= $event['id'] ?>/miembros/<?= $m['id'] ?>/remove" method="POST"
                                          onsubmit="return confirm('¿Quitar a <?= esc($m['display_name'] ?? '') ?> del equipo?')">
                                        <?= csrf_field() ?>
                                        <button type="submit" class="btn-jp btn-jp-danger btn-jp-sm btn-jp-icon" title="Quitar">
                                            <i class="bi bi-person-dash-fill"></i>
                                        </button>
                                    </form>
                                </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

        <?php endif; // empty teams ?>

        <!-- Nota para admin si hay externos sin notificación posible -->
        <?php if ($isAdmin): ?>
        <div class="alert-jp info">
            <i class="bi bi-info-circle-fill me-2"></i>
            Los <strong>participantes externos</strong> no tienen cuenta en la plataforma. La convocatoria interna solo llega a los miembros registrados.
        </div>
        <?php endif; ?>

    </div><!-- /col-8 -->
</div><!-- /row -->


<!-- ══════════════════════════════════════════════════════════
     MODALES (solo admin)
═══════════════════════════════════════════════════════════ -->
<?php if ($isAdmin): ?>

<!-- Modal: Nuevo equipo -->
<div id="modalNewTeam" class="ev-modal-overlay d-none">
    <div class="ev-modal">
        <div class="ev-modal-header">
            <span><i class="bi bi-shield-fill me-2"></i>Nuevo equipo</span>
            <button onclick="closeModal('modalNewTeam')"><i class="bi bi-x-lg"></i></button>
        </div>
        <form action="/torneos/<?= $event['id'] ?>/equipos/create" method="POST">
            <?= csrf_field() ?>
            <div class="ev-modal-body">
                <div class="row g-3">
                    <div class="col-12 col-md-8">
                        <label class="form-label">Nombre del equipo <span style="color:var(--danger)">*</span></label>
                        <input type="text" name="name" class="form-control-jp" required
                               placeholder="Ej: Equipo A, Sub-14 Azul…">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Categoría</label>
                        <input type="text" name="category" class="form-control-jp" placeholder="Sub-14, Abs…">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notas</label>
                        <textarea name="notes" class="form-control-jp" rows="2" placeholder="Información adicional…"></textarea>
                    </div>
                </div>
            </div>
            <div class="ev-modal-footer">
                <button type="button" class="btn-jp btn-jp-secondary" onclick="closeModal('modalNewTeam')">Cancelar</button>
                <button type="submit" class="btn-jp btn-jp-primary"><i class="bi bi-check-lg me-1"></i>Crear equipo</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Añadir miembro -->
<div id="modalAddMember" class="ev-modal-overlay d-none">
    <div class="ev-modal" style="max-width:620px">
        <div class="ev-modal-header">
            <span id="modalAddMemberTitle"><i class="bi bi-person-plus-fill me-2"></i>Añadir miembro</span>
            <button onclick="closeModal('modalAddMember')"><i class="bi bi-x-lg"></i></button>
        </div>
        <form id="addMemberForm" method="POST">
            <?= csrf_field() ?>
            <div class="ev-modal-body">
                <!-- Tipo de miembro -->
                <div class="mb-3">
                    <label class="form-label">Tipo de miembro</label>
                    <div class="d-flex gap-3">
                        <label style="cursor:pointer;display:flex;align-items:center;gap:6px;font-size:13.5px">
                            <input type="radio" name="member_type" value="user" checked onchange="toggleMemberType(this)"> Miembro de la plataforma
                        </label>
                        <label style="cursor:pointer;display:flex;align-items:center;gap:6px;font-size:13.5px">
                            <input type="radio" name="member_type" value="external" onchange="toggleMemberType(this)"> Participante externo
                        </label>
                    </div>
                </div>

                <!-- Selector de usuario interno -->
                <div id="selectorUser" class="mb-3">
                    <label class="form-label">Usuario <span style="color:var(--danger)">*</span></label>
                    <select name="user_id" class="form-control-jp">
                        <option value="">— Seleccionar usuario —</option>
                        <?php foreach ($selectableUsers as $u): ?>
                            <option value="<?= $u['id'] ?>" data-role="<?= $u['role'] ?>">
                                <?= esc($u['name']) ?> (<?= $u['role'] ?> · <?= esc($u['email']) ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Selector de externo -->
                <div id="selectorExternal" class="mb-3 d-none">
                    <label class="form-label">Participante externo <span style="color:var(--danger)">*</span></label>
                    <select name="external_id" class="form-control-jp">
                        <option value="">— Seleccionar externo —</option>
                        <?php foreach ($externalParticipants as $ep): ?>
                            <option value="<?= $ep['id'] ?>" data-type="<?= $ep['type'] ?>">
                                <?= esc($ep['name']) ?> (<?= $ep['type'] ?><?= !empty($ep['position']) ? ' · ' . esc($ep['position']) : '' ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="mt-2">
                        <button type="button" class="btn-jp btn-jp-secondary btn-jp-sm"
                                onclick="closeModal('modalAddMember');openModal('modalNewExternal')">
                            <i class="bi bi-plus-lg me-1"></i>Crear nuevo externo
                        </button>
                    </div>
                </div>

                <!-- Rol en el equipo -->
                <div class="row g-3">
                    <div class="col-12 col-md-4">
                        <label class="form-label">Rol en el equipo <span style="color:var(--danger)">*</span></label>
                        <select name="role" id="memberRole" class="form-control-jp" onchange="toggleRoleFields(this.value)">
                            <option value="player">Jugador</option>
                            <option value="coach">Entrenador</option>
                            <option value="staff">Staff</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-4" id="fieldDorsal">
                        <label class="form-label">Dorsal</label>
                        <input type="number" name="dorsal" class="form-control-jp" min="1" max="99" placeholder="—">
                    </div>
                    <div class="col-6 col-md-4" id="fieldPosition">
                        <label class="form-label">Posición</label>
                        <input type="text" name="position" class="form-control-jp" placeholder="Portero, Lateral…">
                    </div>
                    <div class="col-12 d-none" id="fieldStaffRole">
                        <label class="form-label">Función / cargo</label>
                        <input type="text" name="staff_role" class="form-control-jp"
                               placeholder="Ej: Primer entrenador, Ayudante, Fisioterapeuta, Delegado…">
                    </div>
                </div>
            </div>
            <div class="ev-modal-footer">
                <button type="button" class="btn-jp btn-jp-secondary" onclick="closeModal('modalAddMember')">Cancelar</button>
                <button type="submit" class="btn-jp btn-jp-primary"><i class="bi bi-check-lg me-1"></i>Añadir miembro</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Nuevo participante externo -->
<div id="modalNewExternal" class="ev-modal-overlay d-none">
    <div class="ev-modal" style="max-width:560px">
        <div class="ev-modal-header">
            <span><i class="bi bi-person-badge-fill me-2"></i>Nuevo participante externo</span>
            <button onclick="closeModal('modalNewExternal');openModal('modalAddMember')"><i class="bi bi-x-lg"></i></button>
        </div>
        <form action="/torneos/externos/create" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="back_event" value="<?= $event['id'] ?>">
            <div class="ev-modal-body">
                <div class="row g-3">
                    <div class="col-12 col-md-8">
                        <label class="form-label">Nombre completo <span style="color:var(--danger)">*</span></label>
                        <input type="text" name="name" class="form-control-jp" required placeholder="Nombre y apellidos">
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Tipo <span style="color:var(--danger)">*</span></label>
                        <select name="type" class="form-control-jp" required>
                            <option value="player">Jugador</option>
                            <option value="coach">Entrenador</option>
                            <option value="staff">Staff</option>
                        </select>
                    </div>
                    <div class="col-6 col-md-4">
                        <label class="form-label">Posición (jugadores)</label>
                        <input type="text" name="position" class="form-control-jp" placeholder="Portero, Lateral…">
                    </div>
                    <div class="col-6 col-md-4">
                        <label class="form-label">F. nacimiento</label>
                        <input type="date" name="birth_date" class="form-control-jp">
                    </div>
                    <div class="col-6 col-md-4">
                        <label class="form-label">Teléfono</label>
                        <input type="text" name="phone" class="form-control-jp" placeholder="+34 600…">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Email</label>
                        <input type="email" name="email" class="form-control-jp" placeholder="opcional">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notas</label>
                        <textarea name="notes" class="form-control-jp" rows="2" placeholder="Club de origen, observaciones…"></textarea>
                    </div>
                </div>
            </div>
            <div class="ev-modal-footer">
                <button type="button" class="btn-jp btn-jp-secondary"
                        onclick="closeModal('modalNewExternal');openModal('modalAddMember')">Cancelar</button>
                <button type="submit" class="btn-jp btn-jp-primary"><i class="bi bi-check-lg me-1"></i>Crear y volver</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: Guardar resultado -->
<div id="modalResult" class="ev-modal-overlay d-none">
    <div class="ev-modal">
        <div class="ev-modal-header">
            <span><i class="bi bi-award-fill me-2"></i>Añadir resultado</span>
            <button onclick="closeModal('modalResult')"><i class="bi bi-x-lg"></i></button>
        </div>
        <form action="/torneos/<?= $event['id'] ?>/resultado" method="POST">
            <?= csrf_field() ?>
            <div class="ev-modal-body">
                <div class="row g-3">
                    <?php if (count($event['teams']) > 1): ?>
                    <div class="col-12">
                        <label class="form-label">Equipo (opcional)</label>
                        <select name="team_id" class="form-control-jp">
                            <option value="">Resultado general del evento</option>
                            <?php foreach ($event['teams'] as $t): ?>
                                <option value="<?= $t['id'] ?>"><?= esc($t['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php else: ?>
                        <input type="hidden" name="team_id" value="">
                    <?php endif; ?>
                    <div class="col-12">
                        <label class="form-label">Resultado</label>
                        <input type="text" name="result_text" class="form-control-jp"
                               placeholder="Ej: 3-1, Campeones, 2º clasificado…">
                    </div>
                    <div class="col-12">
                        <label class="form-label">Notas adicionales</label>
                        <textarea name="notes" class="form-control-jp" rows="3"
                                  placeholder="Descripción del torneo, puntos destacados…"></textarea>
                    </div>
                </div>
            </div>
            <div class="ev-modal-footer">
                <button type="button" class="btn-jp btn-jp-secondary" onclick="closeModal('modalResult')">Cancelar</button>
                <button type="submit" class="btn-jp btn-jp-primary"><i class="bi bi-check-lg me-1"></i>Guardar</button>
            </div>
        </form>
    </div>
</div>

<?php endif; // isAdmin ?>

<!-- Modal: Declinar asistencia (todos los convocados) -->
<?php if (!empty($myMembership)): ?>
<div id="modalDecline" class="ev-modal-overlay d-none">
    <div class="ev-modal" style="max-width:440px">
        <div class="ev-modal-header">
            <span><i class="bi bi-x-circle-fill me-2" style="color:var(--danger)"></i>Declinar asistencia</span>
            <button onclick="closeModal('modalDecline')"><i class="bi bi-x-lg"></i></button>
        </div>
        <form action="/torneos/<?= $event['id'] ?>/respond" method="POST">
            <?= csrf_field() ?>
            <input type="hidden" name="status" value="declined">
            <div class="ev-modal-body">
                <p style="font-size:13.5px;color:var(--text-muted)">¿Seguro que quieres declinar tu asistencia a este evento?</p>
                <label class="form-label">Motivo (opcional)</label>
                <textarea name="notes" class="form-control-jp" rows="3"
                          placeholder="Lesión, compromiso personal…"></textarea>
            </div>
            <div class="ev-modal-footer">
                <button type="button" class="btn-jp btn-jp-secondary" onclick="closeModal('modalDecline')">Cancelar</button>
                <button type="submit" class="btn-jp btn-jp-danger"><i class="bi bi-x-circle-fill me-1"></i>Declinar</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?= $this->endSection() ?>


<?= $this->section('scripts') ?>
<style>
/* ── Modal ─────────────────────────────────────────────────── */
.ev-modal-overlay {
    position:fixed;inset:0;background:rgba(0,0,0,.55);
    z-index:1050;display:flex;align-items:center;justify-content:center;padding:16px;
}
.ev-modal {
    background:var(--card-bg);border:1px solid var(--border);border-radius:12px;
    width:100%;max-width:520px;max-height:90vh;display:flex;flex-direction:column;
    box-shadow:0 20px 60px rgba(0,0,0,.4);
}
.ev-modal-header {
    display:flex;align-items:center;justify-content:space-between;
    padding:16px 20px;border-bottom:1px solid var(--border);
    font-size:15px;font-weight:700;color:var(--text-h);
}
.ev-modal-header button {
    background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:18px;
    width:32px;height:32px;display:flex;align-items:center;justify-content:center;border-radius:6px;
    transition:background .15s,color .15s;
}
.ev-modal-header button:hover { background:var(--bg-secondary);color:var(--text-h); }
.ev-modal-body   { padding:20px;overflow-y:auto;flex:1; }
.ev-modal-footer { display:flex;justify-content:flex-end;gap:8px;padding:16px 20px;border-top:1px solid var(--border); }
</style>

<script>
// ── Modales ────────────────────────────────────────────────────────────
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
        document.querySelectorAll('.ev-modal-overlay:not(.d-none)').forEach(m => closeModal(m.id));
    }
});
document.querySelectorAll('.ev-modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', e => {
        if (e.target === overlay) closeModal(overlay.id);
    });
});

// ── Modal añadir miembro: set team ────────────────────────────────────
function openAddMemberModal(teamId, teamName) {
    const form  = document.getElementById('addMemberForm');
    const title = document.getElementById('modalAddMemberTitle');

    form.action = '/torneos/<?= $event['id'] ?>/equipos/' + teamId + '/miembros/add';
    title.innerHTML = '<i class="bi bi-person-plus-fill me-2"></i>Añadir miembro — ' + teamName;

    // Reset
    form.reset();
    toggleMemberType(form.querySelector('[name=member_type][value=user]'));
    toggleRoleFields('player');

    openModal('modalAddMember');
}

// ── Tipo de miembro ───────────────────────────────────────────────────
function toggleMemberType(radio) {
    const isUser = radio.value === 'user';
    document.getElementById('selectorUser').classList.toggle('d-none',    !isUser);
    document.getElementById('selectorExternal').classList.toggle('d-none', isUser);
}

// ── Rol: mostrar/ocultar campos ───────────────────────────────────────
function toggleRoleFields(role) {
    const isPlayer = role === 'player';
    document.getElementById('fieldDorsal')?.classList.toggle('d-none',     !isPlayer);
    document.getElementById('fieldPosition')?.classList.toggle('d-none',   !isPlayer);
    document.getElementById('fieldStaffRole')?.classList.toggle('d-none',  isPlayer);
}

document.addEventListener('DOMContentLoaded', () => {
    const roleSelect = document.getElementById('memberRole');
    if (roleSelect) toggleRoleFields(roleSelect.value);
});

// ── Modal declinar ────────────────────────────────────────────────────
function openDeclineModal() { openModal('modalDecline'); }

// ── Quick-create clase desde Torneos ──────────────────────────────────
const TRN_CSRF_NAME = '<?= csrf_token() ?>';
const TRN_CSRF_HASH = '<?= csrf_hash() ?>';

function openTorneoQuickCreate() {
    document.getElementById('trn-qc-error').style.display = 'none';
    document.getElementById('trn-modal').classList.remove('d-none');
    document.body.style.overflow = 'hidden';
}
function closeTrnModal() {
    document.getElementById('trn-modal').classList.add('d-none');
    document.body.style.overflow = '';
}
document.getElementById('trn-modal')?.addEventListener('click', e => {
    if (e.target.id === 'trn-modal') closeTrnModal();
});

async function submitTrnQuickCreate() {
    const title = document.getElementById('trn-qc-title').value.trim();
    const date  = document.getElementById('trn-qc-date').value;
    const start = document.getElementById('trn-qc-start').value;
    const errEl = document.getElementById('trn-qc-error');
    if (!title||!date||!start) { errEl.textContent='Título, fecha y hora son obligatorios.'; errEl.style.display='block'; return; }
    errEl.style.display = 'none';
    const btn = document.getElementById('trn-qc-btn');
    btn.disabled = true; btn.innerHTML = '<i class="bi bi-hourglass-split me-1"></i>Creando…';
    const fd = new FormData();
    fd.append(TRN_CSRF_NAME, TRN_CSRF_HASH);
    fd.append('title', title);
    fd.append('session_date', date);
    fd.append('start_time', start);
    fd.append('end_time', document.getElementById('trn-qc-end').value);
    fd.append('location_custom', document.getElementById('trn-qc-location').value);
    try {
        const res  = await fetch('/clases/rapida', {method:'POST', body:fd});
        const data = await res.json();
        if (data.success) {
            closeTrnModal();
            document.getElementById('trn-qc-title').value = '';
            document.getElementById('trn-qc-start').value = '';
            document.getElementById('trn-qc-end').value   = '';
            // Redirect to the new class
            window.location.href = '/clases/' + data.id;
        } else {
            errEl.textContent = data.error||'Error al crear la sesión.';
            errEl.style.display = 'block';
        }
    } catch(e) { errEl.textContent='Error de conexión.'; errEl.style.display='block'; }
    btn.disabled = false; btn.innerHTML = '<i class="bi bi-check-lg me-1"></i>Crear';
}
</script>

<!-- Modal quick-create clase (Torneos) -->
<?php if ($isAdmin): ?>
<div id="trn-modal" class="ev-modal-overlay d-none">
    <div class="ev-modal" style="max-width:480px">
        <div class="ev-modal-header">
            <span><i class="bi bi-collection-play-fill me-2" style="color:var(--accent)"></i>Nueva clase</span>
            <button onclick="closeTrnModal()"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="ev-modal-body">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">Título <span style="color:var(--danger)">*</span></label>
                    <input type="text" id="trn-qc-title" class="form-control-jp"
                           placeholder="Ej: Preparación física – <?= esc($event['name']) ?>">
                </div>
                <div class="col-12 col-md-4">
                    <label class="form-label">Fecha <span style="color:var(--danger)">*</span></label>
                    <input type="date" id="trn-qc-date" class="form-control-jp"
                           value="<?= $event['start_date'] ?>">
                </div>
                <div class="col-6 col-md-4">
                    <label class="form-label">Inicio <span style="color:var(--danger)">*</span></label>
                    <input type="time" id="trn-qc-start" class="form-control-jp"
                           value="<?= !empty($event['concentration_time']) ? substr($event['concentration_time'],0,5) : '' ?>">
                </div>
                <div class="col-6 col-md-4">
                    <label class="form-label">Fin</label>
                    <input type="time" id="trn-qc-end" class="form-control-jp">
                </div>
                <div class="col-12">
                    <label class="form-label">Lugar</label>
                    <input type="text" id="trn-qc-location" class="form-control-jp"
                           value="<?= esc($event['location'] ?? '') ?>"
                           placeholder="Lugar de la sesión">
                </div>
            </div>
            <div id="trn-qc-error" style="color:var(--danger);font-size:13px;margin-top:10px;display:none"></div>
        </div>
        <div class="ev-modal-footer">
            <button class="btn-jp btn-jp-secondary" onclick="closeTrnModal()">Cancelar</button>
            <button class="btn-jp btn-jp-primary" id="trn-qc-btn" onclick="submitTrnQuickCreate()">
                <i class="bi bi-check-lg me-1"></i>Crear y abrir clase
            </button>
        </div>
    </div>
</div>
<?php endif; ?>

<?= $this->endSection() ?>
