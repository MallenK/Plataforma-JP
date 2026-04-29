<?= $this->extend('layouts/app') ?>
<?= $this->section('page_content') ?>

<?php
helper('avatar');
$csrfName = csrf_token();
$csrfHash = csrf_hash();
$isAdmin  = in_array($currentRole, ['superadmin', 'admin']);

$statusCfg = [
    'pendiente'   => ['label' => 'Pendiente',   'cls' => 'compra-status-pendiente',   'icon' => 'bi-hourglass-split'],
    'en_revision' => ['label' => 'En revisión', 'cls' => 'compra-status-en_revision', 'icon' => 'bi-eye'],
    'aprobado'    => ['label' => 'Aprobado',    'cls' => 'compra-status-aprobado',    'icon' => 'bi-check-circle-fill'],
    'denegado'    => ['label' => 'Denegado',    'cls' => 'compra-status-denegado',    'icon' => 'bi-x-circle-fill'],
    'comprado'    => ['label' => 'Comprado',    'cls' => 'compra-status-comprado',    'icon' => 'bi-bag-check-fill'],
    'cancelado'   => ['label' => 'Cancelado',   'cls' => 'compra-status-cancelado',   'icon' => 'bi-slash-circle'],
];
$priorityCfg = [
    'alta'  => ['label' => 'Alta',  'cls' => 'compra-pri-alta'],
    'media' => ['label' => 'Media', 'cls' => 'compra-pri-media'],
    'baja'  => ['label' => 'Baja',  'cls' => 'compra-pri-baja'],
];
$categoryCfg = [
    'equipamiento'       => ['label' => 'Equipamiento',   'icon' => 'bi-box-seam'],
    'tecnologia'         => ['label' => 'Tecnología',     'icon' => 'bi-cpu'],
    'material_deportivo' => ['label' => 'Material dep.',  'icon' => 'bi-trophy'],
    'instalaciones'      => ['label' => 'Instalaciones',  'icon' => 'bi-building'],
    'oficina'            => ['label' => 'Oficina',        'icon' => 'bi-briefcase'],
    'otros'              => ['label' => 'Otros',          'icon' => 'bi-three-dots'],
];
?>

<?php if (session()->getFlashdata('success')): ?>
<div class="alert-jp success mb-3">
    <i class="bi bi-check-circle-fill"></i>
    <?= esc(session()->getFlashdata('success')) ?>
</div>
<?php endif; ?>
<?php if (session()->getFlashdata('error')): ?>
<div class="alert-jp error mb-3">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <?= esc(session()->getFlashdata('error')) ?>
</div>
<?php endif; ?>

<!-- ── Cabecera ────────────────────────────────────────────── -->
<div class="page-header">
    <div class="page-header-text">
        <h2>Lista de Compras</h2>
        <p>Solicitudes de material y equipamiento del equipo</p>
    </div>
    <button class="btn-jp btn-jp-primary" data-bs-toggle="modal" data-bs-target="#modalNuevaCompra">
        <i class="bi bi-plus-lg"></i> Nueva solicitud
    </button>
</div>

<!-- ── Métricas ───────────────────────────────────────────── -->
<div class="row g-3 mb-3">
    <div class="col-6 col-md-3">
        <div class="metric-card">
            <div class="metric-card-header">
                <span class="metric-label">Total solicitudes</span>
                <div class="metric-icon blue"><i class="bi bi-list-check"></i></div>
            </div>
            <div class="metric-value"><?= (int)($stats['total'] ?? 0) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="metric-card">
            <div class="metric-card-header">
                <span class="metric-label">Pendientes</span>
                <div class="metric-icon orange"><i class="bi bi-hourglass-split"></i></div>
            </div>
            <div class="metric-value"><?= (int)($stats['pendiente'] ?? 0) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="metric-card">
            <div class="metric-card-header">
                <span class="metric-label">Aprobadas</span>
                <div class="metric-icon green"><i class="bi bi-check-circle"></i></div>
            </div>
            <div class="metric-value"><?= (int)($stats['aprobado'] ?? 0) ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="metric-card">
            <div class="metric-card-header">
                <span class="metric-label">Compradas</span>
                <div class="metric-icon" style="background:#eff6ff;color:#1d4ed8"><i class="bi bi-bag-check-fill"></i></div>
            </div>
            <div class="metric-value"><?= (int)($stats['comprado'] ?? 0) ?></div>
        </div>
    </div>
