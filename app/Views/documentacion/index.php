<?= $this->extend('layouts/app') ?>

<?php
$pageTitle    = 'Documentación';
$pageSubtitle = 'Archivos y recursos de la academia';

$userId   = session('id');
$role     = session('role');
$isAdmin  = in_array($role, ['admin', 'superadmin']);
$isPlayer = $role === 'player';

// ── Helper: icono y color según extensión ───────────────────────────
function fileIcon(string $ext): array {
    return match(true) {
        $ext === 'pdf'                                    => ['bi-file-earmark-pdf-fill',   '#e53e3e'],
        in_array($ext, ['doc','docx'])                    => ['bi-file-earmark-word-fill',  '#3182ce'],
        in_array($ext, ['xls','xlsx'])                    => ['bi-file-earmark-excel-fill', '#38a169'],
        in_array($ext, ['ppt','pptx'])                    => ['bi-file-earmark-ppt-fill',   '#dd6b20'],
        in_array($ext, ['jpg','jpeg','png','gif','webp']) => ['bi-file-earmark-image-fill', '#805ad5'],
        in_array($ext, ['mp4','mov','avi','webm'])        => ['bi-file-earmark-play-fill',  '#00b5d8'],
        default                                           => ['bi-file-earmark-fill',       'var(--text-muted)'],
    };
}

// ── Helper: formato de bytes ─────────────────────────────────────────
function fmtBytes(int $b): string {
    if ($b >= 1073741824) return round($b/1073741824, 1).' GB';
    if ($b >= 1048576)    return round($b/1048576, 1).' MB';
    if ($b >= 1024)       return round($b/1024, 1).' KB';
    return $b.' B';
}

// ── Helper: label de tipo de carpeta ────────────────────────────────
function folderTypeLabel(string $type): array {
    return match($type) {
        'public'   => ['Pública',   'active'],
        'personal' => ['Personal',  'inactive'],
        'internal' => ['Interna',   'badge-orange'],
        default    => [$type, 'inactive'],
    };
}

$previewExts = ['pdf','jpg','jpeg','png','gif','webp','mp4','webm'];
?>

<?= $this->section('page_content') ?>

<?php /* ── Flash messages ──────────────────────────────────────── */ ?>

<?php if (session()->getFlashdata('success')): ?>
<div class="alert-jp success" style="display:flex;align-items:center;gap:10px;margin-bottom:16px">
    <i class="bi bi-check-circle-fill"></i>
    <?= esc(session()->getFlashdata('success')) ?>
</div>
<?php endif; ?>

<?php if (session()->getFlashdata('error') || session()->getFlashdata('upload_error')): ?>
<div class="alert-jp danger" style="display:flex;align-items:center;gap:10px;margin-bottom:16px">
    <i class="bi bi-exclamation-triangle-fill"></i>
    <?= esc(session()->getFlashdata('error') ?? session()->getFlashdata('upload_error')) ?>
</div>
<?php endif; ?>


<?php /* ── Cabecera ─────────────────────────────────────────────── */ ?>

<div class="page-header">
    <div class="page-header-text">
        <h2>Documentación</h2>
        <p><?= $activeFolder ? esc($activeFolder['name']) : 'Archivos y recursos' ?></p>
    </div>
    <div class="d-flex gap-2">
        <?php if ($isAdmin): ?>
        <button class="btn-jp btn-jp-secondary" onclick="openModal('modal-new-folder')">
            <i class="bi bi-folder-plus"></i> Nueva carpeta
        </button>
        <?php endif; ?>
        <?php if (!empty($writableFolders)): ?>
        <button class="btn-jp btn-jp-primary" onclick="openModal('modal-upload')">
            <i class="bi bi-cloud-upload-fill"></i> Subir archivo
        </button>
        <?php endif; ?>
    </div>
</div>


<?php /* ── Grid de carpetas ───────────────────────────────────── */ ?>

