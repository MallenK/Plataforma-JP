<?= $this->extend('layouts/app') ?>
<?= $this->section('page_content') ?>

<?php
helper('avatar');

$isGroup   = ($n['type'] ?? '') === 'group';
$source    = \App\Models\NotificationModel::sourceLink($n);
$ts        = strtotime((string) $n['created_at']);
$fecha     = $ts ? date('d/m/Y', $ts) : '';
$hora      = $ts ? date('H:i', $ts) : '';
$sender    = $n['sender_name'] ?? 'Sistema';
$roleLabel = match ($n['sender_role'] ?? '') {
    'superadmin'       => 'Super Admin',
    'admin'            => 'Administrador',
    'coach'            => 'Entrenador',
    'alumno', 'player' => 'Alumno',
    'staff'            => 'Staff',
    default            => '',
};
?>

<div class="notif-detail">

    <a href="<?= base_url('notificaciones') ?>" class="btn-jp btn-jp-secondary btn-jp-sm notif-detail-back">
        <i class="bi bi-arrow-left"></i> Volver a notificaciones
    </a>

    <article class="card-jp notif-detail-card">
        <header class="notif-detail-head">
            <div class="notif-avatar-wrap">
                <?= avatar_html($n['sender_avatar'] ?? null, $sender, 'notif-avatar') ?>
                <?php if ($isGroup): ?>
                <span class="notif-group-badge"><i class="bi bi-people-fill"></i></span>
                <?php endif; ?>
            </div>
            <div class="notif-detail-meta">
                <div class="notif-detail-sender"><?= esc($sender) ?></div>
                <div class="notif-detail-sub">
                    <?php if ($roleLabel !== ''): ?><span><?= esc($roleLabel) ?></span><?php endif; ?>
                    <span><i class="bi bi-calendar3"></i> <?= esc($fecha) ?></span>
                    <span><i class="bi bi-clock"></i> <?= esc($hora) ?></span>
                </div>
            </div>
            <span class="notif-detail-chip">
                <i class="bi <?= $isGroup ? 'bi-people-fill' : 'bi-person-fill' ?>"></i>
                <?= $isGroup ? 'Grupal' : 'Individual' ?>
            </span>
        </header>

        <div class="card-jp-body notif-detail-body">
            <h1 class="notif-detail-title"><?= esc($n['title']) ?></h1>
            <div class="notif-detail-text"><?= nl2br(esc($n['body'])) ?></div>

            <?php if (!empty($n['file_name'])): ?>
            <a href="<?= base_url('notificaciones/' . (int) $n['id'] . '/download') ?>" class="notif-detail-file">
                <i class="bi bi-paperclip"></i>
                <span class="notif-detail-file-name"><?= esc($n['file_name']) ?></span>
                <?php if (!empty($n['file_size'])): ?>
                <span class="notif-detail-file-size"><?= esc(formatBytes((int) $n['file_size'])) ?></span>
                <?php endif; ?>
                <i class="bi bi-download notif-detail-file-dl"></i>
            </a>
            <?php endif; ?>

            <?php if (!empty($n['is_sender'])): ?>
            <p class="notif-detail-sent">
                <i class="bi bi-send"></i>
                Enviada por ti a <?= (int) ($n['recipient_count'] ?? 0) ?> destinatario(s) ·
                <?= (int) ($n['read_count'] ?? 0) ?> la han leído
            </p>
            <?php endif; ?>
        </div>

        <?php if ($source): ?>
        <footer class="notif-detail-actions">
            <a href="<?= base_url($source['path']) ?>" class="btn-jp btn-jp-primary">
                <i class="bi <?= esc($source['icon'], 'attr') ?>"></i> <?= esc($source['label']) ?>
            </a>
        </footer>
        <?php endif; ?>
    </article>
</div>

<?= $this->endSection() ?>
