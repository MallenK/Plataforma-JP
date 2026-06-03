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

<?php /* ── Barra de búsqueda y filtros ─────────────────────────────── */ ?>
<div class="card-jp mb-4" style="padding:14px 16px">
    <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
        <div style="position:relative;flex:1;min-width:180px">
            <i class="bi bi-search" style="position:absolute;left:10px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:13px"></i>
            <input type="text" id="doc-search" placeholder="Buscar carpeta o archivo…"
                   style="width:100%;padding:8px 12px 8px 32px;border:1px solid var(--border-color);border-radius:8px;font-size:13px;background:var(--bg-card);color:var(--text-h)">
        </div>
        <div style="display:flex;gap:6px;flex-wrap:wrap" id="doc-filters">
            <button class="doc-filter-btn active" data-filter="all"
                    style="padding:6px 14px;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;border:1px solid var(--border-color);background:var(--accent);color:#fff">
                Todos
            </button>
            <button class="doc-filter-btn" data-filter="public"
                    style="padding:6px 14px;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;border:1px solid var(--border-color);background:var(--bg-card);color:var(--text-h)">
                Públicas
            </button>
            <?php if (!$isPlayer): ?>
            <button class="doc-filter-btn" data-filter="personal"
                    style="padding:6px 14px;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;border:1px solid var(--border-color);background:var(--bg-card);color:var(--text-h)">
                Personales
            </button>
            <?php if ($isAdmin): ?>
            <button class="doc-filter-btn" data-filter="internal"
                    style="padding:6px 14px;border-radius:8px;font-size:12px;font-weight:600;cursor:pointer;border:1px solid var(--border-color);background:var(--bg-card);color:var(--text-h)">
                Internas
            </button>
            <?php endif; ?>
            <?php endif; ?>
        </div>
        <span id="doc-count" style="font-size:12px;color:var(--text-muted);white-space:nowrap"></span>
    </div>
</div>

<?php /* ── Grid de carpetas ───────────────────────────────────── */ ?>

<?php if (!empty($folders) || ($isAdmin && !empty($allUsers))): ?>
<?php
$fPublic   = array_filter($folders, fn($f) => $f['type'] === 'public');
$fInternal = array_filter($folders, fn($f) => $f['type'] === 'internal');
$fPersonal = array_filter($folders, fn($f) => $f['type'] === 'personal');

// Agrupar carpetas personales por rol del propietario
// Para admin/superadmin: incluir todos los usuarios, con o sin carpeta
$personalByRole = [];
if ($isAdmin && !empty($allUsers)) {
    $personalFolderByOwner = [];
    foreach ($fPersonal as $f) {
        $personalFolderByOwner[(int)($f['owner_id'] ?? 0)] = $f;
    }
    foreach ($allUsers as $u) {
        $uid     = (int)$u['id'];
        $roleKey = $u['role'];
        if (isset($personalFolderByOwner[$uid])) {
            $personalByRole[$roleKey][] = $personalFolderByOwner[$uid];
        }
    }
} else {
    foreach ($fPersonal as $f) {
        $ownerRole = $f['owner_role'] ?? 'other';
        $personalByRole[$ownerRole][] = $f;
    }
}

// Orden y etiquetas de los grupos de rol
$roleGroups = [
    'player'     => ['Jugadores',      'bi-dribbble',         'var(--accent)'],
    'alumno'     => ['Jugadores',      'bi-dribbble',         'var(--accent)'],
    'coach'      => ['Entrenadores',   'bi-whistle-fill',     '#38a169'],
    'staff'      => ['Staff',          'bi-briefcase-fill',   '#dd6b20'],
    'admin'      => ['Administración', 'bi-shield-fill',      '#805ad5'],
    'superadmin' => ['Superadmin',     'bi-shield-lock-fill', '#e53e3e'],
];