<?php if (!empty($folders)): ?>
<div class="row g-3 mb-3">
    <?php foreach ($folders as $f):
        [$typeLabel, $typeBadge] = folderTypeLabel($f['type']);
        $isActive = $activeFolder && (int)$activeFolder['id'] === (int)$f['id'];
        $canWrite = in_array($f['type'], ['public']) && !$isPlayer
                 || ($f['type'] === 'personal' && (int)($f['owner_id'] ?? 0) === $userId)
                 || ($f['type'] === 'internal');
    ?>
    <div class="col-6 col-md-4 col-lg-3">
        <a href="<?= base_url('documentacion?folder=' . $f['id']) ?>" style="text-decoration:none">
            <div class="card-jp <?= $isActive ? 'card-jp-active' : '' ?>" style="cursor:pointer;<?= $isActive ? 'border-color:var(--accent);' : '' ?>">
                <div class="card-jp-body py-3 text-center" style="position:relative">
                    <?php if ($isAdmin && in_array($f['type'], ['public','internal'])): ?>
                    <div style="position:absolute;top:8px;right:8px;display:flex;gap:4px" onclick="event.preventDefault()">
                        <?php if ($f['type'] === 'internal'): ?>
                        <button class="btn-jp btn-jp-secondary btn-jp-sm btn-jp-icon"
                            title="Gestionar permisos"
                            onclick="openPermissionsModal(<?= $f['id'] ?>)">
                            <i class="bi bi-key-fill"></i>
                        </button>
                        <?php endif; ?>
                        <button class="btn-jp btn-jp-danger btn-jp-sm btn-jp-icon"
                            title="Eliminar carpeta"
                            onclick="deleteFolder(<?= $f['id'] ?>, '<?= esc($f['name']) ?>')">
                            <i class="bi bi-trash-fill"></i>
                        </button>
                    </div>
                    <?php endif; ?>

                    <div class="metric-icon <?= esc($f['color'] ?? 'blue') ?> mx-auto mb-2">
                        <i class="bi <?= esc($f['icon'] ?? 'bi-folder-fill') ?>"></i>
                    </div>
                    <div style="font-size:13px;font-weight:600;color:var(--text-h);margin-bottom:4px">
                        <?= esc($f['name']) ?>
                    </div>
                    <div style="font-size:12px;color:var(--text-muted);margin-bottom:6px">
                        <?= (int)($f['files_count'] ?? 0) ?> archivo(s)
                    </div>
                    <span class="badge-status <?= esc($typeBadge) ?>" style="font-size:10px">
                        <?= esc($typeLabel) ?>
                    </span>
                </div>
            </div>
        </a>
    </div>
    <?php endforeach; ?>
</div>
<?php endif; ?>


<?php /* ── Lista de archivos ──────────────────────────────────── */ ?>

