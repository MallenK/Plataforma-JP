<?= $this->extend('layouts/app') ?>

<?php
helper('avatar');
$pageTitle    = 'Bonos';
$pageSubtitle = 'Gestión de bonos y membresías';
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
    <div class="d-flex gap-2">
        <button class="btn-jp btn-jp-secondary" onclick="openModalTipos()">
            <i class="bi bi-grid-fill"></i> Tipos de bono
        </button>
        <button class="btn-jp btn-jp-primary" onclick="openModalEmitir()">
            <i class="bi bi-plus-lg"></i> Asignar bono
        </button>
    </div>
</div>

<!-- Métricas -->
<div class="row g-3 mb-3">
    <div class="col-6 col-md-4 col-lg-2">
        <div class="metric-card">
            <div class="metric-card-header">
                <span class="metric-label">Bonos activos</span>
                <div class="metric-icon blue"><i class="bi bi-ticket-perforated-fill"></i></div>
            </div>
            <div class="metric-value"><?= (int)($stats['active'] ?? 0) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="metric-card">
            <div class="metric-card-header">
                <span class="metric-label">Emitidos este mes</span>
                <div class="metric-icon green"><i class="bi bi-bag-check-fill"></i></div>
            </div>
            <div class="metric-value"><?= (int)($stats['issued_this_month'] ?? 0) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="metric-card">
            <div class="metric-card-header">
                <span class="metric-label">Por vencer (7 días)</span>
                <div class="metric-icon orange"><i class="bi bi-clock-fill"></i></div>
            </div>
            <div class="metric-value"><?= (int)($stats['expiring_soon'] ?? 0) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="metric-card<?= (int)($stats['low_sessions'] ?? 0) > 0 ? ' alert-warning' : '' ?>" style="<?= (int)($stats['low_sessions'] ?? 0) > 0 ? 'border-color:#f59e0b' : '' ?>">
            <div class="metric-card-header">
                <span class="metric-label">Última sesión</span>
                <div class="metric-icon" style="background:#f59e0b22;color:#f59e0b"><i class="bi bi-exclamation-triangle-fill"></i></div>
            </div>
            <div class="metric-value"><?= (int)($stats['low_sessions'] ?? 0) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
        <div class="metric-card<?= (int)($stats['depleted'] ?? 0) > 0 ? ' alert-danger' : '' ?>" style="<?= (int)($stats['depleted'] ?? 0) > 0 ? 'border-color:var(--danger)' : '' ?>">
            <div class="metric-card-header">
                <span class="metric-label">Agotados</span>
                <div class="metric-icon" style="background:#dc262622;color:#dc2626"><i class="bi bi-x-octagon-fill"></i></div>
            </div>
            <div class="metric-value"><?= (int)($stats['depleted'] ?? 0) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-4 col-lg-2">
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
            <a href="?filtro=activos"       class="calendar-view-tab <?= ($filtro ?? 'activos') === 'activos'       ? 'active' : '' ?>" style="text-decoration:none">Activos</a>
            <a href="?filtro=casi-agotados" class="calendar-view-tab <?= ($filtro ?? '') === 'casi-agotados' ? 'active' : '' ?>" style="text-decoration:none">Última sesión</a>
            <a href="?filtro=agotados"      class="calendar-view-tab <?= ($filtro ?? '') === 'agotados'      ? 'active' : '' ?>" style="text-decoration:none">Agotados</a>
            <a href="?filtro=sin-asignar"   class="calendar-view-tab <?= ($filtro ?? '') === 'sin-asignar'   ? 'active' : '' ?>" style="text-decoration:none">Sin asignar</a>
            <a href="?filtro=vencidos"      class="calendar-view-tab <?= ($filtro ?? '') === 'vencidos'      ? 'active' : '' ?>" style="text-decoration:none">Vencidos</a>
            <a href="?filtro=todos"         class="calendar-view-tab <?= ($filtro ?? '') === 'todos'         ? 'active' : '' ?>" style="text-decoration:none">Todos</a>
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
                            No hay tipos de bono activos.
                            <button type="button" onclick="closeModalEmitir();openModalTipos();"
                                style="background:none;border:none;padding:0;color:var(--accent);cursor:pointer;font-size:12px;text-decoration:underline">
                                Crear tipos de bono
                            </button>
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
                            <i class="bi bi-info-circle-fill me-2"></i>
                            <strong>Este jugador ya tiene un bono activo.</strong>
                            <span id="alertaBonoDetalles"></span>
                            <br><small>El nuevo bono quedará <strong>encolado</strong> y se activará automáticamente cuando el actual se agote o caduque.</small>
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

