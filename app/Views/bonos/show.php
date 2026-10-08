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
$statusLbl  = $unassigned ? 'Sin asignar' : ($isActive ? 'Con saldo' : ($remaining === 0 ? 'Agotado' : 'Vencido'));
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
                <div style="font-size:16px;font-weight:700;color:var(--text-h);margin-top:12px"><a href="<?= base_url('alumnos/' . (int) $bono['player_id']) ?>" class="row-link-anchor" title="Ver perfil del alumno"><?= esc($bono['player_name']) ?></a></div>
                <div style="font-size:13px;color:var(--text-muted)"><?= esc($bono['player_email']) ?></div>
                <?php if (!empty($overbooked)): ?>
                <div style="margin-top:8px;display:inline-block;background:#fef3c7;border:1px solid #fde68a;color:#92400e;border-radius:8px;padding:6px 10px;font-size:12px;font-weight:600">
                    <i class="bi bi-exclamation-triangle-fill me-1"></i>
                    <?= (int) $overbooked['over'] ?> <?= $overbooked['over'] === 1 ? 'clase' : 'clases' ?> por encima del saldo
                    <span style="font-weight:400">(<?= (int) $overbooked['scheduled'] ?> programadas<?= $overbooked['debts'] ? ' + ' . (int) $overbooked['debts'] . ' dadas sin bono' : '' ?>, saldo <?= (int) $overbooked['balance'] ?>)</span>
                </div>
                <?php endif; ?>
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

        <?php if (!$unassigned && !empty($debts)): ?>
        <!-- Deudas abiertas del alumno (TICKET-013) -->
        <div class="card-jp" style="border:1px solid #fecaca">
            <div class="card-jp-header">
                <span class="card-jp-title" style="color:#b91c1c"><i class="bi bi-receipt me-2"></i>Clases dadas sin bono (<?= count($debts) ?>)</span>
                <a href="<?= base_url('bonos/deudas') ?>" class="btn-jp btn-jp-secondary btn-jp-sm" style="text-decoration:none">Gestionar</a>
            </div>
            <div class="card-jp-body" style="font-size:13px">
                <?php foreach ($debts as $d): ?>
                <div style="display:flex;justify-content:space-between;padding:4px 0;border-bottom:1px solid var(--border)">
                    <span><?= esc($d['title']) ?></span>
                    <span style="color:var(--text-muted)"><?= date('d/m/Y', strtotime($d['session_date'])) ?></span>
                </div>
                <?php endforeach; ?>
                <div style="font-size:12px;color:var(--text-muted);margin:8px 0 10px">
                    No se descuentan solas: tú decides con qué bono saldarlas. Si el alumno tiene varios bonos, hazlo desde el que quieras (también puedes elegir clase a clase en «Gestionar»).
                </div>
                <?php $canSettle = $isActive; $nSettle = min(count($debts), $remaining); ?>
                <form action="<?= base_url('bonos/' . (int) $bono['id'] . '/saldar-deudas') ?>" method="post" style="margin:0"
                      data-ru-confirm="¿Saldar <?= (int) $nSettle ?> clase(s) con este bono?"
                      data-ru-confirm-desc="Se descuenta 1 sesión de «<?= esc($bono['bono_name'], 'attr') ?>» por cada clase, empezando por la más antigua. Queda registrado y puedes devolver la sesión después."
                      data-ru-confirm-label="Saldar con este bono">
                    <?= csrf_field() ?>
                    <button type="submit" class="btn-jp btn-jp-primary btn-jp-sm" <?= $canSettle ? '' : 'disabled title="Este bono no tiene saldo o está caducado"' ?>>
                        <i class="bi bi-ticket-perforated-fill me-1"></i>Saldar <?= (int) $nSettle ?> clase(s) con este bono
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?>

        <?php if (!$unassigned): ?>
        <!-- Próximas clases del alumno: clic en una fila para abrirla -->
        <?php
        $upItems  = $upcoming['items'] ?? [];
        $upTotal  = (int) ($upcoming['total'] ?? 0);
        $dowShort = [1 => 'Lun', 2 => 'Mar', 3 => 'Mié', 4 => 'Jue', 5 => 'Vie', 6 => 'Sáb', 7 => 'Dom'];
        $covChip  = [
            'covered'   => ['Cubierta',        '#059669', '#d1fae5'],
            'at_risk'   => ['Caduca antes',    '#92400e', '#fef3c7'],
            'uncovered' => ['Sin bono',        '#b45309', '#ffedd5'],
        ];
        ?>
        <div class="card-jp">
            <div class="card-jp-header">
                <span class="card-jp-title"><i class="bi bi-calendar-event-fill me-2" style="color:var(--accent)"></i>Próximas clases del alumno<?= $upTotal ? ' (' . $upTotal . ')' : '' ?></span>
                <span style="font-size:11px;color:var(--text-muted)">con todos sus bonos · el bono se elige al pasar lista</span>
            </div>
            <?php if (empty($upItems)): ?>
            <div class="card-jp-body">
                <p style="color:var(--text-muted);font-size:13px;margin:0">Este alumno no tiene clases próximas programadas.</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table class="table-jp" style="font-size:13px">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Clase</th>
                            <th>Entrenador</th>
                            <th>Bono</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($upItems as $c):
                        $ts   = strtotime($c['session_date']);
                        $chip = $covChip[$c['bono_coverage'] ?? ''] ?? null;
                    ?>
                    <tr class="row-link" data-href="<?= base_url('clases/' . (int) $c['id']) ?>" style="cursor:pointer">
                        <td style="white-space:nowrap">
                            <div style="font-weight:600;color:var(--text-h)"><?= $dowShort[(int) date('N', $ts)] ?> <?= date('d/m/Y', $ts) ?></div>
                            <div style="font-size:11px;color:var(--text-muted)"><?= substr($c['start_time'], 0, 5) ?>–<?= substr($c['end_time'], 0, 5) ?></div>
                        </td>
                        <td>
                            <a href="<?= base_url('clases/' . (int) $c['id']) ?>" class="row-link-anchor" style="font-weight:600"><?= esc($c['title']) ?></a>
                            <div style="font-size:11px;color:var(--text-muted)">
                                <?= ($c['class_format'] ?? '') === 'pareja' ? 'Pareja' : 'Individual' ?>
                                <?php $place = $c['location_name'] ?: ($c['location_custom'] ?? ''); if ($place): ?> · <?= esc($place) ?><?php endif; ?>
                            </div>
                        </td>
                        <td style="color:var(--text-body)"><?= !empty($c['coach_names']) ? esc($c['coach_names']) : '<span style="color:var(--text-muted)">Sin asignar</span>' ?></td>
                        <td>
                            <?php if ($chip): ?>
                            <span style="display:inline-block;padding:2px 8px;border-radius:10px;font-size:11px;font-weight:700;color:<?= $chip[1] ?>;background:<?= $chip[2] ?>"><?= $chip[0] ?></span>
                            <?php else: ?>
                            <span style="color:var(--text-muted)">—</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php if ($upTotal > count($upItems)): ?>
            <div style="padding:10px 20px;border-top:1px solid var(--border);font-size:12px;color:var(--text-muted)">
                Mostrando las próximas <?= count($upItems) ?> de <?= $upTotal ?> clases.
            </div>
            <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if (!$unassigned && !empty($bono['expires_at'])): ?>
        <!-- Ampliar caducidad (TICKET-013) -->
        <?php $daysLeft = (int) floor((strtotime($bono['expires_at']) - strtotime($today)) / 86400); ?>
        <div class="card-jp" <?= ($daysLeft <= 7) ? 'style="border:1px solid #fde68a"' : '' ?>>
            <div class="card-jp-header">
                <span class="card-jp-title"><i class="bi bi-calendar-plus-fill me-2" style="color:#d97706"></i>Ampliar caducidad</span>
            </div>
            <div class="card-jp-body">
                <p style="font-size:13px;color:var(--text-muted);margin:0 0 10px">
                    <?php if ($expired): ?>
                        Este bono caducó el <?= date('d/m/Y', strtotime($bono['expires_at'])) ?>. Si lo amplías, los días cuentan desde hoy. Ejemplo: «+15 días» = caduca dentro de 15 días.
                    <?php elseif ($daysLeft <= 7): ?>
                        <strong style="color:#92400e">Caduca en <?= max(0, $daysLeft) ?> día<?= $daysLeft === 1 ? '' : 's' ?></strong> (<?= date('d/m/Y', strtotime($bono['expires_at'])) ?>).
                    <?php else: ?>
                        Caduca el <?= date('d/m/Y', strtotime($bono['expires_at'])) ?>.
                    <?php endif; ?>
                    Puedes darle más días si lo necesita. Queda anotado en el registro del bono y se avisa al alumno.
                </p>
                <form action="<?= base_url('bonos/' . $bono['id'] . '/ampliar') ?>" method="post" class="d-flex flex-wrap gap-2 align-items-center" id="formExtend">
                    <?= csrf_field() ?>
                    <?php foreach (\App\Services\BonoControlService::EXTEND_PRESETS as $d): ?>
                    <button type="submit" name="mode" value="<?= $d ?>" class="btn-jp btn-jp-secondary btn-jp-sm">+<?= $d ?> días</button>
                    <?php endforeach; ?>
                    <span style="font-size:12px;color:var(--text-muted)">o</span>
                    <input type="date" name="custom_date" class="form-control-jp" style="width:auto;padding:5px 8px"
                           min="<?= date('Y-m-d', strtotime(max($bono['expires_at'], $today) . ' +1 day')) ?>" aria-label="Fecha personalizada">
                    <button type="submit" name="mode" value="custom" class="btn-jp btn-jp-primary btn-jp-sm">Fecha personalizada</button>
                </form>
            </div>
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
                <span class="card-jp-title"><i class="bi bi-clock-history me-2" style="color:var(--text-muted)"></i>Todos los bonos del alumno (<?= count($history) ?>)</span>
            </div>
            <?php if (empty($history)): ?>
            <div class="card-jp-body">
                <p style="color:var(--text-muted);font-size:13px;margin:0">Sin historial.</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table style="width:100%;border-collapse:collapse;font-size:12px" data-jp-list="bono-historial">
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
                        $hLbl     = $hActive ? 'Con saldo' : ((int)$h['sessions_remaining'] === 0 ? 'Agotado' : 'Vencido');
                        $isCurrent = (int)$h['id'] === (int)$bono['id'];
                    ?>
                    <tr style="border-bottom:1px solid var(--border);<?= $isCurrent ? 'background:var(--accent-light)' : '' ?>">
                        <td style="padding:8px 12px;font-weight:<?= $isCurrent ? '700' : '500' ?>">
                            <?php if ($isCurrent): ?><?= esc($h['bono_name']) ?> <span style="font-size:10px;color:var(--text-muted)">(este)</span>
                            <?php else: ?><a href="<?= base_url('bonos/' . (int) $h['id']) ?>" class="row-link-anchor"><?= esc($h['bono_name']) ?></a><?php endif; ?>
                        </td>
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

        <?php if (!$unassigned): ?>
        <!-- Registro de movimientos del alumno (TICKET-013) -->
        <div class="card-jp">
            <div class="card-jp-header">
                <span class="card-jp-title"><i class="bi bi-journal-text me-2" style="color:var(--text-muted)"></i>Registro de movimientos</span>
            </div>
            <?php if (empty($movements)): ?>
            <div class="card-jp-body">
                <p style="color:var(--text-muted);font-size:13px;margin:0">Todavía no hay movimientos. Aquí verás cuándo se emitió el bono, cada clase descontada o devuelta, las ampliaciones de fecha y los avisos.</p>
            </div>
            <?php else: ?>
            <div class="table-responsive">
                <table style="width:100%;border-collapse:collapse;font-size:12px">
                    <thead>
                        <tr>
                            <?php foreach (['Fecha', 'Movimiento', 'Bono', 'Sesiones', 'Detalle', 'Por'] as $th): ?>
                            <th style="padding:8px 12px;color:var(--text-muted);font-weight:700;text-transform:uppercase;letter-spacing:.4px;border-bottom:1px solid var(--border);text-align:left"><?= $th ?></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($movements as $m):
                        [$mLabel, $mIcon, $mTone] = \App\Services\BonoLedgerService::label($m['type']);
                        $mColor = $mTone === 'ok' ? '#047857' : ($mTone === 'risk' ? '#92400e' : 'var(--text-h)');
                        $detail = trim(($m['session_title'] ? $m['session_title'] . ' (' . date('d/m', strtotime($m['session_date'])) . ')' : '') . ($m['note'] ? ' ' . $m['note'] : ''));
                    ?>
                    <tr style="border-bottom:1px solid var(--border)">
                        <td style="padding:8px 12px;color:var(--text-muted);white-space:nowrap"><?= date('d/m/Y H:i', strtotime($m['created_at'])) ?></td>
                        <td style="padding:8px 12px;font-weight:600;color:<?= $mColor ?>"><i class="bi <?= $mIcon ?> me-1"></i><?= esc($mLabel) ?></td>
                        <td style="padding:8px 12px;color:var(--text-muted)"><?= !empty($m['bono_name']) ? esc($m['bono_name']) . ' #' . (int) $m['bono_id'] : '—' ?><?= !empty($m['related_bono_id']) ? ' <span title="Bono relacionado" style="font-size:10px">↔ #' . (int) $m['related_bono_id'] . '</span>' : '' ?></td>
                        <td style="padding:8px 12px"><?= (int) $m['delta'] === 0 ? '—' : ((int) $m['delta'] > 0 ? '+' : '') . (int) $m['delta'] ?></td>
                        <td style="padding:8px 12px;color:var(--text-muted)"><?= esc($detail) ?></td>
                        <td style="padding:8px 12px;color:var(--text-muted)"><?= esc($m['actor_name'] ?? 'Sistema') ?></td>
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