function renderFolderCard(array $f, ?array $activeFolder, bool $isAdmin): void {
    [$typeLabel, $typeBadge] = folderTypeLabel($f['type']);
    $noFolder = !empty($f['no_folder']);
    $cardName = strtolower($f['owner_name'] ?? $f['name'] ?? '');
    ?>
    <div class="col-6 col-md-4 col-lg-3 doc-folder-item"
         data-type="<?= esc($f['type']) ?>"
         data-name="<?= esc($cardName) ?>">
        <?php if ($noFolder): ?>
        <div class="card-jp" style="opacity:0.6;cursor:default" title="Sin carpeta asignada">
            <div class="card-jp-body py-3 text-center" style="position:relative">
                <?php if ($isAdmin): ?>
                <div style="position:absolute;top:8px;right:8px" onclick="event.stopPropagation()">
                    <form method="post" action="<?= base_url('documentacion/folder/user/' . (int)$f['owner_id'] . '/create') ?>"
                          onsubmit="return confirm('¿Crear carpeta personal para <?= esc(($f['owner_name'] ?? 'este usuario'), 'js') ?>?')">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn-jp btn-jp-secondary btn-jp-sm btn-jp-icon" title="Crear carpeta">
                            <i class="bi bi-folder-plus"></i>
                        </button>
                    </form>
                </div>
                <?php endif; ?>
                <div class="metric-icon blue mx-auto mb-2">
                    <i class="bi bi-person-fill"></i>
                </div>
                <div style="font-size:13px;font-weight:600;color:var(--text-h);margin-bottom:4px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;padding:0 4px">
                    <?= esc($f['owner_name'] ?? $f['name'] ?? '—') ?>
                </div>
                <div style="font-size:12px;color:var(--text-muted);margin-bottom:6px">—</div>
                <span class="badge-status inactive" style="font-size:10px">Sin carpeta</span>
            </div>
        </div>
        <?php else: ?>
        <div class="card-jp" style="cursor:pointer" onclick="openFolderModal(<?= (int)$f['id'] ?>)">
            <div class="card-jp-body py-3 text-center" style="position:relative">
                <?php if ($isAdmin && in_array($f['type'], ['public','internal'])): ?>
                <div style="position:absolute;top:8px;right:8px;display:flex;gap:4px" onclick="event.stopPropagation()">
                    <?php if ($f['type'] === 'internal'): ?>
                    <button class="btn-jp btn-jp-secondary btn-jp-sm btn-jp-icon"
                        title="Gestionar permisos"
                        onclick="openPermissionsModal(<?= $f['id'] ?>)">
                        <i class="bi bi-key-fill"></i>
                    </button>
                    <?php endif; ?>
                    <button class="btn-jp btn-jp-danger btn-jp-sm btn-jp-icon"
                        title="Eliminar carpeta"
                        onclick="deleteFolder(<?= $f['id'] ?>, '<?= esc($f['name'], 'js') ?>')">
                        <i class="bi bi-trash-fill"></i>
                    </button>
                </div>
                <?php endif; ?>
                <div class="metric-icon <?= esc($f['color'] ?? 'blue') ?> mx-auto mb-2">
                    <i class="bi <?= esc($f['icon'] ?? 'bi-folder-fill') ?>"></i>
                </div>
                <div style="font-size:13px;font-weight:600;color:var(--text-h);margin-bottom:4px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;padding:0 4px">
                    <?= $f['type'] === 'personal'
                        ? esc($f['owner_name'] ?? $f['name'])
                        : esc($f['name']) ?>
                </div>
                <div style="font-size:12px;color:var(--text-muted);margin-bottom:6px">
                    <?= (int)($f['files_count'] ?? 0) ?> archivo(s)
                </div>
                <span class="badge-status <?= esc($typeBadge) ?>" style="font-size:10px">
                    <?= esc($typeLabel) ?>
                </span>
            </div>
        </div>
        <?php endif; ?>
    </div>
    <?php
}
?>

<?php if (!empty($fPublic) || !empty($fInternal)): ?>
<div class="row g-3 mb-3 doc-section-group">
    <?php foreach ($fPublic as $f): renderFolderCard($f, $activeFolder, $isAdmin); endforeach; ?>
    <?php foreach ($fInternal as $f): renderFolderCard($f, $activeFolder, $isAdmin); endforeach; ?>
