<?php
/**
 * Chip de un adjunto de observaciones (foto/vídeo/documento).
 * Espera: $att (fila de class_session_attachments), $canDelete (bool).
 */
$mime = $att['file_mime'] ?? '';
if (str_starts_with($mime, 'image/')) {
    $icon = 'bi-image-fill'; $color = '#059669';
} elseif (str_starts_with($mime, 'video/')) {
    $icon = 'bi-camera-video-fill'; $color = '#7c3aed';
} else {
    $icon = 'bi-file-earmark-fill'; $color = '#2563eb';
}
$sizeKb = round(($att['file_size'] ?? 0) / 1024);
$sizeLabel = $sizeKb >= 1024 ? round($sizeKb / 1024, 1) . ' MB' : $sizeKb . ' KB';
?>
<span class="cs-attach-chip">
    <a href="/clases/adjuntos/<?= $att['id'] ?>/descargar" target="_blank" rel="noopener">
        <i class="bi <?= $icon ?> me-1" style="color:<?= $color ?>"></i>
        <span class="cs-attach-name"><?= esc($att['file_name']) ?></span>
        <span class="cs-attach-size"><?= $sizeLabel ?></span>
    </a>
    <?php if (!empty($canDelete)): ?>
    <form action="/clases/adjuntos/<?= $att['id'] ?>/eliminar" method="POST" style="display:inline"
          data-ru-confirm="¿Eliminar este adjunto?"
          data-ru-confirm-desc="Se borrará «<?= esc($att['file_name'], 'attr') ?>» de forma permanente."
          data-ru-confirm-label="Eliminar" data-ru-confirm-danger>
        <?= csrf_field() ?>
        <button type="submit" class="cs-attach-del" title="Eliminar"><i class="bi bi-x-lg"></i></button>
    </form>
    <?php endif; ?>
</span>