</div>

<!-- ── Filtros ────────────────────────────────────────────── -->
<div class="card-jp mb-3" style="padding:14px 20px">
    <div class="calendar-view-tabs" style="flex-wrap:wrap;gap:3px">
        <?php
        $tabs = [
            'todos'       => ['Todas',        null],
            'pendiente'   => ['Pendiente',    $stats['pendiente']   ?? 0],
            'en_revision' => ['En revisión',  $stats['en_revision'] ?? 0],
            'aprobado'    => ['Aprobado',     $stats['aprobado']    ?? 0],
            'denegado'    => ['Denegado',     $stats['denegado']    ?? 0],
            'comprado'    => ['Comprado',     $stats['comprado']    ?? 0],
            'cancelado'   => ['Cancelado',    $stats['cancelado']   ?? 0],
        ];
        foreach ($tabs as $key => [$label, $count]): ?>
        <a href="?filtro=<?= $key ?>"
           class="calendar-view-tab <?= $filtro === $key ? 'active' : '' ?>"
           style="text-decoration:none">
            <?= $label ?>
            <?php if ($count !== null && $count > 0): ?>
            <span class="compra-tab-badge"><?= $count ?></span>
            <?php endif; ?>
        </a>
        <?php endforeach; ?>
    </div>
</div>