</div>
<?php endif; ?>

<?php foreach ($roleGroups as $roleKey => [$roleLabel, $roleIcon, $roleColor]): ?>
<?php if (!empty($personalByRole[$roleKey])): ?>
<div class="doc-section-group">
<div style="margin-bottom:8px;margin-top:4px">
    <span style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--text-muted)">
        <i class="bi <?= $roleIcon ?> me-1" style="color:<?= $roleColor ?>"></i><?= $roleLabel ?>
    </span>
</div>
<div class="row g-3 mb-3">
    <?php foreach ($personalByRole[$roleKey] as $f): renderFolderCard($f, $activeFolder, $isAdmin); endforeach; ?>
</div>
</div>
<?php endif; ?>
<?php endforeach; ?>

<?php // Carpetas personales de roles no contemplados en $roleGroups ?>
<?php
$knownRoles = array_keys($roleGroups);
$fPersonalOther = [];
foreach ($personalByRole as $rk => $entries) {
    if (!in_array($rk, $knownRoles)) {
        $fPersonalOther = array_merge($fPersonalOther, $entries);
    }
}
?>
<?php if (!empty($fPersonalOther)): ?>
<div style="margin-bottom:8px;margin-top:4px">
    <span style="font-size:11px;font-weight:700;text-transform:uppercase;letter-spacing:.6px;color:var(--text-muted)">
        <i class="bi bi-person-fill me-1"></i>Otros
    </span>
</div>
<div class="row g-3 mb-3">
    <?php foreach ($fPersonalOther as $f): renderFolderCard($f, $activeFolder, $isAdmin); endforeach; ?>
</div>
<?php endif; ?>

<?php endif; ?>


<?php /* ═══════════════════════════════════════════════════════════
       MODALES
       ═══════════════════════════════════════════════════════════ */ ?>

<?php /* ── Modal: Archivos de carpeta (carga via AJAX) ──────── */ ?>
<div class="modal-overlay" id="modal-folder-files">
    <div class="modal-box" style="max-width:700px;width:95%">
        <div class="modal-header">
            <span class="card-jp-title" id="modal-folder-title">
                <i class="bi bi-folder2-open me-2" style="color:var(--accent)"></i>
                <span id="modal-folder-name">Cargando...</span>
            </span>
            <div class="d-flex align-items-center gap-2">
                <span id="modal-folder-count" style="font-size:12px;color:var(--text-muted)"></span>
                <button onclick="closeModal('modal-folder-files')" class="btn-jp btn-jp-secondary btn-jp-sm btn-jp-icon">
                    <i class="bi bi-x-lg"></i>
                </button>
            </div>
        </div>
        <div id="modal-folder-body" style="min-height:120px;padding:16px">
            <div style="text-align:center;padding:40px 0;color:var(--text-muted)">
                <i class="bi bi-hourglass-split" style="font-size:28px"></i>
                <p style="margin-top:8px;font-size:13px">Cargando archivos...</p>
            </div>
        </div>
        <div id="modal-folder-upload" style="display:none;border-top:1px solid var(--border);padding:16px">
            <form method="post" action="<?= base_url('documentacion/upload') ?>" enctype="multipart/form-data"
                  class="d-flex gap-2 align-items-center flex-wrap">
                <?= csrf_field() ?>
                <input type="hidden" name="folder_id" id="modal-upload-folder-id" value="">
                <input type="hidden" name="redirect_to" value="/documentacion">
                <input type="file" name="archivo" class="form-control-jp" style="flex:1;min-width:180px"
                       accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.webp,.mp4,.mov,.avi,.webm" required>
                <input type="text" name="description" class="form-control-jp" placeholder="Descripción" style="flex:1;min-width:140px">
                <button type="submit" class="btn-jp btn-jp-primary btn-jp-sm" style="white-space:nowrap">
                    <i class="bi bi-cloud-upload-fill me-1"></i>Subir
                </button>
            </form>
        </div>
    </div>
