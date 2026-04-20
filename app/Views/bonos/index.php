<?= $this->extend('layouts/app') ?>

<?php
helper('avatar');
$pageTitle    = 'Bonos';
$pageSubtitle = 'Gestión de bonos y membresías';
$errorBonoActivo = session()->getFlashdata('error_bono_activo');
?>

<?= $this->section('page_content') ?>

<?php if (session()->getFlashdata('success')): ?>
<div class="alert-jp success mb-3"><i class="bi bi-check-circle-fill me-2"></i><?= esc(session()->getFlashdata('success')) ?></div>
<?php endif; ?>
<?php if (session()->getFlashdata('error')): ?>
<div class="alert-jp error mb-3"><i class="bi bi-exclamation-triangle-fill me-2"></i><?= esc(session()->getFlashdata('error')) ?></div>
<?php endif; ?>

<!-- Cabecera -->
<div class="page-header">
    <div class="page-header-text">
        <h2>Bonos</h2>
        <p>Membresías y bonos de entrenamiento</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= base_url('configuracion?section=facturacion') ?>" class="btn-jp btn-jp-secondary">
            <i class="bi bi-gear"></i> Tipos de bono
        </a>
        <button class="btn-jp btn-jp-primary" onclick="openModalEmitir()">
            <i class="bi bi-plus-lg"></i> Nuevo bono
        </button>
    </div>
</div>

<!-- Métricas -->
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="metric-card">
            <div class="metric-card-header">
                <span class="metric-label">Bonos activos</span>
                <div class="metric-icon blue"><i class="bi bi-ticket-perforated-fill"></i></div>
            </div>
            <div class="metric-value"><?= (int)($stats['active'] ?? 0) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="metric-card">
            <div class="metric-card-header">
                <span class="metric-label">Emitidos este mes</span>
                <div class="metric-icon green"><i class="bi bi-bag-check-fill"></i></div>
            </div>
            <div class="metric-value"><?= (int)($stats['issued_this_month'] ?? 0) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="metric-card">
            <div class="metric-card-header">
                <span class="metric-label">Por vencer (7 días)</span>
                <div class="metric-icon orange"><i class="bi bi-clock-fill"></i></div>
            </div>
            <div class="metric-value"><?= (int)($stats['expiring_soon'] ?? 0) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="metric-card">
            <div class="metric-card-header">
                <span class="metric-label">Sin asignar</span>
                <div class="metric-icon" style="background:#7c3aed22;color:#7c3aed"><i class="bi bi-person-dash-fill"></i></div>
            </div>
            <div class="metric-value"><?= (int)($stats['unassigned'] ?? 0) ?></div>
        </div>
    </div>
</div>

