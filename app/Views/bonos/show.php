<?= $this->extend('layouts/app') ?>

<?php
helper('avatar');
$pageTitle    = 'Detalle de bono';
$pageSubtitle = 'Información del bono emitido';

$today      = date('Y-m-d');
$remaining  = (int)$bono['sessions_remaining'];
$total      = (int)$bono['sessions_total'];
$pct        = $total > 0 ? round(($remaining / $total) * 100) : 0;
$expired    = !empty($bono['expires_at']) && $bono['expires_at'] < $today;
$isActive   = $remaining > 0 && !$expired;
$unassigned = empty($bono['player_id']);
$statusLbl  = $unassigned ? 'Sin asignar' : ($isActive ? 'Activo' : ($remaining === 0 ? 'Agotado' : 'Vencido'));
$statusCls  = $unassigned ? '' : ($isActive ? 'active' : 'inactive');
$barColor   = $pct > 50 ? 'var(--success)' : ($pct > 20 ? 'var(--warning)' : 'var(--danger)');
?>

<?= $this->section('page_content') ?>

<?php if (session()->getFlashdata('success')): ?>
<div class="alert-jp success mb-3"><i class="bi bi-check-circle-fill me-2"></i><?= esc(session()->getFlashdata('success')) ?></div>
<?php endif; ?>
<?php if (session()->getFlashdata('error')): ?>
<div class="alert-jp error mb-3"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif; ?>

<div class="page-header">
    <a href="<?= base_url('bonos') ?>" class="btn-jp btn-jp-secondary">
        <i class="bi bi-arrow-left"></i> Volver
    </a>
</div>

