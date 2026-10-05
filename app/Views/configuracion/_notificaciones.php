<?php
/**
 * Preferencias de notificación del usuario. Se incrusta en Configuración (admin) y en
 * la página propia /configuracion/notificaciones (resto de roles).
 * Espera: $prefs (NotificationPreferenceModel::forUser), $categories, $returnTo ('section'|'page').
 */
?>
<div class="card-jp mb-3">
    <div class="card-jp-header">
        <span class="card-jp-title"><i class="bi bi-phone-vibrate me-2" style="color:#2563eb"></i>Este dispositivo</span>
    </div>
    <div class="card-jp-body">
        <?= view('partials/push_ui', ['mode' => 'card', 'embedded' => true]) ?>
    </div>
</div>

<div class="card-jp">
    <div class="card-jp-header">
        <span class="card-jp-title"><i class="bi bi-bell-fill me-2" style="color:#d97706"></i>Qué notificaciones quiero recibir</span>
    </div>
    <div class="card-jp-body">
        <p class="text-muted" style="font-size:13px">
            <strong>En la plataforma</strong>: aparecen en la campanita y en el Centro de notificaciones.
            <strong>Aviso en el dispositivo</strong>: te llega al móvil u ordenador aunque no tengas la web abierta
            (requiere activarlo arriba en cada dispositivo y solo funciona si «En la plataforma» está activo).
        </p>

        <form action="<?= base_url('configuracion/notificaciones/save') ?>" method="POST" id="notif-prefs-form">
            <?= csrf_field() ?>
            <input type="hidden" name="return_to" value="<?= esc($returnTo ?? 'page', 'attr') ?>">

            <div class="table-responsive">
                <table class="table align-middle mb-0" style="font-size:14px">
                    <thead>
                        <tr class="text-muted" style="font-size:12px">
                            <th>Tipo</th>
                            <th class="text-center" style="width:130px">En la plataforma</th>
                            <th class="text-center" style="width:130px">Aviso en el dispositivo</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($categories as $key => $cat): $p = $prefs[$key] ?? ['in_app' => true, 'push' => true]; ?>
                        <tr data-cat="<?= esc($key, 'attr') ?>">
                            <td>
                                <div class="fw-semibold"><i class="bi <?= esc($cat['icon'], 'attr') ?> me-2"></i><?= esc($cat['label']) ?></div>
                                <div class="text-muted" style="font-size:12.5px"><?= esc($cat['desc']) ?></div>
                            </td>
                            <td class="text-center">
                                <div class="form-check form-switch d-inline-block m-0">
                                    <input class="form-check-input pref-inapp" type="checkbox" role="switch"
                                           name="pref[<?= esc($key, 'attr') ?>][in_app]" value="1"
                                           aria-label="<?= esc($cat['label'], 'attr') ?> en la plataforma"
                                           <?= $p['in_app'] ? 'checked' : '' ?>>
                                </div>
                            </td>
                            <td class="text-center">
                                <div class="form-check form-switch d-inline-block m-0">
                                    <input class="form-check-input pref-push" type="checkbox" role="switch"
                                           name="pref[<?= esc($key, 'attr') ?>][push]" value="1"
                                           aria-label="<?= esc($cat['label'], 'attr') ?> como aviso en el dispositivo"
                                           <?= $p['push'] ? 'checked' : '' ?>>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="mt-4 d-flex justify-content-end">
                <button type="submit" class="btn-jp btn-jp-primary">
                    <i class="bi bi-check-lg me-1"></i>Guardar preferencias
                </button>
            </div>
        </form>
    </div>
</div>

<script>
(function () {
    // Sin «En la plataforma» no hay aviso en el dispositivo (el aviso abre esa notificación).
    function sync(row) {
        var inApp = row.querySelector('.pref-inapp'), push = row.querySelector('.pref-push');
        if (!inApp.checked) push.checked = false;
        push.disabled = !inApp.checked;
    }
    var rows = document.querySelectorAll('#notif-prefs-form tr[data-cat]');
    rows.forEach(function (row) {
        sync(row);
        row.querySelector('.pref-inapp').addEventListener('change', function () {
            var push = row.querySelector('.pref-push');
            if (this.checked) push.checked = true; // al volver a activar, lo habitual es querer ambos
            sync(row);
        });
    });
})();
</script>