<!-- ── Tabla ──────────────────────────────────────────────── -->
<div class="card-jp" style="padding:0">

    <?php if (empty($requests)): ?>
    <div class="empty-state">
        <i class="bi bi-cart-x"></i>
        <h3>Sin solicitudes<?= $filtro !== 'todos' ? ' con este estado' : '' ?></h3>
        <p>Añade la primera solicitud de compra para el equipo.</p>
        <button class="btn-jp btn-jp-primary" data-bs-toggle="modal" data-bs-target="#modalNuevaCompra">
            <i class="bi bi-plus-lg"></i> Nueva solicitud
        </button>
    </div>
    <?php else: ?>
    <div class="table-responsive">
        <table class="table-jp">
            <thead>
                <tr>
                    <th>Producto</th>
                    <th>Categoría</th>
                    <th>Prioridad</th>
                    <th>Estado</th>
                    <th>Solicitado por</th>
                    <th>Precio</th>
                    <th>Fecha</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($requests as $r):
                $sc = $statusCfg[$r['status']]   ?? $statusCfg['pendiente'];
                $pc = $priorityCfg[$r['priority']] ?? $priorityCfg['media'];
                $cc = $categoryCfg[$r['category']] ?? $categoryCfg['otros'];
            ?>
            <tr>
                <!-- Producto -->
                <td style="max-width:240px">
                    <div class="td-name" style="max-width:220px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap"
                         title="<?= esc($r['name']) ?>">
                        <?= esc($r['name']) ?>
                    </div>
                    <?php if ($r['description']): ?>
                    <div class="td-sub" style="max-width:220px;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical">
                        <?= esc($r['description']) ?>
                    </div>
                    <?php endif; ?>
                </td>

                <!-- Categoría -->
                <td>
                    <span class="compra-cat-badge">
                        <i class="bi <?= $cc['icon'] ?>"></i>
                        <?= $cc['label'] ?>
                    </span>
                </td>

                <!-- Prioridad -->
                <td>
                    <span class="compra-pri-badge <?= $pc['cls'] ?>">
                        <?= $pc['label'] ?>
                    </span>
                </td>

                <!-- Estado + comentario -->
                <td>
                    <span class="compra-status-badge <?= $sc['cls'] ?>">
                        <i class="bi <?= $sc['icon'] ?>"></i>
                        <?= $sc['label'] ?>
                    </span>
                    <?php if ($r['admin_comment']): ?>
                    <div class="td-sub mt-1" style="max-width:180px;overflow:hidden;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical"
                         title="<?= esc($r['admin_comment']) ?>">
                        <i class="bi bi-chat-left-text"></i> <?= esc($r['admin_comment']) ?>
                    </div>
                    <?php endif; ?>
                </td>

                <!-- Solicitado por -->
                <td>
                    <div class="td-user">
                        <?= avatar_html($r['requester_avatar'] ?? null, $r['requester_name'] ?? '?', 'td-avatar') ?>
                        <span class="td-name" style="font-size:13px"><?= esc($r['requester_name'] ?? '') ?></span>
                    </div>
                </td>

                <!-- Precio -->
                <td>
                    <?php if ($r['price']): ?>
                    <span style="font-weight:600;color:var(--text-h)">
                        <?= number_format((float)$r['price'], 2, ',', '.') ?> €
                    </span>
                    <?php else: ?>
                    <span class="td-sub">—</span>
                    <?php endif; ?>
                </td>

                <!-- Fecha -->
                <td>
                    <span class="td-sub"><?= timeAgo($r['created_at']) ?></span>
                </td>

                <!-- Acciones -->
                <td>
                    <div style="display:flex;align-items:center;gap:6px">
                        <?php if ($r['url']): ?>
                        <a href="<?= esc($r['url']) ?>" target="_blank" rel="noopener noreferrer"
                           class="btn-jp btn-jp-secondary btn-jp-sm btn-jp-icon" title="Ver producto">
                            <i class="bi bi-box-arrow-up-right"></i>
                        </a>
                        <?php endif; ?>

                        <?php if ($isAdmin): ?>
                        <button class="btn-jp btn-jp-secondary btn-jp-sm btn-jp-icon"
                                title="Cambiar estado"
                                data-id="<?= (int)$r['id'] ?>"
                                data-status="<?= esc($r['status'], 'attr') ?>"
                                data-comment="<?= esc($r['admin_comment'] ?? '', 'attr') ?>"
                                onclick="openStatusModal(this)">
                            <i class="bi bi-pencil-square"></i>
                        </button>

                        <form method="post"
                              action="<?= base_url('compras/'.(int)$r['id'].'/eliminar') ?>"
                              onsubmit="return confirm('¿Eliminar esta solicitud permanentemente?')"
                              style="display:inline">
                            <input type="hidden" name="<?= $csrfName ?>" value="<?= $csrfHash ?>">
                            <button type="submit" class="btn-jp btn-jp-danger btn-jp-sm btn-jp-icon" title="Eliminar">
                                <i class="bi bi-trash3"></i>
                            </button>
                        </form>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>


<!-- ══════════════════════════════════════════════════════════
     MODAL: Nueva solicitud
     ══════════════════════════════════════════════════════════ -->
<div class="modal fade" id="modalNuevaCompra" tabindex="-1" aria-labelledby="labelNuevaCompra" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="labelNuevaCompra">
                    <i class="bi bi-cart-plus me-2" style="color:var(--accent)"></i>Nueva solicitud de compra
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" action="<?= base_url('compras/store') ?>">
                <input type="hidden" name="<?= $csrfName ?>" value="<?= $csrfHash ?>">
                <div class="modal-body pt-3">

                    <div class="form-group">
                        <label class="form-label">Nombre del producto <span style="color:var(--danger)">*</span></label>
                        <input type="text" name="name" class="form-control-jp"
                               placeholder="Ej: Balón de entrenamiento Nike Pro" required maxlength="200">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Enlace al producto</label>
                        <input type="url" name="url" class="form-control-jp"
                               placeholder="https://www.amazon.es/...">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Descripción / Motivo</label>
                        <textarea name="description" class="form-control-jp" rows="2"
                                  placeholder="¿Por qué se necesita este producto?" maxlength="1000"></textarea>
                    </div>

                    <div class="row g-2">
                        <div class="col-6">
                            <div class="form-group mb-0">
                                <label class="form-label">Precio estimado (€)</label>
                                <input type="number" name="price" class="form-control-jp"
                                       placeholder="0.00" step="0.01" min="0" max="99999.99">
                            </div>
                        </div>
                        <div class="col-6">
                            <div class="form-group mb-0">
                                <label class="form-label">Prioridad</label>
                                <select name="priority" class="form-control-jp">
                                    <option value="alta">🔴 Alta</option>
                                    <option value="media" selected>🟡 Media</option>
                                    <option value="baja">🟢 Baja</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="form-group mb-0 mt-3">
                        <label class="form-label">Categoría</label>
                        <select name="category" class="form-control-jp">
                            <option value="equipamiento">📦 Equipamiento</option>
                            <option value="tecnologia">💻 Tecnología</option>
                            <option value="material_deportivo">🏆 Material deportivo</option>
                            <option value="instalaciones">🏢 Instalaciones</option>
                            <option value="oficina">💼 Oficina</option>
                            <option value="otros" selected>📌 Otros</option>
                        </select>
                    </div>

                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn-jp btn-jp-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-jp btn-jp-primary">
                        <i class="bi bi-plus-lg"></i> Añadir solicitud
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>