<div class="card-jp">
    <?php if ($activeFolder): ?>

    <div class="card-jp-header">
        <span class="card-jp-title">
            <i class="bi <?= esc($activeFolder['icon'] ?? 'bi-folder2-open') ?> me-2" style="color:var(--accent)"></i>
            <?= esc($activeFolder['name']) ?>
        </span>
        <div class="d-flex align-items-center gap-3">
            <span style="font-size:12px;color:var(--text-muted)"><?= count($files) ?> archivo(s)</span>
            <a href="<?= base_url('documentacion') ?>" class="btn-jp btn-jp-secondary btn-jp-sm">
                <i class="bi bi-x-lg"></i>
            </a>
        </div>
    </div>

    <?php if (!empty($files)): ?>
    <div class="table-responsive">
        <table class="table-jp" id="files-table">
            <thead>
                <tr>
                    <th>Archivo</th>
                    <th>Tamaño</th>
                    <th>Subido por</th>
                    <th>Fecha</th>
                    <th style="text-align:right">Acciones</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($files as $f):
                    [$icon, $color] = fileIcon($f['extension']);
                    $canPreview = in_array($f['extension'], $previewExts);
                    $canDelete  = $isAdmin || (int)$f['uploader_id'] === $userId;
                ?>
                <tr>
                    <td>
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi <?= esc($icon) ?>" style="font-size:20px;color:<?= $color ?>;flex-shrink:0"></i>
                            <div>
                                <div style="font-weight:600;color:var(--text-h);font-size:13.5px">
                                    <?= esc($f['name_original']) ?>
                                </div>
                                <?php if (!empty($f['description'])): ?>
                                <div style="font-size:11px;color:var(--text-muted)">
                                    <?= esc($f['description']) ?>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </td>
                    <td style="font-size:12px;color:var(--text-muted);white-space:nowrap">
                        <?= fmtBytes((int)$f['size_bytes']) ?>
                    </td>
                    <td style="font-size:12px;color:var(--text-muted)">
                        <?= esc($f['uploader_name'] ?? '—') ?>
                    </td>
                    <td style="font-size:12px;color:var(--text-muted);white-space:nowrap">
                        <?= date('d/m/Y H:i', strtotime($f['created_at'])) ?>
                    </td>
                    <td>
                        <div class="d-flex gap-1 justify-content-end">
                            <?php if ($canPreview): ?>
                            <a href="<?= base_url('documentacion/file/' . $f['id'] . '/preview') ?>"
                               target="_blank"
                               class="btn-jp btn-jp-secondary btn-jp-sm btn-jp-icon" title="Previsualizar">
                                <i class="bi bi-eye"></i>
                            </a>
                            <?php endif; ?>
                            <a href="<?= base_url('documentacion/file/' . $f['id'] . '/download') ?>"
                               class="btn-jp btn-jp-secondary btn-jp-sm btn-jp-icon" title="Descargar">
                                <i class="bi bi-download"></i>
                            </a>
                            <?php if ($canDelete): ?>
                            <form method="post" action="<?= base_url('documentacion/file/' . $f['id'] . '/delete') ?>"
                                  style="display:inline"
                                  onsubmit="return confirm('¿Eliminar «<?= esc($f['name_original']) ?>»?')">
                                <?= csrf_field() ?>
                                <button type="submit" class="btn-jp btn-jp-danger btn-jp-sm btn-jp-icon" title="Eliminar">
                                    <i class="bi bi-trash-fill"></i>
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

    <?php else: ?>
    <div class="empty-state">
        <i class="bi bi-folder2-open"></i>
        <h3>Carpeta vacía</h3>
        <p>Todavía no hay archivos en esta carpeta.</p>
        <?php if (!empty($writableFolders)): ?>
        <button class="btn-jp btn-jp-primary" onclick="openUploadModal(<?= $activeFolder['id'] ?>)">
            <i class="bi bi-cloud-upload-fill"></i> Subir primer archivo
        </button>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <?php else: ?>

    <div class="empty-state">
        <i class="bi bi-folder2-open"></i>
        <h3>Selecciona una carpeta</h3>
        <p>Haz clic en una carpeta para ver sus archivos.</p>
    </div>

    <?php endif; ?>
</div>


<?php /* ═══════════════════════════════════════════════════════════
       MODALES
       ═══════════════════════════════════════════════════════════ */ ?>