<!-- ── Modal tipos de bono ──────────────────────────────────────── -->
<div id="modalTiposBono" class="bono-modal-overlay d-none">
    <div class="bono-modal" style="max-width:700px">
        <div class="bono-modal-header">
            <span><i class="bi bi-grid-fill me-2" style="color:var(--accent)"></i>Tipos de bono</span>
            <button onclick="closeModalTipos()"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="bono-modal-body" style="padding:0">
            <div style="padding:14px 20px;border-bottom:1px solid var(--border);display:flex;align-items:center;justify-content:space-between;gap:12px">
                <span style="font-size:13px;color:var(--text-muted)">Gestiona los tipos disponibles para asignar a jugadores</span>
                <button class="btn-jp btn-jp-primary btn-jp-sm" onclick="openModalFormTipo('create')" style="white-space:nowrap">
                    <i class="bi bi-plus-lg"></i> Nuevo tipo
                </button>
            </div>
            <div style="overflow-y:auto;max-height:420px">
                <?php if (empty($allBonoTypes)): ?>
                <div style="padding:48px 20px;text-align:center;color:var(--text-muted)">
                    <i class="bi bi-grid" style="font-size:2rem;display:block;margin-bottom:8px"></i>
                    Sin tipos de bono. Crea el primero con el botón de arriba.
                </div>
                <?php else: ?>
                <table id="tablaTiposBono" style="width:100%;border-collapse:collapse;font-size:13px">
                    <thead>
                        <tr>
                            <th style="padding:10px 16px;text-align:left;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);border-bottom:2px solid var(--border)">Nombre</th>
                            <th style="padding:10px 16px;text-align:center;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);border-bottom:2px solid var(--border)">Sesiones</th>
                            <th style="padding:10px 16px;text-align:center;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);border-bottom:2px solid var(--border)">Precio</th>
                            <th style="padding:10px 16px;text-align:center;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);border-bottom:2px solid var(--border)">Validez</th>
                            <th style="padding:10px 16px;text-align:center;font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:var(--text-muted);border-bottom:2px solid var(--border)">Estado</th>
                            <th style="padding:10px 16px;border-bottom:2px solid var(--border)"></th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($allBonoTypes as $t): ?>
                    <tr id="tipo-row-<?= $t['id'] ?>" style="border-bottom:1px solid var(--border)<?= !$t['active'] ? ';opacity:.55' : '' ?>">
                        <td style="padding:12px 16px;font-weight:600;color:var(--text-h)"><?= esc($t['name']) ?></td>
                        <td style="padding:12px 16px;text-align:center;color:var(--text-muted)"><?= (int)$t['sessions'] ?></td>
                        <td style="padding:12px 16px;text-align:center;color:var(--text-muted)"><?= number_format((float)$t['price'], 2) ?>€</td>
                        <td style="padding:12px 16px;text-align:center;color:var(--text-muted)"><?= (int)$t['validity_days'] ?>d</td>
                        <td style="padding:12px 16px;text-align:center">
                            <span class="tipo-badge-<?= $t['id'] ?> badge-status <?= $t['active'] ? 'active' : 'inactive' ?>">
                                <?= $t['active'] ? 'Activo' : 'Inactivo' ?>
                            </span>
                        </td>
                        <td style="padding:12px 16px;text-align:right;white-space:nowrap">
                            <button class="btn-jp btn-jp-secondary btn-jp-sm me-1"
                                data-id="<?= $t['id'] ?>"
                                data-name="<?= esc($t['name']) ?>"
                                onclick="openModalFormTipo('edit', this.dataset.id, this.dataset.name)"
                                title="Editar nombre">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn-jp btn-jp-secondary btn-jp-sm tipo-toggle-btn"
                                id="tipo-toggle-<?= $t['id'] ?>"
                                data-id="<?= $t['id'] ?>"
                                data-active="<?= (int)$t['active'] ?>"
                                onclick="toggleTipoBono(this.dataset.id, this.dataset.active)"
                                title="<?= $t['active'] ? 'Desactivar' : 'Activar' ?>"
                                style="<?= $t['active'] ? 'color:#dc2626;border-color:#dc262644' : 'color:#16a34a;border-color:#16a34a44' ?>">
                                <i class="bi bi-toggle-<?= $t['active'] ? 'on' : 'off' ?>"></i>
                            </button>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- ── Modal formulario crear / editar tipo ────────────────────── -->