<!-- Tabla de bonos -->
<div class="card-jp">
    <div class="card-jp-header">
        <span class="card-jp-title">
            <i class="bi bi-ticket-perforated-fill me-2" style="color:var(--accent)"></i>
            Bonos emitidos
        </span>
        <div class="calendar-view-tabs">
            <a href="?filtro=activos"      class="calendar-view-tab <?= ($filtro ?? 'activos') === 'activos'      ? 'active' : '' ?>" style="text-decoration:none">Activos</a>
            <a href="?filtro=sin-asignar"  class="calendar-view-tab <?= ($filtro ?? '') === 'sin-asignar'  ? 'active' : '' ?>" style="text-decoration:none">Sin asignar</a>
            <a href="?filtro=vencidos"     class="calendar-view-tab <?= ($filtro ?? '') === 'vencidos'     ? 'active' : '' ?>" style="text-decoration:none">Vencidos</a>
            <a href="?filtro=todos"        class="calendar-view-tab <?= ($filtro ?? '') === 'todos'        ? 'active' : '' ?>" style="text-decoration:none">Todos</a>
        </div>
    </div>

    <?php if (empty($bonos)): ?>
    <div class="empty-state">
        <i class="bi bi-ticket-perforated"></i>
        <h2>Sin bonos</h2>
        <p>No hay bonos
            <?php
            echo match($filtro ?? 'activos') {
                'vencidos'    => 'vencidos',
                'sin-asignar' => 'sin asignar',
                'todos'       => 'registrados',
                default       => 'activos',
            };
            ?>.
        </p>
        <?php if (($filtro ?? 'activos') !== 'vencidos'): ?>
        <button onclick="openModalEmitir()" class="btn-jp btn-jp-primary">
            <i class="bi bi-plus-lg"></i> Crear primer bono
        </button>
        <?php endif; ?>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table style="width:100%;border-collapse:collapse;font-size:13px">
            <thead>
                <tr>
                    <th style="padding:10px 12px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);border-bottom:2px solid var(--border)">Jugador</th>
                    <th style="padding:10px 12px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);border-bottom:2px solid var(--border)">Tipo de bono</th>
                    <th style="padding:10px 12px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);border-bottom:2px solid var(--border)">Sesiones</th>
                    <th style="padding:10px 12px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);border-bottom:2px solid var(--border)">Inicio</th>
                    <th style="padding:10px 12px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);border-bottom:2px solid var(--border)">Caduca</th>
                    <th style="padding:10px 12px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);border-bottom:2px solid var(--border)">Estado</th>
                    <th style="padding:10px 12px;border-bottom:2px solid var(--border)"></th>
                </tr>
            </thead>
            <tbody>
            <?php
            $today = date('Y-m-d');
            foreach ($bonos as $b):
                $remaining   = (int)$b['sessions_remaining'];
                $total       = (int)$b['sessions_total'];
                $pct         = $total > 0 ? round(($remaining / $total) * 100) : 0;
                $expired     = !empty($b['expires_at']) && $b['expires_at'] < $today;
                $isActive    = $remaining > 0 && !$expired;
                $unassigned  = empty($b['player_id']);
                $statusCls   = $isActive ? 'active' : 'inactive';
                $statusLbl   = $unassigned ? 'Sin asignar' : ($isActive ? 'Activo' : ($remaining === 0 ? 'Agotado' : 'Vencido'));
                $barColor    = $pct > 50 ? 'var(--success)' : ($pct > 20 ? 'var(--warning)' : 'var(--danger)');
            ?>
            <tr style="border-bottom:1px solid var(--border)">
                <td style="padding:12px;vertical-align:middle">
                    <?php if ($unassigned): ?>
                    <div style="display:flex;align-items:center;gap:10px">
                        <div style="width:36px;height:36px;border-radius:50%;background:#7c3aed22;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                            <i class="bi bi-person-dash-fill" style="color:#7c3aed;font-size:14px"></i>
                        </div>
                        <div style="font-size:13px;color:var(--text-muted);font-style:italic">Sin asignar</div>
                    </div>
                    <?php else: ?>
                    <div style="display:flex;align-items:center;gap:10px">
                        <?= avatar_html($b['player_avatar'] ?? null, $b['player_name'], 'td-avatar') ?>
                        <div>
                            <div style="font-weight:600;font-size:13px;color:var(--text-h)"><?= esc($b['player_name']) ?></div>
                            <div style="font-size:11px;color:var(--text-muted)"><?= esc($b['player_email']) ?></div>
                        </div>
                    </div>
                    <?php endif; ?>
                </td>
                <td style="padding:12px;vertical-align:middle;font-weight:600"><?= esc($b['bono_name']) ?></td>
                <td style="padding:12px;vertical-align:middle">
                    <div style="font-weight:700;color:var(--text-h)"><?= $remaining ?> / <?= $total ?></div>
                    <div style="margin-top:4px;height:5px;background:var(--border);border-radius:3px;width:80px">
                        <div style="height:5px;border-radius:3px;background:<?= $barColor ?>;width:<?= $pct ?>%"></div>
                    </div>
                </td>
                <td style="padding:12px;vertical-align:middle;font-size:12px;color:var(--text-muted)"><?= date('d/m/Y', strtotime($b['start_date'])) ?></td>
                <td style="padding:12px;vertical-align:middle;font-size:12px;color:<?= $expired ? 'var(--danger)' : 'var(--text-muted)' ?>">
                    <?= !empty($b['expires_at']) ? date('d/m/Y', strtotime($b['expires_at'])) : '—' ?>
                </td>
                <td style="padding:12px;vertical-align:middle">
                    <?php if ($unassigned): ?>
                    <span class="badge-status" style="background:#7c3aed22;color:#7c3aed;border:1px solid #7c3aed44"><?= $statusLbl ?></span>
                    <?php else: ?>
                    <span class="badge-status <?= $statusCls ?>"><?= $statusLbl ?></span>
                    <?php endif; ?>
                </td>
                <td style="padding:12px;vertical-align:middle">
                    <a href="<?= base_url('bonos/' . $b['id']) ?>" class="btn-jp btn-jp-secondary btn-jp-sm">
                        <i class="bi bi-eye"></i>
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>