<?php /* ── Modal: Subir archivo ──────────────────────────────── */ ?>
<div class="modal-overlay" id="modal-upload">
    <div class="modal-box">
        <div class="modal-header">
            <span class="card-jp-title"><i class="bi bi-cloud-upload-fill me-2" style="color:var(--accent)"></i>Subir archivo</span>
            <button onclick="closeModal('modal-upload')" class="btn-jp btn-jp-secondary btn-jp-sm btn-jp-icon"><i class="bi bi-x-lg"></i></button>
        </div>
        <form method="post" action="<?= base_url('documentacion/upload') ?>" enctype="multipart/form-data" id="form-upload">
            <?= csrf_field() ?>
            <div class="row g-3 mt-1">

                <div class="col-12">
                    <div class="form-group">
                        <label class="form-label">Carpeta destino <span style="color:var(--danger)">*</span></label>
                        <select name="folder_id" class="form-control-jp" id="upload-folder-select" required>
                            <option value="">— Selecciona carpeta —</option>
                            <?php foreach ($writableFolders as $wf): ?>
                            <option value="<?= $wf['id'] ?>"
                                <?= $activeFolder && (int)$activeFolder['id'] === (int)$wf['id'] ? 'selected' : '' ?>>
                                <?= esc($wf['name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="col-12">
                    <div class="form-group">
                        <label class="form-label">Archivo <span style="color:var(--danger)">*</span></label>
                        <input type="file" name="archivo" id="file-input" class="form-control-jp" required
                            accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.webp,.mp4,.mov,.avi,.webm">
                        <div style="font-size:11px;color:var(--text-muted);margin-top:4px">
                            PDF/Office: 25 MB · Imágenes: 10 MB · Vídeo: 500 MB
                        </div>
                    </div>
                </div>

                <div class="col-12">
                    <div class="form-group">
                        <label class="form-label">Descripción <span style="font-weight:400;color:var(--text-muted)">(opcional)</span></label>
                        <input type="text" name="description" class="form-control-jp" placeholder="Breve descripción del archivo...">
                    </div>
                </div>

                <div class="col-12">
                    <div id="upload-progress" style="display:none">
                        <div style="font-size:12px;color:var(--text-muted);margin-bottom:6px">Subiendo archivo...</div>
                        <div style="height:4px;background:var(--border);border-radius:2px;overflow:hidden">
                            <div id="progress-bar" style="height:100%;background:var(--accent);width:0;transition:width .3s"></div>
                        </div>
                    </div>
                </div>

                <div class="col-12 d-flex gap-2 justify-content-end">
                    <button type="button" onclick="closeModal('modal-upload')" class="btn-jp btn-jp-secondary">Cancelar</button>
                    <button type="submit" class="btn-jp btn-jp-primary" id="btn-upload">
                        <i class="bi bi-cloud-upload-fill"></i> Subir
                    </button>
                </div>

            </div>
        </form>
    </div>
</div>


<?php /* ── Modal: Nueva carpeta (solo admin) ───────────────────── */ ?>
<?php if ($isAdmin): ?>
<div class="modal-overlay" id="modal-new-folder">
    <div class="modal-box">
        <div class="modal-header">
            <span class="card-jp-title"><i class="bi bi-folder-plus me-2" style="color:var(--accent)"></i>Nueva carpeta</span>
            <button onclick="closeModal('modal-new-folder')" class="btn-jp btn-jp-secondary btn-jp-sm btn-jp-icon"><i class="bi bi-x-lg"></i></button>
        </div>
        <form method="post" action="<?= base_url('documentacion/folder/create') ?>">
            <?= csrf_field() ?>
            <div class="row g-3 mt-1">

                <div class="col-12">
                    <div class="form-group">
                        <label class="form-label">Nombre <span style="color:var(--danger)">*</span></label>
                        <input type="text" name="name" class="form-control-jp" required placeholder="Ej: Material de entrenamiento">
                    </div>
                </div>

                <div class="col-12 col-md-6">
                    <div class="form-group">
                        <label class="form-label">Tipo <span style="color:var(--danger)">*</span></label>
                        <select name="type" class="form-control-jp" required>
                            <option value="public">Pública (visible para todos excepto player)</option>
                            <option value="internal">Interna (acceso con permisos)</option>
                        </select>
                    </div>
                </div>

                <div class="col-12 col-md-3">
                    <div class="form-group">
                        <label class="form-label">Color</label>
                        <select name="color" class="form-control-jp">
                            <option value="blue">Azul</option>
                            <option value="green">Verde</option>
                            <option value="orange">Naranja</option>
                            <option value="purple">Morado</option>
                            <option value="red">Rojo</option>
                        </select>
                    </div>
                </div>

                <div class="col-12 col-md-3">
                    <div class="form-group">
                        <label class="form-label">Icono</label>
                        <select name="icon" class="form-control-jp">
                            <option value="bi-folder-fill">Carpeta</option>
                            <option value="bi-file-earmark-text-fill">Documento</option>
                            <option value="bi-camera-video-fill">Vídeo</option>
                            <option value="bi-clipboard2-pulse-fill">Salud</option>
                            <option value="bi-brain">Psicología</option>
                            <option value="bi-trophy-fill">Torneos</option>
                            <option value="bi-shield-lock-fill">Privado</option>
                            <option value="bi-star-fill">Destacado</option>
                        </select>
                    </div>
                </div>

                <div class="col-12 d-flex gap-2 justify-content-end">
                    <button type="button" onclick="closeModal('modal-new-folder')" class="btn-jp btn-jp-secondary">Cancelar</button>
                    <button type="submit" class="btn-jp btn-jp-primary">
                        <i class="bi bi-folder-plus"></i> Crear carpeta
                    </button>
                </div>

            </div>
        </form>
    </div>
</div>
<?php endif; ?>


<?php /* ── Modal: Permisos de carpeta interna (solo admin) ─────── */ ?>
<?php if ($isAdmin): ?>
<div class="modal-overlay" id="modal-permissions">
    <div class="modal-box" style="max-width:600px">
        <div class="modal-header">
            <span class="card-jp-title"><i class="bi bi-key-fill me-2" style="color:var(--accent)"></i>Gestionar permisos</span>
            <button onclick="closeModal('modal-permissions')" class="btn-jp btn-jp-secondary btn-jp-sm btn-jp-icon"><i class="bi bi-x-lg"></i></button>
        </div>

        <form method="post" id="form-permissions" action="">
            <?= csrf_field() ?>

            <p style="font-size:12px;color:var(--text-muted);margin:12px 0">
                Los roles <strong>admin</strong> y <strong>superadmin</strong> tienen acceso siempre.
                Los <strong>player</strong> nunca tienen acceso a carpetas internas.
            </p>

            <div style="max-height:300px;overflow-y:auto;border:1px solid var(--border);border-radius:8px">
                <table class="table-jp" style="margin:0">
                    <thead>
                        <tr>
                            <th>Usuario</th>
                            <th>Rol</th>
                            <th style="text-align:center">Leer</th>
                            <th style="text-align:center">Escribir</th>
                        </tr>
                    </thead>
                    <tbody id="perm-tbody">
                        <?php if (!empty($assignableUsers)):
                            // Indexar permisos actuales por user_id para lookup rápido
                            $permsByUser = [];
                            foreach ($folderPermissions as $p) {
                                $permsByUser[$p['user_id']] = $p;
                            }
                        ?>
                        <?php foreach ($assignableUsers as $u):
                            // Admin/superadmin → acceso hardcoded, mostrar deshabilitado
                            $isAlwaysAllowed = in_array($u['role'], ['admin', 'superadmin']);
                            $perm = $permsByUser[$u['id']] ?? [];
                            $hasRead  = $isAlwaysAllowed || !empty($perm['can_read']);
                            $hasWrite = $isAlwaysAllowed || !empty($perm['can_write']);
                        ?>
                        <tr>
                            <td>
                                <div style="font-weight:600;font-size:13px;color:var(--text-h)"><?= esc($u['name']) ?></div>
                                <div style="font-size:11px;color:var(--text-muted)"><?= esc($u['email']) ?></div>
                            </td>
                            <td><span class="badge-status active" style="font-size:10px"><?= esc(ucfirst($u['role'])) ?></span></td>
                            <td style="text-align:center">
                                <input type="checkbox"
                                    name="perms[<?= $u['id'] ?>][can_read]"
                                    value="1"
                                    <?= $hasRead  ? 'checked' : '' ?>
                                    <?= $isAlwaysAllowed ? 'disabled' : '' ?>>
                            </td>
                            <td style="text-align:center">
                                <input type="checkbox"
                                    name="perms[<?= $u['id'] ?>][can_write]"
                                    value="1"
                                    <?= $hasWrite ? 'checked' : '' ?>
                                    <?= $isAlwaysAllowed ? 'disabled' : '' ?>>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <div class="d-flex gap-2 justify-content-end" style="margin-top:16px">
                <button type="button" onclick="closeModal('modal-permissions')" class="btn-jp btn-jp-secondary">Cancelar</button>
                <button type="submit" class="btn-jp btn-jp-primary">
                    <i class="bi bi-floppy-fill"></i> Guardar permisos
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Form oculto para eliminar carpeta (necesita POST + CSRF) -->
<form method="post" id="form-delete-folder" action="" style="display:none">
    <?= csrf_field() ?>
</form>
<?php endif; ?>

<?= console_debug('DocumentacionController::index', [
    'role'            => $role,
    'active_folder'   => $activeFolder ? ['id' => $activeFolder['id'], 'name' => $activeFolder['name'], 'type' => $activeFolder['type']] : null,
    'folders_count'   => count($folders ?? []),
    'files_count'     => count($files ?? []),
    'writable_folders'=> count($writableFolders ?? []),
    'folders'         => array_map(fn($f) => ['id' => $f['id'], 'name' => $f['name'], 'type' => $f['type'], 'files' => $f['files_count']], $folders ?? []),
], collapsed: true) ?>

<?= $this->endSection() ?>


<?= $this->section('scripts') ?>
<style>
/* ── Modales ──────────────────────────────────────────────────────── */
.modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    background: rgba(0,0,0,.55);
    z-index: 1000;
    align-items: center;
    justify-content: center;
    padding: 16px;
}
.modal-overlay.open { display: flex; }

.modal-box {
    background: var(--bg-card);
    border: 1px solid var(--border);
    border-radius: 12px;
    padding: 24px;
    width: 100%;
    max-width: 520px;
    max-height: 90vh;
    overflow-y: auto;
}

.modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 4px;
}