<div id="modalFormTipo" class="bono-modal-overlay d-none" style="z-index:1060">
    <div class="bono-modal" style="max-width:440px">
        <div class="bono-modal-header">
            <span id="modalFormTipoTitle"><i class="bi bi-plus-circle-fill me-2" style="color:var(--accent)"></i>Nuevo tipo de bono</span>
            <button onclick="closeModalFormTipo()"><i class="bi bi-x-lg"></i></button>
        </div>
        <div class="bono-modal-body">
            <div id="tipoFormError" style="display:none;padding:10px 12px;background:#fee2e2;border-radius:6px;font-size:13px;color:#dc2626;border:1px solid #fca5a5;margin-bottom:12px"></div>
            <input type="hidden" id="tipoFormMode" value="create">
            <input type="hidden" id="tipoFormId" value="">
            <div class="row g-3">
                <div class="col-12">
                    <label class="form-label">Nombre <span style="color:var(--danger)">*</span></label>
                    <input type="text" id="tipoFormName" class="form-control-jp" placeholder="Ej: Bono 10 sesiones" maxlength="100">
                </div>
                <div class="col-12 col-md-6" id="tipoFieldSessions">
                    <label class="form-label">Sesiones <span style="color:var(--danger)">*</span></label>
                    <input type="number" id="tipoFormSessions" class="form-control-jp" min="1" value="10">
                </div>
                <div class="col-12 col-md-6" id="tipoFieldPrice">
                    <label class="form-label">Precio (€)</label>
                    <input type="number" id="tipoFormPrice" class="form-control-jp" min="0" step="0.01" value="0.00">
                </div>
                <div class="col-12" id="tipoFieldValidity">
                    <label class="form-label">Validez (días) <span style="color:var(--danger)">*</span></label>
                    <input type="number" id="tipoFormValidity" class="form-control-jp" min="1" value="90">
                </div>
            </div>
        </div>
        <div style="display:flex;justify-content:flex-end;gap:8px;padding:16px 20px;border-top:1px solid var(--border)">
            <button type="button" class="btn-jp btn-jp-secondary" onclick="closeModalFormTipo()">Cancelar</button>
            <button type="button" class="btn-jp btn-jp-primary" onclick="submitFormTipo()">
                <i class="bi bi-check-lg me-1"></i><span id="btnFormTipoLabel">Crear tipo</span>
            </button>
        </div>
    </div>
</div>

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
let BONOS_CSRF_NAME = '<?= csrf_token() ?>';
let BONOS_CSRF_HASH = '<?= csrf_hash() ?>';

function openModalEmitir() {
    document.getElementById('modalEmitirBono').classList.remove('d-none');
    document.body.style.overflow = 'hidden';
}
function closeModalEmitir() {
    document.getElementById('modalEmitirBono').classList.add('d-none');
    document.body.style.overflow = '';
}

function openModalTipos() {
    document.getElementById('modalTiposBono').classList.remove('d-none');
    document.body.style.overflow = 'hidden';
}
function closeModalTipos() {
    document.getElementById('modalTiposBono').classList.add('d-none');
    if (document.getElementById('modalFormTipo').classList.contains('d-none') &&
        document.getElementById('modalEmitirBono').classList.contains('d-none')) {
        document.body.style.overflow = '';
    }
}