<!-- ── Modal crear / emitir bono ──────────────────────────────── -->
<div id="modalEmitirBono" class="bono-modal-overlay d-none">
    <div class="bono-modal">
        <div class="bono-modal-header">
            <span><i class="bi bi-ticket-perforated-fill me-2" style="color:var(--accent)"></i>Nuevo bono</span>
            <button onclick="closeModalEmitir()"><i class="bi bi-x-lg"></i></button>
        </div>
        <form action="<?= base_url('bonos/store') ?>" method="post">
            <?= csrf_field() ?>
            <div class="bono-modal-body">
                <div class="row g-3">

                    <div class="col-12">
                        <label class="form-label">Tipo de bono <span style="color:var(--danger)">*</span></label>
                        <select name="bono_type_id" class="form-control-jp" required>
                            <option value="">— Selecciona un tipo —</option>
                            <?php foreach ($bonoTypes as $bt): ?>
                            <option value="<?= $bt['id'] ?>">
                                <?= esc($bt['name']) ?> — <?= $bt['sessions'] ?> sesiones
                            </option>
                            <?php endforeach; ?>
                        </select>
                        <?php if (empty($bonoTypes)): ?>
                        <p style="font-size:12px;color:var(--warning);margin:4px 0 0">
                            No hay tipos de bono activos. Créalos en <a href="<?= base_url('configuracion?section=facturacion') ?>">Configuración</a>.
                        </p>
                        <?php endif; ?>
                    </div>

                    <div class="col-12">
                        <label class="form-label">Jugador <span style="color:var(--text-muted);font-weight:400;font-size:12px">(opcional — puedes asignarlo después)</span></label>
                        <select name="player_id" id="selectPlayer" class="form-control-jp" onchange="checkBonoActivo(this.value)">
                            <option value="">— Sin asignar —</option>
                            <?php foreach ($players as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= esc($p['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div id="alertaBonoActivo" style="display:none;margin-top:8px;padding:10px 12px;background:var(--warning-light);border-radius:6px;font-size:13px;color:#92400e;border:1px solid #fcd34d">
                            <i class="bi bi-exclamation-triangle-fill me-2"></i>
                            <strong>Este jugador ya tiene un bono activo.</strong>
                            <span id="alertaBonoDetalles"></span>
                            <br><small>Debes esperar a que lo agote o caduque antes de emitir uno nuevo.</small>
                        </div>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label">Fecha de inicio</label>
                        <input type="date" name="start_date" class="form-control-jp" value="<?= date('Y-m-d') ?>">
                    </div>

                    <div class="col-12">
                        <label class="form-label">Notas (opcional)</label>
                        <textarea name="notes" class="form-control-jp" rows="2" placeholder="Observaciones sobre este bono…"></textarea>
                    </div>

                </div>
            </div>
            <div style="display:flex;justify-content:flex-end;gap:8px;padding:16px 20px;border-top:1px solid var(--border)">
                <button type="button" class="btn-jp btn-jp-secondary" onclick="closeModalEmitir()">Cancelar</button>
                <button type="submit" id="btnEmitir" class="btn-jp btn-jp-primary">
                    <i class="bi bi-check-lg me-1"></i>Crear bono
                </button>
            </div>
        </form>
    </div>
</div>

<!-- ── Modal alerta bono activo (respuesta del servidor) ────── -->
<?php if ($errorBonoActivo): ?>
<div id="modalBonoActivoServer" class="bono-modal-overlay">
    <div class="bono-modal" style="max-width:420px">
        <div class="bono-modal-header">
            <span><i class="bi bi-exclamation-triangle-fill me-2" style="color:var(--warning)"></i>Bono activo existente</span>
            <button onclick="document.getElementById('modalBonoActivoServer').classList.add('d-none')"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="bono-modal-body">
            <p>Este jugador ya tiene un <strong>bono activo</strong> con sesiones disponibles.</p>
            <p style="color:var(--text-muted);font-size:13px">Un jugador solo puede tener un bono activo a la vez. Espera a que lo agote o caduque antes de emitir uno nuevo.</p>
        </div>
        <div style="display:flex;justify-content:flex-end;padding:16px 20px;border-top:1px solid var(--border)">
            <button class="btn-jp btn-jp-primary" onclick="document.getElementById('modalBonoActivoServer').classList.add('d-none')">Entendido</button>
        </div>
    </div>
</div>
<?php endif; ?>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<style>
.bono-modal-overlay {
    position:fixed;inset:0;background:rgba(0,0,0,.55);z-index:1050;
    display:flex;align-items:center;justify-content:center;padding:16px;
}
.bono-modal-overlay.d-none { display:none !important; }
.bono-modal {
    background:var(--bg-card);border:1px solid var(--border);border-radius:12px;
    width:100%;max-width:520px;max-height:90vh;display:flex;flex-direction:column;
    box-shadow:0 20px 60px rgba(0,0,0,.3);
}
.bono-modal-header {
    display:flex;align-items:center;justify-content:space-between;
    padding:16px 20px;border-bottom:1px solid var(--border);
    font-size:15px;font-weight:700;color:var(--text-h);
}
.bono-modal-header button {
    background:none;border:none;color:var(--text-muted);cursor:pointer;font-size:18px;
    width:32px;height:32px;display:flex;align-items:center;justify-content:center;border-radius:6px;
}
.bono-modal-header button:hover { background:var(--bg-app);color:var(--text-h); }
.bono-modal-body { padding:20px;overflow-y:auto;flex:1; }
</style>
<script>
const BONOS_CSRF_NAME = '<?= csrf_token() ?>';
const BONOS_CSRF_HASH = '<?= csrf_hash() ?>';

function openModalEmitir() {
    document.getElementById('modalEmitirBono').classList.remove('d-none');
    document.body.style.overflow = 'hidden';
}
function closeModalEmitir() {
    document.getElementById('modalEmitirBono').classList.add('d-none');
    document.body.style.overflow = '';
}
document.addEventListener('keydown', e => { if(e.key === 'Escape') closeModalEmitir(); });

async function checkBonoActivo(playerId) {
    const alertEl   = document.getElementById('alertaBonoActivo');
    const detalles  = document.getElementById('alertaBonoDetalles');
    const btnEmitir = document.getElementById('btnEmitir');

    if (!playerId) {
        alertEl.style.display = 'none';
        btnEmitir.disabled = false;
        return;
    }

    const fd = new FormData();
    fd.append(BONOS_CSRF_NAME, BONOS_CSRF_HASH);
    fd.append('player_id', playerId);

    try {
        const res  = await fetch('<?= base_url('bonos/check-active') ?>', { method:'POST', body:fd });
        const data = await res.json();
        if (data.has_active && data.bono) {
            const b = data.bono;
            detalles.textContent = ` (${b.bono_name ?? ''}: ${b.sessions_remaining} sesiones restantes)`;
            alertEl.style.display = 'block';
            btnEmitir.disabled = true;
        } else {
            alertEl.style.display = 'none';
            btnEmitir.disabled = false;
        }
    } catch(e) {
        alertEl.style.display = 'none';
        btnEmitir.disabled = false;
    }
}
</script>
<?= $this->endSection() ?>