/* Carpeta activa en el grid */
.card-jp-active {
    box-shadow: 0 0 0 2px var(--accent);
}

/* Badge naranja para carpetas internas */
.badge-status.badge-orange {
    background: rgba(237,137,54,.15);
    color: #dd6b20;
}
</style>

<script>
// ── Modal helpers ─────────────────────────────────────────────────────
function openModal(id) {
    document.getElementById(id).classList.add('open');
    document.body.style.overflow = 'hidden';
}
function closeModal(id) {
    document.getElementById(id).classList.remove('open');
    document.body.style.overflow = '';
}

// Cerrar modal al clicar el fondo oscuro
document.querySelectorAll('.modal-overlay').forEach(overlay => {
    overlay.addEventListener('click', (e) => {
        if (e.target === overlay) closeModal(overlay.id);
    });
});

// Cerrar con Escape
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        document.querySelectorAll('.modal-overlay.open').forEach(m => closeModal(m.id));
    }
});

// ── Abrir modal de subida con carpeta preseleccionada ────────────────
function openUploadModal(folderId) {
    if (folderId) {
        const sel = document.getElementById('upload-folder-select');
        if (sel) sel.value = folderId;
    }
    openModal('modal-upload');
}

// ── Abrir modal de permisos (carga la acción del formulario) ─────────
function openPermissionsModal(folderId) {
    const form = document.getElementById('form-permissions');
    if (form) {
        form.action = '/documentacion/folder/' + folderId + '/permissions';
    }
    openModal('modal-permissions');
}

