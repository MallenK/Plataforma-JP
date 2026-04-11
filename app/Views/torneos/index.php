<?= $this->extend('layouts/app') ?>

<?php
function eventStatusBadge(string $status): string {
    $map = [
        'planned'   => ['Planificado', '#2563eb', 'bi-calendar-event-fill'],
        'active'    => ['En curso',    '#059669', 'bi-play-circle-fill'],
        'finished'  => ['Finalizado',  '#6b7280', 'bi-check-circle-fill'],
        'cancelled' => ['Cancelado',   '#dc2626', 'bi-x-circle-fill'],
    ];
    [$label, $color, $icon] = $map[$status] ?? ['—', '#6b7280', 'bi-dash'];
    return "<span class=\"badge-status\" style=\"background:{$color}22;color:{$color};border:1px solid {$color}44\">
              <i class=\"bi {$icon} me-1\"></i>{$label}
            </span>";
}

function eventTypeBadge(string $type): string {
    if ($type === 'campus') {
        return '<span class="badge-status" style="background:#7c3aed22;color:#7c3aed;border:1px solid #7c3aed44"><i class="bi bi-mortarboard-fill me-1"></i>Campus</span>';
    }
    return '<span class="badge-status" style="background:#d9770622;color:#d97706;border:1px solid #d9770644"><i class="bi bi-trophy-fill me-1"></i>Torneo</span>';
}
?>

<?= $this->section('page_content') ?>

<div class="page-header">
    <div class="page-header-text">
        <h2>Torneos y Campus</h2>
        <p>Eventos especiales — competiciones y campus formativos</p>
    </div>
    <?php if ($isAdmin): ?>
    <div class="d-flex gap-2">
        <a href="/torneos/nuevo?type=torneo" class="btn-jp btn-jp-primary">
            <i class="bi bi-trophy-fill me-1"></i>Nuevo torneo
        </a>
        <a href="/torneos/nuevo?type=campus" class="btn-jp btn-jp-secondary">
            <i class="bi bi-mortarboard-fill me-1"></i>Nuevo campus
        </a>
    </div>
    <?php endif; ?>
</div>

<?php if ($flash = session()->getFlashdata('success')): ?>
    <div class="alert-jp success mb-3"><i class="bi bi-check-circle-fill me-2"></i><?= esc($flash) ?></div>
<?php endif; ?>
<?php if ($flash = session()->getFlashdata('error')): ?>
    <div class="alert-jp danger mb-3"><i class="bi bi-x-circle-fill me-2"></i><?= esc($flash) ?></div>
<?php endif; ?>

<!-- ── Convocatorias pendientes de confirmar ──────────────────────── -->
<?php if (!empty($myPending)): ?>
<div class="card-jp mb-4" style="border-left:3px solid #7c3aed">
    <div class="card-jp-header">
        <span class="card-jp-title" style="color:#7c3aed">
            <i class="bi bi-bell-fill me-2"></i>
            Tienes <?= count($myPending) ?> convocatoria(s) pendiente(s) de confirmar
        </span>
    </div>
    <div class="card-jp-body p-0">
        <div class="table-responsive">
            <table class="table-jp">
                <thead>
                    <tr><th>Evento</th><th>Tipo</th><th>Equipo</th><th>Fecha</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($myPending as $n): ?>
                    <tr>
                        <td style="font-weight:600;color:var(--text-h)"><?= esc($n['event_name']) ?></td>
                        <td><?= eventTypeBadge($n['event_type']) ?></td>
                        <td style="font-size:13px"><?= esc($n['team_name']) ?></td>
                        <td style="font-size:13px"><?= date('d/m/Y', strtotime($n['start_date'])) ?></td>
                        <td class="text-end">
                            <a href="/torneos/<?= $n['event_id'] ?>" class="btn-jp btn-jp-primary btn-jp-sm">
                                <i class="bi bi-check2 me-1"></i>Ver y confirmar
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- ── Filtros ────────────────────────────────────────────────────── -->
<div class="card-jp mb-3">
    <div class="card-jp-body py-2 px-3">
        <div class="d-flex flex-wrap gap-2 align-items-center">
            <!-- Filtro por tipo -->
            <div class="d-flex gap-1">
                <?php
                $activeType   = $filters['type']   ?? '';
                $activeStatus = $filters['status'] ?? '';
                foreach (['' => 'Todos', 'torneo' => 'Torneos', 'campus' => 'Campus'] as $val => $lbl):
                ?>
                    <a href="?type=<?= $val ?>&status=<?= urlencode($activeStatus) ?>"
                       class="btn-jp btn-jp-sm <?= $activeType === $val ? 'btn-jp-primary' : 'btn-jp-secondary' ?>">
                        <?= $lbl ?>
                    </a>
                <?php endforeach; ?>
            </div>
            <!-- Filtro por estado -->
            <div>
                <select class="form-control-jp" style="font-size:12px;padding:5px 10px"
                        onchange="window.location='?type=<?= urlencode($activeType) ?>&status='+this.value">
                    <?php foreach (['' => 'Todos los estados', 'planned' => 'Planificados', 'active' => 'En curso', 'finished' => 'Finalizados', 'cancelled' => 'Cancelados'] as $val => $lbl): ?>
                        <option value="<?= $val ?>" <?= $activeStatus === $val ? 'selected' : '' ?>><?= $lbl ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
    </div>
