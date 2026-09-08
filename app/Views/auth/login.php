<?= $this->extend('layouts/base') ?>

<?= $this->section('content') ?>
<?php $isDemo = function_exists('demo_mode') && demo_mode(); ?>

<div class="login-stage<?= $isDemo ? ' login-stage--demo' : '' ?>">
    <div class="login-aura-layer login-aura-grid" aria-hidden="true"></div>
    <div class="login-aura-layer login-aura-glow-primary" aria-hidden="true"></div>
    <div class="login-aura-layer login-aura-glow-secondary" aria-hidden="true"></div>

    <div class="login-split">

    <div class="login-card">

        <h1 class="login-title">Bienvenido</h1>
        <p class="login-subtitle">Accede a tu plataforma de gestión</p>

        <div id="errorBox" class="login-error d-none"></div>

        <form id="loginForm" novalidate>

            <div class="login-field">
                <div class="login-input-wrap">
                    <i class="bi bi-envelope icon-leading"></i>
                    <input type="email" id="login-email" name="email" placeholder="Email" required autocomplete="username">
                </div>
            </div>

            <div class="login-field">
                <div class="login-input-wrap">
                    <i class="bi bi-lock icon-leading"></i>
                    <input type="password" id="login-password" name="password" placeholder="Contraseña" required autocomplete="current-password">
                    <button type="button" class="login-toggle-pw" id="togglePw" aria-label="Mostrar contraseña">
                        <i class="bi bi-eye" id="eyeIcon"></i>
                    </button>
                </div>
            </div>

            <div class="login-field-row">
                <label class="login-remember">
                    <input type="checkbox" id="remember" name="remember" value="1">
                    <span class="login-check-box">
                        <svg width="11" height="9" viewBox="0 0 11 9" fill="none"><path d="M1 4.3L4 7.3L10 1.3" stroke="#fff" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>
                    </span>
                    Recuérdame
                </label>
                <a href="<?= base_url('forgot-password') ?>">¿Olvidaste tu contraseña?</a>
            </div>

            <button type="submit" class="login-btn-submit">
                <span class="btn-label">Entrar</span>
                <span class="btn-spinner d-none">
                    <span class="spinner-border spinner-border-sm me-1" style="width:14px;height:14px"></span>Entrando…
                </span>
            </button>
        </form>

        <?php if ($isDemo): ?>
        <div class="login-demo">
            <div class="login-demo-sep"><span>o entra como invitado</span></div>
            <p class="login-demo-hint">Entorno de demostración con datos ficticios. Elige un rol para explorar la plataforma:</p>
            <div class="login-demo-roles">
                <?php foreach (demo_guest_accounts() as $role => $acc): ?>
                <form method="post" action="<?= base_url('demo/invitado') ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="role" value="<?= esc($role, 'attr') ?>">
                    <button type="submit" class="login-demo-btn"><?= esc($acc['label']) ?></button>
                </form>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

    </div>

    <?php if ($isDemo): ?>
    <aside class="login-pitch">
        <h2 class="login-pitch-title">La plataforma de gestión para tu academia</h2>
        <p class="login-pitch-lead">
            Un único panel para llevar el día a día de una academia o negocio de clases:
            sin hojas de cálculo sueltas ni grupos de WhatsApp descontrolados.
        </p>

        <ul class="login-pitch-list">
            <li><i class="bi bi-people"></i><span><strong>Alumnos y profesores</strong> — fichas, seguimiento y grupos.</span></li>
            <li><i class="bi bi-calendar3"></i><span><strong>Calendario de clases</strong> — sesiones individuales y recurrentes, asignación de profesores y control de asistencia.</span></li>
            <li><i class="bi bi-ticket-perforated"></i><span><strong>Bonos y membresías</strong> — se descuentan solos al pasar lista.</span></li>
            <li><i class="bi bi-folder2-open"></i><span><strong>Documentación y mensajería</strong> — material formativo con permisos y chat interno entre roles.</span></li>
            <li><i class="bi bi-bell"></i><span><strong>Notificaciones y soporte</strong> — avisos individuales o grupales y sistema de incidencias.</span></li>
        </ul>

        <p class="login-pitch-for">
            <strong>Pensada para:</strong> academias de tecnificación deportiva, academias de
            refuerzo escolar, academias de idiomas y profesionales de clases particulares
            que gestionan varios alumnos o grupos.
        </p>

        <div class="login-contact">
            <h3>¿Te interesa para tu academia?</h3>
            <p>Déjame tus datos y te escribo.</p>

            <div id="contactMsg" class="login-contact-msg d-none"></div>

            <form id="contactForm" method="post" action="<?= base_url('demo/contacto') ?>" novalidate>
                <?= csrf_field() ?>
                <input type="text" name="website" tabindex="-1" autocomplete="off" class="login-contact-hp" aria-hidden="true">
                <div class="login-contact-row">
                    <input type="text"  name="name"    placeholder="Nombre" required maxlength="150">
                    <input type="email" name="email"   placeholder="Email" required maxlength="191">
                </div>
                <input type="text" name="company" placeholder="Academia / club (opcional)" maxlength="150">
                <textarea name="message" placeholder="Cuéntame qué necesitas" required rows="3" maxlength="4000"></textarea>
                <button type="submit" class="login-contact-btn">
                    <span class="btn-label">Enviar</span>
                    <span class="btn-spinner d-none">Enviando…</span>
                </button>
            </form>
        </div>
    </aside>
    <?php endif; ?>

    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>

<script>
    const CSRF = {
        name: "<?= csrf_token() ?>",
        hash: "<?= csrf_hash() ?>"
    };
</script>

<script src="<?= base_url('assets/js/auth.js') ?>"></script>
<?php if (service('request')->getGet('expired')): ?>
<script>window.showAuthError('Tu sesión ha expirado por inactividad. Inicia sesión de nuevo.');</script>
<?php endif; ?>

<?php if ($isDemo): ?>
<script>
(function () {
    var form = document.getElementById('contactForm');
    if (!form) return;
    var box  = document.getElementById('contactMsg');
    var btn  = form.querySelector('.login-contact-btn');

    function show(text, ok) {
        box.textContent = text;
        box.className = 'login-contact-msg ' + (ok ? 'is-ok' : 'is-err');
    }

    form.addEventListener('submit', function (e) {
        e.preventDefault();
        btn.disabled = true;
        btn.querySelector('.btn-label').classList.add('d-none');
        btn.querySelector('.btn-spinner').classList.remove('d-none');

        var fd = new FormData(form);
        fd.set(CSRF.name, CSRF.hash);

        fetch(form.action, { method: 'POST', body: fd, headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data.csrf) { CSRF.hash = data.csrf; }
                if (data.status === 'ok') {
                    form.reset();
                    show('¡Gracias! Te escribiré pronto.', true);
                } else {
                    show(data.error || 'No se ha podido enviar. Inténtalo de nuevo.', false);
                }
            })
            .catch(function () { show('No se ha podido enviar. Inténtalo de nuevo.', false); })
            .finally(function () {
                btn.disabled = false;
                btn.querySelector('.btn-label').classList.remove('d-none');
                btn.querySelector('.btn-spinner').classList.add('d-none');
            });
    });
})();
</script>
<?php endif; ?>

<?= $this->endSection() ?>