function openModalFormTipo(mode, id, name) {
    id   = id   ?? '';
    name = name ?? '';
    document.getElementById('tipoFormMode').value = mode;
    document.getElementById('tipoFormId').value   = id;
    document.getElementById('tipoFormError').style.display = 'none';
    document.getElementById('tipoFormName').value = name;

    const isEdit = mode === 'edit';
    document.getElementById('modalFormTipoTitle').innerHTML = isEdit
        ? '<i class="bi bi-pencil-fill me-2" style="color:var(--accent)"></i>Editar nombre'
        : '<i class="bi bi-plus-circle-fill me-2" style="color:var(--accent)"></i>Nuevo tipo de bono';
    document.getElementById('btnFormTipoLabel').textContent = isEdit ? 'Guardar' : 'Crear tipo';
    document.getElementById('tipoFieldSessions').style.display = isEdit ? 'none' : '';
    document.getElementById('tipoFieldPrice').style.display    = isEdit ? 'none' : '';
    document.getElementById('tipoFieldValidity').style.display = isEdit ? 'none' : '';

    if (!isEdit) {
        document.getElementById('tipoFormSessions').value = '10';
        document.getElementById('tipoFormPrice').value    = '0.00';
        document.getElementById('tipoFormValidity').value = '90';
    }

    document.getElementById('modalFormTipo').classList.remove('d-none');
}
function closeModalFormTipo() {
    document.getElementById('modalFormTipo').classList.add('d-none');
}

async function submitFormTipo() {
    const mode  = document.getElementById('tipoFormMode').value;
    const id    = document.getElementById('tipoFormId').value;
    const name  = document.getElementById('tipoFormName').value.trim();
    const errEl = document.getElementById('tipoFormError');
    errEl.style.display = 'none';

    if (name.length < 2) {
        errEl.textContent = 'El nombre debe tener al menos 2 caracteres.';
        errEl.style.display = 'block';
        return;
    }

    const fd = new FormData();
    fd.append(BONOS_CSRF_NAME, BONOS_CSRF_HASH);
    fd.append('name', name);

    if (mode === 'create') {
        const sessions = parseInt(document.getElementById('tipoFormSessions').value) || 0;
        const price    = parseFloat(document.getElementById('tipoFormPrice').value) || 0;
        const validity = parseInt(document.getElementById('tipoFormValidity').value) || 0;
        if (sessions < 1) { errEl.textContent = 'Las sesiones deben ser al menos 1.'; errEl.style.display = 'block'; return; }
        if (validity < 1) { errEl.textContent = 'La validez debe ser al menos 1 día.'; errEl.style.display = 'block'; return; }
        fd.append('sessions', sessions);
        fd.append('price', price);
        fd.append('validity_days', validity);
    }

    const url = mode === 'create'
        ? '<?= base_url('bonos/tipos/store') ?>'
        : '<?= base_url('bonos/tipos/') ?>' + id + '/update';

    try {
        const res  = await fetch(url, { method: 'POST', body: fd });
        const data = await res.json();
        if (data.csrf_name) { BONOS_CSRF_NAME = data.csrf_name; BONOS_CSRF_HASH = data.csrf_hash; }

        if (!data.ok) {
            errEl.textContent = data.error ?? 'Error al guardar.';
            errEl.style.display = 'block';
            return;
        }

        if (mode === 'create') {
            _addTipoRow(data.tipo);
            const sel = document.querySelector('select[name="bono_type_id"]');
            if (sel) {
                const opt = document.createElement('option');
                opt.value       = data.tipo.id;
                opt.textContent = data.tipo.name + ' — ' + data.tipo.sessions + ' sesiones';
                sel.appendChild(opt);
            }
        } else {
            const row = document.getElementById('tipo-row-' + id);
            if (row) {
                row.cells[0].textContent = name;
                const editBtn = row.querySelector('[data-id="' + id + '"]');
                if (editBtn) editBtn.dataset.name = name;
            }
        }

        closeModalFormTipo();
    } catch(e) {
        errEl.textContent = 'Error de conexión. Inténtalo de nuevo.';
        errEl.style.display = 'block';
    }
}