</div>


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
const isAdmin = <?= $isAdmin ? 'true' : 'false' ?>;
const CSRF_NAME  = '<?= csrf_token() ?>';
const CSRF_HASH  = '<?= csrf_hash() ?>';

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

// ── Eliminar archivo individual (AJAX POST con confirmación) ─────────
function deleteFile(id, name) {
    if (!confirm('¿Eliminar el archivo «' + name + '»?\nEsta acción no se puede deshacer.')) return;

    const fd = new FormData();
    fd.append(CSRF_NAME, CSRF_HASH);

    fetch('/documentacion/file/' + id + '/delete', {
        method: 'POST',
        body: fd,
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        if (data.success) {
            // Remove the file row from the table
            const btn = document.querySelector('[onclick*="deleteFile(' + id + ',"]');
            if (btn) {
                const row = btn.closest('tr');
                if (row) row.remove();
            }
            // Update count in modal header
            const countEl = document.getElementById('modal-folder-count');
            if (countEl) {
                const match = countEl.textContent.match(/\d+/);
                if (match) countEl.textContent = (parseInt(match[0]) - 1) + ' archivo(s)';
            }
        } else {
            showAlert(data.error || 'Error al eliminar el archivo.');
        }
    })
    .catch(() => showAlert('Error de red al eliminar el archivo.'));
}