<!-- ══════════════════════════════════════════════════════════
     MODAL: Cambiar estado (solo admin)
     ══════════════════════════════════════════════════════════ -->
<?php if ($isAdmin): ?>
<div class="modal fade" id="modalCambiarEstado" tabindex="-1" aria-labelledby="labelCambiarEstado" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title fw-bold" id="labelCambiarEstado">
                    <i class="bi bi-pencil-square me-2" style="color:var(--accent)"></i>Actualizar estado
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="post" id="form-cambiar-estado" action="">
                <input type="hidden" name="<?= $csrfName ?>" value="<?= $csrfHash ?>">
                <div class="modal-body pt-3">

                    <div class="form-group">
                        <label class="form-label">Nuevo estado</label>
                        <select name="status" id="select-estado" class="form-control-jp"
                                onchange="handleStatusChange(this)">
                            <option value="pendiente">⏳ Pendiente</option>
                            <option value="en_revision">🔍 En revisión</option>
                            <option value="aprobado">✅ Aprobado</option>
                            <option value="denegado">❌ Denegado</option>
                            <option value="comprado">🛒 Comprado</option>
                            <option value="cancelado">🚫 Cancelado</option>
                        </select>
                    </div>

                    <div class="form-group mb-0">
                        <label class="form-label">
                            Comentario
                            <span id="hint-requerido" class="d-none"
                                  style="color:var(--danger);font-size:11px;text-transform:none;letter-spacing:0">
                                — requerido para este estado
                            </span>
                        </label>
                        <textarea name="admin_comment" id="textarea-comentario"
                                  class="form-control-jp" rows="3"
                                  placeholder="Añade un comentario o motivo…" maxlength="1000"></textarea>
                    </div>

                </div>
                <div class="modal-footer border-0 pt-0">
                    <button type="button" class="btn-jp btn-jp-secondary" data-bs-dismiss="modal">Cancelar</button>
                    <button type="submit" class="btn-jp btn-jp-primary">
                        <i class="bi bi-check-lg"></i> Guardar cambio
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<?php if ($isAdmin): ?>
<script>
function openStatusModal(btn) {
    const id             = btn.dataset.id;
    const currentStatus  = btn.dataset.status;
    const currentComment = btn.dataset.comment || '';
    document.getElementById('form-cambiar-estado').action =
        '<?= base_url('compras/') ?>' + id + '/estado';
    document.getElementById('select-estado').value       = currentStatus;
    document.getElementById('textarea-comentario').value = currentComment;
    handleStatusChange(document.getElementById('select-estado'));
    bootstrap.Modal.getOrCreateInstance(
        document.getElementById('modalCambiarEstado')
    ).show();
}

function handleStatusChange(sel) {
    const needsComment = ['denegado', 'cancelado'].includes(sel.value);
    document.getElementById('hint-requerido').classList.toggle('d-none', !needsComment);
    document.getElementById('textarea-comentario').required = needsComment;
}
</script>
<?php endif; ?>
<?= $this->endSection() ?>