</div>

<!-- ── Grid de eventos ───────────────────────────────────────────── -->
<?php if (empty($events)): ?>
    <div class="card-jp">
        <div class="empty-state py-5">
            <i class="bi bi-trophy" style="font-size:3rem;color:var(--text-muted)"></i>
            <p class="mt-3 mb-1" style="color:var(--text-h);font-weight:600">No hay eventos</p>
            <p style="color:var(--text-muted);font-size:13px">
                <?= $isAdmin ? 'Crea el primer torneo o campus desde los botones de arriba.' : 'Los eventos aparecerán aquí cuando sean publicados.' ?>
            </p>
        </div>
    </div>
<?php else: ?>
<div class="row g-3">
    <?php foreach ($events as $ev): ?>
    <div class="col-12 col-md-6 col-xl-4">
        <div class="card-jp h-100 d-flex flex-column"
             style="<?= $ev['status'] === 'active' ? 'border-left:3px solid #059669' : ($ev['status'] === 'cancelled' ? 'opacity:.6' : '') ?>">
            <div class="card-jp-body d-flex flex-column gap-2" style="height:100%">

                <!-- Type + status -->
                <div class="d-flex gap-2 flex-wrap">
                    <?= eventTypeBadge($ev['type']) ?>
                    <?= eventStatusBadge($ev['status']) ?>
                </div>

                <!-- Name -->
                <div>
                    <div style="font-weight:700;font-size:15px;color:var(--text-h);line-height:1.3">
                        <?= esc($ev['name']) ?>
                    </div>
                    <?php if (!empty($ev['category'])): ?>
                        <div style="font-size:12px;color:var(--text-muted);margin-top:3px">
                            <i class="bi bi-tag-fill me-1"></i><?= esc($ev['category']) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Info -->
                <div class="d-flex flex-column gap-1" style="font-size:12.5px;color:var(--text-muted)">
                    <div>
                        <i class="bi bi-calendar3 me-1" style="color:var(--accent)"></i>
                        <?= date('d/m/Y', strtotime($ev['start_date'])) ?>
                        <?php if ($ev['start_date'] !== $ev['end_date']): ?>
                            <span style="opacity:.6"> → </span><?= date('d/m/Y', strtotime($ev['end_date'])) ?>
                        <?php endif; ?>
                    </div>
                    <?php if (!empty($ev['location'])): ?>
                        <div><i class="bi bi-geo-alt-fill me-1" style="color:#059669"></i><?= esc($ev['location']) ?></div>
                    <?php endif; ?>
                    <div>
                        <i class="bi bi-people-fill me-1"></i>
                        <?= $ev['teams_count'] ?> equipo(s) · <?= $ev['members_count'] ?> miembro(s)
                    </div>
                </div>

                <!-- Actions -->
                <div class="mt-auto pt-2 d-flex gap-2">
                    <a href="/torneos/<?= $ev['id'] ?>" class="btn-jp btn-jp-primary btn-jp-sm" style="flex:1;text-align:center">
                        <i class="bi bi-eye-fill me-1"></i>Ver detalle
                    </a>
                    <?php if ($isAdmin && !in_array($ev['status'], ['cancelled', 'finished'])): ?>
                    <a href="/torneos/<?= $ev['id'] ?>/editar"
                       class="btn-jp btn-jp-secondary btn-jp-sm btn-jp-icon" title="Editar">
                        <i class="bi bi-pencil-fill"></i>
                    </a>
                    <?php endif; ?>
                </div>

            </div>
        </div>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<?= $this->endSection() ?>