// ── Eliminar carpeta (form POST con confirmación) ────────────────────
function deleteFolder(id, name) {
    if (!confirm('¿Eliminar la carpeta «' + name + '» y todos sus archivos?\nEsta acción no se puede deshacer.')) return;
    const form = document.getElementById('form-delete-folder');
    if (form) {
        form.action = '/documentacion/folder/' + id + '/delete';
        form.submit();
    }
}

// ── Indicador de progreso en subida ─────────────────────────────────
document.getElementById('form-upload')?.addEventListener('submit', function() {
    const progress = document.getElementById('upload-progress');
    const bar      = document.getElementById('progress-bar');
    const btn      = document.getElementById('btn-upload');

    if (progress) progress.style.display = 'block';
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Subiendo...'; }

    // Animar barra al 90% (el 100% se logra con la redirección del servidor)
    if (bar) {
        let w = 0;
        const interval = setInterval(() => {
            w = Math.min(w + 2, 90);
            bar.style.width = w + '%';
            if (w >= 90) clearInterval(interval);
        }, 150);
    }
});

// ── Búsqueda en tabla de archivos ────────────────────────────────────
(function () {
    const tbl = document.getElementById('files-table');
    if (!tbl) return;

    const searchBar = document.querySelector('#search-files');
    if (!searchBar) return;

    searchBar.addEventListener('input', function () {
        const q = this.value.toLowerCase();
        tbl.querySelectorAll('tbody tr').forEach(row => {
            row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
        });
    });
})();
</script>
<?= $this->endSection() ?>
