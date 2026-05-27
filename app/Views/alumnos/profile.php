<?= $this->extend('layouts/app') ?>

<?= $this->section('page_content') ?>

<?php
$canEdit       = in_array(session('role'), ['admin', 'superadmin', 'player']);
$pos           = esc($profile['position'] ?? '—');
$categoryLabel = match($profile['category'] ?? '') {
    'prebenjamin' => 'Prebenjamín',
    'benjamin'    => 'Benjamín',
    'alevin'      => 'Alevín',
    'infantil'    => 'Infantil',
    'cadete'      => 'Cadete',
    'juvenil'     => 'Juvenil',
    'junior'      => 'Júnior',
    'senior'      => 'Sénior',
    'veterano'    => 'Veterano',
    default       => '—',
};
?>

<div class="page-header">
    <div class="page-header-text">
        <h2>Mi ficha técnica</h2>
        <p>Datos deportivos del alumno</p>
    </div>
    <?php if ($canEdit): ?>
    <a href="/alumno?edit=1" class="btn-jp btn-jp-secondary">
        <i class="bi bi-pencil"></i> Editar ficha
    </a>
    <?php endif; ?>
</div>

<div class="row g-3">

    <!-- Stats rápidas -->
    <div class="col-6 col-md-3">
        <div class="metric-card">
            <div class="metric-card-header">
                <span class="metric-label">Posición</span>
                <div class="metric-icon blue"><i class="bi bi-geo-alt-fill"></i></div>
            </div>
            <div class="metric-value" style="font-size:20px"><?= $pos ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="metric-card">
            <div class="metric-card-header">
                <span class="metric-label">Categoría</span>
                <div class="metric-icon green"><i class="bi bi-trophy-fill"></i></div>
            </div>
            <div class="metric-value" style="font-size:20px"><?= $categoryLabel ?></div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="metric-card">
            <div class="metric-card-header">
                <span class="metric-label">Altura</span>
                <div class="metric-icon orange"><i class="bi bi-rulers"></i></div>
            </div>
            <div class="metric-value" style="font-size:20px">
                <?= $profile['height'] ? esc($profile['height']) . ' cm' : '—' ?>
            </div>
        </div>
    </div>
    <div class="col-6 col-md-3">
        <div class="metric-card">
            <div class="metric-card-header">
                <span class="metric-label">Peso</span>
                <div class="metric-icon purple"><i class="bi bi-activity"></i></div>
            </div>
            <div class="metric-value" style="font-size:20px">
                <?= $profile['weight'] ? esc($profile['weight']) . ' kg' : '—' ?>
            </div>
        </div>
    </div>

    <!-- Detalle ficha -->
    <div class="col-12 col-lg-8">
        <div class="card-jp">
            <div class="card-jp-header">
                <span class="card-jp-title">
                    <i class="bi bi-person-badge-fill me-2" style="color:var(--accent)"></i>
                    Datos deportivos
                </span>
            </div>
            <div class="card-jp-body">
                <div class="row g-3">
                    <div class="col-12 col-md-6">
                        <div class="form-group mb-0">
                            <label class="form-label">Fecha de nacimiento</label>
                            <input type="text" class="form-control-jp"
                                value="<?= esc($profile['birth_date'] ?? '—') ?>" readonly>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="form-group mb-0">
                            <label class="form-label">Posición</label>
                            <input type="text" class="form-control-jp" value="<?= $pos ?>" readonly>
                        </div>
                    </div>
                    <div class="col-12 col-md-3">
                        <div class="form-group mb-0">
                            <label class="form-label">Altura (cm)</label>
                            <input type="text" class="form-control-jp"
                                value="<?= esc($profile['height'] ?? '—') ?>" readonly>
                        </div>
                    </div>
                    <div class="col-12 col-md-3">
                        <div class="form-group mb-0">
                            <label class="form-label">Peso (kg)</label>
                            <input type="text" class="form-control-jp"
                                value="<?= esc($profile['weight'] ?? '—') ?>" readonly>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="form-group mb-0">
                            <label class="form-label">Categoría</label>
                            <input type="text" class="form-control-jp" value="<?= $categoryLabel ?>" readonly>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="form-group mb-0">
                            <label class="form-label">Equipo</label>
                            <input type="text" class="form-control-jp" value="<?= esc($profile['team'] ?? '—') ?>" readonly>
                        </div>
                    </div>
                    <div class="col-12 col-md-6">
                        <div class="form-group mb-0">
                            <label class="form-label">Liga</label>
                            <input type="text" class="form-control-jp" value="<?= esc($profile['league'] ?? '—') ?>" readonly>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Notas médicas -->
    <div class="col-12 col-lg-4">
        <div class="card-jp h-100">
            <div class="card-jp-header">
                <span class="card-jp-title">
                    <i class="bi bi-clipboard2-pulse-fill me-2" style="color:var(--danger)"></i>
                    Notas médicas
                </span>
            </div>
            <div class="card-jp-body">
                <?php if (!empty($profile['medical_notes'])): ?>
                    <p style="font-size:13.5px;color:var(--text-body);margin:0;line-height:1.6">
                        <?= nl2br(esc($profile['medical_notes'])) ?>
                    </p>
                <?php else: ?>
                    <p style="font-size:13px;color:var(--text-muted);margin:0">
                        Sin notas médicas registradas.
                    </p>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Documentos personales -->
    <?php
    $currentUserId  = session('id');
    $currentRole    = session('role');
    $isAdminOrSuper = in_array($currentRole, ['superadmin', 'admin']);
    $playerId       = $profile['player_id'] ?? $profile['id'] ?? null;
    $publicAnns     = array_filter($annotations ?? [], fn($a) => $a['type'] === 'public');

    $previewExts = ['pdf','jpg','jpeg','png','gif','webp','mp4','webm'];

    function profileDocIcon(string $ext): array {
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
    ?>
    <div class="col-12">
        <div class="card-jp">
            <div class="card-jp-header">
                <span class="card-jp-title">
                    <i class="bi bi-folder-fill me-2" style="color:var(--accent)"></i>
                    Mis documentos
                </span>
                <span style="font-size:12px;color:var(--text-muted)"><?= count($documents ?? []) ?> archivo(s)</span>
            </div>

            <?php if (session()->getFlashdata('success')): ?>
            <div class="card-jp-body pb-0">
                <div class="alert-jp success" style="display:flex;align-items:center;gap:10px">
                    <i class="bi bi-check-circle-fill"></i>
                    <?= esc(session()->getFlashdata('success')) ?>
                </div>
            </div>
            <?php endif; ?>
            <?php if (session()->getFlashdata('upload_error')): ?>
            <div class="card-jp-body pb-0">
                <div class="alert-jp danger" style="display:flex;align-items:center;gap:10px">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <?= esc(session()->getFlashdata('upload_error')) ?>
                </div>
            </div>
            <?php endif; ?>

            <?php if (!empty($documents)): ?>
            <div class="table-responsive">
                <table class="table-jp">
                    <thead>
                        <tr>
                            <th>Archivo</th>
                            <th>Fecha</th>
                            <th style="text-align:right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($documents as $doc):
                        [$dicon, $dcolor] = profileDocIcon($doc['extension'] ?? '');
                        $canPreview = in_array($doc['extension'] ?? '', $previewExts);
                    ?>
                    <tr>
                        <td>
                            <div class="d-flex align-items-center gap-2">
                                <i class="bi <?= esc($dicon) ?>" style="font-size:18px;color:<?= $dcolor ?>;flex-shrink:0"></i>
                                <div>
                                    <div style="font-weight:600;color:var(--text-h);font-size:13px"><?= esc($doc['name_original']) ?></div>
                                    <?php if (!empty($doc['description'])): ?>
                                    <div style="font-size:11px;color:var(--text-muted)"><?= esc($doc['description']) ?></div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td style="font-size:12px;color:var(--text-muted);white-space:nowrap">
                            <?= date('d/m/Y', strtotime($doc['created_at'])) ?>
                        </td>
                        <td>
                            <div class="d-flex gap-1 justify-content-end">
                                <?php if ($canPreview): ?>
                                <a href="<?= base_url('documentacion/file/' . (int)$doc['id'] . '/preview') ?>"
                                   target="_blank"
                                   class="btn-jp btn-jp-secondary btn-jp-sm btn-jp-icon" title="Previsualizar">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <?php endif; ?>
                                <a href="<?= base_url('documentacion/file/' . (int)$doc['id'] . '/download') ?>"
                                   class="btn-jp btn-jp-secondary btn-jp-sm btn-jp-icon" title="Descargar">
                                    <i class="bi bi-download"></i>
                                </a>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
            <div class="card-jp-body">
                <p style="font-size:13px;color:var(--text-muted);margin:0 0 12px 0">Sin documentos todavía.</p>
            </div>
            <?php endif; ?>

            <!-- Formulario de subida: admin/superadmin y el propio jugador -->
            <?php if (!empty($personalFolder)): ?>
            <div class="card-jp-body" style="border-top:1px solid var(--border)">
                <form method="post" action="<?= base_url('documentacion/upload') ?>" enctype="multipart/form-data"
                      class="d-flex gap-2 align-items-center flex-wrap">
                    <?= csrf_field() ?>
                    <input type="hidden" name="folder_id" value="<?= (int)$personalFolder['id'] ?>">
                    <input type="hidden" name="redirect_to" value="/alumno">
                    <input type="file" name="archivo" class="form-control-jp" style="flex:1;min-width:200px"
                           accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.jpg,.jpeg,.png,.gif,.webp,.mp4,.mov,.avi,.webm" required>
                    <input type="text" name="description" class="form-control-jp" placeholder="Descripción (opcional)" style="flex:1;min-width:160px">
                    <button type="submit" class="btn-jp btn-jp-primary btn-jp-sm" style="white-space:nowrap">
                        <i class="bi bi-cloud-upload-fill me-1"></i>Subir documento
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Anotaciones públicas -->
    <?php
    $publicAnns = array_filter($annotations ?? [], fn($a) => $a['type'] === 'public');
    ?>
    <div class="col-12">
        <div class="card-jp">
            <div class="card-jp-header">
                <span class="card-jp-title">
                    <i class="bi bi-chat-square-text-fill me-2" style="color:var(--accent)"></i>
                    Anotaciones
                </span>
                <span style="font-size:12px;color:var(--text-muted)"><?= count($publicAnns) ?> anotación(es)</span>
            </div>

            <div class="card-jp-body d-flex flex-column gap-2">
                <?php if (session()->getFlashdata('annotation_success')): ?>
                <div class="alert-jp success" style="display:flex;align-items:center;gap:10px">
                    <i class="bi bi-check-circle-fill"></i>
                    <?= esc(session()->getFlashdata('annotation_success')) ?>
                </div>
                <?php endif; ?>
                <?php if (session()->getFlashdata('annotation_error')): ?>
                <div class="alert-jp danger" style="display:flex;align-items:center;gap:10px">
                    <i class="bi bi-exclamation-triangle-fill"></i>
                    <?= esc(session()->getFlashdata('annotation_error')) ?>
                </div>
                <?php endif; ?>

                <?php if (empty($publicAnns)): ?>
                    <p style="font-size:13px;color:var(--text-muted);margin:0">Sin anotaciones todavía.</p>
                <?php else: ?>
                    <?php foreach ($publicAnns as $ann): ?>
                    <?php $canDelete = (int)$ann['author_id'] === (int)$currentUserId || $isAdminOrSuper; ?>
                    <div style="background:var(--bg-card-inner,rgba(0,0,0,.04));border-radius:8px;padding:12px 14px">
                        <div style="font-size:13.5px;color:var(--text-body);line-height:1.55;white-space:pre-wrap"><?= nl2br(esc($ann['content'])) ?></div>
                        <div style="display:flex;align-items:center;justify-content:space-between;margin-top:8px">
                            <span style="font-size:11px;color:var(--text-muted)">
                                <i class="bi bi-person-fill me-1"></i><?= esc($ann['author_name']) ?>
                                &nbsp;·&nbsp;
                                <?= date('d/m/Y H:i', strtotime($ann['created_at'])) ?>
                            </span>
                            <?php if ($canDelete): ?>
                            <form action="<?= base_url('anotaciones/' . $ann['id'] . '/eliminar') ?>" method="post"
                                  onsubmit="return confirm('¿Eliminar esta anotación?')">
                                <?= csrf_field() ?>
                                <button type="submit"
                                        style="background:none;border:none;color:var(--danger);font-size:12px;cursor:pointer;padding:0;line-height:1"
                                        title="Eliminar">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </form>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Formulario — visible para todos -->
            <div class="card-jp-body" style="border-top:1px solid var(--border)">
                <form action="<?= base_url('alumnos/' . $playerId . '/anotaciones') ?>" method="post">
                    <?= csrf_field() ?>
                    <input type="hidden" name="type" value="public">
                    <div class="form-group mb-2">
                        <textarea name="content" class="form-control-jp" rows="2"
                                  placeholder="Añadir anotación..." required
                                  style="resize:vertical;min-height:64px"></textarea>
                    </div>
                    <div class="text-end">
                        <button type="submit" class="btn-jp btn-jp-primary" style="padding:6px 16px;font-size:13px">
                            <i class="bi bi-plus-circle me-1"></i>Añadir anotación
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</div>

<?= $this->endSection() ?>