function _addTipoRow(t) {
    const tbody = document.querySelector('#tablaTiposBono tbody');
    if (!tbody) { location.reload(); return; }

    const tr = document.createElement('tr');
    tr.id = 'tipo-row-' + t.id;
    tr.style.borderBottom = '1px solid var(--border)';
    tr.innerHTML =
        '<td style="padding:12px 16px;font-weight:600;color:var(--text-h)">' + _esc(t.name) + '</td>' +
        '<td style="padding:12px 16px;text-align:center;color:var(--text-muted)">' + t.sessions + '</td>' +
        '<td style="padding:12px 16px;text-align:center;color:var(--text-muted)">' + t.price + '€</td>' +
        '<td style="padding:12px 16px;text-align:center;color:var(--text-muted)">' + t.validity_days + 'd</td>' +
        '<td style="padding:12px 16px;text-align:center"><span class="tipo-badge-' + t.id + ' badge-status active">Activo</span></td>' +
        '<td style="padding:12px 16px;text-align:right;white-space:nowrap">' +
            '<button class="btn-jp btn-jp-secondary btn-jp-sm me-1" data-id="' + t.id + '" data-name="' + _esc(t.name) + '" onclick="openModalFormTipo(\'edit\', this.dataset.id, this.dataset.name)" title="Editar nombre"><i class="bi bi-pencil"></i></button>' +
            '<button class="btn-jp btn-jp-secondary btn-jp-sm tipo-toggle-btn" id="tipo-toggle-' + t.id + '" data-id="' + t.id + '" data-active="1" onclick="toggleTipoBono(this.dataset.id, this.dataset.active)" title="Desactivar" style="color:#dc2626;border-color:#dc262644"><i class="bi bi-toggle-on"></i></button>' +
        '</td>';
    tbody.appendChild(tr);
}

async function toggleTipoBono(id, currentActive) {
    const fd = new FormData();
    fd.append(BONOS_CSRF_NAME, BONOS_CSRF_HASH);

    try {
        const res  = await fetch('<?= base_url('bonos/tipos/') ?>' + id + '/toggle', { method: 'POST', body: fd });
        const data = await res.json();
        if (data.csrf_name) { BONOS_CSRF_NAME = data.csrf_name; BONOS_CSRF_HASH = data.csrf_hash; }

        if (!data.ok) { alert(data.error ?? 'Error al cambiar el estado.'); return; }

        const row   = document.getElementById('tipo-row-' + id);
        const badge = document.querySelector('.tipo-badge-' + id);
        const btn   = document.getElementById('tipo-toggle-' + id);

        if (data.active) {
            row.style.opacity   = '1';
            badge.className     = 'tipo-badge-' + id + ' badge-status active';
            badge.textContent   = 'Activo';
            btn.dataset.active  = '1';
            btn.title           = 'Desactivar';
            btn.style.color     = '#dc2626';
            btn.style.borderColor = '#dc262644';
            btn.innerHTML       = '<i class="bi bi-toggle-on"></i>';
            // Remove from emitir dropdown if it was missing (type reactivated)
        } else {
            row.style.opacity   = '.55';
            badge.className     = 'tipo-badge-' + id + ' badge-status inactive';
            badge.textContent   = 'Inactivo';
            btn.dataset.active  = '0';
            btn.title           = 'Activar';
            btn.style.color     = '#16a34a';
            btn.style.borderColor = '#16a34a44';
            btn.innerHTML       = '<i class="bi bi-toggle-off"></i>';
            // Remove from emitir dropdown
            const opt = document.querySelector('select[name="bono_type_id"] option[value="' + id + '"]');
            if (opt) opt.remove();
        }
    } catch(e) {
        alert('Error de conexión. Inténtalo de nuevo.');
    }
}

function _esc(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

document.addEventListener('keydown', e => {
    if (e.key !== 'Escape') return;
    if (!document.getElementById('modalFormTipo').classList.contains('d-none')) { closeModalFormTipo(); return; }
    if (!document.getElementById('modalTiposBono').classList.contains('d-none')) { closeModalTipos(); return; }
    closeModalEmitir();
});

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
            btnEmitir.disabled = false;
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