<div class="row g-3">

    <!-- Estado del bono -->
    <div class="col-12 col-lg-4">
        <div class="card-jp">
            <div class="card-jp-body text-center py-4">
                <?php if ($unassigned): ?>
                <div style="width:80px;height:80px;border-radius:50%;background:#7c3aed22;display:flex;align-items:center;justify-content:center;margin:0 auto">
                    <i class="bi bi-person-dash-fill" style="color:#7c3aed;font-size:32px"></i>
                </div>
                <div style="font-size:16px;font-weight:700;color:var(--text-h);margin-top:12px">Sin jugador asignado</div>
                <div style="font-size:13px;color:var(--text-muted)">Asigna un jugador desde abajo</div>
                <?php else: ?>
                <?= avatar_html($bono['player_avatar'] ?? null, $bono['player_name'], 'profile-avatar-lg') ?>
                <div style="font-size:16px;font-weight:700;color:var(--text-h);margin-top:12px"><?= esc($bono['player_name']) ?></div>
                <div style="font-size:13px;color:var(--text-muted)"><?= esc($bono['player_email']) ?></div>
                <?php endif; ?>

                <?php if ($unassigned): ?>
                <span class="badge-status mt-2 d-inline-block" style="background:#7c3aed22;color:#7c3aed;border:1px solid #7c3aed44"><?= $statusLbl ?></span>
                <?php else: ?>
                <span class="badge-status <?= $statusCls ?> mt-2 d-inline-block"><?= $statusLbl ?></span>
                <?php endif; ?>
            </div>
            <div class="card-jp-body">

                <!-- Barra de progreso de sesiones -->
                <div style="margin-bottom:16px">
                    <div style="display:flex;justify-content:space-between;margin-bottom:6px">
                        <span style="font-size:12px;color:var(--text-muted);text-transform:uppercase;font-weight:700;letter-spacing:.5px">Sesiones restantes</span>
                        <span style="font-size:14px;font-weight:800;color:var(--text-h)"><?= $remaining ?> / <?= $total ?></span>
                    </div>
                    <div style="height:8px;background:var(--border);border-radius:4px">
                        <div style="height:8px;border-radius:4px;background:<?= $barColor ?>;width:<?= $pct ?>%;transition:width .3s"></div>
                    </div>
                </div>

                <div class="d-flex flex-column gap-3">
                    <div class="d-flex justify-content-between">
                        <span style="font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px">Tipo de bono</span>
                        <span style="font-size:13px;font-weight:600;color:var(--text-h)"><?= esc($bono['bono_name']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span style="font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px">Inicio</span>
                        <span style="font-size:13px;font-weight:600;color:var(--text-h)"><?= date('d/m/Y', strtotime($bono['start_date'])) ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span style="font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px">Caduca</span>
                        <span style="font-size:13px;font-weight:600;color:<?= $expired ? 'var(--danger)' : 'var(--text-h)' ?>">
                            <?= !empty($bono['expires_at']) ? date('d/m/Y', strtotime($bono['expires_at'])) : '—' ?>
                        </span>
                    </div>
                    <?php if (!empty($bono['created_by_name'])): ?>
                    <div class="d-flex justify-content-between">
                        <span style="font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px">Emitido por</span>
                        <span style="font-size:13px;font-weight:600;color:var(--text-h)"><?= esc($bono['created_by_name']) ?></span>
                    </div>
                    <?php endif; ?>
                    <div class="d-flex justify-content-between">
                        <span style="font-size:12px;color:var(--text-muted);text-transform:uppercase;letter-spacing:.5px">Fecha emisión</span>
                        <span style="font-size:13px;font-weight:600;color:var(--text-h)"><?= date('d/m/Y', strtotime($bono['created_at'])) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Panel derecho -->
    <div class="col-12 col-lg-8 d-flex flex-column gap-3">

        <!-- Asignar jugador (solo si está sin asignar) -->
        <?php if ($unassigned): ?>
        <div class="card-jp" style="border:2px solid #7c3aed44">
            <div class="card-jp-header">
                <span class="card-jp-title" style="color:#7c3aed">
                    <i class="bi bi-person-plus-fill me-2"></i>Asignar jugador
                </span>
            </div>
            <form action="<?= base_url('bonos/' . $bono['id'] . '/assign') ?>" method="post">
                <?= csrf_field() ?>
                <div class="card-jp-body">
                    <p style="font-size:13px;color:var(--text-muted);margin:0 0 12px">
                        Este bono todavía no tiene jugador asignado. Selecciona uno para activarlo.
                    </p>
                    <div class="row g-3">
                        <div class="col-12 col-md-8">
                            <select name="player_id" class="form-control-jp" required>
                                <option value="">— Selecciona un jugador —</option>
                                <?php foreach ($players as $p): ?>
                                <option value="<?= $p['id'] ?>"><?= esc($p['name']) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-12 col-md-4">
                            <button type="submit" class="btn-jp w-100" style="background:#7c3aed;color:#fff;border:none;padding:10px 16px;border-radius:var(--radius-sm);font-weight:600;cursor:pointer">
                                <i class="bi bi-person-check-fill me-1"></i>Asignar
                            </button>
                        </div>
                    </div>
                </div>
            </form>
        </div>
        <?php endif; ?>

        <!-- Editar bono -->
        <div class="card-jp">
            <div class="card-jp-header">
                <span class="card-jp-title"><i class="bi bi-pencil-fill me-2" style="color:var(--accent)"></i>Editar bono</span>
            </div>
            <form action="<?= base_url('bonos/' . $bono['id'] . '/update') ?>" method="post">
                <?= csrf_field() ?>
                <div class="card-jp-body">
                    <div class="row g-3">
                        <div class="col-12 col-md-4">
                            <label class="form-label">Sesiones restantes</label>
                            <input type="number" name="sessions_remaining" class="form-control-jp"
                                   value="<?= $remaining ?>" min="0" max="<?= $total ?>">
                        </div>
                        <div class="col-12 col-md-4">
                            <label class="form-label">Fecha de caducidad</label>
                            <input type="date" name="expires_at" class="form-control-jp"
                                   value="<?= esc($bono['expires_at'] ?? '') ?>">
                        </div>
                        <div class="col-12">
                            <label class="form-label">Notas</label>
                            <textarea name="notes" class="form-control-jp" rows="2"><?= esc($bono['notes'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>
                <div style="padding:12px 20px;border-top:1px solid var(--border);display:flex;justify-content:flex-end;gap:8px">
                    <button type="submit" class="btn-jp btn-jp-primary btn-jp-sm">
                        <i class="bi bi-check-lg me-1"></i>Guardar cambios
                    </button>
                </div>
            </form>
        </div>

        <!-- Historial de bonos del jugador -->
        <?php if (!$unassigned): ?>
        <div class="card-jp">
            <div class="card-jp-header">
                <span class="card-jp-title"><i class="bi bi-clock-history me-2" style="color:var(--text-muted)"></i>Historial de bonos</span>
            </div>
            <?php if (empty($history)): ?>
            <div class="card-jp-body">
                <p style="color:var(--text-muted);font-size:13px;margin:0">Sin historial.</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table style="width:100%;border-collapse:collapse;font-size:12px">
                    <thead>
                        <tr>
                            <th style="padding:8px 12px;color:var(--text-muted);font-weight:700;text-transform:uppercase;letter-spacing:.4px;border-bottom:1px solid var(--border)">Tipo</th>
                            <th style="padding:8px 12px;color:var(--text-muted);font-weight:700;text-transform:uppercase;letter-spacing:.4px;border-bottom:1px solid var(--border)">Sesiones</th>
                            <th style="padding:8px 12px;color:var(--text-muted);font-weight:700;text-transform:uppercase;letter-spacing:.4px;border-bottom:1px solid var(--border)">Inicio</th>
                            <th style="padding:8px 12px;color:var(--text-muted);font-weight:700;text-transform:uppercase;letter-spacing:.4px;border-bottom:1px solid var(--border)">Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($history as $h):
                        $hExpired = !empty($h['expires_at']) && $h['expires_at'] < $today;
                        $hActive  = (int)$h['sessions_remaining'] > 0 && !$hExpired;
                        $hCls     = $hActive ? 'active' : 'inactive';
                        $hLbl     = $hActive ? 'Activo' : ((int)$h['sessions_remaining'] === 0 ? 'Agotado' : 'Vencido');
                        $isCurrent = (int)$h['id'] === (int)$bono['id'];
                    ?>
                    <tr style="border-bottom:1px solid var(--border);<?= $isCurrent ? 'background:var(--accent-light)' : '' ?>">
                        <td style="padding:8px 12px;font-weight:<?= $isCurrent ? '700' : '500' ?>"><?= esc($h['bono_name']) ?></td>
                        <td style="padding:8px 12px"><?= (int)$h['sessions_remaining'] ?> / <?= (int)$h['sessions_total'] ?></td>
                        <td style="padding:8px 12px;color:var(--text-muted)"><?= date('d/m/Y', strtotime($h['start_date'])) ?></td>
                        <td style="padding:8px 12px"><span class="badge-status <?= $hCls ?>" style="font-size:10px"><?= $hLbl ?></span></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Zona peligrosa -->
        <div class="card-jp" style="border:1px solid var(--danger-light)">
            <div class="card-jp-header">
                <span class="card-jp-title" style="color:var(--danger)"><i class="bi bi-exclamation-triangle-fill me-2"></i>Zona peligrosa</span>
            </div>
            <div class="card-jp-body">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <div style="font-size:13.5px;font-weight:600;color:var(--text-h)">Eliminar bono</div>
                        <div style="font-size:12px;color:var(--text-muted)">Esta acción no se puede deshacer.</div>
                    </div>
                    <form action="<?= base_url('bonos/' . $bono['id'] . '/delete') ?>" method="post">
                        <?= csrf_field() ?>
                        <button type="submit"
                                onclick="return confirm('¿Eliminar este bono definitivamente?')"
                                class="btn-jp btn-jp-sm"
                                style="background:var(--danger-light);color:var(--danger);border:1px solid var(--danger)">
                            <i class="bi bi-trash"></i> Eliminar
                        </button>
                    </form>
                </div>
            </div>
        </div>

    </div>
</div>

<?= $this->endSection() ?>