// ── Indicador de progreso en subida ─────────────────────────────────
document.getElementById('form-upload')?.addEventListener('submit', function() {
    const progress = document.getElementById('upload-progress');
    const bar      = document.getElementById('progress-bar');
    const btn      = document.getElementById('btn-upload');

    if (progress) progress.style.display = 'block';
    if (btn) { btn.disabled = true; btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Subiendo...'; }

    if (bar) {
        let w = 0;
        const interval = setInterval(() => {
            w = Math.min(w + 2, 90);
            bar.style.width = w + '%';
            if (w >= 90) clearInterval(interval);
        }, 150);
    }
});

// ── Modal de archivos de carpeta (AJAX) ─────────────────────────────
function openFolderModal(folderId) {
    const body  = document.getElementById('modal-folder-body');
    const title = document.getElementById('modal-folder-name');
    const count = document.getElementById('modal-folder-count');
    const uploadBox = document.getElementById('modal-folder-upload');

    title.textContent = 'Cargando...';
    count.textContent = '';
    uploadBox.style.display = 'none';
    body.innerHTML = '<div style="text-align:center;padding:40px 0;color:var(--text-muted)"><i class="bi bi-hourglass-split" style="font-size:28px"></i><p style="margin-top:8px;font-size:13px">Cargando archivos...</p></div>';

    openModal('modal-folder-files');

    fetch('/documentacion/folder/' + folderId + '/files', {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        if (data.error) { body.innerHTML = '<p style="color:var(--danger);padding:16px">' + data.error + '</p>'; return; }

        const folder = data.folder;
        const files  = data.files || [];

        title.textContent = folder.type === 'personal'
            ? (folder.owner_name || folder.name)
            : folder.name;
        count.textContent = files.length + ' archivo(s)';

        const previewExts = ['pdf','jpg','jpeg','png','gif','webp','mp4','webm'];

        if (files.length === 0) {
            body.innerHTML = '<div style="text-align:center;padding:32px 0;color:var(--text-muted)"><i class="bi bi-folder2-open" style="font-size:32px"></i><p style="margin-top:10px;font-size:13px">Carpeta vacía</p></div>';
        } else {
            let rows = files.map(f => {
                const ext = (f.extension || '').toLowerCase();
                const canPreview = previewExts.includes(ext);
                const previewBtn = canPreview
                    ? `<a href="/documentacion/file/${f.id}/preview" target="_blank" class="btn-jp btn-jp-secondary btn-jp-sm btn-jp-icon" title="Ver"><i class="bi bi-eye"></i></a>`
                    : '';
                const size = fmtBytes(parseInt(f.size_bytes) || 0);
                const date = f.created_at ? new Date(f.created_at).toLocaleDateString('es-ES') : '—';
                return `<tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:8px">
                            <i class="bi bi-file-earmark-fill" style="font-size:18px;color:var(--text-muted);flex-shrink:0"></i>
                            <div>
                                <div style="font-weight:600;color:var(--text-h);font-size:13px">${escHtml(f.name_original)}</div>
                                ${f.description ? `<div style="font-size:11px;color:var(--text-muted)">${escHtml(f.description)}</div>` : ''}
                            </div>
                        </div>
                    </td>
                    <td style="font-size:12px;color:var(--text-muted);white-space:nowrap">${size}</td>
                    <td style="font-size:12px;color:var(--text-muted);white-space:nowrap">${date}</td>
                    <td>
                        <div style="display:flex;gap:4px;justify-content:flex-end">
                            ${previewBtn}
                            <a href="/documentacion/file/${f.id}/download" class="btn-jp btn-jp-secondary btn-jp-sm btn-jp-icon" title="Descargar"><i class="bi bi-download"></i></a>
                            ${isAdmin ? `<button type="button" onclick="deleteFile(${f.id},'${escHtml(f.name_original).replace(/'/g,"\\'")})" class="btn-jp btn-jp-danger btn-jp-sm btn-jp-icon" title="Eliminar"><i class="bi bi-trash-fill"></i></button>` : ''}
                        </div>
                    </td>
                </tr>`;
            }).join('');

            body.innerHTML = `<div class="table-responsive"><table class="table-jp">
                <thead><tr><th>Archivo</th><th>Tamaño</th><th>Fecha</th><th style="text-align:right">Acciones</th></tr></thead>
                <tbody>${rows}</tbody>
            </table></div>`;
        }

        if (data.canWrite) {
            document.getElementById('modal-upload-folder-id').value = folderId;
            uploadBox.style.display = 'block';
        }
    })
    .catch(() => {
        body.innerHTML = '<p style="color:var(--danger);padding:16px">Error al cargar los archivos.</p>';
    });
}

function fmtBytes(b) {
    if (b >= 1073741824) return (b/1073741824).toFixed(1) + ' GB';
    if (b >= 1048576)    return (b/1048576).toFixed(1) + ' MB';
    if (b >= 1024)       return (b/1024).toFixed(1) + ' KB';
    return b + ' B';
}

function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

// ── Buscador + filtros de carpetas ───────────────────────────
(function () {
    const searchInput = document.getElementById('doc-search');
    const filterBtns  = document.querySelectorAll('.doc-filter-btn');
    const countEl     = document.getElementById('doc-count');
    let activeFilter  = 'all';

    function applyFilters() {
        const q = (searchInput.value || '').toLowerCase().trim();
        const items = document.querySelectorAll('.doc-folder-item');
        let visible = 0;

        items.forEach(item => {
            const type = item.dataset.type || '';
            const name = item.dataset.name || '';
            const matchFilter = activeFilter === 'all' || type === activeFilter;
            const matchSearch = !q || name.includes(q);
            const show = matchFilter && matchSearch;
            item.style.display = show ? '' : 'none';
            if (show) visible++;
        });

        countEl.textContent = visible + ' carpeta' + (visible !== 1 ? 's' : '');

        // Hide empty section headers
        document.querySelectorAll('.doc-section-group').forEach(group => {
            const hasVisible = [...group.querySelectorAll('.doc-folder-item')].some(i => i.style.display !== 'none');
            group.style.display = hasVisible ? '' : 'none';
        });
    }

    searchInput?.addEventListener('input', applyFilters);

    filterBtns.forEach(btn => {
        btn.addEventListener('click', () => {
            activeFilter = btn.dataset.filter;
            filterBtns.forEach(b => {
                b.style.background = b === btn ? 'var(--accent)' : 'var(--bg-card)';
                b.style.color = b === btn ? '#fff' : 'var(--text-h)';
            });
            applyFilters();
        });
    });

    applyFilters();
})();
</script>
<?= $this->endSection() ?>
